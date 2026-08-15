<?php

namespace App\Http\Controllers\Cb;

use App\Domain\Workflow\Services\CorbeilleParcoursQueryService;
use App\Enums\CorbeilleEnum;
use App\Http\Controllers\Controller;
use App\Models\Workflow\InstanceParcours;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PaiementCbController extends Controller
{
    public function __construct(private CorbeilleParcoursQueryService $corbeilles) {}

    public function index()
    {
        return Inertia::render('Cb/Paiements/Index', [
            'dossiersControle' => $this->corbeilles->instanceRows(CorbeilleEnum::CB_DOSSIER_MULTIPLE, 'En attente'),
            'etatsAjournes' => $this->corbeilles->instanceRows(CorbeilleEnum::CB_ETAT_PAIEMENT_AJOURNE, 'Ajourné'),
        ]);
    }

    public function valider(Request $request, $id)
    {
        $instance = InstanceParcours::findOrFail($id);
        $instance->update(['corbeille_actuelle' => CorbeilleEnum::DMG_ELABORATION_OP->value]);

        return redirect()->back()->with('success', 'Dossier validé et transmis à la DMG pour élaboration OP.');
    }

    public function ajourner(Request $request, $id)
    {
        $instance = InstanceParcours::findOrFail($id);
        // Retourne à la DMG pour correction du dossier
        $instance->update(['corbeille_actuelle' => CorbeilleEnum::DMG_ATTENTE_PAIEMENT_PRESENCE->value]); // Exemple

        return redirect()->back()->with('success', 'Dossier ajourné et renvoyé à la DMG.');
    }
}
