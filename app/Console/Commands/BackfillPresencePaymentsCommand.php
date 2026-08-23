<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Attendance\Pointage;
use App\Models\Payment\DroitPaiement;
use App\Models\Payment\Paiement;
use App\Models\Workflow\InstanceParcours;
use Illuminate\Support\Facades\DB;

class BackfillPresencePaymentsCommand extends Command
{
    protected $signature = 'fix:backfill-presence-payments';
    protected $description = 'Generate missing DroitPaiement and Paiement for pointages stuck in dmg_attente_paiement_presence.';

    public function handle()
    {
        $this->info("Fetching stuck pointages...");

        // Find all pointages whose instance is in dmg_attente_paiement_presence but have NO DroitPaiement
        $pointageIds = DB::table('pointages')
            ->join('instances_parcours', 'instances_parcours.pointage_id', '=', 'pointages.id')
            ->where('instances_parcours.corbeille_actuelle', 'dmg_attente_paiement_presence')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                      ->from('droits_paiement')
                      ->whereColumn('droits_paiement.pointage_id', 'pointages.id');
            })
            ->pluck('pointages.id')
            ->toArray();
            
        $this->info("Found " . count($pointageIds) . " pointages missing payments.");
        if (count($pointageIds) === 0) {
            return;
        }
        
        $bar = $this->output->createProgressBar(count($pointageIds));
        $bar->start();

        $fixedCount = 0;
        
        // Chunk processing to avoid memory limits
        foreach (array_chunk($pointageIds, 500) as $chunk) {
            $pointages = Pointage::whereIn('id', $chunk)->with(['stage.contrats'])->get();
            foreach ($pointages as $pointage) {
                try {
                    DB::transaction(function () use ($pointage, &$fixedCount) {
                        $stage = $pointage->stage;
                        if (!$stage) return;
                        
                        $contratActif = $stage->contrats()->latest()->first();
                        $montantPaiement = $contratActif ? $contratActif->prime_mensuelle : 45000;
                        
                        // Just fetch legacy date if it exists
                        $legacyDate = DB::connection('legacy')->table('pointage_models')
                            ->where('id', $pointage->ancien_id)
                            ->value('date_ca');
                        
                        $createdAt = $legacyDate && $legacyDate !== '0000-00-00 00:00:00' ? $legacyDate : now();

                        $droitPaiement = DroitPaiement::create([
                            'stage_id' => $stage->id,
                            'pointage_id' => $pointage->id,
                            'periode_id' => $pointage->periode_id,
                            'source_financement_id' => $stage->source_financement_id ?? 1,
                            'nature' => 'PRESENCE',
                            'montant' => $montantPaiement,
                            'statut' => 'OUVERT',
                            'created_at' => $createdAt,
                            'updated_at' => $createdAt,
                        ]);

                        Paiement::create([
                            'droit_paiement_id' => $droitPaiement->id,
                            'statut' => 'A_TRAITER',
                            'montant' => $montantPaiement,
                            'created_at' => $createdAt,
                            'updated_at' => $createdAt,
                        ]);
                        
                        $fixedCount++;
                    });
                } catch (\Exception $e) {
                    $this->error("Failed to generate payment for pointage {$pointage->id}: " . $e->getMessage());
                }
                $bar->advance();
            }
        }
        
        $bar->finish();
        
        $this->info("\nFixed $fixedCount presence pointages.");
    }
}
