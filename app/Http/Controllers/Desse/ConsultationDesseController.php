<?php

namespace App\Http\Controllers\Desse;

use App\Domain\Supervision\Services\VisaRegionalService;
use App\Http\Controllers\Controller;
use App\Jobs\ExporterSupervisionJob;
use App\Models\Company\Entreprise;
use App\Models\Reference\Agence;
use App\Models\Reference\SituationStage;
use App\Models\Reference\SourceFinancement;
use App\Models\Reference\TypeStage;
use App\Models\Reference\TypeStructure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Écrans de consultation DESSE regroupés : registre des bénéficiaires (legacy
 * `GET /desse/beneficiaire/index` + `beneficiaire.index-json`) et stagiaires sans
 * contrat (legacy `GET /desse/stagiaire-sans-contrat`, condition `avis_contrat = 0`).
 *
 * Consultation seule : aucune décision n'est prise ici (le legacy ne permettait la
 * validation que depuis un profil restreint via TraitementEtapeController, circuit
 * déjà couvert par les écrans de workflow Next). Les deux listes réutilisent les
 * requêtes et le périmètre d'agences de VisaRegionalService au lieu de dupliquer
 * un filtrage.
 */
class ConsultationDesseController extends Controller
{
    /** Listes servies par ce contrôleur → méthode de requête de VisaRegionalService. */
    private const LISTES = [
        'beneficiaires' => 'beneficiairesQuery',
        'stagiaires-sans-contrat' => 'sansContratQuery',
    ];

    private const FILTRES = [
        'agence_id',
        'entreprise_id',
        'source_financement_id',
        'type_stage_id',
        'type_structure_id',
        'situation_stage',
        'etape_id',
        'date_debut',
        'date_fin',
        'annee_saisie',
        'recherche',
    ];

    private const PAR_PAGE = 25;

    public function __construct(private readonly VisaRegionalService $visas) {}

    public function index(Request $request, string $liste): Response
    {
        abort_unless(array_key_exists($liste, self::LISTES), 404);

        $filtres = $request->only(self::FILTRES);
        $query = $this->visas->{self::LISTES[$liste]}($filtres);

        if (! empty($filtres['etape_id'])) {
            $query->whereHas('instanceParcours', function ($q) use ($filtres): void {
                $q->whereNull('terminee_le')
                    ->where('corbeille_actuelle', $filtres['etape_id']);
            });
        }

        $lignes = $query->paginate(self::PAR_PAGE)->withQueryString();

        $lignes->through(fn ($stage): array => $this->visas->formatLigneConsultation($stage));

        return Inertia::render('Desse/Consultation/Index', [
            'liste' => $liste,
            'filters' => $filtres,
            'stages' => $lignes,
            'compteur' => $this->visas->{self::LISTES[$liste]}($filtres)->count(),
            'agences' => Agence::cachedPluck('nom'),
            'entreprises' => $this->entreprises(),
            'typesfinancements' => SourceFinancement::cachedPluck('nom'),
            'typestages' => TypeStage::cachedPluck('nom'),
            'typesstructures' => TypeStructure::cachedPluck('nom'),
            'situations' => SituationStage::cachedPluck('nom', 'code'),
            'etapes' => $this->etapes(),
        ]);
    }

    /**
     * Export CSV synchrone de la liste courante.
     */
    public function export(Request $request, string $liste)
    {
        abort_unless(array_key_exists($liste, self::LISTES), 404);

        $filtres = $request->only(self::FILTRES);
        $query = $this->appliquerEtape($this->visas->{self::LISTES[$liste]}($filtres), $filtres);

        return response()->streamDownload(function () use ($query): void {
            $handle = fopen('php://output', 'w');

            // BOM UTF-8 pour Excel
            fwrite($handle, "\xEF\xBB\xBF");

            foreach ($this->visas->lignesExportConsultation($query) as $ligne) {
                fputcsv($handle, $ligne, ';');
            }

            fclose($handle);
        }, sprintf('%s_%s.csv', $liste, now()->format('Ymd_His')), [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Export lancé en arrière-plan pour les gros volumes : le job réauthentifie le
     * demandeur, l'export ne peut donc pas déborder de son périmètre d'agences.
     */
    public function exportAsynchrone(Request $request, string $liste): JsonResponse
    {
        abort_unless(array_key_exists($liste, self::LISTES), 404);

        $filtres = $request->only(self::FILTRES);

        $batch = Bus::batch([
            new ExporterSupervisionJob($liste, $filtres, Auth::id()),
        ])->name('export-supervision-'.$liste)->dispatch();

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
                && Storage::disk('local')->exists(ExporterSupervisionJob::chemin($batch->id)),
        ]);
    }

    /**
     * Téléchargement du fichier produit par un export en arrière-plan. Le nom du fichier
     * est dérivé de l'identifiant de batch : un utilisateur ne peut jamais viser le
     * fichier d'un autre export.
     */
    public function exportTelechargement(string $batchId)
    {
        $batch = Bus::findBatch($batchId);

        abort_if($batch === null, 404, 'Export introuvable.');

        $chemin = ExporterSupervisionJob::chemin($batch->id);

        abort_unless(Storage::disk('local')->exists($chemin), 404, "L'export n'est pas encore disponible.");

        return response()->download(
            Storage::disk('local')->path($chemin),
            sprintf('supervision_%s.csv', $batch->id)
        );
    }

    /**
     * Filtre « étape du workflow » : le legacy filtrait sur `etapetraitement_id` ; l'étape
     * vivante de Next est la corbeille courante de l'instance de parcours.
     */
    private function appliquerEtape($query, array $filtres)
    {
        if (! empty($filtres['etape_id'])) {
            $query->whereHas('instanceParcours', function ($q) use ($filtres): void {
                $q->whereNull('terminee_le')
                    ->where('corbeille_actuelle', $filtres['etape_id']);
            });
        }

        return $query;
    }

    /**
     * Entreprises bornées au périmètre d'agences de l'utilisateur, comme sur l'écran visas.
     *
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

    /**
     * Étapes proposées au filtre : les corbeilles actives portées par au moins un stage.
     *
     * @return array<string, string>
     */
    private function etapes(): array
    {
        return \App\Models\Workflow\InstanceParcours::query()
            ->whereNull('terminee_le')
            ->distinct()
            ->pluck('corbeille_actuelle')
            ->filter()
            ->mapWithKeys(fn (string $code): array => [
                $code => \App\Enums\CorbeilleEnum::tryFrom($code)?->label() ?? $code,
            ])
            ->sort()
            ->all();
    }
}
