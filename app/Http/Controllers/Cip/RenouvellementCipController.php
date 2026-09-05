<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cip;

use App\Domain\Contract\Services\AvenantPdfService;
use App\Domain\Contract\Services\RenouvellementService;
use App\Http\Controllers\Controller;
use App\Jobs\DecideRenewalAvenantsJob;
use App\Jobs\RenewStagesJob;
use App\Models\Company\Entreprise;
use App\Models\Contract\AvenantContrat;
use App\Models\Internship\Stage;
use App\Models\Reference\Agence;
use App\Models\Reference\SourceFinancement;
use App\Models\Reference\TypeStage;
use App\Models\Reference\TypeStructure;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Corbeilles CIP et Chef d'Agence du renouvellement de contrat.
 *
 * Portage unifié des écrans legacy :
 * - `renouvellement/stagiaire-atttente` (attente)
 * - `renouvellement/anticiper/stagiaire-atttente` (anticipe)
 * - `renouvellement/stagiaire-ajourne` (ajourne)
 * - `renouvellement/attente-validation-by-ca` (chef_validation)
 */
class RenouvellementCipController extends Controller
{
    private const ONGLETS = ['attente', 'anticipe', 'ajourne', 'chef_validation'];

    public function __construct(
        private readonly RenouvellementService $renouvellements,
        private readonly AvenantPdfService $avenantPdfService
    ) {}

    public function index(Request $request): Response
    {
        $filtres = $request->only([
            'agence_id',
            'entreprise_id',
            'source_financement_id',
            'type_stage_id',
            'type_structure_id',
            'date_debut',
            'date_fin',
            'recherche',
        ]);

        $onglet = $request->string('onglet')->toString();
        $onglet = in_array($onglet, self::ONGLETS, true) ? $onglet : 'attente';

        $user = Auth::user();
        $peutValiderCa = (bool) (
            $user?->hasRole('administrateur')
            || $user?->hasRole('chef_agence')
            || (method_exists($user, 'can') && $user?->can('valider_chef_agence'))
            || $user?->id === 1
        );

        // Si l'utilisateur n'est pas autorisé pour l'onglet CA, on le redirige vers l'onglet par défaut
        if ($onglet === 'chef_validation' && ! $peutValiderCa) {
            $onglet = 'attente';
        }

        $query = match ($onglet) {
            'anticipe' => $this->renouvellements->anticipeQuery($filtres),
            'ajourne' => $this->renouvellements->ajourneQuery($filtres),
            'chef_validation' => $this->renouvellements->chefAgenceValidationQuery($filtres),
            default => $this->renouvellements->attenteQuery($filtres),
        };

        $stages = $query->paginate(25)->withQueryString();
        $stages->through(fn (Stage $stage): array => $this->renouvellements->formatLigne($stage));

        $agenceIds = $this->renouvellements->agencesAutorisees() ?? [];

        return Inertia::render('Cip/Renouvellements/Index', [
            'onglet' => $onglet,
            'stages' => $stages,
            'compteurs' => $this->renouvellements->compteurs($filtres),
            'filters' => $filtres,
            'agences' => Agence::cachedPluck('nom'),
            'entreprises' => Entreprise::cached()
                ->when($agenceIds !== [], fn ($c) => $c->whereIn('agence_id', $agenceIds))
                ->sortBy('raison_sociale')
                ->pluck('raison_sociale', 'id')
                ->all(),
            'typesfinancements' => SourceFinancement::cachedPluck('nom'),
            'typestages' => TypeStage::cachedPluck('nom'),
            'typesstructures' => TypeStructure::cachedPluck('nom'),
            'peutValiderCa' => $peutValiderCa,
        ]);
    }

