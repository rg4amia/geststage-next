<?php

namespace App\Http\Controllers\Dmg;

use App\Domain\Payment\Services\DmgService;
use App\Domain\Workflow\Services\CorbeilleParcoursQueryService;
use App\Http\Controllers\Controller;
use App\Models\Payment\DossierPaiement;
use App\Models\Payment\Paiement;
use App\Models\Reference\Periode;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PaiementDmgController extends Controller
{
    public function __construct(
        private DmgService $dmgService,
        private CorbeilleParcoursQueryService $corbeilles
    ) {}

    public function index(Request $request)
    {
        $mois = $request->query('mois', Carbon::now()->format('Y-m'));
        $periode = Periode::where('code', $mois)->first();

        $attentePaiementDemarrage = collect();
        $attentePaiementPresence = collect();
        if ($periode) {
            $paiementsATraiter = Paiement::with(['droitPaiement.stage.beneficiaire', 'droitPaiement.stage.agence', 'droitPaiement.stage.entreprise', 'droitPaiement.stage.sourceFinancement'])
                ->aTraiter()
                ->whereHas('droitPaiement', function ($q) use ($periode) {
                    $q->where('periode_id', $periode->id);
                })
                ->get();

            $attentePaiementDemarrage = $paiementsATraiter->filter(function ($p) {
                return $p->droitPaiement->nature === 'DEMARRAGE';
            })->values();

            $attentePaiementPresence = $paiementsATraiter->filter(function ($p) {
                return $p->droitPaiement->nature === 'PRESENCE';
            })->values();
        }

        $dossiersBrouillon = DossierPaiement::with(['agence', 'sourceFinancement', 'periode'])
            ->withCount('paiements')
            ->brouillon()
            ->where('periode_id', $periode?->id)
            ->get();

        $dossiersTransmis = DossierPaiement::with(['agence', 'sourceFinancement', 'periode'])
            ->withCount('paiements')
            ->where('statut', 'TRANSMIS_CB')
            ->where('periode_id', $periode?->id)
            ->get();

        $dossiersAjournes = DossierPaiement::with(['agence', 'sourceFinancement', 'periode'])
            ->withCount('paiements')
            ->whereIn('statut', ['AJOURNE_DMG', 'AJOURNE_CB'])
            ->where('periode_id', $periode?->id)
            ->get();

        $ops = \App\Models\Payment\OrdrePaiement::where('periode_id', $periode?->id)->get();
        $bordereaux = \App\Models\Payment\BordereauPaiement::where('periode_id', $periode?->id)->get();

        return Inertia::render('Dmg/Paiements/Index', [
            'attenteDemarrage' => $this->corbeilles->paiementRows($attentePaiementDemarrage),
            'attentePresence' => $this->corbeilles->paiementRows($attentePaiementPresence),
            'dossiers' => $this->corbeilles->dossierRows($dossiersBrouillon, 'En élaboration'),
            'dossiersTransmis' => $this->corbeilles->dossierRows($dossiersTransmis, 'Transmis CB'),
            'dossiersAjournes' => $this->corbeilles->dossierRows($dossiersAjournes, 'Ajourné DMG/CB'),
            'ops' => $ops,
            'bordereaux' => $bordereaux,
            'moisActuel' => $mois,
            'periode' => $periode,
        ]);
    }

    public function generer(Request $request)
    {
        $request->validate(['periode_id' => 'required|exists:periodes,id']);

        $this->dmgService->genererDossiersPaiement($request->periode_id);

        return redirect()->back()->with('success', 'Dossiers de paiement générés avec succès.');
    }

    public function transmettre(Request $request, $dossierId)
    {
        $dossier = DossierPaiement::findOrFail($dossierId);
        $this->dmgService->transmettreDossierCb($dossier);

        return redirect()->back()->with('success', 'Dossier transmis au Chef de Bureau (CB).');
    }

    public function elaborerOp(Request $request)
    {
        $request->validate([
            'dossiers' => 'required|array',
            'periode_id' => 'required|exists:periodes,id'
        ]);

        $this->dmgService->elaborerOp($request->dossiers, $request->periode_id);

        return redirect()->back()->with('success', 'Ordre de Paiement élaboré.');
    }

    public function creerBordereau(Request $request)
    {
        $request->validate([
            'ops' => 'required|array',
            'periode_id' => 'required|exists:periodes,id'
        ]);

        $this->dmgService->creerBordereau($request->ops, $request->periode_id);

        return redirect()->back()->with('success', 'Bordereau de Paiement créé.');
    }

    public function transmettreBordereau(Request $request, $id)
    {
        $bordereau = \App\Models\Payment\BordereauPaiement::findOrFail($id);
        $this->dmgService->transmettreBordereauAc($bordereau);

        return redirect()->back()->with('success', 'Bordereau transmis à l\'Agent Comptable.');
    }
}
