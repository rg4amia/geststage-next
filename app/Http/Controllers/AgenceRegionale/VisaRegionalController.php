<?php

namespace App\Http\Controllers\AgenceRegionale;

use App\Domain\Supervision\Services\PiecesStageService;
use App\Domain\Supervision\Services\VisaRegionalService;
use App\Enums\VisaDesseEnum;
use App\Http\Controllers\Controller;
use App\Jobs\ExporterVisasRegionauxJob;
use App\Models\Company\Entreprise;
use App\Models\Internship\Stage;
use App\Models\Reference\Agence;
use App\Models\Reference\SituationStage;
use App\Models\Reference\SourceFinancement;
use App\Models\Reference\TypeStage;
use App\Models\Reference\TypeStructure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Écran unique de supervision régionale des dossiers de stage.
 *
 * Portage regroupé des écrans legacy `Validation_Stagiaire_Desse`,
 * `Liste_Stagiaires_Rejetes_Desse`, `stagiaire_passe_etape_desse`, `liste-stagiaire-pae`,
 * `daicg/stagiaire-valider-par-chef-agence`, `daicg/stagiaire-valider-par-desse`,
 * `cip/differed-by-agent-comptable`, `desse/suivie[-ar]/stagiaire-saved`,
 * `Tableau_Statistique` et `Telechargement_Pieces`.
 *
 * La route canonique est `/agence-regionale/visas` ; `/desse/visas` reste en alias de
 * redirection pour les liens déjà diffusés.
 */
class VisaRegionalController extends Controller
{
    private const FILTRES = [
        'agence_id',
        'entreprise_id',
        'source_financement_id',
        'type_stage_id',
        'type_structure_id',
        'situation_stage',
        'corbeille',
        'date_debut',
        'date_fin',
        'date_valid_ar_debut',
        'date_valid_ar_fin',
        'date_valid_desse_debut',
        'date_valid_desse_fin',
        'annee_saisie',
        'recherche',
    ];

    private const PAR_PAGE = 25;

    public function __construct(
        private readonly VisaRegionalService $visas,
        private readonly PiecesStageService $pieces
    ) {}

    public function index(Request $request): Response
    {
        $filtres = $request->only(self::FILTRES);
        $onglet = $this->onglet($request);

        $peutViser = $this->peutViser();

        $donnees = [
            'onglet' => $onglet,
            'filters' => $filtres,
            'compteurs' => $this->visas->compteurs($filtres),
            'peutViser' => $peutViser,
            'agences' => Agence::cachedPluck('nom'),
            'entreprises' => $this->entreprises(),
            'typesfinancements' => SourceFinancement::cachedPluck('nom'),
            'typestages' => TypeStage::cachedPluck('nom'),
            'typesstructures' => TypeStructure::cachedPluck('nom'),
            'situations' => SituationStage::cachedPluck('nom', 'code'),
        ];

        if ($onglet === 'statistiques') {
            return Inertia::render('AgenceRegionale/Visas/Index', $donnees + [
                'statistiques' => $this->visas->statistiques(
                    $filtres['date_valid_ar_debut'] ?? null,
                    $filtres['date_valid_ar_fin'] ?? null
                ),
            ]);
        }

        $lignes = $this->visas->queryPourOnglet($onglet, $filtres)
            ->paginate(self::PAR_PAGE)
            ->withQueryString();

        $lignes->through(fn ($modele): array => in_array($onglet, VisaRegionalService::ONGLETS_PAIEMENT, true)
            ? $this->visas->formatLignePaiement($modele)
            : $this->visas->formatLigne($modele));

        return Inertia::render('AgenceRegionale/Visas/Index', $donnees + ['stages' => $lignes]);
    }

    /**
     * Accorde le visa DESSE.
     */
    public function viser(Request $request, Stage $stage): RedirectResponse
    {
        $this->autoriserVisa($stage);

        if ($stage->visa_desse !== VisaDesseEnum::EN_ATTENTE) {
            return back()->with('error', 'Ce dossier a déjà été tranché.');
        }

        $this->visas->viser($stage, Auth::id());

        return back()->with('success', 'Visa accordé.');
    }

