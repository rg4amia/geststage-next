<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Internship\Stage;
use App\Models\User;
use App\Domain\Validation\Services\ValidationChefAgenceService;
use Illuminate\Support\Facades\DB;

class FixLegacyChefAgenceValidationCommand extends Command
{
    protected $signature = 'fix:legacy-chef-agence-validation';
    protected $description = 'Fix legacy stages that were validated by CA (etat_chef_agence=2) but got stuck in ca_attente_validation_demarrage without payments.';

    public function handle(ValidationChefAgenceService $validationService)
    {
        $this->info("Fetching stuck instances...");

        // Find all stages that have an ancien_id and are stuck in CA's corbeille
        $stages = Stage::whereNotNull('ancien_id')
            ->whereHas('instanceParcours', function ($q) {
                $q->where('corbeille_actuelle', 'ca_attente_validation_demarrage');
            })
            ->with(['instanceParcours', 'contrats'])
            ->get();
            
        $this->info("Found {$stages->count()} total stages in CA corbeille.");
        
        $adminUser = User::whereHas('roles', fn($q) => $q->where('name', 'administrateur'))->first() ?? User::first();

        $fixedCount = 0;
        
        $bar = $this->output->createProgressBar($stages->count());
        $bar->start();

        foreach ($stages as $stage) {
            try {
                // Check legacy DB for etat_chef_agence
                $legacyRow = DB::connection('legacy')->table('contrats_pae')->where('id', $stage->ancien_id)->first();
                if ($legacyRow && $legacyRow->etat_chef_agence == 2) {
                    // It was ALREADY validated by CA in legacy. We must generate the payment and transition.
                    $validationService->validerDemarrage($stage->instanceParcours, $adminUser);
                    $fixedCount++;
                }
            } catch (\Exception $e) {
                // Ignore errors for individual stages
            }
            $bar->advance();
        }
        
        $bar->finish();
        
        $this->info("\nFixed $fixedCount stages (Generated missing DroitPaiement and transitioned to DMG).");
    }
}
