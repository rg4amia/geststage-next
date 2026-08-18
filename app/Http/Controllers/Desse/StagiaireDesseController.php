<?php

namespace App\Http\Controllers\Desse;

use App\Domain\Workflow\Services\DesseDoublonService;
use App\Domain\Workflow\Services\WorkflowTransitionService;
use App\Enums\CorbeilleEnum;
use App\Enums\DoublonTypeEnum;
use App\Http\Controllers\Controller;
use App\Models\Company\Entreprise;
use App\Models\Reference\Agence;
use App\Models\Reference\SourceFinancement;
use App\Models\Reference\TypeStage;
use App\Models\Reference\TypeStructure;
use App\Models\Workflow\DesseDoublonDecision;
use App\Models\Workflow\InstanceParcours;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;

class StagiaireDesseController extends Controller
{
    private const FILTER_KEYS = [
        'agence_id',
        'entreprise_id',
        'source_financement_id',
        'type_stage_id',
        'type_structure_id',
        'date_debut',
        'date_fin',
        'search',
    ];

    private const EAGER_LOADS = [
        'stage.beneficiaire',
        'stage.entreprise.typeStructure',
        'stage.agence',
        'stage.sourceFinancement',
        'stage.typeStage',
    ];

    /**
     * Corbeilles alimentant l'onglet "Retour Chef d'Agence" : la cible du nouveau
     * workflow (DESSE_RETOUR_AGENCE) et son équivalent historique migré depuis le
     * legacy (statut 7 -> CIP_AJOURNE_DESSE), qui concentre les dossiers déjà
     * ajournés par la DESSE avant la mise en place de ce module.
     */
    private const RETOUR_CHEFAGENCE_CORBEILLES = [
        CorbeilleEnum::DESSE_RETOUR_AGENCE,
        CorbeilleEnum::CIP_AJOURNE_DESSE,
    ];

    public function __construct(
        private WorkflowTransitionService $workflow,
        private DesseDoublonService $doublons,
    ) {}

    public function index(Request $request)
    {
        $tab = $request->query('tab', 'attente');
        $typeDoublon = DoublonTypeEnum::tryFrom((string) $request->query('type_doublon')) ?? DoublonTypeEnum::PIECE_IDENTITE;
        $filters = $request->only(self::FILTER_KEYS);

        $data = match ($tab) {
            'doublons' => $this->paginateInstances(
                $this->applyFilters($this->doublons->eligibleInstancesQuery($typeDoublon), $filters),
            ),
            'retour_chefagence' => $this->paginateInstances(
                $this->applyFilters($this->corbeillesQuery(self::RETOUR_CHEFAGENCE_CORBEILLES), $filters),
            ),
            'doublons_traites' => $this->paginateDecisions($filters),
            default => $this->paginateInstances(
                $this->applyFilters($this->corbeilleQuery(CorbeilleEnum::DESSE_ATTENTE_VERIFICATION_DMG), $filters),
            ),
        };

        $doublonCounts = $this->doublons->countsByType();

        return Inertia::render('Desse/Stagiaires/Index', [
            'tab' => $tab,
            'typeDoublon' => $typeDoublon->value,
            'data' => $data,
            'filters' => $filters,
            'counts' => [
                'attente' => $this->corbeilleQuery(CorbeilleEnum::DESSE_ATTENTE_VERIFICATION_DMG)->count(),
                'doublons' => array_sum($doublonCounts),
                'retour_chefagence' => $this->corbeillesQuery(self::RETOUR_CHEFAGENCE_CORBEILLES)->count(),
                'doublons_traites' => DesseDoublonDecision::count(),
            ],
            'doublonCounts' => $doublonCounts,
            'doublonTypes' => collect(DoublonTypeEnum::cases())->map(fn ($type) => [
                'value' => $type->value,
                'label' => $type->label(),
            ]),
            'corbeilleLabels' => CorbeilleEnum::labels(),
            'corbeilleActionnable' => CorbeilleEnum::DESSE_DOUBLONS_A_TRAITER->value,
            'agences' => Cache::remember('filter_agences_desse', 1800, fn () => Agence::orderBy('nom')->get(['id', 'nom'])->toArray()),
            'entreprises' => Cache::remember('filter_entreprises_desse', 1800, fn () => Entreprise::orderBy('raison_sociale')->get(['id', 'raison_sociale'])->toArray()),
            'sourcesFinancement' => Cache::remember('filter_sources_financement_desse', 1800, fn () => SourceFinancement::orderBy('nom')->get(['id', 'nom'])->toArray()),
            'typesStage' => Cache::remember('filter_types_stage_desse', 1800, fn () => TypeStage::orderBy('nom')->get(['id', 'nom'])->toArray()),
            'typesStructure' => Cache::remember('filter_types_structure_desse', 1800, fn () => TypeStructure::orderBy('nom')->get(['id', 'nom'])->toArray()),
        ]);
    }

    public function valider(Request $request, int $id): RedirectResponse
    {
        $instance = InstanceParcours::findOrFail($id);
        $this->workflow->desseValidePejedec($instance);

        return back()->with('success', 'Dossier validé par la DESSE et transmis à la DAICG.');
    }

