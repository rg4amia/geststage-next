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

        $demarrageOmis = collect();
        if ($request->filled('periode_id')) {
            $periode = Periode::query()->find($request->integer('periode_id'));

            $demarrageOmis = (clone $query)
                ->where('corbeille_actuelle', CorbeilleEnum::CA_ATTENTE_VALIDATION_OMIS->value)
                ->when($periode, function (Builder $builder) use ($periode): void {
                    $builder->whereHas('stage', function (Builder $stageQuery) use ($periode): void {
                        $stageQuery->whereBetween('date_debut', [
                            $periode->date_debut,
                            $periode->date_fin,
                        ]);
                    });
                })
                ->orderByDesc('created_at')
                ->get()
                ->map(fn (InstanceParcours $instance) => $this->formatRow($instance))
                ->values();
        }

        $retourAjournement = (clone $query)
            ->where('corbeille_actuelle', CorbeilleEnum::CA_RETOUR_AJOURNEMENT->value)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (InstanceParcours $instance) => $this->formatRow($instance))
            ->values();

        return Inertia::render('ChefAgence/ValidationDemarrage/Index', [
            'demarrage' => $demarrage,
            'demarrageOmis' => $demarrageOmis,
            'retourAjournement' => $retourAjournement,
            'agences' => Agence::query()->orderBy('nom')->pluck('nom', 'id'),
            'entreprises' => Entreprise::query()->orderBy('raison_sociale')->pluck('raison_sociale', 'id'),
            'typesfinancements' => SourceFinancement::query()->orderBy('nom')->pluck('nom', 'id'),
            'typestages' => TypeStage::query()->orderBy('nom')->pluck('nom', 'id'),
            'typestructures' => TypeStructure::query()->orderBy('nom')->pluck('nom', 'id'),
            'periodes' => Periode::query()->orderByDesc('code')->pluck('code', 'id'),
            'filters' => $filters,
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
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:instances_parcours,id'],
            'type' => ['required', 'string', 'in:demarrage,demarrageOmis,retourAjournement'],
        ]);

        $instances = InstanceParcours::query()
            ->with('stage')
            ->whereIn('id', $data['ids'])
            ->get()
            ->keyBy('id');

        foreach ($data['ids'] as $id) {
            $instance = $instances->get($id);

            if (! $instance) {
                continue;
            }

            if ($data['type'] === 'demarrageOmis') {
                $this->workflowService->caValideDemarrageOmis($instance);

                continue;
            }

            $this->validationService->validerDemarrage($instance, $request->user());
        }

        return back()->with('success', count($data['ids']).' dossier(s) validé(s) avec succès.');
    }

    public function ajournerGroup(Request $request)
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:instances_parcours,id'],
            'motif' => ['required', 'string', 'max:1000'],
        ]);

        $instances = InstanceParcours::query()
            ->whereIn('id', $data['ids'])
            ->get();

        foreach ($instances as $instance) {
            $this->workflowService->caAjourneSoumission($instance);
        }

        return back()->with('success', count($data['ids']).' dossier(s) ajourné(s) avec succès.');
    }

    public function genererAddGroup(Request $request)
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:instances_parcours,id'],
            'type' => ['nullable', 'string'],
        ]);

        return back()->with('success', 'La génération d\'ADD a été déclenchée pour '.count($data['ids']).' dossier(s).');
    }

    private function baseQuery(Request $request): Builder
    {
        return InstanceParcours::query()
            ->with([
                'stage.beneficiaire',
                'stage.entreprise.typeStructure',
                'stage.agence',
                'stage.sourceFinancement',
                'stage.typeStage',
                'stage.contrats',
            ])
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
        $stage = $instance->stage;
        $beneficiaire = $stage?->beneficiaire;
        $contrat = $stage?->contrats?->first();

        return [
            'id' => $instance->id,
            'date' => $instance->created_at?->format('d/m/Y') ?? '-',
            'agence' => $stage?->agence?->nom ?? '-',
            'entreprise' => $stage?->entreprise?->raison_sociale ?? '-',
            'source_financement' => $stage?->sourceFinancement?->nom ?? '-',
            'type_stage' => $stage?->typeStage?->nom ?? '-',
            'type_structure' => $stage?->entreprise?->typeStructure?->nom ?? '-',
            'numero_aej' => $beneficiaire?->numero_aej ?? '-',
            'nom_prenoms' => trim(($beneficiaire?->nom ?? '').' '.($beneficiaire?->prenoms ?? '')) ?: '-',
            'date_naissance' => $beneficiaire?->date_naissance ? Carbon::parse($beneficiaire->date_naissance)->format('d/m/Y') : '-',
            'sexe' => $beneficiaire?->sexe ?? '-',
            'contrat_label' => $contrat ? 'Avec Contrat' : 'Sans Contrat',
            'incidence_financiere' => (float) ($contrat?->prime_mensuelle ?? 0) > 0 ? 'Oui' : 'Non',
            'date_debut' => $stage?->date_debut ? Carbon::parse($stage->date_debut)->translatedFormat('d M Y') : '-',
            'date_fin' => $stage?->date_fin_prevue ? Carbon::parse($stage->date_fin_prevue)->translatedFormat('d M Y') : '-',
            'corbeille_actuelle' => $instance->corbeille_actuelle,
        ];
    }

    /**
     * @param  array<int, CorbeilleEnum>  $corbeilles
     */
    private function findInstanceForChefAgence(int $id, array $corbeilles): InstanceParcours
    {
        return InstanceParcours::query()
            ->with('stage')
            ->where('id', $id)
            ->whereIn('corbeille_actuelle', array_map(fn (CorbeilleEnum $corbeille) => $corbeille->value, $corbeilles))
            ->firstOrFail();
    }
}
