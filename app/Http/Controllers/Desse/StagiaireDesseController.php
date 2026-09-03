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
use App\Models\Workflow\EvenementParcours;
use App\Models\Workflow\InstanceParcours;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
        'stage.conseiller',
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

        // Vue « par groupe » des doublons : sans clé, la liste maître regroupe les profils par
        // clé de doublon (une ligne = un groupe + nb profils) ; avec `doublon_cle`, on liste
        // les profils du groupe sélectionné (paginés), en-tête du groupe en contexte.
        $doublonCle = $request->query('doublon_cle');
        $groupe = null;

        $data = match ($tab) {
            'doublons' => $this->paginateGroupedDoublons($typeDoublon, $filters, $doublonCle, $groupe),
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
            'doublonCle' => $doublonCle,
            'groupe' => $groupe,
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
            'agences' => Agence::cachedOptions('nom'),
            'entreprises' => Entreprise::cachedOptions('raison_sociale'),
            'sourcesFinancement' => SourceFinancement::cachedOptions('nom'),
            'typesStage' => TypeStage::cachedOptions('nom'),
            'typesStructure' => TypeStructure::cachedOptions('nom'),
        ]);
    }

    public function valider(Request $request, int $id): RedirectResponse
    {
        $instance = InstanceParcours::findOrFail($id);
        $this->workflow->desseValidePejedec($instance);

        return back()->with('success', 'Dossier validé par la DESSE et transmis à la DAICG.');
    }

    /**
     * Traitement d'un dossier « Retour Chef d'Agence » : portage de la méthode legacy
     * (TraitementEtapeController etape_next=9 → IndexDmgController::verification) qui faisait
     * passer les dossiers de l'étape 7/8 vers l'étape 9 « DMG : validé après vérification »,
     * décision (etat) et motif enregistrés avant la transition.
     *
     *  - « valide » : le dossier est libéré du circuit doublon et rejoint la file DMG
     *    (présence si le cycle de pointage a démarré, démarrage sinon).
     *  - « ajourne » : le dossier reste dans le circuit retour (cip_ajourne_desse) pour une
     *    nouvelle correction par le CIP ; le motif est alors obligatoire.
     *
     * Seule une instance encore dans les corbeilles de retour (cip_ajourne_desse /
     * desse_retour_agence) peut être traitée ici.
     */
    public function validerRetourAgence(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'decision' => ['required', 'in:valide,ajourne'],
            'motif' => ['nullable', 'string', 'max:1000', 'required_if:decision,ajourne'],
        ]);

        $instance = InstanceParcours::with('stage')->findOrFail($id);

        if ($instance->terminee_le !== null
            || $instance->stage === null
            || ! in_array($instance->corbeille_actuelle, array_map(
                fn (CorbeilleEnum $c) => $c->value,
                self::RETOUR_CHEFAGENCE_CORBEILLES
            ), true)) {
            return back()->with('error', "Ce dossier n'est plus en attente de traitement DESSE (il a déjà quitté le circuit « Retour Chef d'Agence ») et ne peut pas être traité ici.");
        }

        $this->workflow->desseValideRetourAgence(
            $instance,
            $validated['decision'],
            $validated['motif'] ?? null,
            Auth::id(),
        );

        return back()->with(
            'success',
            $validated['decision'] === 'ajourne'
                ? 'Dossier renvoyé au CIP pour une nouvelle correction (motif enregistré).'
                : 'Dossier validé par la DESSE et transmis à la DMG pour vérification et paiement.'
        );
    }

    /**
     * Historique de traitement d'un dossier « Retour Chef d'Agence » : les événements du
     * parcours pertinents (décisions DESSE + traçage de la migration legacy) avec l'auteur,
     * la date, la décision et le motif, pour alimenter la modale de détail de l'onglet.
     */
    public function historiqueRetourAgence(int $id): JsonResponse
    {
        $instance = InstanceParcours::with('stage.beneficiaire')->findOrFail($id);

        $evenements = $instance->evenements()
            ->with(['acteur', 'etapeSource', 'etapeCible'])
            ->whereIn('type', ['DESSE_RETOUR_VALIDATION', 'DESSE_RETOUR_AJOURNEMENT', 'MIGRATION_STATUT'])
            ->orderByDesc('survenu_le')
            ->orderByDesc('id')
            ->get()
            ->map(fn (EvenementParcours $e): array => [
                'id' => $e->id,
                'type' => $e->type,
                'survenu_le' => $e->survenu_le?->toISOString(),
                'auteur' => $e->acteur ? $e->acteur->nom : null,
                'etape_source' => $e->etapeSource ? [
                    'code' => $e->etapeSource->code,
                    'nom' => $e->etapeSource->nom,
                ] : null,
                'etape_cible' => $e->etapeCible ? [
                    'code' => $e->etapeCible->code,
                    'nom' => $e->etapeCible->nom,
                ] : null,
                'donnees' => $e->donnees,
            ]);

        return response()->json([
            'instance_id' => $instance->id,
            'beneficiaire' => [
                'nom' => $instance->stage?->beneficiaire?->nom,
                'prenoms' => $instance->stage?->beneficiaire?->prenoms,
            ],
            'corbeille_actuelle' => $instance->corbeille_actuelle,
            'evenements' => $evenements,
        ]);
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

    /**
     * Liste « Doublons à traiter » regroupée par clé de doublon.
     *
     * - Sans `doublonCle` : vue maître — une ligne par groupe de doublons (clé normalisée,
     *   nombre de profils, aperçu), paginée sur les groupes.
     * - Avec `doublonCle` : vue détail — les profils du groupe sélectionné (une ligne par
     *   dossier), paginés, avec les métadonnées du groupe (`groupe`).
     */
    private function paginateGroupedDoublons(
        DoublonTypeEnum $type,
        array $filters,
        ?string $doublonCle,
        ?array &$groupe
    ) {
        $query = $this->doublons->eligibleInstancesQuery($type)
            ->with(self::EAGER_LOADS)
            ->orderByDesc('instances_parcours.created_at');

        $this->applyFilters($query, $filters);

        $instances = $query->get();

        if ($doublonCle === null) {
            // Vue maître : un groupe = une clé de doublon partagée par plusieurs profils.
            $groupes = $instances->groupBy(
                fn ($i) => (string) ($this->doublons->normalizedKeyFor($i, $type) ?? '')
            )->filter(fn ($profils, $cle) => $cle !== '');

            $groupes = $groupes->map(function ($profils, $cle) {
                $preview = $profils->values()->take(5)->map(fn ($p) => $p->toArray());

                return [
                    'cle' => $cle,
                    'nb_profils' => $profils->count(),
                    'profils' => $preview,
                ];
            })->values();

            return $this->collectionPaginate($groupes, 10);
        }

        // Vue détail : profils partageant exactement la clé sélectionnée.
        $profils = $instances->filter(
            fn ($i) => $this->doublons->normalizedKeyFor($i, $type) === $doublonCle
        )->values();

        if ($profils->isNotEmpty()) {
            $groupe = [
                'cle' => $doublonCle,
                'nb_profils' => $profils->count(),
            ];
        }

        return $this->collectionPaginate($profils, 5);
    }

    /**
     * Pagine une collection en conservant les paramètres d'URL de la requête courante.
     */
    private function collectionPaginate(\Illuminate\Support\Collection $items, int $perPage)
    {
        $page = \Illuminate\Pagination\Paginator::resolveCurrentPage('page');
        $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
            $items->forPage($page, $perPage)->values(),
            $items->count(),
            $perPage,
            $page,
            ['path' => \Illuminate\Pagination\Paginator::resolveCurrentPath(), 'query' => request()->query()]
        );

        return $paginator->withQueryString();
    }

    private function paginateDecisions(array $filters)
    {
        $query = DesseDoublonDecision::query()->with([
            'decidePar',
            'instance.stage.beneficiaire.typePaiement',
            'instance.stage.entreprise.typeStructure',
            'instance.stage.agence',
            'instance.stage.conseiller',
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
