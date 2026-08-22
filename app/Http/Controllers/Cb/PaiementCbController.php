<?php

namespace App\Http\Controllers\Cb;

use App\Domain\Workflow\Services\CorbeilleParcoursQueryService;
use App\Http\Controllers\Controller;
use App\Models\Payment\DossierPaiement;
use App\Models\Payment\GroupePaiement;
use App\Models\Reference\Periode;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class PaiementCbController extends Controller
{
    public function __construct(private CorbeilleParcoursQueryService $corbeilles) {}

    public function index(Request $request)
    {
        $mois = $request->query('mois', Carbon::now()->format('Y-m'));
        $periode = Periode::where('code', $mois)->first();

        $dossiersAttenteCB = DossierPaiement::with(['agence', 'sourceFinancement', 'periode'])
            ->withCount('paiements')
            ->where('statut', 'TRANSMIS_CB')
            ->where('periode_id', $periode?->id)
            ->orderByDesc('created_at')
            ->get();

        $dossiersAjournes = DossierPaiement::with(['agence', 'sourceFinancement', 'periode'])
            ->withCount('paiements')
            ->where('statut', 'AJOURNE_CB')
            ->where('periode_id', $periode?->id)
            ->orderByDesc('created_at')
            ->get();

        // Périodes disponibles
        $periodeOptions = Periode::orderByDesc('code')
            ->limit(24)
            ->get(['id', 'code']);

        return Inertia::render('Cb/Paiements/Index', [
            'dossiersControle' => $this->corbeilles->dossierRows($dossiersAttenteCB, 'En attente CB'),
            'etatsAjournes' => $this->corbeilles->dossierRows($dossiersAjournes, 'Ajourné CB'),
            'moisActuel' => $mois,
            'periode' => $periode,
            'periodeOptions' => $periodeOptions,
        ]);
    }

    /**
     * API : Liste des dossiers pour un mois donné (select2)
     */
    public function dossiersByMois(Request $request): JsonResponse
    {
        $mois = $request->query('mois', Carbon::now()->format('Y-m'));
        $periode = Periode::where('code', $mois)->first();

        $dossiers = DossierPaiement::with(['agence', 'sourceFinancement'])
            ->withCount('paiements')
            ->where('statut', 'TRANSMIS_CB')
            ->where('periode_id', $periode?->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (DossierPaiement $d) => [
                'id' => $d->id,
                'identifiant' => $d->numero,
                'agence' => $d->agence?->nom ?? '-',
                'source_financement' => $d->sourceFinancement?->nom ?? '-',
                'nombre_stagiaires' => $d->paiements_count,
                'montant_total' => $d->montant_total,
            ]);

        return response()->json($dossiers);
    }

    /**
     * API : Liste des stagiaires d'un dossier (server-side DataTable)
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
                'beneficiaires.numero_cmu',
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
        $dossier->update(['statut' => 'VALIDE_CB']);

        return redirect()->back()->with('success', 'Dossier validé et transmis à la DMG pour élaboration OP.');
    }

    public function ajourner(Request $request, $id)
    {
        $dossier = DossierPaiement::findOrFail($id);
        $dossier->update(['statut' => 'AJOURNE_CB']);

        return redirect()->back()->with('success', 'Dossier ajourné et renvoyé à la DMG.');
    }
}
