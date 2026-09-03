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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        $periodeId = $periode?->id;
        $dossiers = fn (array $statuts) => DossierPaiement::query()
            ->with(['agence', 'sourceFinancement', 'periode'])->withCount('paiements')
            ->whereIn('statut', $statuts)->where('periode_id', $periodeId)
            ->orderByDesc('created_at')->limit(500)->get();

        // Le squelette de la page part immédiatement ; les jeux de données lourds sont chargés
        // ensuite par Inertia, un groupe = une requête parallèle. Sur 2026-08 le rendu synchrone
        // coûtait ~17 s (compteurs 10,5 s + démarrage 3 s + présence 3,3 s), les onglets « dossiers »
        // étant eux négligeables (~25 ms par requête).
        //
        // Les builders sont volontairement reconstruits dans chaque closure : ils sont paresseux
        // (aucun SQL tant qu'on ne les exécute pas) et, une requête différée relançant index()
        // en entier, les partager entre groupes ferait travailler chaque requête pour les autres.
        return Inertia::render('Dmg/Paiements/Index', [
            'attenteDemarrage' => Inertia::defer(fn () => $this->corbeilles->paiementRows(
                $this->dmgService->applyCohorteFilter(
                    $this->dmgService->attentePaiementDemarrage($filters, $mois),
                    $cohorte
                )->orderByDesc('paiements.created_at')->limit(DmgService::LIMITE_LISTE_ATTENTE)->get()
            ), 'demarrage'),
            // Aucune cohorte ici : la présence couvre le mois entier (cf. applyCohorteFilter()).
            'attentePresence' => Inertia::defer(fn () => $this->corbeilles->paiementRows(
                $this->dmgService->attentePaiementPresence($filters, $mois)
                    ->orderByDesc('paiements.created_at')->limit(DmgService::LIMITE_LISTE_ATTENTE)->get()
            ), 'presence'),
            // 5 COUNT sur la requête lourde : isolé pour ne pas retarder l'arrivée des listes.
            'compteurs' => Inertia::defer(fn () => $this->compteurs(
                $this->dmgService->attentePaiementDemarrage($filters, $mois),
                $this->dmgService->attentePaiementPresence($filters, $mois),
            ), 'compteurs'),
            'dossiers' => Inertia::defer(fn () => $this->corbeilles->dossierRows($dossiers(['BROUILLON']), 'En elaboration'), 'dossiers'),
            'dossiersTransmis' => Inertia::defer(fn () => $this->corbeilles->dossierRows($dossiers(['TRANSMIS_CB', 'VALIDE_CB', 'EN_OP']), 'Circuit CB/OP'), 'dossiers'),
            'dossiersAjournes' => Inertia::defer(fn () => $this->corbeilles->dossierRows($dossiers(['AJOURNE_DMG', 'AJOURNE_CB']), 'Ajourne DMG/CB'), 'dossiers'),
            'dossiersGroupables' => Inertia::defer(fn () => $this->corbeilles->dossierRows(
                DossierPaiement::query()
                    ->where('periode_id', $periodeId)
                    ->where('statut', 'BROUILLON')
                    ->whereNull('ordre_paiement_id')
                    ->whereDoesntHave('groupes')
                    ->orderByDesc('created_at')
                    ->limit(500)
                    ->get(),
                'Eligible multi-dossier'
            ), 'dossiers'),
            'dossiersEligiblesOp' => Inertia::defer(fn () => $this->corbeilles->dossierRows(
                DossierPaiement::query()
                    ->where('periode_id', $periodeId)
                    ->where('statut', 'VALIDE_CB')
                    ->whereNull('ordre_paiement_id')
                    ->orderByDesc('created_at')
                    ->limit(500)
                    ->get(),
                'Valide CB'
            ), 'dossiers'),
            'groupesDossiers' => Inertia::defer(fn () => DossierGroupe::query()
                ->with('sourceFinancement:id,nom')
                ->withCount('dossiers')
                ->where('periode_id', $periodeId)
                ->orderByDesc('created_at')
                ->limit(500)
                ->get(), 'dossiers'),
            'ops' => Inertia::defer(fn () => OrdrePaiement::where('periode_id', $periodeId)->orderByDesc('created_at')->limit(500)->get(), 'dossiers'),
            'opsEligiblesBordereau' => Inertia::defer(fn () => OrdrePaiement::query()
                ->where('periode_id', $periodeId)
                ->where('statut', 'BROUILLON')
                ->whereNull('bordereau_paiement_id')
                ->orderByDesc('created_at')
                ->limit(500)
                ->get(), 'dossiers'),
            'bordereaux' => Inertia::defer(fn () => BordereauPaiement::where('periode_id', $periodeId)->orderByDesc('created_at')->limit(500)->get(), 'dossiers'),
            'moisActuel' => $mois,
            'periode' => $periode,
            'filters' => $filters,
            'cohorte' => $cohorte,
            'limiteAffichee' => 100,
            'agences' => Agence::cachedOptions('nom'),
            'entreprises' => Entreprise::cachedOptions('raison_sociale'),
            'sourcesFinancement' => SourceFinancement::cachedOptions('nom'),
            'typesStage' => TypeStage::cachedOptions('nom'),
            'typestructures' => TypeStructure::cachedOptions('nom'),
            'periodeOptions' => Periode::cachedOptions('code', descending: true),
        ]);
    }

    /**
     * API : Dossiers CB par mois (DMG - onglet Transmis CB)
     */
    public function dossiersCbByMois(Request $request): JsonResponse
    {
        $mois = $request->query('mois', Carbon::now()->format('Y-m'));
        $periode = Periode::where('code', $mois)->first();
        $statut = $request->query('statut', 'TRANSMIS_CB');
        $search = $request->query('search');

        $query = DossierPaiement::with(['agence', 'sourceFinancement'])
            ->withCount('paiements')
            ->where('statut', $statut)
            ->where('periode_id', $periode?->id);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('numero', 'like', "%{$search}%")
                    ->orWhereHas('paiements.droitPaiement.stage.beneficiaire', function ($q2) use ($search) {
                        $q2->where('nom', 'like', "%{$search}%")
                            ->orWhere('prenoms', 'like', "%{$search}%")
                            ->orWhere('numero_aej', 'like', "%{$search}%");
                    });
            });
        }

        $dossiers = $query
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (DossierPaiement $d) => [
                'id' => $d->id,
                'identifiant' => $d->numero,
                'agence' => $d->agence?->nom ?? '-',
                'source_financement' => $d->sourceFinancement?->nom ?? '-',
                'nombre_stagiaires' => $d->paiements_count,
                'montant_total' => $d->montant_total,
                'statut' => $d->statut,
            ]);

        return response()->json($dossiers);
    }

    /**
     * API : Stagiaires d'un dossier pour la vue DMG
     */
    public function stagiairesByDossier(Request $request): JsonResponse
    {
        $request->validate([
            'dossier_id' => 'required|integer',
            'start' => 'nullable|integer',
            'length' => 'nullable|integer',
            'search' => 'nullable|string',
        ]);

        $dossierId = $request->input('dossier_id');
        $start = $request->integer('start', 0);
        $length = $request->integer('length', 10);
        $search = $request->input('search', '');

        $query = DB::table('lignes_dossiers_paiement')
            ->join('paiements', 'lignes_dossiers_paiement.paiement_id', '=', 'paiements.id')
            ->join('droits_paiement', 'paiements.droit_paiement_id', '=', 'droits_paiement.id')
            ->join('stages', 'droits_paiement.stage_id', '=', 'stages.id')
            ->join('beneficiaires', 'stages.beneficiaire_id', '=', 'beneficiaires.id')
            ->leftJoin('entreprises', 'stages.entreprise_id', '=', 'entreprises.id')
            ->leftJoin('agences', 'stages.agence_id', '=', 'agences.id')
            ->leftJoin('sources_financement', 'droits_paiement.source_financement_id', '=', 'sources_financement.id')
            ->leftJoin('types_stage', 'stages.type_stage_id', '=', 'types_stage.id')
            ->where('lignes_dossiers_paiement.dossier_paiement_id', $dossierId)
            ->whereNull('lignes_dossiers_paiement.retire_le')
            ->select(
                'paiements.id as paiement_id',
                'paiements.created_at',
                'lignes_dossiers_paiement.montant',
                'paiements.statut',
                'beneficiaires.nom',
                'beneficiaires.prenoms',
                'beneficiaires.numero_aej',
                'beneficiaires.date_naissance',
                'beneficiaires.numero_tresor_money as tresor_pay',
                'entreprises.raison_sociale as entreprise',
                'agences.nom as agence',
                'sources_financement.nom as source_financement',
                'types_stage.nom as type_stage',
                'stages.id as stage_id',
                'stages.date_debut',
                'stages.date_fin_prevue as date_fin'
            );

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('beneficiaires.nom', 'LIKE', "%{$search}%")
                    ->orWhere('beneficiaires.prenoms', 'LIKE', "%{$search}%")
                    ->orWhere('beneficiaires.numero_aej', 'LIKE', "%{$search}%");
            });
        }

        $total = (clone $query)->count();
        $stagiaires = $query->orderByDesc('paiements.created_at')
            ->offset($start)
            ->limit($length)
            ->get();

        return response()->json([
            'draw' => $request->integer('draw', 1),
            'recordsTotal' => $total,
            'recordsFiltered' => $total,
            'data' => $stagiaires,
        ]);
    }

    /**
     * Compteurs de l'écran : les totaux des deux files, puis le détail par cohorte du seul
     * démarrage. Compter la présence par cohorte renvoyait le résidu « hors cohorte »
     * (1 stagiaire sur 2 415 en 2026-08) là où l'onglet affiche bien le mois entier.
     *
     * @return array<string, int|array<string, int>>
     */
    private function compteurs(Builder $demarrageBase, Builder $presenceBase): array
    {
        $result = [
            'demarrage' => (clone $demarrageBase)->count(),
            'presence' => $presenceBase->count(),
        ];

        foreach (['global', 'cohorte1', 'cohorte2', 'cohorte3'] as $cohorte) {
            $result[$cohorte] = [
                'demarrage' => $this->dmgService->applyCohorteFilter(clone $demarrageBase, $cohorte)->count(),
            ];
        }

        return $result;
    }

    public function documentsByStage(Request $request): JsonResponse
    {
        $request->validate(['stage_id' => 'required|integer']);
        $stageId = $request->input('stage_id');

        $documents = DB::table('documents')
            ->join('versions_documents', 'documents.id', '=', 'versions_documents.document_id')
            ->leftJoin('types_document', 'documents.type_document_id', '=', 'types_document.id')
            ->where('documents.stage_id', $stageId)
            ->select(
                'documents.id',
                'documents.nom',
                'types_document.code as type_code',
                'types_document.nom as type_nom',
                'versions_documents.chemin',
                'versions_documents.nom_original',
                'versions_documents.type_mime',
                'versions_documents.taille_octets'
            )
            ->orderByDesc('versions_documents.numero_version')
            ->get();

        return response()->json(['data' => $documents]);
    }
}
