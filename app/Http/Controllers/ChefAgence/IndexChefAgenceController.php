<?php

namespace App\Http\Controllers\ChefAgence;

use App\Domain\Workflow\Services\WorkflowTransitionService;
use App\Enums\CorbeilleEnum;
use App\Http\Controllers\Controller;
use App\Models\Workflow\InstanceParcours;
use Illuminate\Http\Request;
use Inertia\Inertia;

class IndexChefAgenceController extends Controller
{
    protected $workflowService;

    public function __construct(WorkflowTransitionService $workflowService)
    {
        $this->workflowService = $workflowService;
    }

    public function listeStagiaireAttenteValidation(Request $request)
    {
        // 1. Démarrage (Mois en cours)
        $demarrage = InstanceParcours::with([
            'stage.beneficiaire', 
            'stage.entreprise', 
            'stage.agence', 
            'stage.contrats'
        ])->where('corbeille_actuelle', CorbeilleEnum::CA_ATTENTE_VALIDATION_DEMARRAGE)
        ->get();

        // 2. Démarrage Omis (Mois antérieurs ou retours)
        $demarrageOmis = InstanceParcours::with([
            'stage.beneficiaire', 
            'stage.entreprise', 
            'stage.agence', 
            'stage.contrats'
        ])->where('corbeille_actuelle', CorbeilleEnum::CA_ATTENTE_VALIDATION_OMIS)
        ->get();

        return Inertia::render('ChefAgence/ValidationDemarrage/Index', [
            'demarrage' => $demarrage,
            'demarrageOmis' => $demarrageOmis
        ]);
    }

    public function validerDemarrage(Request $request, $id)
    {
        $instance = InstanceParcours::findOrFail($id);
        $this->workflowService->caValideDemarrage($instance);
        return redirect()->back()->with('success', 'Dossier validé et transmis à la DMG.');
    }

    public function validerDemarrageOmis(Request $request, $id)
    {
        $instance = InstanceParcours::findOrFail($id);
        $this->workflowService->caValideDemarrageOmis($instance);
        return redirect()->back()->with('success', 'Dossier Démarrage Omis validé, transmis au CIP pour pointage.');
    }
}
