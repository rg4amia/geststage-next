<?php

namespace App\Http\Controllers\Dmg;

use App\Domain\Payment\Services\DmgService;
use App\Http\Controllers\Controller;
use App\Models\Payment\DossierPaiement;
use App\Models\Payment\Paiement;
use App\Models\Reference\Periode;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;

class PaiementDmgController extends Controller
{
    protected $dmgService;

    public function __construct(DmgService $dmgService)
    {
        $this->dmgService = $dmgService;
    }

    public function index(Request $request)
    {
        $mois = $request->query('mois', Carbon::now()->format('Y-m'));
        $periode = Periode::where('nom', 'like', "%$mois%")->first();

        $attentePaiementDemarrage = collect();
        $attentePaiementPresence = collect();
        if ($periode) {
            $paiementsATraiter = Paiement::with(['droitPaiement.stage.beneficiaire', 'droitPaiement.stage.agence', 'droitPaiement.stage.entreprise'])
                ->aTraiter()
                ->whereHas('droitPaiement', function ($q) use ($periode) {
                    $q->where('periode_id', $periode->id);
                })
                ->get();
            
            $attentePaiementDemarrage = $paiementsATraiter->filter(function($p) {
                return $p->droitPaiement->nature === 'DEMARRAGE';
            })->values();

            $attentePaiementPresence = $paiementsATraiter->filter(function($p) {
                return $p->droitPaiement->nature === 'PRESENCE';
            })->values();
        }

        $dossiersBrouillon = DossierPaiement::with(['agence', 'sourceFinancement', 'periode'])
            ->brouillon()
            ->where('periode_id', $periode?->id)
            ->get();

        $dossiersTransmis = DossierPaiement::with(['agence', 'sourceFinancement', 'periode'])
            ->transmisAc()
            ->where('periode_id', $periode?->id)
            ->get();

        $dossiersAjournes = DossierPaiement::with(['agence', 'sourceFinancement', 'periode'])
            ->ajourneDmg()
            ->where('periode_id', $periode?->id)
            ->get();

        return Inertia::render('Dmg/Paiements/Index', [
            'attentePaiementDemarrage' => $attentePaiementDemarrage,
            'attentePaiementPresence' => $attentePaiementPresence,
            'dossiersBrouillon' => $dossiersBrouillon,
            'dossiersTransmis' => $dossiersTransmis,
            'dossiersAjournes' => $dossiersAjournes,
            'moisActuel' => $mois,
            'periode' => $periode
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
        $this->dmgService->transmettreDossierAc($dossier);

        return redirect()->back()->with('success', 'Dossier transmis à l\'Agent Comptable.');
    }
}
