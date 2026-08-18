<?php

namespace App\Http\Controllers\ChefAgence;

use App\Domain\Validation\Services\ValidationChefAgenceService;
use App\Domain\Workflow\Services\WorkflowTransitionService;
use App\Enums\CorbeilleEnum;
use App\Http\Controllers\Controller;
use App\Models\Company\Entreprise;
use App\Models\Reference\Agence;
use App\Models\Reference\Periode;
use App\Models\Reference\SourceFinancement;
use App\Models\Reference\TypeStage;
use App\Models\Reference\TypeStructure;
use App\Models\Workflow\InstanceParcours;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class IndexChefAgenceController extends Controller
{
    public function __construct(
        private readonly WorkflowTransitionService $workflowService,
        private readonly ValidationChefAgenceService $validationService
    ) {}

    public function listeStagiaireAttenteValidation(Request $request)
    {
        $filters = $request->only([
            'agence_id',
            'entreprise_id',
            'typesfinancement_id',
            'typestage_id',
            'type_structure_id',
            'created_begin',
            'created_end',
            'periode_id',
        ]);

        $query = $this->baseQuery($request);

        $demarrage = (clone $query)
            ->where('corbeille_actuelle', CorbeilleEnum::CA_ATTENTE_VALIDATION_DEMARRAGE->value)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (InstanceParcours $instance) => $this->formatRow($instance))
            ->values();

        // Filtre période : on extrait l'année et le mois depuis le code de la période (format 'Y-m')
        // pour faire correspondre exactement le mois de début du stage, indépendamment
        // des bornes date_debut/date_fin de la période.
        $periodeSelectionnee = $request->filled('periode_id')
            ? Periode::query()->find($request->integer('periode_id'))
            : null;

        $demarrageOmis = (clone $query)
            ->where('corbeille_actuelle', CorbeilleEnum::CA_ATTENTE_VALIDATION_OMIS->value)
            ->when($periodeSelectionnee, function (Builder $builder) use ($periodeSelectionnee): void {
                // Le code de la période est au format 'Y-m' (ex : '2024-06').
                // On parse ce code pour extraire l'année et le mois exacts.
                try {
                    $dt = Carbon::createFromFormat('Y-m', $periodeSelectionnee->code);
                    $builder->whereHas('stage', function (Builder $stageQuery) use ($dt): void {
                        $stageQuery
                            ->whereYear('date_debut', $dt->year)
                            ->whereMonth('date_debut', $dt->month);
                    });
                } catch (\Exception $e) {
                    // Si le code n'est pas au format Y-m, on tombe en fallback sur date_debut/date_fin
                    $builder->whereHas('stage', function (Builder $stageQuery) use ($periodeSelectionnee): void {
                        $stageQuery->whereBetween('date_debut', [
                            $periodeSelectionnee->date_debut,
                            $periodeSelectionnee->date_fin,
                        ]);
                    });
                }
            })
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (InstanceParcours $instance) => $this->formatRow($instance))
            ->values();

        $retourAjournement = (clone $query)
            ->where('corbeille_actuelle', CorbeilleEnum::CA_RETOUR_AJOURNEMENT->value)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (InstanceParcours $instance) => $this->formatRow($instance))
            ->values();

        // Compteurs par onglet (pour les cartes statistiques du frontend)
        $baseQueryCount = $this->baseQuery($request);
        $counts = [
            'demarrage'         => (clone $baseQueryCount)->where('corbeille_actuelle', CorbeilleEnum::CA_ATTENTE_VALIDATION_DEMARRAGE->value)->count(),
            // Le compteur Démarrage Omis applique aussi le filtre mois si une période est sélectionnée
            'demarrageOmis'     => (clone $baseQueryCount)
                ->where('corbeille_actuelle', CorbeilleEnum::CA_ATTENTE_VALIDATION_OMIS->value)
                ->when($periodeSelectionnee, function (Builder $q) use ($periodeSelectionnee): void {
                    try {
                        $dt = Carbon::createFromFormat('Y-m', $periodeSelectionnee->code);
                        $q->whereHas('stage', fn (Builder $s) => $s
                            ->whereYear('date_debut', $dt->year)
                            ->whereMonth('date_debut', $dt->month));
                    } catch (\Exception $e) {
                        $q->whereHas('stage', fn (Builder $s) => $s->whereBetween('date_debut', [
                            $periodeSelectionnee->date_debut,
                            $periodeSelectionnee->date_fin,
                        ]));
                    }
                })
                ->count(),
            'retourAjournement' => (clone $baseQueryCount)->where('corbeille_actuelle', CorbeilleEnum::CA_RETOUR_AJOURNEMENT->value)->count(),
        ];

        if ($request->wantsJson()) {
            return response()->json([
                'demarrage'          => $demarrage,
                'demarrageOmis'      => $demarrageOmis,
                'retourAjournement'  => $retourAjournement,
                'counts'             => $counts,
            ]);
        }

        return Inertia::render('ChefAgence/ValidationDemarrage/Index', [
            'agences'            => \Illuminate\Support\Facades\Cache::remember('ref.agences', 86400, fn () => Agence::query()->orderBy('nom')->pluck('nom', 'id')),
            'entreprises'        => \Illuminate\Support\Facades\Cache::remember('ref.entreprises', 86400, fn () => Entreprise::query()->orderBy('raison_sociale')->pluck('raison_sociale', 'id')),
            'typesfinancements'  => \Illuminate\Support\Facades\Cache::remember('ref.typesfinancements', 86400, fn () => SourceFinancement::query()->orderBy('nom')->pluck('nom', 'id')),
            'typestages'         => \Illuminate\Support\Facades\Cache::remember('ref.typestages', 86400, fn () => TypeStage::query()->orderBy('nom')->pluck('nom', 'id')),
            'typestructures'     => \Illuminate\Support\Facades\Cache::remember('ref.typestructures', 86400, fn () => TypeStructure::query()->orderBy('nom')->pluck('nom', 'id')),
            'periodes'           => \Illuminate\Support\Facades\Cache::remember('ref.periodes', 86400, fn () => Periode::query()->orderByDesc('code')->pluck('code', 'id')),
            'filters'            => $filters,
        ]);
    }

    public function validerDemarrage(int $id)
    {
        $instance = $this->findInstanceForChefAgence($id, [
            CorbeilleEnum::CA_ATTENTE_VALIDATION_DEMARRAGE,
        ]);

        $this->validationService->validerDemarrage($instance, request()->user());

        return back()->with('success', 'Le dossier a été validé avec succès.');
    }

    public function validerDemarrageOmis(int $id)
    {
        $instance = $this->findInstanceForChefAgence($id, [
            CorbeilleEnum::CA_ATTENTE_VALIDATION_OMIS,
        ]);

        $this->workflowService->caValideDemarrageOmis($instance);

        return back()->with('success', 'Le dossier a été validé avec succès.');
    }

    public function validerGroup(Request $request)
    {
        $data = $request->validate([
            'ids'  => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:instances_parcours,id'],
            'type' => ['required', 'string', 'in:demarrage,demarrageOmis,retourAjournement'],
        ]);

        $instances = InstanceParcours::query()
            ->with('stage')
            ->whereIn('id', $data['ids'])
            ->get()
            ->keyBy('id');

        $count = 0;
        foreach ($data['ids'] as $id) {
            $instance = $instances->get($id);

            if (! $instance) {
                continue;
            }

            if ($data['type'] === 'demarrageOmis') {
                $this->workflowService->caValideDemarrageOmis($instance);
            } else {
                $this->validationService->validerDemarrage($instance, $request->user());
            }
            $count++;
        }

        return back()->with('success', $count.' dossier(s) validé(s) avec succès.');
    }

    public function ajournerGroup(Request $request)
    {
        $data = $request->validate([
            'ids'   => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:instances_parcours,id'],
            'motif' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        $instances = InstanceParcours::query()
            ->with(['stage', 'etapeCourante'])
            ->whereIn('id', $data['ids'])
            ->get();

        $count = 0;
        DB::transaction(function () use ($instances, $data, $request) {
            foreach ($instances as $instance) {
                $this->workflowService->caAjourneSoumission($instance);

                // Alimentation de la table ajournements complète
                try {
                    $etapeOrigineId = $instance->etape_courante_id;
                    $etapeRetour = \App\Models\Workflow\EtapeParcours::where('definition_parcours_id', $instance->definition_parcours_id)
                        ->where('code', CorbeilleEnum::CIP_MES_STAGIAIRES->value)
                        ->first();
                    $etapeRetourId = $etapeRetour ? $etapeRetour->id : $etapeOrigineId;

                    $motifAjournementId = \Illuminate\Support\Facades\DB::table('motifs_ajournement')->first()->id ?? \Illuminate\Support\Facades\DB::table('motifs_ajournement')->insertGetId([
                        'code' => 'AUTRE',
                        'nom' => 'Autre',
                        'domaine' => 'Validation',
                        'actif' => true
                    ]);
                    
                    $roleCorrecteurId = \Illuminate\Support\Facades\DB::table('roles')->where('name', 'like', '%CIP%')->first()->id ?? \Illuminate\Support\Facades\DB::table('roles')->first()->id;
                    $numeroCycle = \App\Models\Adjournment\Ajournement::where('instance_parcours_id', $instance->id)->max('numero_cycle') + 1;

                    \App\Models\Adjournment\Ajournement::create([
                        'uuid_public'            => \Illuminate\Support\Str::uuid(),
                        'instance_parcours_id'   => $instance->id,
                        'etape_origine_id'       => $etapeOrigineId,
                        'etape_correction_id'    => $etapeRetourId,
                        'etape_retour_id'        => $etapeOrigineId,
                        'motif_ajournement_id'   => $motifAjournementId,
                        'role_correcteur_id'     => $roleCorrecteurId,
                        'auteur_id'              => $request->user()->id,
                        'code_corbeille_origine' => $instance->corbeille_actuelle ?? CorbeilleEnum::CA_ATTENTE_VALIDATION_DEMARRAGE->value,
                        'code_corbeille_retour'  => CorbeilleEnum::CIP_MES_STAGIAIRES->value,
                        'motif_detaille'         => $data['motif'],
                        'correction_attendue'    => "Veuillez corriger le dossier selon le motif d'ajournement fourni.",
                        'numero_cycle'           => $numeroCycle,
                        'statut'                 => 'OUVERT',
                    ]);
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::warning("Impossible d'enregistrer l'ajournement complet pour l'instance {$instance->id}: ".$e->getMessage());
                }
            }
        });

        return back()->with('success', $instances->count().' dossier(s) ajourné(s) avec succès.');
    }

    public function genererAddGroup(Request $request)
    {
        $data = $request->validate([
            'ids'   => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:instances_parcours,id'],
            'type'  => ['nullable', 'string'],
        ]);

        return back()->with('success', 'La génération d\'ADD a été déclenchée pour '.count($data['ids']).' dossier(s).');
    }

    private function baseQuery(Request $request): Builder
    {
        $user = Auth::user();

        return InstanceParcours::query()
            ->with([
                'stage.beneficiaire',
                'stage.entreprise.typeStructure',
                'stage.agence',
                'stage.sourceFinancement',
                'stage.typeStage',
                'stage.contrats',
            ])
            // ─── SCOPE AGENCE DU CA CONNECTÉ ───────────────────────────────────
            // Le CA ne voit que les dossiers de son agence (comme le legacy).
            // Si l'utilisateur n'a pas d'agence assignée (superadmin), on ne filtre pas.
            ->when($user->agence_id, function (Builder $query) use ($user): void {
                $query->whereHas('stage', fn (Builder $stageQuery) => $stageQuery->where('agence_id', $user->agence_id));
            })
            // ─── FILTRES ADDITIONNELS (surchargent le scope agence si passés) ──
            ->when($request->filled('agence_id'), function (Builder $query) use ($request): void {
                $query->whereHas('stage', fn (Builder $stageQuery) => $stageQuery->where('agence_id', $request->integer('agence_id')));
            })
            ->when($request->filled('entreprise_id'), function (Builder $query) use ($request): void {
                $query->whereHas('stage', fn (Builder $stageQuery) => $stageQuery->where('entreprise_id', $request->integer('entreprise_id')));
            })
            ->when($request->filled('typesfinancement_id'), function (Builder $query) use ($request): void {
                $query->whereHas('stage', fn (Builder $stageQuery) => $stageQuery->where('source_financement_id', $request->integer('typesfinancement_id')));
            })
            ->when($request->filled('typestage_id'), function (Builder $query) use ($request): void {
                $query->whereHas('stage', fn (Builder $stageQuery) => $stageQuery->where('type_stage_id', $request->integer('typestage_id')));
            })
            ->when($request->filled('type_structure_id'), function (Builder $query) use ($request): void {
                $query->whereHas('stage', function (Builder $stageQuery) use ($request): void {
                    $stageQuery->whereHas('entreprise', fn (Builder $enterpriseQuery) => $enterpriseQuery->where('type_structure_id', $request->integer('type_structure_id')));
                });
            })
            ->when($request->filled('created_begin'), function (Builder $query) use ($request): void {
                $query->whereDate('created_at', '>=', $request->date('created_begin')->format('Y-m-d'));
            })
            ->when($request->filled('created_end'), function (Builder $query) use ($request): void {
                $query->whereDate('created_at', '<=', $request->date('created_end')->format('Y-m-d'));
            });
    }

    /**
     * @return array<string, mixed>
     */
    private function formatRow(InstanceParcours $instance): array
    {
        $stage          = $instance->stage;
        $beneficiaire   = $stage?->beneficiaire;
        $contrat        = $stage?->contrats?->first();

        return [
            'id'                  => $instance->id,
            'date'                => $instance->created_at?->format('d/m/Y') ?? '-',
            'agence'              => $stage?->agence?->nom ?? '-',
            'entreprise'          => $stage?->entreprise?->raison_sociale ?? '-',
            'source_financement'  => $stage?->sourceFinancement?->nom ?? '-',
            'type_stage'          => $stage?->typeStage?->nom ?? '-',
            'type_structure'      => $stage?->entreprise?->typeStructure?->nom ?? '-',
            'numero_aej'          => $beneficiaire?->numero_aej ?? '-',
            'nom_prenoms'         => trim(($beneficiaire?->nom ?? '').' '.($beneficiaire?->prenoms ?? '')) ?: '-',
            'date_naissance'      => $beneficiaire?->date_naissance ? Carbon::parse($beneficiaire->date_naissance)->format('d/m/Y') : '-',
            'sexe'                => $beneficiaire?->sexe ?? '-',
            'contrat_label'       => $contrat ? 'Avec Contrat' : 'Sans Contrat',
            'incidence_financiere' => (float) ($contrat?->prime_mensuelle ?? 0) > 0 ? 'Oui' : 'Non',
            'date_debut'          => $stage?->date_debut ? Carbon::parse($stage->date_debut)->translatedFormat('d M Y') : '-',
            'date_fin'            => $stage?->date_fin_prevue ? Carbon::parse($stage->date_fin_prevue)->translatedFormat('d M Y') : '-',
            'corbeille_actuelle'  => $instance->corbeille_actuelle,
        ];
    }

    /**
     * @param  array<int, CorbeilleEnum>  $corbeilles
     */
    private function findInstanceForChefAgence(int $id, array $corbeilles): InstanceParcours
    {
        $user = Auth::user();

        return InstanceParcours::query()
            ->with('stage')
            ->where('id', $id)
            ->whereIn('corbeille_actuelle', array_map(fn (CorbeilleEnum $corbeille) => $corbeille->value, $corbeilles))
            // Vérification de sécurité : l'instance appartient bien à l'agence du CA
            ->when($user->agence_id, function (Builder $query) use ($user): void {
                $query->whereHas('stage', fn (Builder $stageQuery) => $stageQuery->where('agence_id', $user->agence_id));
            })
            ->firstOrFail();
    }
}