    /**
     * Refuse le visa DESSE. Le motif est obligatoire : c'est la seule consigne que le CIP
     * reçoit pour corriger le dossier.
     */
    public function rejeter(Request $request, Stage $stage): RedirectResponse
    {
        $this->autoriserVisa($stage);

        $donnees = $request->validate([
            'motif' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        if ($stage->visa_desse !== VisaDesseEnum::EN_ATTENTE) {
            return back()->with('error', 'Ce dossier a déjà été tranché.');
        }

        $this->visas->rejeter($stage, $donnees['motif'], Auth::id());

        return back()->with('success', 'Dossier rejeté.');
    }

    /**
     * Remet un dossier rejeté en attente de visa.
     */
    public function remettreEnAttente(Request $request, Stage $stage): RedirectResponse
    {
        $this->autoriserVisa($stage);

        if ($stage->visa_desse !== VisaDesseEnum::REJETE) {
            return back()->with('error', 'Seul un dossier rejeté peut être remis en attente de visa.');
        }

        $this->visas->remettreEnAttente($stage);

        return back()->with('success', 'Dossier remis en attente de visa.');
    }

    /**
     * Inventaire des pièces justificatives d'un stage (portage de `Telechargement_Pieces`).
     */
    public function piecesStage(Stage $stage): JsonResponse
    {
        $this->autoriserConsultation($stage);

        return response()->json([
            'stage_id' => $stage->id,
            'pieces' => collect($this->pieces->inventaire($stage->id))
                ->map(fn (array $piece): array => $piece + [
                    'url' => $piece['disponible']
                        ? route('agence-regionale.visas.piece', ['stage' => $stage->id, 'cle' => $piece['cle']])
                        : null,
                ])
                ->all(),
            'url_archive' => route('agence-regionale.visas.pieces.archive', ['stage' => $stage->id]),
        ]);
    }

    /**
     * Sert une pièce justificative. Le client n'envoie qu'un stage et une clé de pièce :
     * le chemin est résolu et confiné côté serveur par PiecesStageService.
     */
    public function piece(Stage $stage, string $cle): BinaryFileResponse
    {
        $this->autoriserConsultation($stage);

        abort_unless(in_array($cle, PiecesStageService::clesValides(), true), 404, 'Pièce inconnue.');

        $fichier = $this->pieces->cheminAbsolu($stage->id, $cle);

        abort_if($fichier === null, 404, 'Pièce justificative introuvable.');

        return response()->file($fichier);
    }

    /**
     * Archive ZIP de toutes les pièces d'un stage : équivalent du « fichier groupé » legacy.
     */
    public function archivePieces(Stage $stage): BinaryFileResponse
    {
        $this->autoriserConsultation($stage);

        $prefixe = 'pieces_'.($stage->beneficiaire?->numero_aej ?: $stage->id);
        $archive = $this->pieces->archiveZip($stage->id, $prefixe);

        abort_if($archive === null, 404, 'Aucune pièce à télécharger pour ce dossier.');

        return response()->download($archive, $prefixe.'.zip')->deleteFileAfterSend();
    }

    /**
     * Export CSV synchrone de l'onglet courant.
     */
    public function export(Request $request): StreamedResponse
    {
        $filtres = $request->only(self::FILTRES);
        $onglet = $this->onglet($request);

        abort_if(
            in_array($onglet, VisaRegionalService::ONGLETS_SANS_LISTE, true),
            422,
            "Cet onglet n'a pas d'export de liste."
        );

        $lignes = $this->visas->lignesExport(
            $this->visas->queryPourOnglet($onglet, $filtres),
            in_array($onglet, VisaRegionalService::ONGLETS_PAIEMENT, true)
        );

        return response()->streamDownload(function () use ($lignes): void {
            $handle = fopen('php://output', 'w');

            // BOM UTF-8 pour Excel
            fwrite($handle, "\xEF\xBB\xBF");

            foreach ($lignes as $ligne) {
                fputcsv($handle, $ligne, ';');
            }

            fclose($handle);
        }, sprintf('visas_%s_%s.csv', $onglet, now()->format('Ymd_His')), [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Lance l'export en arrière-plan, pour les onglets globaux dont le volume dépasse le
     * temps de réponse HTTP.
     */
    public function exportAsynchrone(Request $request): JsonResponse
    {
        $filtres = $request->only(self::FILTRES);
        $onglet = $this->onglet($request);

        abort_if(
            in_array($onglet, VisaRegionalService::ONGLETS_SANS_LISTE, true),
            422,
            "Cet onglet n'a pas d'export de liste."
        );

        $batch = Bus::batch([
            new ExporterVisasRegionauxJob($onglet, $filtres, Auth::id()),
        ])->name('export-visas-'.$onglet)->dispatch();

        return response()->json([
            'batch_id' => $batch->id,
            'message' => 'Export lancé en arrière-plan.',
        ]);
    }

    /**
     * Avancement d'un export lancé en arrière-plan.
     */
    public function exportProgression(string $batchId): JsonResponse
    {
        $batch = Bus::findBatch($batchId);

        if (! $batch) {
            return response()->json(['message' => 'Export introuvable.'], 404);
        }

        return response()->json([
            'id' => $batch->id,
            'progress' => $batch->progress(),
            'completed' => $batch->finished(),
            'failedJobs' => $batch->failedJobs,
            'disponible' => $batch->finished()
                && $batch->failedJobs === 0
                && Storage::disk('local')->exists(ExporterVisasRegionauxJob::chemin($batch->id)),
        ]);
    }

    /**
     * Télécharge le fichier produit par un export en arrière-plan.
     */
    public function exportTelechargement(string $batchId): BinaryFileResponse
    {
        $batch = Bus::findBatch($batchId);

        abort_if($batch === null, 404, 'Export introuvable.');

        $chemin = ExporterVisasRegionauxJob::chemin($batch->id);

        abort_unless(Storage::disk('local')->exists($chemin), 404, "L'export n'est pas encore disponible.");

        return response()->download(
            Storage::disk('local')->path($chemin),
            sprintf('visas_%s.csv', $batch->id)
        );
    }

    private function onglet(Request $request): string
    {
        $onglet = $request->string('onglet')->toString();

        return in_array($onglet, VisaRegionalService::ONGLETS, true) ? $onglet : 'attente_visa_desse';
    }

    /**
     * Seule la DESSE (et l'administrateur) tranche le visa ; la DAICG, les chefs d'agence
     * et les CIP consultent.
     */
    private function peutViser(): bool
    {
        $user = Auth::user();

        return (bool) ($user && method_exists($user, 'can') && $user->can('viser_visas_ar'));
    }

    private function autoriserVisa(Stage $stage): void
    {
        abort_unless($this->peutViser(), 403, "Vous n'êtes pas habilité à viser les dossiers.");

        $this->verifierPerimetre($stage);
    }

    private function autoriserConsultation(Stage $stage): void
    {
        $this->verifierPerimetre($stage);
    }

    /**
     * Un dossier hors périmètre d'agence ne doit pas être atteignable par son identifiant,
     * même si l'écran ne l'a jamais listé.
     */
    private function verifierPerimetre(Stage $stage): void
    {
        $agences = $this->visas->agencesAutorisees();

        if ($agences !== null && ! in_array((int) $stage->agence_id, $agences, true)) {
            abort(403, "Ce dossier n'est pas dans votre périmètre.");
        }
    }

    /**
     * @return array<int, string>
     */
    private function entreprises(): array
    {
        $agences = $this->visas->agencesAutorisees() ?? [];

        return Entreprise::cached()
            ->when($agences !== [], fn ($collection) => $collection->whereIn('agence_id', $agences))
            ->sortBy('raison_sociale')
            ->pluck('raison_sociale', 'id')
            ->all();
    }
}
