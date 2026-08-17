<?php

namespace App\Http\Controllers\Cip;

use App\Domain\Workflow\Services\CorbeilleParcoursQueryService;
use App\Enums\CorbeilleEnum;
use App\Http\Controllers\Controller;
use App\Models\Company\Entreprise;
use App\Models\Reference\Agence;
use App\Models\Reference\SituationStage;
use App\Models\Reference\SourceFinancement;
use App\Models\Reference\TypeStage;
use App\Models\Reference\TypeStructure;
use App\Models\Workflow\EtapeParcours;
use App\Models\Workflow\InstanceParcours;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;

class MesStagiairesCipController extends Controller
{
    /**
     * Corbeille : Mes Stagiaires
     */
    public function index(Request $request)
    {
        $filters = $request->only([
            'agence_id',
            'entreprise_id',
            'typesfinancement_id',
            'typestage_id',
            'type_structure_id',
            'date_debut',
            'date_fin',
            'page',
        ]);

        if ($request->wantsJson()) {
            $cacheKey = 'mes_stagiaires_index_'.Auth::id().'_'.md5(json_encode($filters));

            $instances = Cache::remember($cacheKey, 1800, function () use ($request) {
                $query = InstanceParcours::with([
                    'stage.beneficiaire',
                    'stage.entreprise.typeStructure',
                    'stage.agence',
                    'stage.sourceFinancement',
                    'stage.typeStage',
                    'stage.contrats',
                    'stage.pointages.periode',
                    'stage.pointages.versionCourante',
                ]);

                // Apply filters
                if ($request->filled('agence_id')) {
                    $query->whereHas('stage', function ($q) use ($request) {
                        $q->where('agence_id', $request->agence_id);
                    });
                }
                if ($request->filled('entreprise_id')) {
                    $query->whereHas('stage', function ($q) use ($request) {
                        $q->where('entreprise_id', $request->entreprise_id);
                    });
                }
                if ($request->filled('typesfinancement_id')) {
                    $query->whereHas('stage', function ($q) use ($request) {
                        $q->where('source_financement_id', $request->typesfinancement_id);
                    });
                }
                if ($request->filled('typestage_id')) {
                    $query->whereHas('stage', function ($q) use ($request) {
                        $q->where('type_stage_id', $request->typestage_id);
                    });
                }
                if ($request->filled('type_structure_id')) {
                    $query->whereHas('stage.entreprise', function ($q) use ($request) {
                        $q->where('type_structure_id', $request->type_structure_id);
                    });
                }
                if ($request->filled('date_debut')) {
                    $query->whereHas('stage', function ($q) use ($request) {
                        $q->where('date_debut', '>=', $request->date_debut);
                    });
                }
                if ($request->filled('date_fin')) {
                    $query->whereHas('stage', function ($q) use ($request) {
                        $q->where('date_fin_prevue', '<=', $request->date_fin);
                    });
                }

                return $query->orderBy('created_at', 'desc')
                    ->paginate(15)
                    ->withQueryString();
            });

            return response()->json(['instances' => $instances, 'filters' => $filters]);
        }

        // Shell Inertia — données de filtres uniquement
        $agences = Cache::remember('filter_agences_mes_stagiaires', 1800, fn () => Agence::orderBy('nom')->pluck('nom', 'id'));
        $entreprises = Cache::remember('filter_entreprises_mes_stagiaires', 1800, fn () => Entreprise::orderBy('raison_sociale')->pluck('raison_sociale', 'id'));
        $typesfinancements = Cache::remember('filter_typesfinancements_mes_stagiaires', 1800, fn () => SourceFinancement::orderBy('nom')->pluck('nom', 'id'));
        $typestages = Cache::remember('filter_typestages_mes_stagiaires', 1800, fn () => TypeStage::orderBy('nom')->pluck('nom', 'id'));
        $typestructures = Cache::remember('filter_typestructures_mes_stagiaires', 1800, fn () => TypeStructure::orderBy('nom')->pluck('nom', 'id'));
        $etapes = Cache::remember('filter_etapes_mes_stagiaires', 1800, fn () => EtapeParcours::orderBy('nom')->pluck('nom', 'id'));
        $situationstages = Cache::remember('filter_situationstages_mes_stagiaires', 1800, fn () => SituationStage::pluck('nom', 'id'));

        return Inertia::render('Cip/MesStagiaires/Index', [
            'agences' => $agences,
            'entreprises' => $entreprises,
            'typesfinancements' => $typesfinancements,
            'typestages' => $typestages,
            'typestructures' => $typestructures,
            'etapes' => $etapes,
            'situationstages' => $situationstages,
            'filters' => $filters,
        ]);
    }

    /**
     * Corbeille : Pointage Ajourné par DMG
     */
    public function pointageAjourneDmg(Request $request)
    {
        $instances = InstanceParcours::with(['stage.beneficiaire', 'stage.entreprise', 'stage.agence'])
            ->where('corbeille_actuelle', CorbeilleEnum::CIP_POINTAGE_AJOURNE_DMG)
            ->get();

        return Inertia::render('Cip/Pointages/AjourneDmg', [
            'instances' => $instances,
        ]);
    }

    /**
     * Corbeille : Suivi des cas spécifiques
     */
    public function suivi(Request $request, CorbeilleParcoursQueryService $corbeilles)
    {
        return Inertia::render('Cip/Suivi/Index', [
            'differesAC' => $corbeilles->instanceRows(CorbeilleEnum::CIP_DIFFERE_AC, 'Différé AC'),
            'doublonsDESSE' => $corbeilles->instanceRows(CorbeilleEnum::CIP_AJOURNE_DESSE, 'Doublon DESSE'),
            'renouvellements' => $corbeilles->instanceRows(CorbeilleEnum::CIP_FIN_CONTRAT, 'Renouvellement'),
            'suspensionsAbandons' => $corbeilles->instanceRows(CorbeilleEnum::CIP_AJOURNE_AAF, 'Suspension ou abandon'),
        ]);
    }
}