    /**
     * Propose le renouvellement d'un stage au chef d'agence.
     */
    public function renouveler(Request $request, int $id): RedirectResponse
    {
        $stage = Stage::with(['contrats', 'sourceFinancement'])->findOrFail($id);

        $rules = [
            'duree_mois' => ['required', 'integer', 'min:1', 'max:12'],
            'date_debut_avenant' => ['required', 'date'],
            'observation' => ['required', 'string', 'max:1000'],
            'contrat_avenant' => ['required', 'file', 'mimes:pdf,doc,docx', 'max:10240'],
            'prime' => ['nullable', 'numeric', 'min:0'],
            'type_structure_id' => ['nullable', 'exists:types_structure,id'],
        ];

        $codeFinancement = strtoupper((string) ($stage->sourceFinancement?->code ?? ''));

        // BUDGET AEJ exige le type de structure
        if (str_contains($codeFinancement, 'BUDGET')) {
            $rules['type_structure_id'] = ['required', 'exists:types_structure,id'];
        }

        // C2D ou PEJEDEC exige la prime >= 0
        if (str_contains($codeFinancement, 'C2D') || str_contains($codeFinancement, 'PEJEDEC')) {
            $rules['prime'] = ['required', 'numeric', 'min:0'];
        }

        $donnees = $request->validate($rules);

        // Validation temporelle date_debut_avenant >= date_fin_prevue initiale
        $dateDebutAvenant = Carbon::parse($donnees['date_debut_avenant']);
        if ($stage->date_fin_prevue && $dateDebutAvenant->lt($stage->date_fin_prevue)) {
            return back()->withErrors([
                'date_debut_avenant' => "La date de début d'avenant doit être postérieure ou égale à la date de fin initiale du stage ({$stage->date_fin_prevue->format('d/m/Y')}).",
            ]);
        }

        // Règle cohorte legacy : jour autorisé 1 à 5, 10 ou 20 du mois (hors PEJEDEC)
        if (! str_contains($codeFinancement, 'PEJEDEC')) {
            $allowedDays = [1, 2, 3, 4, 5, 10, 20];
            if (! in_array($dateDebutAvenant->day, $allowedDays, true)) {
                return back()->withErrors([
                    'date_debut_avenant' => "Pour ce financement, veuillez choisir un jour de début parmi le 1 au 5, 10 ou 20 du mois.",
                ]);
            }
        }

        // Upload du fichier joint
        $documentPath = null;
        if ($request->hasFile('contrat_avenant')) {
            $documentPath = $request->file('contrat_avenant')->store('avenants/'.$stage->id, 'public');
        }

        try {
            $resultat = $this->renouvellements->renouveler(
                stage: $stage,
                dureeMois: (int) $donnees['duree_mois'],
                motif: $donnees['observation'],
                dateEffet: $dateDebutAvenant,
                nouvellePrime: isset($donnees['prime']) && $donnees['prime'] !== '' ? (float) $donnees['prime'] : null,
                typeStructureId: ! empty($donnees['type_structure_id']) ? (int) $donnees['type_structure_id'] : null,
                documentPath: $documentPath,
                proposeParId: Auth::id(),
            );
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with(
            'success',
            "Avenant {$resultat['numero']} créé, fin de stage portée au {$resultat['nouvelle_date_fin']}. "
            .'En attente de validation du chef d\'agence.'
        );
    }

    /**
     * Renvoie au chef d'agence un renouvellement qu'il avait ajourné.
     */
    public function renvoyer(int $avenantId): RedirectResponse
    {
        $avenant = AvenantContrat::findOrFail($avenantId);

        if ($avenant->statut !== AvenantContrat::STATUT_AJOURNE) {
            return back()->with('error', "Ce renouvellement n'est pas ajourné.");
        }

        $this->renouvellements->renvoyerAuChefAgence($avenant);

        return back()->with('success', "Renouvellement {$avenant->numero} renvoyé au chef d'agence.");
    }

    /**
     * Le chef d'agence valide l'avenant de renouvellement.
     */
    public function valider(int $avenantId): RedirectResponse
    {
        $avenant = AvenantContrat::with('contrat.stage')->findOrFail($avenantId);

        $this->verifierAutorisationChefAgence($avenant);

        if ($avenant->statut !== AvenantContrat::STATUT_ATTENTE_CA) {
            return back()->with('error', "Ce renouvellement n'est pas en attente de validation.");
        }

        $this->renouvellements->validerParChefAgence($avenant, (int) Auth::id());

        return back()->with('success', "Renouvellement {$avenant->numero} validé avec succès.");
    }

    /**
     * Le chef d'agence ajourne l'avenant avec motif pour correction.
     */
    public function ajourner(Request $request, int $avenantId): RedirectResponse
    {
        $avenant = AvenantContrat::with('contrat.stage')->findOrFail($avenantId);

        $this->verifierAutorisationChefAgence($avenant);

        $request->validate([
            'observation' => ['required', 'string', 'min:3', 'max:1000'],
        ]);

        if ($avenant->statut !== AvenantContrat::STATUT_ATTENTE_CA) {
            return back()->with('error', "Ce renouvellement n'est pas en attente de validation.");
        }

        $this->renouvellements->ajournerParChefAgence($avenant, (string) $request->input('observation'), (int) Auth::id());

        return back()->with('success', "Renouvellement {$avenant->numero} ajourné.");
    }

    /**
     * Validation groupée d'avenants.
     */
    public function validerGroupe(Request $request): JsonResponse|RedirectResponse
    {
        $this->verifierAccesChefAgence();

        $avenantIds = $this->extraireIds($request->input('avenant_ids'));

        if ($avenantIds === []) {
            return back()->with('error', 'Aucun renouvellement sélectionné.');
        }

        $avenants = AvenantContrat::with('contrat.stage')
            ->whereIn('id', $avenantIds)
            ->where('statut', AvenantContrat::STATUT_ATTENTE_CA)
            ->get();

        $agencesAutorisees = $this->renouvellements->agencesAutorisees();

        $avenantsValides = $avenants->filter(function (AvenantContrat $avenant) use ($agencesAutorisees): bool {
            if ($agencesAutorisees === null) {
                return true;
            }

            return in_array($avenant->contrat?->stage?->agence_id, $agencesAutorisees, true);
        });

        if ($avenantsValides->count() <= 5) {
            DB::transaction(function () use ($avenantsValides): void {
                foreach ($avenantsValides as $avenant) {
                    $this->renouvellements->validerParChefAgence($avenant, (int) Auth::id());
                }
            });

            return back()->with('success', "{$avenantsValides->count()} renouvellement(s) validé(s) avec succès.");
        }

        $batch = Bus::batch([
            new DecideRenewalAvenantsJob(
                avenantIds: $avenantsValides->pluck('id')->all(),
                action: 'valider',
                observation: null,
                decideurId: (int) Auth::id()
            ),
        ])->name('Validation groupée renouvellements')->dispatch();

        return response()->json([
            'batch_id' => $batch->id,
            'message' => "Traitement de validation en masse en cours pour {$avenantsValides->count()} renouvellement(s).",
        ]);
    }

    /**
     * Ajournement groupé d'avenants.
     */
    public function ajournerGroupe(Request $request): JsonResponse|RedirectResponse
    {
        $this->verifierAccesChefAgence();

        $request->validate([
            'observation' => ['required', 'string', 'min:3', 'max:1000'],
        ]);

        $avenantIds = $this->extraireIds($request->input('avenant_ids'));

        if ($avenantIds === []) {
            return back()->with('error', 'Aucun renouvellement sélectionné.');
        }

        $avenants = AvenantContrat::with('contrat.stage')
            ->whereIn('id', $avenantIds)
            ->where('statut', AvenantContrat::STATUT_ATTENTE_CA)
            ->get();

        $agencesAutorisees = $this->renouvellements->agencesAutorisees();

        $avenantsAjournes = $avenants->filter(function (AvenantContrat $avenant) use ($agencesAutorisees): bool {
            if ($agencesAutorisees === null) {
                return true;
            }

            return in_array($avenant->contrat?->stage?->agence_id, $agencesAutorisees, true);
        });

        $observation = (string) $request->input('observation');

        if ($avenantsAjournes->count() <= 5) {
            DB::transaction(function () use ($avenantsAjournes, $observation): void {
                foreach ($avenantsAjournes as $avenant) {
                    $this->renouvellements->ajournerParChefAgence($avenant, $observation, (int) Auth::id());
                }
            });

            return back()->with('success', "{$avenantsAjournes->count()} renouvellement(s) ajourné(s).");
        }

        $batch = Bus::batch([
            new DecideRenewalAvenantsJob(
                avenantIds: $avenantsAjournes->pluck('id')->all(),
                action: 'ajourner',
                observation: $observation,
                decideurId: (int) Auth::id()
            ),
        ])->name('Ajournement groupé renouvellements')->dispatch();

        return response()->json([
            'batch_id' => $batch->id,
            'message' => "Traitement d'ajournement en masse en cours pour {$avenantsAjournes->count()} renouvellement(s).",
        ]);
    }

    /**
     * Renouvellement groupé de stages (CIP).
     */
    public function renouvelerGroupe(Request $request): JsonResponse|RedirectResponse
    {
        $stageIds = $this->extraireIds($request->input('stage_ids'));

        if ($stageIds === []) {
            return back()->with('error', 'Aucun stage sélectionné.');
        }

        $dureeMois = (int) $request->input('duree_mois', 6);
        $motif = $request->input('motif', 'Renouvellement massif de contrat');

        if (count($stageIds) <= 5) {
            $stages = Stage::with('contrats.avenants')->whereIn('id', $stageIds)->get();
            $count = 0;

            foreach ($stages as $stage) {
                try {
                    $this->renouvellements->renouveler($stage, $dureeMois, $motif, null, null, null, null, (int) Auth::id());
                    $count++;
                } catch (\Throwable) {
                    // Ignore stage déjà renouvelé
                }
            }

            return back()->with('success', "{$count} stage(s) renouvelé(s) en attente du chef d'agence.");
        }

        $batch = Bus::batch([
            new RenewStagesJob(
                stageIds: $stageIds,
                dureeMois: $dureeMois,
                motif: $motif,
                proposeParId: (int) Auth::id()
            ),
        ])->name('Renouvellement groupé stages')->dispatch();

        return response()->json([
            'batch_id' => $batch->id,
            'message' => 'Renouvellement massif lancé en arrière-plan.',
        ]);
    }

    /**
     * Statut de progression d'un traitement en lot (batch).
     */
    public function getBatchStatus(string $batchId): JsonResponse
    {
        $batch = Bus::findBatch($batchId);

        if (! $batch) {
            return response()->json(['message' => 'Batch introuvable'], 404);
        }

        return response()->json([
            'id' => $batch->id,
            'name' => $batch->name,
            'totalJobs' => $batch->totalJobs,
            'pendingJobs' => $batch->pendingJobs,
            'failedJobs' => $batch->failedJobs,
            'processedJobs' => $batch->processedJobs(),
            'progress' => $batch->progress(),
            'completed' => $batch->finished(),
        ]);
    }

    /**
     * Génère et télécharge l'avenant au format PDF.
     */
    public function genererPdf(Request $request): mixed
    {
        $id = $request->input('stage_id') ?? $request->input('id');

        if (! $id) {
            abort(400, 'Identifiant du stage manquant.');
        }

        $stage = Stage::findOrFail($id);

        $dateDebut = $request->input('date_debut');
        $dateFin = $request->input('date_fin');
        $prime = $request->has('prime') && $request->input('prime') !== '' ? (float) $request->input('prime') : null;

        $pdf = $this->avenantPdfService->genererPdf(
            stage: $stage,
            dateDebutAvenant: $dateDebut,
            dateFinAvenant: $dateFin,
            prime: $prime
        );

        $fileName = $this->avenantPdfService->genererNomFichier($stage);

        return $pdf->stream($fileName);
    }

    /**
     * Retourne les données d'un type de structure.
     */
    public function getTypeStructure(int $id): JsonResponse
    {
        $typeStructure = TypeStructure::find($id);

        if (! $typeStructure) {
            return response()->json(['message' => 'Type de structure non trouvé'], 404);
        }

        return response()->json([
            'id' => $typeStructure->id,
            'code' => $typeStructure->code,
            'nom' => $typeStructure->nom,
            'libelle' => $typeStructure->nom,
        ]);
    }

    /**
     * Exporte la corbeille courante au format CSV.
     */
    public function export(Request $request): StreamedResponse
    {
        $filtres = $request->only([
            'agence_id',
            'entreprise_id',
            'source_financement_id',
            'type_stage_id',
            'type_structure_id',
            'date_debut',
            'date_fin',
            'recherche',
        ]);

        $onglet = $request->string('onglet')->toString();
        $onglet = in_array($onglet, self::ONGLETS, true) ? $onglet : 'attente';

        $query = match ($onglet) {
            'anticipe' => $this->renouvellements->anticipeQuery($filtres),
            'ajourne' => $this->renouvellements->ajourneQuery($filtres),
            'chef_validation' => $this->renouvellements->chefAgenceValidationQuery($filtres),
            default => $this->renouvellements->attenteQuery($filtres),
        };

        $rows = $this->renouvellements->exportRows($query);
        $fileName = sprintf('renouvellements_%s_%s.csv', $onglet, now()->format('Ymd_His'));

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');

            // BOM UTF-8 pour Excel
            fwrite($handle, "\xEF\xBB\xBF");

            foreach ($rows as $row) {
                fputcsv($handle, $row, ';');
            }

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Contrôle que l'utilisateur a l'habilitation Chef d'agence et agit sur son périmètre agence.
     */
    private function verifierAutorisationChefAgence(AvenantContrat $avenant): void
    {
        $this->verifierAccesChefAgence();

        // Vérification périmètre agence
        $agencesAutorisees = $this->renouvellements->agencesAutorisees();

        if ($agencesAutorisees !== null) {
            $agenceStage = $avenant->contrat?->stage?->agence_id;
            if ($agenceStage && ! in_array($agenceStage, $agencesAutorisees, true)) {
                abort(403, "Vous n'êtes pas habilité sur l'agence de ce stage.");
            }
        }
    }

    private function verifierAccesChefAgence(): void
    {
        $user = Auth::user();

        if (! $user) {
            abort(401);
        }

        $estAutorise = method_exists($user, 'hasRole') && (
            $user->hasRole('administrateur')
            || $user->hasRole('chef_agence')
            || (method_exists($user, 'can') && $user->can('valider_chef_agence'))
            || $user->id === 1
        );

        if (! $estAutorise) {
            abort(403, "Action réservée au chef d'agence ou administrateur.");
        }
    }

    /**
     * Extrait une liste d'entiers à partir d'un tableau ou d'une chaîne séparée par des virgules.
     *
     * @return array<int, int>
     */
    private function extraireIds(mixed $valeur): array
    {
        if (is_array($valeur)) {
            return array_values(array_filter(array_map('intval', $valeur)));
        }

        if (is_string($valeur) && trim($valeur) !== '') {
            return array_values(array_filter(array_map('intval', explode(',', $valeur))));
        }

        return [];
    }
}
