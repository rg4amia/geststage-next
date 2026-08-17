<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Beneficiary\Beneficiaire;
use App\Models\Company\Entreprise;
use App\Models\Internship\Stage;
use App\Models\Reference\SituationStage;
use App\Models\Reference\TypePaiement;
use App\Models\Reference\TypeStructure;

class UpdateLegacyMissingDataCommand extends Command
{
    protected $signature = 'migrate:update-missing-data';
    protected $description = 'Met à jour les données manquantes issues de l\'ancienne base (Situation Stage, Type Structure, Type Paiement, N° Trésor Money, N° Wave)';

    public function handle()
    {
        $this->info("Début de la mise à jour des données manquantes...");

        try {
            DB::connection('legacy')->getPdo();
        } catch (\Exception $e) {
            $this->error("Impossible de se connecter à la base 'legacy'.");
            return 1;
        }

        $this->migrateReferences();
        $this->updateBeneficiaires();
        $this->updateEntreprises();
        $this->updateStages();
        $this->updateSourcesFinancement();

        $this->info("Mise à jour terminée avec succès !");
        return 0;
    }

    private function migrateReferences()
    {
        $this->info("Migration des référentiels manquants...");
        
        // Type Paiement
        if (DB::connection('legacy')->getSchemaBuilder()->hasTable('type_paiements')) {
            $typesPaiement = DB::connection('legacy')->table('type_paiements')->get();
            foreach ($typesPaiement as $tp) {
                TypePaiement::updateOrCreate(
                    ['ancien_id' => $tp->id],
                    [
                        'code' => 'TP-'.str_pad($tp->id, 3, '0', STR_PAD_LEFT),
                        'nom' => $tp->libelle,
                    ]
                );
            }
        }

        // Type Structure
        if (DB::connection('legacy')->getSchemaBuilder()->hasTable('type_structures')) {
            $typesStructure = DB::connection('legacy')->table('type_structures')->get();
            foreach ($typesStructure as $ts) {
                TypeStructure::updateOrCreate(
                    ['ancien_id' => $ts->id],
                    [
                        'code' => 'TS-'.str_pad($ts->id, 3, '0', STR_PAD_LEFT),
                        'nom' => $ts->libelle_type_structure ?? $ts->libelle ?? 'Structure '.$ts->id,
                    ]
                );
            }
        }

        // Situation Stage
        if (DB::connection('legacy')->getSchemaBuilder()->hasTable('situation_stage')) {
            $situations = DB::connection('legacy')->table('situation_stage')->get();
            foreach ($situations as $s) {
                SituationStage::updateOrCreate(
                    ['ancien_id' => $s->id_situation_stage],
                    [
                        'code' => 'SS-'.str_pad($s->id_situation_stage, 3, '0', STR_PAD_LEFT),
                        'nom' => $s->libelle_situation_stage,
                    ]
                );
            }
        }
    }

    private function updateBeneficiaires()
    {
        $this->info("Mise à jour des bénéficiaires (Type paiement, Numéros mobile)...");

        $query = DB::connection('legacy')->table('contrats_pae');
        $total = $query->count();
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $typesPaiementMap = TypePaiement::pluck('id', 'ancien_id')->toArray();

        $query->orderBy('id')->chunk(1000, function ($contrats) use ($bar, $typesPaiementMap) {
            foreach ($contrats as $legacyContrat) {
                if (empty($legacyContrat->numero_aej)) {
                    $bar->advance();
                    continue;
                }

                $type_paiement_id = $typesPaiementMap[$legacyContrat->type_paiement_id] ?? null;

                DB::table('beneficiaires')
                    ->where('numero_aej', $legacyContrat->numero_aej)
                    ->update([
                        'numero_tresor_money' => $legacyContrat->numero_yup ?? null,
                        'numero_wave' => $legacyContrat->numero_wave ?? null,
                        'type_paiement_id' => $type_paiement_id,
                    ]);
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
    }

    private function updateEntreprises()
    {
        $this->info("Mise à jour des entreprises (Type de structure)...");

        $query = DB::connection('legacy')->table('contrats_pae');
        $total = $query->count();
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $typesStructureMap = TypeStructure::pluck('id', 'ancien_id')->toArray();

        // L'ancien système liait le type de structure au contrat, 
        // on l'applique sur l'entreprise directement
        $query->orderBy('id')->chunk(1000, function ($contrats) use ($bar, $typesStructureMap) {
            foreach ($contrats as $legacyContrat) {
                if (empty($legacyContrat->id_entreprise) || empty($legacyContrat->type_structure_id)) {
                    $bar->advance();
                    continue;
                }

                $type_structure_id = $typesStructureMap[$legacyContrat->type_structure_id] ?? null;

                if ($type_structure_id) {
                    DB::table('entreprises')
                        ->where('ancien_id', $legacyContrat->id_entreprise)
                        ->update(['type_structure_id' => $type_structure_id]);
                }
                
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
    }

    private function updateStages()
    {
        $this->info("Mise à jour des stages (Situation de stage)...");

        $query = DB::connection('legacy')->table('contrats_pae');
        $total = $query->count();
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $situationsStageMap = SituationStage::pluck('code', 'ancien_id')->toArray();

        $query->orderBy('id')->chunk(1000, function ($contrats) use ($bar, $situationsStageMap) {
            foreach ($contrats as $legacyContrat) {
                if (empty($legacyContrat->id) || empty($legacyContrat->id_situation_stage)) {
                    $bar->advance();
                    continue;
                }

                $code_situation = $situationsStageMap[$legacyContrat->id_situation_stage] ?? null;

                if ($code_situation) {
                    DB::table('stages')
                        ->where('ancien_id', $legacyContrat->id)
                        ->update(['situation_stage' => $code_situation]);
                }
                
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
    }

    private function updateSourcesFinancement()
    {
        $this->info("Mise à jour des sources de financement...");

        $query = DB::connection('legacy')->table('contrats_pae');
        $total = $query->count();
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $sourcesMap = \App\Models\Reference\SourceFinancement::pluck('id', 'ancien_id')->toArray();

        $query->orderBy('id')->chunk(1000, function ($contrats) use ($bar, $sourcesMap) {
            foreach ($contrats as $legacyContrat) {
                if (empty($legacyContrat->id) || empty($legacyContrat->source_financement)) {
                    $bar->advance();
                    continue;
                }

                $source_financement_id = $sourcesMap[$legacyContrat->source_financement] ?? null;

                if ($source_financement_id) {
                    DB::table('stages')
                        ->where('ancien_id', $legacyContrat->id)
                        ->update(['source_financement_id' => $source_financement_id]);
                }
                
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
    }
}
