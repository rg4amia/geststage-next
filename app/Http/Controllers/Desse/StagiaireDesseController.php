<?php

namespace App\Http\Controllers\Desse;

use App\Domain\Workflow\Services\CorbeilleParcoursQueryService;
use App\Domain\Workflow\Services\WorkflowTransitionService;
use App\Enums\CorbeilleEnum;
use App\Http\Controllers\Controller;
use App\Models\Workflow\InstanceParcours;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class StagiaireDesseController extends Controller
{
    public function __construct(
        private CorbeilleParcoursQueryService $corbeilles,
        private WorkflowTransitionService $workflow,
    ) {}

    public function index()
    {
        $attenteValidation = $this->corbeilles->instanceRows(
            CorbeilleEnum::DESSE_ATTENTE_VERIFICATION_DMG,
            'Attente de vérification'
        );
        $doublons = $this->corbeilles->instanceRows(
            CorbeilleEnum::DESSE_DOUBLONS_A_TRAITER,
            'Doublon suspecté'
        );

        return Inertia::render('Desse/Stagiaires/Index', [
            'attenteValidation' => $attenteValidation,
            'doublons' => $doublons,
            'statistiques' => [
                'attente_validation' => $attenteValidation->count(),
                'doublons' => $doublons->count(),
                'total' => $attenteValidation->count() + $doublons->count(),
            ],
        ]);
    }

    public function valider(Request $request, int $id): RedirectResponse
    {
        $instance = InstanceParcours::findOrFail($id);
        $this->workflow->desseValidePejedec($instance);

        return back()->with('success', 'Dossier validé par la DESSE et transmis à la DAICG.');
    }

    public function ajourner(Request $request, int $id): RedirectResponse
    {
        $request->validate([
            'motif' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        $instance = InstanceParcours::findOrFail($id);
        $this->workflow->desseAjournePejedec($instance);

        return back()->with('success', 'Dossier ajourné et renvoyé vers l’agence.');
    }

    public function traiterDoublon(Request $request, int $id): RedirectResponse
    {
        $instance = InstanceParcours::findOrFail($id);
        $this->workflow->desseTraiteDoublon($instance);

        return back()->with('success', 'Doublon traité et retiré de la corbeille de traitement.');
    }
}
