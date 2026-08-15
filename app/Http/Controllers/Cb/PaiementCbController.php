<?php

namespace App\Http\Controllers\Cb;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Workflow\InstanceParcours;
use App\Enums\CorbeilleEnum;

class PaiementCbController extends Controller
{
    public function index()
    {
        // 1. Dossiers en attente de contrôle CB avant OP
        $controleDossiers = InstanceParcours::with(['stage.beneficiaire', 'stage.entreprise'])
            ->where('corbeille_actuelle', CorbeilleEnum::CB_DOSSIER_MULTIPLE->value)
            ->get()
            ->map(function ($instance) {
                return [
                    'id' => $instance->id,
                    'numero_dossier' => 'DOS-' . str_pad($instance->id, 4, '0', STR_PAD_LEFT),
                    'beneficiaire' => [
                        'nom' => $instance->stage->beneficiaire->nom ?? 'Inconnu',
                        'prenoms' => $instance->stage->beneficiaire->prenoms ?? '',
                        'matricule' => $instance->stage->beneficiaire->matricule ?? '',
                    ],
                    'montant' => 45000, // Mock montant pour l'instant
                    'statut' => 'En attente'
                ];
            });

        // 2. Dossiers ajournés par la Direction ou AC qui reviennent au CB
        $ajournes = InstanceParcours::with(['stage.beneficiaire'])
            ->where('corbeille_actuelle', CorbeilleEnum::CB_ETAT_PAIEMENT_AJOURNE->value)
            ->get()
            ->map(function ($instance) {
                return [
                    'id' => $instance->id,
                    'numero_dossier' => 'DOS-' . str_pad($instance->id, 4, '0', STR_PAD_LEFT),
                    'beneficiaire' => [
                        'nom' => $instance->stage->beneficiaire->nom ?? 'Inconnu',
                        'prenoms' => $instance->stage->beneficiaire->prenoms ?? '',
                    ],
                    'motif_ajournement' => 'Pièce manquante', // Mock motif
                    'date_ajournement' => $instance->updated_at->format('d/m/Y')
                ];
            });

        return Inertia::render('Cb/Paiements/Index', [
            'controleDossiers' => $controleDossiers,
            'ajournes' => $ajournes,
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
