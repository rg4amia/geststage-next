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

        $bordereauxAttenteVisa = \App\Models\Payment\BordereauPaiement::where('statut', 'TRANSMIS_AC')
            ->where('periode_id', $periode?->id)
            ->get();

        $bordereauxVises = \App\Models\Payment\BordereauPaiement::where('statut', 'VISE_AC')
            ->where('periode_id', $periode?->id)
            ->get();

        return Inertia::render('AgentComptable/Paiements/Index', [
            // On peut adapter corbeilles->dossierRows pour accepter des bordereaux, ou le faire manuellement
            // Mais pour garder l'API de CorbeilleParcoursQueryService, on l'utilise tel quel en attendant
            'bordereauxAttente' => $bordereauxAttenteVisa,
            'ordresRejetes' => $bordereauxVises,
            'statutPaiements' => $bordereauxVises,
            'moisActuel' => $mois,
            'periode' => $periode,
        ]);
    }

    public function viser(Request $request, $id)
    {
        $bordereau = \App\Models\Payment\BordereauPaiement::findOrFail($id);
        $this->acService->viserBordereau($bordereau);

        return redirect()->back()->with('success', 'Bordereau visé avec succès.');
    }

    public function ajourner(Request $request, $id)
    {
        $request->validate(['motif' => 'required|string|min:5']);

        $bordereau = \App\Models\Payment\BordereauPaiement::findOrFail($id);
        $this->acService->ajournerBordereau($bordereau, $request->motif);

        return redirect()->back()->with('success', 'Bordereau ajourné vers la DMG.');
    }

    public function rejeter(Request $request, $id)
    {
        $request->validate(['motif' => 'required|string|min:5']);

        $bordereau = \App\Models\Payment\BordereauPaiement::findOrFail($id);
        $this->acService->rejeterBordereau($bordereau, $request->motif);

        return redirect()->back()->with('success', 'Bordereau rejeté définitivement.');
    }
}
