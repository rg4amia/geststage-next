<?php

namespace App\Http\Controllers\Dmg;

use App\Domain\Payment\Services\DmgService;
use App\Domain\Workflow\Services\CorbeilleParcoursQueryService;
use App\Http\Controllers\Controller;
use App\Models\Company\Entreprise;
use App\Models\Payment\BordereauPaiement;
use App\Models\Payment\DossierGroupe;
use App\Models\Payment\DossierPaiement;
use App\Models\Payment\OrdrePaiement;
use App\Models\Reference\Agence;
use App\Models\Reference\Periode;
use App\Models\Reference\SourceFinancement;
use App\Models\Reference\TypeStage;
use App\Models\Reference\TypeStructure;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class PaiementDmgController extends Controller
{
    public function __construct(
        private DmgService $dmgService,
        private CorbeilleParcoursQueryService $corbeilles,
    ) {}

    public function index(Request $request): Response
    {
        $mois = $request->string('mois', Carbon::now()->format('Y-m'))->toString();
        $periode = Periode::where('code', $mois)->first();
        $filters = $request->only([
            'agence_id', 'entreprise_id', 'source_financement_id', 'type_stage_id',
            'type_structure_id', 'date_debut', 'date_fin', 'search', 'dossier_physique',
        ]);
        $cohorte = $request->string('cohorte', 'global')->toString();

        $demarrage = $this->dmgService->attentePaiementDemarrage($filters, $mois);
        $presence = $this->dmgService->attentePaiementPresence($filters, $mois);
        $compteurs = $this->compteurs($filters, $mois);

        if ($cohorte !== 'global') {
            $demarrage = $this->dmgService->applyCohorteFilter($demarrage, $cohorte);
        }

        $periodeId = $periode?->id;
        $dossiers = fn (array $statuts) => DossierPaiement::query()
            ->with(['agence', 'sourceFinancement', 'periode'])->withCount('paiements')
            ->whereIn('statut', $statuts)->where('periode_id', $periodeId)
            ->orderByDesc('created_at')->limit(500)->get();
        $dossiersGroupables = DossierPaiement::query()
            ->where('periode_id', $periodeId)
            ->where('statut', 'BROUILLON')
            ->whereNull('ordre_paiement_id')
            ->whereDoesntHave('groupes')
            ->orderByDesc('created_at')
            ->limit(500)
            ->get();
        $dossiersEligiblesOp = DossierPaiement::query()
            ->where('periode_id', $periodeId)
            ->where('statut', 'VALIDE_CB')
            ->whereNull('ordre_paiement_id')
            ->orderByDesc('created_at')
            ->limit(500)
            ->get();
        $groupesDossiers = DossierGroupe::query()
            ->with('sourceFinancement:id,nom')
            ->withCount('dossiers')
            ->where('periode_id', $periodeId)
            ->orderByDesc('created_at')
            ->limit(500)
            ->get();
        $opsEligiblesBordereau = OrdrePaiement::query()
            ->where('periode_id', $periodeId)
            ->where('statut', 'BROUILLON')
            ->whereNull('bordereau_paiement_id')
            ->orderByDesc('created_at')
            ->limit(500)
            ->get();

        return Inertia::render('Dmg/Paiements/Index', [
            'attenteDemarrage' => $this->corbeilles->paiementRows($demarrage->orderByDesc('paiements.created_at')->limit(100)->get()),
            'attentePresence' => $this->corbeilles->paiementRows($presence->orderByDesc('paiements.created_at')->limit(100)->get()),
            'compteurs' => $compteurs,
            'dossiers' => $this->corbeilles->dossierRows($dossiers(['BROUILLON']), 'En elaboration'),
            'dossiersTransmis' => $this->corbeilles->dossierRows($dossiers(['TRANSMIS_CB', 'VALIDE_CB', 'EN_OP']), 'Circuit CB/OP'),
            'dossiersAjournes' => $this->corbeilles->dossierRows($dossiers(['AJOURNE_DMG', 'AJOURNE_CB']), 'Ajourne DMG/CB'),
            'dossiersGroupables' => $this->corbeilles->dossierRows($dossiersGroupables, 'Eligible multi-dossier'),
            'dossiersEligiblesOp' => $this->corbeilles->dossierRows($dossiersEligiblesOp, 'Valide CB'),
            'groupesDossiers' => $groupesDossiers,
            'ops' => OrdrePaiement::where('periode_id', $periodeId)->orderByDesc('created_at')->limit(500)->get(),
            'opsEligiblesBordereau' => $opsEligiblesBordereau,
            'bordereaux' => BordereauPaiement::where('periode_id', $periodeId)->orderByDesc('created_at')->limit(500)->get(),
            'moisActuel' => $mois,
            'periode' => $periode,
            'filters' => $filters,
            'cohorte' => $cohorte,
            'limiteAffichee' => 100,
            'agences' => Cache::remember('ref.agences_arr', 86400, fn () => Agence::orderBy('nom')->get(['id', 'nom'])->toArray()),
            'entreprises' => Cache::remember('ref.entreprises_arr', 86400, fn () => Entreprise::orderBy('raison_sociale')->get(['id', 'raison_sociale'])->toArray()),
            'sourcesFinancement' => Cache::remember('ref.sources_financement_arr', 86400, fn () => SourceFinancement::orderBy('nom')->get(['id', 'nom'])->toArray()),
            'typesStage' => Cache::remember('ref.types_stage_arr', 86400, fn () => TypeStage::orderBy('nom')->get(['id', 'nom'])->toArray()),
            'typestructures' => Cache::remember('ref.typestructures_arr', 86400, fn () => TypeStructure::orderBy('nom')->get(['id', 'nom'])->toArray()),
            'periodeOptions' => Cache::remember('ref.periodes_paiement', 3600, fn () => Periode::orderByDesc('code')->get(['id', 'code'])->toArray()),
        ]);
    }

    /** @param array<string, mixed> $filters */
    private function compteurs(array $filters, string $mois): array
    {
        $result = [];
        foreach (['global', 'cohorte1', 'cohorte2', 'cohorte3'] as $cohorte) {
            $demarrage = $this->dmgService->attentePaiementDemarrage($filters, $mois);
            $presence = $this->dmgService->attentePaiementPresence($filters, $mois);
            if ($cohorte !== 'global') {
                $demarrage = $this->dmgService->applyCohorteFilter($demarrage, $cohorte);
                $presence = $this->dmgService->applyCohorteFilter($presence, $cohorte);
            }
            $result[$cohorte] = ['demarrage' => $demarrage->count(), 'presence' => $presence->count()];
        }

        return $result;
    }
}
