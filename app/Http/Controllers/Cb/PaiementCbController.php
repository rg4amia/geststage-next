<?php

namespace App\Http\Controllers\Cb;

use App\Domain\Payment\Services\CbPaiementService;
use App\Domain\Workflow\Services\CorbeilleParcoursQueryService;
use App\Http\Controllers\Controller;
use App\Models\Payment\DossierGroupe;
use App\Models\Payment\DossierPaiement;
use App\Models\Reference\Periode;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class PaiementCbController extends Controller
{
    public function __construct(
        private CorbeilleParcoursQueryService $corbeilles,
        private CbPaiementService $cbService,
    ) {}

    public function index(Request $request)
    {
        $mois = $request->query('mois', Carbon::now()->format('Y-m'));
        $periode = Periode::where('code', $mois)->first();

        $dossiersAttenteCB = DossierPaiement::with(['agence', 'sourceFinancement', 'periode'])
            ->withCount(['paiementsActifs as paiements_count'])
            ->where('statut', 'TRANSMIS_CB')
            ->where('periode_id', $periode?->id)
            ->orderByDesc('created_at')
            ->get();

        $dossiersAjournes = DossierPaiement::with(['agence', 'sourceFinancement', 'periode'])
            ->withCount(['paiementsActifs as paiements_count'])
            ->where('statut', 'AJOURNE_CB')
            ->where('periode_id', $periode?->id)
            ->orderByDesc('created_at')
            ->get();

        // Périodes disponibles
        $periodeOptions = Periode::orderByDesc('code')
            ->limit(24)
            ->get(['id', 'code']);

        $groupesControle = DossierGroupe::query()
            ->with('sourceFinancement:id,nom')
            ->withCount('dossiers')
            ->where('statut', 'TRANSMIS_CB')
            ->where('periode_id', $periode?->id)
            ->orderByDesc('created_at')
            ->get();

        $groupesAjournes = DossierGroupe::query()
            ->with('sourceFinancement:id,nom')
            ->withCount('dossiers')
            ->where('statut', 'AJOURNE_CB')
            ->where('periode_id', $periode?->id)
            ->orderByDesc('created_at')
            ->get();

        return Inertia::render('Cb/Paiements/Index', [
            'dossiersControle' => $this->corbeilles->dossierRows($dossiersAttenteCB, 'En attente CB'),
            'etatsAjournes' => $this->corbeilles->dossierRows($dossiersAjournes, 'Ajourné CB'),
            'groupesControle' => $groupesControle,
            'groupesAjournes' => $groupesAjournes,
            'moisActuel' => $mois,
            'periode' => $periode,
            'periodeOptions' => $periodeOptions,
        ]);
    }

    /**
     * API : Liste des dossiers pour un mois donné (select2).
     *
     * Un dossier rattaché à un multi-dossier déjà transmis au CB (`DossierGroupe` `TRANSMIS_CB`)
     * n'apparaît pas individuellement : il doit être traité en bloc via son multi-dossier,
     * seul listé — portage de la règle de regroupement du legacy `dossierByMonthSelected()`.
     */
    public function dossiersByMois(Request $request): JsonResponse
    {
        $mois = $request->query('mois', Carbon::now()->format('Y-m'));
        $periode = Periode::where('code', $mois)->first();

        $dossiers = DossierPaiement::with(['agence', 'sourceFinancement'])
            ->withCount(['paiementsActifs as paiements_count'])
            ->where('statut', 'TRANSMIS_CB')
            ->where('periode_id', $periode?->id)
            ->whereDoesntHave('groupes', fn ($q) => $q->where('dossiers_groupes.statut', 'TRANSMIS_CB'))
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (DossierPaiement $d) => [
                'id' => $d->id,
                'type' => 'dossier',
                'identifiant' => $d->numero,
                'agence' => $d->agence?->nom ?? '-',
                'source_financement' => $d->sourceFinancement?->libelle ?? $d->sourceFinancement?->nom ?? '-',
                'nombre_stagiaires' => $d->paiements_count,
                'montant_total' => $d->montant_total,
            ]);

        $groupes = DossierGroupe::with('sourceFinancement')
            ->withCount('dossiers')
            ->where('statut', 'TRANSMIS_CB')
            ->where('periode_id', $periode?->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (DossierGroupe $g) => [
                'id' => $g->id,
                'type' => 'groupe',
                'identifiant' => $g->numero,
                'agence' => '-',
                'source_financement' => $g->sourceFinancement?->nom ?? '-',
                'nombre_stagiaires' => null,
                'dossiers_count' => $g->dossiers_count,
                'montant_total' => $g->montant_total,
            ]);

        return response()->json($dossiers->concat($groupes)->values());
    }

    /**
     * API : Liste des stagiaires d'un dossier (server-side DataTable)
     */
    public function stagiairesByDossier(Request $request): JsonResponse
    {
        $request->validate([
            'dossier_id' => 'required_without:groupe_id|nullable|integer',
            'groupe_id' => 'required_without:dossier_id|nullable|integer',
            'start' => 'nullable|integer',
            'length' => 'nullable|integer',
            'search' => 'nullable|string',
        ]);

        $dossierId = $request->input('dossier_id');
        $groupeId = $request->input('groupe_id');
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
            ->when($dossierId, fn ($q) => $q->where('lignes_dossiers_paiement.dossier_paiement_id', $dossierId))
            ->when($groupeId, fn ($q) => $q->whereIn('lignes_dossiers_paiement.dossier_paiement_id', function ($sub) use ($groupeId) {
                $sub->from('lignes_dossiers_groupes')
                    ->select('dossier_paiement_id')
                    ->where('dossier_groupe_id', $groupeId)
                    ->whereNull('retire_le');
            }))
            ->whereNull('lignes_dossiers_paiement.retire_le')
            ->select(
                'paiements.id as paiement_id',
                'paiements.created_at',
                'lignes_dossiers_paiement.montant',
                // Trajectoire de la prime : brut calculé, cotisation CMU prélevée
                // selon la règle paramétrée, net réellement versé — visibles par le
                // CB au même titre que dans la corbeille DMG.
                'paiements.montant_brut',
                'paiements.montant_prelevement',
                'paiements.type_prelevement',
                'paiements.statut',
                'beneficiaires.nom',
                'beneficiaires.prenoms',
                'beneficiaires.numero_aej',
                'beneficiaires.date_naissance',
                'beneficiaires.numero_tresor_money as tresor_pay',
                'beneficiaires.numero_cmu',
                'beneficiaires.numero_piece_identite',
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

        [$stagiaires, $doublonCount] = $this->signalerDoublons($stagiaires);

        return response()->json([
            'draw' => $request->integer('draw', 1),
            'recordsTotal' => $total,
            'recordsFiltered' => $total,
            'doublonCount' => $doublonCount,
            'data' => $stagiaires,
        ]);
    }

    /**
     * Signal informatif (non bloquant) de doublons entre les lignes de la page courante,
     * sur les mêmes clés que `DesseDoublonService::TYPES` (AEJ, CMU, pièce d'identité).
     * Calcul en PHP sur la page consultée : jeu de données de la taille d'un dossier,
     * pas besoin d'une requête SQL agrégée dédiée.
     *
     * @return array{0: \Illuminate\Support\Collection, 1: int}
     */
    private function signalerDoublons(\Illuminate\Support\Collection $stagiaires): array
    {
        $cles = ['numero_aej', 'numero_cmu', 'numero_piece_identite'];
        $compteurs = [];

        foreach ($cles as $cle) {
            $compteurs[$cle] = $stagiaires
                ->map(fn ($s) => strtoupper(trim((string) ($s->{$cle} ?? ''))))
                ->filter(fn ($v) => $v !== '')
                ->countBy();
        }

        $doublonCount = 0;
        $stagiaires = $stagiaires->map(function ($s) use ($cles, $compteurs, &$doublonCount) {
            $doublon = false;
            foreach ($cles as $cle) {
                $valeur = strtoupper(trim((string) ($s->{$cle} ?? '')));
                if ($valeur !== '' && ($compteurs[$cle][$valeur] ?? 0) > 1) {
                    $doublon = true;
                    break;
                }
            }
            $s->doublon = $doublon;
            if ($doublon) {
                $doublonCount++;
            }

            return $s;
        });

        return [$stagiaires, $doublonCount];
    }

    /**
     * API : Documents d'un stage pour prévisualisation
     */
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

    public function valider(Request $request, $id)
    {
        $dossier = DossierPaiement::findOrFail($id);
        $nombre = $this->cbService->validerDossier($dossier, $request->user());

        return redirect()->back()->with('success', "{$nombre} paiement(s) validé(s). Dossier transmis à la DMG pour élaboration OP.");
    }

    public function ajourner(Request $request, $id)
    {
        $data = $request->validate([
            'motif' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        $dossier = DossierPaiement::findOrFail($id);
        $nombre = $this->cbService->ajournerDossier($dossier, $request->user(), $data['motif']);

        return redirect()->back()->with('success', "{$nombre} paiement(s) ajourné(s). Dossier renvoyé à la DMG.");
    }

    public function validerGroupe(Request $request, DossierGroupe $groupe)
    {
        $nombre = $this->cbService->validerGroupe($groupe, $request->user());

        return redirect()->back()->with('success', "{$nombre} paiement(s) validé(s). Multi-dossier transmis à la DMG pour élaboration OP.");
    }

    public function ajournerGroupe(Request $request, DossierGroupe $groupe)
    {
        $data = $request->validate([
            'motif' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        $nombre = $this->cbService->ajournerGroupe($groupe, $request->user(), $data['motif']);

        return redirect()->back()->with('success', "{$nombre} paiement(s) ajourné(s). Multi-dossier renvoyé à la DMG.");
    }

    public function ajournerStagiaires(Request $request)
    {
        $data = $request->validate([
            'paiement_ids' => ['required', 'array', 'min:1'],
            'paiement_ids.*' => ['integer', 'distinct', 'exists:paiements,id'],
            'motif' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        $nombre = $this->cbService->ajournerPaiements($data['paiement_ids'], $request->user(), $data['motif']);

        return redirect()->back()->with('success', "{$nombre} stagiaire(s) ajourné(s). Ils repartent en file d'attente DMG.");
    }
}
