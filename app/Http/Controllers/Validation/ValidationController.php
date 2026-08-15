<?php

namespace App\Http\Controllers\Validation;

use App\Domain\Validation\Services\ValidationChefAgenceService;
use App\Http\Controllers\Controller;
use App\Models\Workflow\EtapeParcours;
use App\Models\Workflow\InstanceParcours;
use Illuminate\Http\Request;

class ValidationController extends Controller
{
    protected $validationService;

    public function __construct(ValidationChefAgenceService $validationService)
    {
        $this->validationService = $validationService;
    }

    public function validerDemarrage(Request $request, $id)
    {
        $instance = InstanceParcours::findOrFail($id);

        $this->validationService->validerDemarrage($instance, $request->user());

        return redirect()->back()->with('success', 'Démarrage validé avec succès. Droit au paiement généré.');
    }

    public function ajourner(Request $request, $id)
    {
        $request->validate([
            'motif' => 'required|string|min:5',
        ]);

        $instance = InstanceParcours::findOrFail($id);

        $etapeRetour = EtapeParcours::where('definition_parcours_id', $instance->definition_parcours_id)
            ->where('nom', 'Saisie Inscription et Contrat CIP')
            ->firstOrFail();

        $this->validationService->ajourner($instance, $request->motif, $etapeRetour, $request->user());

        return redirect()->back()->with('success', 'Dossier ajourné et retourné au CIP pour correction.');
    }
}
