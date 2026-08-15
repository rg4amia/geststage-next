<?php

namespace App\Domain\Registration\Services;

use App\Domain\Workflow\Services\WorkflowTransitionService;
use App\Models\Beneficiary\Beneficiaire;
use App\Models\Contract\Contrat;
use App\Models\Internship\Stage;
use App\Models\User;
use App\Models\Workflow\DefinitionParcours;
use App\Models\Workflow\InstanceParcours;
use Illuminate\Support\Facades\DB;

class InscriptionStagiaireService
{
    public function __construct(
        private readonly WorkflowTransitionService $workflowService
    ) {}

    /**
     * Inscrit un nouveau stagiaire en créant le bénéficiaire, le stage, le contrat initial
     * et en initialisant le moteur de workflow.
     */
    public function inscrire(
        array $donneesBeneficiaire,
        array $donneesStage,
        array $donneesContrat,
        User $cip
    ): InstanceParcours {
        return DB::transaction(function () use ($donneesBeneficiaire, $donneesStage, $donneesContrat, $cip) {
            // 1. Création du bénéficiaire
            $beneficiaire = Beneficiaire::create($donneesBeneficiaire);

            // 2. Création du stage
            $stage = Stage::create(array_merge($donneesStage, [
                'beneficiaire_id' => $beneficiaire->id,
            ]));

            // 3. Création du contrat initial (Brouillon)
            Contrat::create(array_merge($donneesContrat, [
                'stage_id' => $stage->id,
                'statut' => 'BROUILLON',
                'version_verrouillage' => 0,
            ]));

            // 4. Initialisation du Workflow
            $definition = DefinitionParcours::where('code', 'PAE')->where('active', true)->firstOrFail();
            
            return $this->workflowService->initier(
                $definition,
                $cip,
                ['stage_id' => $stage->id],
                ['action' => 'Inscription stagiaire']
            );
        });
    }
}