    public function ajourner(Request $request, int $id): RedirectResponse
    {
        $request->validate([
            'motif' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        $instance = InstanceParcours::findOrFail($id);
        $this->workflow->desseAjournePejedec($instance);

        return back()->with('success', 'Dossier ajourné et renvoyé vers l’agence.');
    }

    public function traiterDoublon(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'type_doublon' => ['required', 'in:'.implode(',', array_column(DoublonTypeEnum::cases(), 'value'))],
            'decision' => ['required', 'in:avere,non_avere'],
            'motif' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        $instance = InstanceParcours::findOrFail($id);

        if ($instance->corbeille_actuelle !== CorbeilleEnum::DESSE_DOUBLONS_A_TRAITER->value) {
            // La visibilité du doublon est volontairement large (toute la base, cf.
            // DesseDoublonService::basePoolQuery), mais seule une ligne encore dans
            // la corbeille "Doublons à traiter" peut faire l'objet d'une décision :
            // les dossiers déjà engagés ailleurs (paiement, pointage...) ne doivent
            // pas être déplacés par une décision de groupe.
            return back()->with('error', "Ce dossier n'est plus en attente de traitement DESSE (il est déjà à une autre étape du circuit) et ne peut donc pas faire l'objet d'une décision de doublon.");
        }

        $type = DoublonTypeEnum::from($validated['type_doublon']);

        $nombreTraites = $this->doublons->treatDuplicateGroup(
            $instance,
            $type,
            $validated['decision'],
            $validated['motif'],
            Auth::id(),
        );

        return back()->with('success', "Doublon traité : {$nombreTraites} dossier(s) impacté(s).");
    }

    private function corbeilleQuery(CorbeilleEnum $corbeille): Builder
    {
        return $this->corbeillesQuery([$corbeille]);
    }

    /**
     * @param  array<CorbeilleEnum>  $corbeilles
     */
    private function corbeillesQuery(array $corbeilles): Builder
    {
        // whereHas('stage') exclut les instances orphelines (stage supprimé/absent),
        // sans quoi l'utilisateur verrait des lignes vides côté bénéficiaire/agence/entreprise.
        return InstanceParcours::query()
            ->whereIn('corbeille_actuelle', array_map(fn (CorbeilleEnum $c) => $c->value, $corbeilles))
            ->whereNull('terminee_le')
            ->whereHas('stage');
    }

    private function paginateInstances(Builder $query)
    {
        return $query->with(self::EAGER_LOADS)
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();
    }

    private function paginateDecisions(array $filters)
    {
        $query = DesseDoublonDecision::query()->with([
            'decidePar',
            'instance.stage.beneficiaire',
            'instance.stage.entreprise.typeStructure',
            'instance.stage.agence',
            'instance.stage.sourceFinancement',
            'instance.stage.typeStage',
        ]);

        $this->applyFilters($query, $filters, 'instance.stage');

        return $query->orderByDesc('decide_le')->paginate(20)->withQueryString();
    }

    /**
     * Applique les filtres communs (agence, entreprise, financement, type de stage,
     * type de structure, dates, recherche) à une requête, en les portant sur la relation
     * "stage" indiquée (ex: 'stage' pour InstanceParcours, 'instance.stage' pour DesseDoublonDecision).
     */
    private function applyFilters(Builder $query, array $filters, string $stageRelation = 'stage'): Builder
    {
        if (! empty($filters['agence_id'])) {
            $query->whereHas($stageRelation, fn ($q) => $q->where('agence_id', $filters['agence_id']));
        }
        if (! empty($filters['entreprise_id'])) {
            $query->whereHas($stageRelation, fn ($q) => $q->where('entreprise_id', $filters['entreprise_id']));
        }
        if (! empty($filters['source_financement_id'])) {
            $query->whereHas($stageRelation, fn ($q) => $q->where('source_financement_id', $filters['source_financement_id']));
        }
        if (! empty($filters['type_stage_id'])) {
            $query->whereHas($stageRelation, fn ($q) => $q->where('type_stage_id', $filters['type_stage_id']));
        }
        if (! empty($filters['type_structure_id'])) {
            $query->whereHas("{$stageRelation}.entreprise", fn ($q) => $q->where('type_structure_id', $filters['type_structure_id']));
        }
        if (! empty($filters['date_debut'])) {
            $query->whereHas($stageRelation, fn ($q) => $q->where('date_debut', '>=', $filters['date_debut']));
        }
        if (! empty($filters['date_fin'])) {
            $query->whereHas($stageRelation, fn ($q) => $q->where('date_fin_prevue', '<=', $filters['date_fin']));
        }
        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->whereHas("{$stageRelation}.beneficiaire", function ($q) use ($search) {
                $q->where('nom', 'ilike', "%{$search}%")
                    ->orWhere('prenoms', 'ilike', "%{$search}%")
                    ->orWhere('numero_aej', 'ilike', "%{$search}%");
            });
        }

        return $query;
    }
}
