<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dmg;

use App\Domain\Payment\Services\DmgService;
use App\Domain\Payment\Services\MultiDossierPdfService;
use App\Domain\Workflow\Services\CorbeilleParcoursQueryService;
use App\Http\Controllers\Controller;
use App\Models\Company\Entreprise;
use App\Models\Payment\DossierGroupe;
use App\Models\Payment\DossierPaiement;
use App\Models\Payment\Paiement;
use App\Models\Reference\Agence;
use App\Models\Reference\Periode;
use App\Models\Reference\SourceFinancement;
use App\Models\Reference\TypeStage;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MultiDossierController extends Controller
{
    public function __construct(
        private DmgService $dmgService,
        private MultiDossierPdfService $pdfService,
        private CorbeilleParcoursQueryService $corbeilles,
    ) {}

    /**
     * Page principale de gestion des multi-dossiers (Inertia).
     */
    public function index(Request $request): InertiaResponse
    {
        $mois = $request->string('mois', Carbon::now()->format('Y-m'))->toString();

        return Inertia::render('Dmg/MultiDossier/Index', [
            'periodeOptions' => Periode::cachedOptions('code', descending: true),
            'agences' => Agence::cachedOptions('nom'),
            'entreprises' => Entreprise::cachedOptions('raison_sociale'),
            'sourcesFinancement' => SourceFinancement::cachedOptions('nom'),
            'typesStage' => TypeStage::cachedOptions('nom'),
            'moisActuel' => $mois,
        ]);
    }

    /**
     * JSON — Dossiers non groupés, filtrés pour le multi-dossier.
     */
    public function getDossiers(Request $request): JsonResponse
    {
        $mois = $request->string('mois')->toString();
        $query = DossierPaiement::query()
            ->with(['agence', 'sourceFinancement', 'periode'])
            ->withCount('paiements')
            ->where('statut', 'BROUILLON')
            ->whereNull('ordre_paiement_id')
            ->whereDoesntHave('groupes');

        if ($mois) {
            $periode = Periode::where('code', $mois)->first();
            if ($periode) {
                $query->where('periode_id', $periode->id);
            }
        }

        if ($agenceId = $request->integer('agence_id')) {
            $query->where('agence_id', $agenceId);
        }

        if ($sfId = $request->integer('source_financement_id')) {
            $query->where('source_financement_id', $sfId);
        }

        if ($typeTraitement = $request->string('typetraitement')->toString()) {
            $query->where('nature', $typeTraitement);
        }

        $dossiers = $query->orderByDesc('created_at')->limit(500)->get()
            ->map(fn (DossierPaiement $d) => [
                'id' => $d->id,
                'identifiant' => $d->numero,
                'agence' => $d->agence?->nom ?? '-',
                'source_financement' => $d->sourceFinancement?->nom ?? '-',
                'nature' => $d->nature,
                'nombre_stagiaires' => $d->paiements_count ?? $d->paiements->count(),
                'montant_total' => $d->montant_total,
                'statut' => $d->statut,
            ]);

        return response()->json($dossiers);
    }

    /**
     * JSON — Stagiaires (paiements) pour les dossiers sélectionnés (serveur-side DataTable).
     */
    public function getStagiaires(Request $request): JsonResponse
    {
        $dossierIds = $request->input('dossiers', []);

        if (empty($dossierIds)) {
            return response()->json([
                'draw' => (int) $request->input('draw', 1),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
            ]);
        }

        $draw = (int) $request->input('draw', 1);
        $start = (int) $request->input('start', 0);
        $length = min((int) $request->input('length', 10), 200);
        $search = $request->input('search.value', '');

        $query = Paiement::query()
            ->with([
                'droitPaiement.stage.beneficiaire',
                'droitPaiement.stage.entreprise',
                'droitPaiement.stage.agence',
                'droitPaiement.stage.sourceFinancement',
                'droitPaiement.stage.typeStage',
            ])
            ->whereHas('dossiersPaiement', fn ($q) => $q->whereIn('dossiers_paiement.id', $dossierIds));

        // Recherche
        if ($search !== '') {
            $term = '%'.addcslashes($search, '%_').'%';
            $operator = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->whereHas('droitPaiement.stage.beneficiaire', fn ($q) => $q
                ->where('nom', $operator, $term)
                ->orWhere('prenoms', $operator, $term)
                ->orWhere('numero_aej', $operator, $term));
        }

        $totalRecords = $query->count();
        $paiements = $query->orderByDesc('paiements.id')->offset($start)->limit($length)->get();

        $data = $paiements->map(function (Paiement $paiement) {
            $stage = $paiement->droitPaiement?->stage;
            $beneficiaire = $stage?->beneficiaire;

            return [
                'paiement_id' => $paiement->id,
                'created_at' => $paiement->created_at?->format('d/m/Y') ?? '-',
                'agence' => $stage?->agence?->nom ?? '-',
                'entreprise' => $stage?->entreprise?->raison_sociale ?? '-',
                'source_financement' => $stage?->sourceFinancement?->nom ?? '-',
                'type_stage' => $stage?->typeStage?->nom ?? '-',
                'numero_aej' => $beneficiaire?->numero_aej ?? '-',
                'nom_prenoms' => trim(($beneficiaire?->nom ?? '').' '.($beneficiaire?->prenoms ?? '')),
                'date_naissance' => $beneficiaire?->date_naissance ?? '-',
                'date_debut' => $stage?->date_debut?->format('d/m/Y') ?? '-',
                'date_fin' => $stage?->date_fin_prevue?->format('d/m/Y') ?? '-',
                'tresor_pay' => $beneficiaire?->numero_tresor_pay ?? '-',
                'montant' => $paiement->montant,
                'statut' => $paiement->statut,
            ];
        });

        return response()->json([
            'draw' => $draw,
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $totalRecords,
            'data' => $data,
        ]);
    }

    /**
     * Valider la sélection — Crée le multi-dossier et génère les PDFs.
     */
    public function validateSelection(Request $request): JsonResponse
    {
        $data = $request->validate([
            'dossiers' => 'required|array|min:1',
            'dossiers.*' => 'integer|exists:dossiers_paiement,id',
            'mois' => 'required|string',
            'observation' => 'nullable|string|max:1000',
        ]);

        $dossierIds = $data['dossiers'];
        $periode = Periode::where('code', $data['mois'])->firstOrFail();

        try {
            $groupe = $this->dmgService->grouperDossiers(
                $periode->id,
                $dossierIds,
                $data['observation'] ?? null,
                $request->user(),
            );

            // Générer les PDFs
            $groupe = $this->pdfService->genererPdfs($groupe);

            return response()->json([
                'success' => 'Multi-dossier créé avec succès.',
                'multi_dossier_id' => $groupe->id,
                'name' => $groupe->numero,
                'attestation_url' => $groupe->attestation_path
                    ? route('dmg.multi-dossier.download_attestation', $groupe)
                    : null,
                'etat_financier_url' => $groupe->etat_financier_path
                    ? route('dmg.multi-dossier.download_etat_financier', $groupe)
                    : null,
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur création multi-dossier: '.$e->getMessage());

            return response()->json(['error' => 'Une erreur est survenue lors de la création du multi-dossier.'], 500);
        }
    }

    /**
     * Ajourner des dossiers (retirer du circuit multi-dossier et remettre en attente).
     */
    public function ajournerDossier(Request $request): JsonResponse
    {
        $data = $request->validate([
            'dossier_id' => 'required|array|min:1',
            'dossier_id.*' => 'integer|exists:dossiers_paiement,id',
            'motif' => 'required|string|min:5|max:1000',
            'mois' => 'required|string',
        ]);

        $result = DB::transaction(function () use ($data) {
            DossierPaiement::whereIn('id', $data['dossier_id'])->lockForUpdate()->get();

            $paiementIds = Paiement::where('statut', 'EN_DOSSIER')
                ->whereHas('dossiersPaiement', fn ($q) => $q->whereIn('dossiers_paiement.id', $data['dossier_id']))
                ->lockForUpdate()
                ->pluck('id');

            Paiement::whereIn('id', $paiementIds)->update(['statut' => 'A_TRAITER']);

            return $paiementIds->all();
        });

        return response()->json([
            'message' => count($result).' paiement(s) retiré(s) du dossier.',
            'paiementIds' => $result,
        ]);
    }

    /**
     * Ajourner des stagiaires individuels (retirer des dossiers sélectionnés).
     */
    public function ajournerStagiaire(Request $request): JsonResponse
    {
        $data = $request->validate([
            'paiementIds' => 'required|array|min:1',
            'paiementIds.*' => 'integer|exists:paiements,id',
            'motif' => 'nullable|string|max:1000',
        ]);

        $result = DB::transaction(function () use ($data) {
            $ids = Paiement::whereIn('id', $data['paiementIds'])->lockForUpdate()->pluck('id');

            Paiement::whereIn('id', $ids)->update(['statut' => 'A_TRAITER']);

            return $ids->all();
        });

        return response()->json([
            'message' => count($result).' stagiaire(s) retiré(s).',
            'paiementIds' => $result,
        ]);
    }

    /**
     * Générer le PDF état de paiement pour les dossiers sélectionnés (téléchargement direct).
     */
    public function generatePdfPaiement(Request $request): StreamedResponse
    {
        $dossierIds = $request->input('dossiers', []);

        if (empty($dossierIds)) {
            abort(400, 'Aucun dossier sélectionné.');
        }

        $paiements = Paiement::query()
            ->with(['droitPaiement.stage.beneficiaire', 'droitPaiement.stage.entreprise', 'droitPaiement.stage.agence', 'droitPaiement.stage.sourceFinancement'])
            ->whereHas('dossiersPaiement', fn ($q) => $q->whereIn('dossiers_paiement.id', $dossierIds))
            ->orderBy('paiements.id')
            ->limit(500)
            ->get();

        abort_if($paiements->isEmpty(), 404, 'Aucun paiement trouvé.');

        $mois = Periode::whereIn('id', DossierPaiement::whereIn('id', $dossierIds)->pluck('periode_id'))->first()?->code ?? '';

        $pdf = \Barryvdh\DomPDF\Facades\Pdf::loadView('pdf.dmg-paiements', [
            'paiements' => $paiements,
            'titre' => 'État de paiement — Multi-dossier',
            'type' => 'etat_paiement',
            'mois' => $mois,
        ])->setPaper('a4', 'landscape');

        $filename = 'etats_paiement_'.date('Y-m-d_H-i-s').'.pdf';

        return $pdf->download($filename);
    }

    /**
     * Générer le PDF attestations pour les dossiers sélectionnés (téléchargement direct).
     */
    public function generatePdfAttestations(Request $request): StreamedResponse
    {
        $dossierIds = $request->input('dossiers', []);

        if (empty($dossierIds)) {
            abort(400, 'Aucun dossier sélectionné.');
        }

        $paiements = Paiement::query()
            ->with(['droitPaiement.stage.beneficiaire', 'droitPaiement.stage.entreprise', 'droitPaiement.stage.agence', 'droitPaiement.stage.sourceFinancement'])
            ->whereHas('dossiersPaiement', fn ($q) => $q->whereIn('dossiers_paiement.id', $dossierIds))
            ->orderBy('paiements.id')
            ->limit(500)
            ->get();

        abort_if($paiements->isEmpty(), 404, 'Aucun paiement trouvé.');

        $mois = Periode::whereIn('id', DossierPaiement::whereIn('id', $dossierIds)->pluck('periode_id'))->first()?->code ?? '';

        $paginatedContrats = preparePaginatedDataWithFooterSpace($paiements);

        $pdf = \Barryvdh\DomPDF\Facades\Pdf::loadView('pdf.dmg-paiements', [
            'paiements' => $paiements,
            'titre' => 'Attestations — Multi-dossier',
            'type' => 'attestation_presence',
            'mois' => $mois,
        ])->setPaper('a4', 'portrait');

        $pdf->getDomPDF()->setHttpContext(
            stream_context_create(['ssl' => ['allow_self_signed' => true, 'verify_peer' => false, 'verify_peer_name' => false]])
        );

        $filename = 'attestations_'.date('Y-m-d_H-i-s').'.pdf';

        return $pdf->download($filename);
    }

    /**
     * Télécharger l'attestation d'un multi-dossier.
     */
    public function downloadAttestation(DossierGroupe $groupe)
    {
        if (! $groupe->attestation_path || ! Storage::disk('temp_files')->exists($groupe->attestation_path)) {
            abort(404, 'Fichier introuvable.');
        }

        return response()->download(
            Storage::disk('temp_files')->path($groupe->attestation_path),
            basename($groupe->attestation_path)
        );
    }

    /**
     * Télécharger l'état financier d'un multi-dossier.
     */
    public function downloadEtatFinancier(DossierGroupe $groupe)
    {
        if (! $groupe->etat_financier_path || ! Storage::disk('temp_files')->exists($groupe->etat_financier_path)) {
            abort(404, 'Fichier introuvable.');
        }

        return response()->download(
            Storage::disk('temp_files')->path($groupe->etat_financier_path),
            basename($groupe->etat_financier_path)
        );
    }
}
