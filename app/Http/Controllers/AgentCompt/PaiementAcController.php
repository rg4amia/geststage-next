<?php

namespace App\Http\Controllers\AgentCompt;

use App\Domain\Payment\Services\AgentComptableService;
use App\Domain\Workflow\Services\CorbeilleParcoursQueryService;
use App\Http\Controllers\Controller;
use App\Models\Payment\DossierPaiement;
use App\Models\Reference\Periode;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PaiementAcController extends Controller
{
    public function __construct(
        private AgentComptableService $acService,
        private CorbeilleParcoursQueryService $corbeilles
    ) {}

    public function index(Request $request)
    {
        $mois = $request->query('mois', Carbon::now()->format('Y-m'));
        $periode = Periode::where('code', $mois)->first();

        $dossiersAttenteVisa = DossierPaiement::with(['agence', 'sourceFinancement', 'periode'])
            ->withCount('paiements')
            ->transmisAc()
            ->where('periode_id', $periode?->id)
            ->get();

        $dossiersVises = DossierPaiement::with(['agence', 'sourceFinancement', 'periode'])
            ->withCount('paiements')
            ->viseAc()
            ->where('periode_id', $periode?->id)
            ->get();

        return Inertia::render('AgentComptable/Paiements/Index', [
            'bordereauxAttente' => $this->corbeilles->dossierRows($dossiersAttenteVisa, 'En attente AC'),
            'ordresRejetes' => $this->corbeilles->dossierRows($dossiersVises, 'Validé AC'),
            'statutPaiements' => $this->corbeilles->dossierRows($dossiersVises, 'Payé'),
            'moisActuel' => $mois,
            'periode' => $periode,
        ]);
    }

    public function viser(Request $request, $id)
    {
        $dossier = DossierPaiement::findOrFail($id);
        $this->acService->viserDossier($dossier);

        return redirect()->back()->with('success', 'Dossier visé avec succès.');
    }

    public function ajourner(Request $request, $id)
    {
        $request->validate(['motif' => 'required|string|min:5']);

        $dossier = DossierPaiement::findOrFail($id);
        $this->acService->ajournerDossier($dossier, $request->motif);

        return redirect()->back()->with('success', 'Dossier ajourné vers la DMG.');
    }
}
