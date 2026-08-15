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

    public function index(Request $request)
    {
        $mois = $request->query('mois', \Carbon\Carbon::now()->format('Y-m'));
        $periode = \App\Models\Reference\Periode::where('code', $mois)->first();

        $dossiersAttenteCB = \App\Models\Payment\DossierPaiement::with(['agence', 'sourceFinancement', 'periode'])
            ->withCount('paiements')
            ->where('statut', 'TRANSMIS_CB')
            ->where('periode_id', $periode?->id)
            ->get();

        $dossiersAjournes = \App\Models\Payment\DossierPaiement::with(['agence', 'sourceFinancement', 'periode'])
            ->withCount('paiements')
            ->where('statut', 'AJOURNE_CB')
            ->where('periode_id', $periode?->id)
            ->get();

        return Inertia::render('Cb/Paiements/Index', [
            'dossiersControle' => $this->corbeilles->dossierRows($dossiersAttenteCB, 'En attente CB'),
            'etatsAjournes' => $this->corbeilles->dossierRows($dossiersAjournes, 'Ajourné CB'),
            'moisActuel' => $mois,
            'periode' => $periode,
        ]);
    }

    public function valider(Request $request, $id)
    {
        $dossier = \App\Models\Payment\DossierPaiement::findOrFail($id);
        $dossier->update(['statut' => 'VALIDE_CB']);

        return redirect()->back()->with('success', 'Dossier validé et transmis à la DMG pour élaboration OP.');
    }

    public function ajourner(Request $request, $id)
    {
        $dossier = \App\Models\Payment\DossierPaiement::findOrFail($id);
        // Retourne à la DMG pour correction du dossier
        $dossier->update(['statut' => 'AJOURNE_CB']);

        return redirect()->back()->with('success', 'Dossier ajourné et renvoyé à la DMG.');
    }
}
