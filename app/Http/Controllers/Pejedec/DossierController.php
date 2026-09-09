<?php

namespace App\Http\Controllers\Pejedec;

use App\Domain\Pejedec\Services\PejedecSourceResolver;
use App\Enums\CorbeilleEnum;
use App\Http\Controllers\Controller;
use App\Models\Company\Entreprise;
use App\Models\Reference\Agence;
use App\Models\Workflow\EtapeParcours;
use App\Models\Workflow\EvenementParcours;
use App\Models\Workflow\InstanceParcours;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DossierController extends Controller
{
    private const EVENEMENT_VALIDATION = 'PEJEDEC_VALIDATION';

    private const CORBEILLES_ATTENTE = [
        'en_stage',
        'daicg_valides_ca',
        'desse_attente_ca',
    ];

    public function __construct(private PejedecSourceResolver $pejedecSourceResolver) {}

    public function attenteValidation(Request $request): Response
    {
        return $this->renderListe($request, false, 'Pejedec/Dossiers/AttenteValidation');
    }

    public function valides(Request $request): Response
    {
        return $this->renderListe($request, true, 'Pejedec/Dossiers/Valides');
    }

    public function valider(Request $request, int $id): RedirectResponse
    {
        $instance = InstanceParcours::with(['stage.sourceFinancement', 'stage.contrats'])->findOrFail($id);
        $this->assertActionnable($instance);

        DB::transaction(function () use ($instance, $request): void {
            $etapeCible = $this->etapePourCorbeille(
                (int) $instance->definition_parcours_id,
                CorbeilleEnum::CIP_POINTAGE_PEJEDEC
            );

            $dejaValide = $instance->evenements()
                ->where('type', self::EVENEMENT_VALIDATION)
                ->exists();

            if (! $dejaValide) {
                EvenementParcours::create([
                    'instance_parcours_id' => $instance->id,
                    'etape_source_id' => $instance->etape_courante_id,
                    'etape_cible_id' => $etapeCible->id,
                    'auteur_id' => $request->user()->id,
                    'type' => self::EVENEMENT_VALIDATION,
                    'cle_idempotence' => "pejedec-validation:{$instance->id}",
                    'donnees' => [
                        'decision' => 'valide',
                        'corbeille_cible' => CorbeilleEnum::CIP_POINTAGE_PEJEDEC->value,
                    ],
                    'survenu_le' => now(),
                ]);
            }

            $instance->update([
                'etape_courante_id' => $etapeCible->id,
                'corbeille_actuelle' => CorbeilleEnum::CIP_POINTAGE_PEJEDEC->value,
            ]);
        });

        return back()->with('success', 'Dossier PEJEDEC validé et transmis au pointage PEJEDEC.');
    }

    private function renderListe(Request $request, bool $valides, string $component): Response
    {
        $filters = $request->only(['agence_id', 'entreprise_id', 'search']);
        $source = $this->pejedecSourceResolver->source();
        $agencesAutorisees = $this->agencesAutorisees($request);

        $query = $this->queryDossiers($valides, $filters, $agencesAutorisees);
        $instances = $query->paginate(20)->withQueryString();
        $instances->getCollection()->transform(fn (InstanceParcours $instance) => $this->row($instance));

        return Inertia::render($component, [
            'dossiers' => $instances,
            'filters' => $filters,
            'sourceFinancement' => $source ? [
                'id' => $source->id,
                'code' => $source->code,
                'nom' => $source->nom,
            ] : null,
            'agences' => Agence::query()
                ->when($agencesAutorisees !== null, fn ($query) => $query->whereIn('id', $agencesAutorisees))
                ->orderBy('nom')
                ->get(['id', 'nom']),
            'entreprises' => Entreprise::orderBy('raison_sociale')->get(['id', 'raison_sociale']),
            'stats' => [
                'attente' => $this->queryDossiers(false, [], $agencesAutorisees)->count(),
                'valides' => $this->queryDossiers(true, [], $agencesAutorisees)->count(),
            ],
        ]);
    }

    private function queryDossiers(bool $valides, array $filters, ?array $agencesAutorisees): Builder
    {
        $sourceId = $this->pejedecSourceResolver->id();

        return InstanceParcours::query()
            ->with([
                'stage.beneficiaire',
                'stage.entreprise',
                'stage.agence',
                'stage.sourceFinancement',
                'stage.contrats',
                'etapeCourante',
            ])
            ->whereNull('terminee_le')
            ->whereHas('stage', function (Builder $stage) use ($sourceId, $filters, $agencesAutorisees): void {
                if ($sourceId) {
                    $stage->where('source_financement_id', $sourceId);
                } else {
                    $stage->whereHas('sourceFinancement', fn (Builder $source) => $source
                        ->where('code', 'PEJEDEC')
                        ->orWhere('ancien_id', 5)
                    );
                }

                if ($agencesAutorisees !== null) {
                    $stage->whereIn('agence_id', $agencesAutorisees);
                }

                if (! empty($filters['agence_id'])) {
                    $stage->where('agence_id', $filters['agence_id']);
                }

                if (! empty($filters['entreprise_id'])) {
                    $stage->where('entreprise_id', $filters['entreprise_id']);
                }

                $stage->whereHas('contrats', fn (Builder $contrat) => $contrat->where('statut', 'VALIDE'));
            })
            ->when(! empty($filters['search']), function (Builder $query) use ($filters): void {
                $search = (string) $filters['search'];
                $operator = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
                $term = '%'.addcslashes($search, '%_').'%';

                $query->whereHas('stage.beneficiaire', function (Builder $beneficiaire) use ($operator, $term): void {
                    $beneficiaire->where('nom', $operator, $term)
                        ->orWhere('prenoms', $operator, $term)
                        ->orWhere('numero_aej', $operator, $term);
                });
            })
            ->when($valides, function (Builder $query): void {
                $query->whereHas('evenements', fn (Builder $event) => $event->where('type', self::EVENEMENT_VALIDATION));
            }, function (Builder $query): void {
                $query->whereIn('corbeille_actuelle', self::CORBEILLES_ATTENTE)
                    ->whereDoesntHave('evenements', fn (Builder $event) => $event->where('type', self::EVENEMENT_VALIDATION));
            })
            ->orderByDesc('created_at');
    }

    private function row(InstanceParcours $instance): array
    {
        $stage = $instance->stage;
        $beneficiaire = $stage?->beneficiaire;
        $jourDebut = $stage?->date_debut?->day;

        return [
            'id' => $instance->id,
            'numero' => 'PEJ-'.str_pad((string) $instance->id, 5, '0', STR_PAD_LEFT),
            'beneficiaire' => [
                'nom' => $beneficiaire?->nom ?? 'Inconnu',
                'prenoms' => $beneficiaire?->prenoms ?? '',
                'matricule' => $beneficiaire?->numero_aej ?? '',
            ],
            'entreprise' => [
                'raison_sociale' => $stage?->entreprise?->raison_sociale ?? '-',
            ],
            'agence' => [
                'nom' => $stage?->agence?->nom ?? '-',
            ],
            'stage' => [
                'date_debut' => $stage?->date_debut?->format('d/m/Y'),
                'date_fin_prevue' => $stage?->date_fin_prevue?->format('d/m/Y'),
                'source_financement' => $stage?->sourceFinancement?->nom ?? 'PEJEDEC',
            ],
            'contrat' => [
                'numero' => $stage?->contrats?->sortByDesc('date_debut')->first()?->numero,
                'prime_mensuelle' => $stage?->contrats?->sortByDesc('date_debut')->first()?->prime_mensuelle,
            ],
            'cohorte' => match (true) {
                $jourDebut >= 1 && $jourDebut <= 10 => '1-10',
                $jourDebut >= 11 && $jourDebut <= 20 => '11-20',
                $jourDebut >= 21 && $jourDebut <= 31 => '21-31',
                default => '-',
            },
            'corbeille' => [
                'code' => $instance->corbeille_actuelle,
                'label' => CorbeilleEnum::tryFrom((string) $instance->corbeille_actuelle)?->label() ?? $instance->corbeille_actuelle,
            ],
            'date_creation' => $instance->created_at?->format('d/m/Y'),
        ];
    }

    private function assertActionnable(InstanceParcours $instance): void
    {
        $source = $instance->stage?->sourceFinancement;

        abort_unless(
            $source?->code === 'PEJEDEC' || (int) $source?->ancien_id === 5,
            403,
            "Ce dossier n'appartient pas au financement PEJEDEC."
        );

        abort_unless($instance->stage?->contrats()->where('statut', 'VALIDE')->exists(), 409, 'Le contrat du dossier doit être validé.');
        abort_unless($instance->terminee_le === null, 409, 'Ce dossier est déjà terminé.');
        abort_unless(! $instance->evenements()->where('type', self::EVENEMENT_VALIDATION)->exists(), 409, 'Ce dossier PEJEDEC est déjà validé.');
        abort_unless(in_array($instance->corbeille_actuelle, self::CORBEILLES_ATTENTE, true), 409, "Ce dossier n'est pas dans une file de validation PEJEDEC.");

        $agencesAutorisees = $this->agencesAutorisees(request());
        abort_unless(
            $agencesAutorisees === null || in_array($instance->stage?->agence_id, $agencesAutorisees, true),
            403,
            "Ce dossier ne relève pas de votre périmètre d'agence."
        );
    }

    private function agencesAutorisees(Request $request): ?array
    {
        $user = $request->user();

        if (! $user || $user->hasRole('administrateur')) {
            return null;
        }

        $agenceIds = $user->perimetresAgences()->pluck('agences.id')->all();

        return $agenceIds === [] ? null : $agenceIds;
    }

    private function etapePourCorbeille(int $definitionParcoursId, CorbeilleEnum $corbeille): EtapeParcours
    {
        $code = strtoupper($corbeille->value);

        return EtapeParcours::firstOrCreate([
            'definition_parcours_id' => $definitionParcoursId,
            'code' => $code,
        ], [
            'nom' => $corbeille->label(),
            'code_corbeille' => $corbeille->value,
            'initiale' => false,
            'finale' => false,
        ]);
    }
}
