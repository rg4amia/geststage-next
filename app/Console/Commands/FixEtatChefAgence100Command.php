<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Workflow\InstanceParcours;
use App\Models\Internship\Stage;
use App\Models\Payment\DroitPaiement;
use App\Models\Payment\Paiement;
use App\Models\Attendance\Pointage;

class FixEtatChefAgence100Command extends Command
{
    protected $signature = 'fix:etat-chef-agence-100';
    protected $description = 'Fix pointages erroneously placed in DMG corbeilles due to etat_chef_agence = 100';

    public function handle()
    {
        $this->info("Fetching legacy stages with etat_chef_agence = 100...");
        $legacyIds = DB::connection('legacy')->table('contrats_pae')
            ->where('etat_chef_agence', 100)
            ->pluck('id')
            ->toArray();
        
        $this->info("Found " . count($legacyIds) . " stages in legacy.");

        $stages = Stage::whereIn('ancien_id', $legacyIds)->pluck('id')->toArray();

        // Find instances
        $pointagesInStage = Pointage::whereIn('stage_id', $stages)->pluck('id')->toArray();

        $instances = InstanceParcours::whereIn('corbeille_actuelle', ['dmg_attente_paiement_presence', 'dmg_attente_paiement_demarrage'])
            ->whereIn('pointage_id', $pointagesInStage)
            ->get();

        $this->info("Found " . $instances->count() . " workflow instances to fix.");

        if ($instances->isEmpty()) {
            return 0;
        }

        $bar = $this->output->createProgressBar($instances->count());
        $bar->start();

        $deletedPaiements = 0;
        $deletedDroits = 0;

        foreach ($instances as $instance) {
            $instance->corbeille_actuelle = 'ca_validation_pointages';
            $instance->save();

            $pointage = Pointage::find($instance->pointage_id);
            if ($pointage) {
                $droit = DroitPaiement::where('stage_id', $pointage->stage_id)
                    ->where('periode_id', $pointage->periode_id)
                    ->first();
                
                if ($droit) {
                    $paiements = Paiement::where('droit_paiement_id', $droit->id)->get();
                    foreach ($paiements as $p) {
                        $p->delete();
                        $deletedPaiements++;
                    }
                    $droit->delete();
                    $deletedDroits++;
                }
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Fixed {$instances->count()} instances.");
        $this->info("Deleted {$deletedDroits} DroitsPaiement and {$deletedPaiements} Paiements.");

        return 0;
    }
}
