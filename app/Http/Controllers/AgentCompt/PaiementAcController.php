<?php

namespace App\Http\Controllers\AgentCompt;

use App\Domain\Payment\Services\AgentComptableService;
use App\Http\Controllers\Controller;
use App\Models\Payment\DossierPaiement;
use App\Models\Reference\Periode;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;

class PaiementAcController extends Controller
{
    protected $acService;

    public function __construct(AgentComptableService $acService)
    {
        $this->acService = $acService;
    }

    public function index(Request $request)
    {
        $mois = $request->query('mois', Carbon::now()->format('Y-m'));
        $periode = Periode::where('nom', 'like', "%$mois%")->first();

        $dossiersAttenteVisa = DossierPaiement::with(['agence', 'sourceFinancement', 'periode'])
            ->transmisAc()
            ->where('periode_id', $periode?->id)
            ->get();

        $dossiersVises = DossierPaiement::with(['agence', 'sourceFinancement', 'periode'])
            ->viseAc()
            ->where('periode_id', $periode?->id)
            ->get();

        return Inertia::render('AgentComptable/Paiements/Index', [
            'dossiersAttenteVisa' => $dossiersAttenteVisa,
            'dossiersVises' => $dossiersVises,
            'moisActuel' => $mois,
            'periode' => $periode
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
