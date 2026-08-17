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
use Illuminate\Support\Facades\Log;
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
            'etape_id',
            'situationstage_id',
            'page',
        ]);


        Log::info("wantsJson hit!", ["wantsJson" => $request->wantsJson(), "accept" => $request->header("Accept")]);
        if ($request->wantsJson()) {
            $cacheKey = 'mes_stagiaires_index_' . Auth::id() . '_' . md5(json_encode($filters));

            $instances = Cache::remember($cacheKey, 1800, function () use ($request) {
                $query = InstanceParcours::with([
                    'stage.beneficiaire.typePaiement',
                    'stage.entreprise.typeStructure',
                    'stage.agence',
                    'stage.sourceFinancement',
                    'stage.typeStage',
                    'stage.contrats',
                    'stage.pointages.periode',
                    'stage.pointages.versionCourante'
                ]);

                $user = Auth::user();

                // Equivalent to legacy ->mine() scope for CIPs
                if ($user && $user->agence_id) {
                    $query->whereHas('stage', function ($q) use ($user) {
                        $q->where('agence_id', $user->agence_id);
                    });
                }

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
                if ($request->filled('etape_id')) {
                    $query->where('etape_courante_id', $request->etape_id);
                }
                if ($request->filled('situationstage_id')) {
                    $query->whereHas('stage', function ($q) use ($request) {
                        $q->where('situation_stage', $request->situationstage_id);
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

                return $query->orderBy('created_at', 'desc')->paginate(50)->withQueryString()->toArray();
            });

            return response()->json(['instances' => $instances, 'filters' => $filters]);
        }

        $user = Auth::user();

        // Shell Inertia — données de filtres uniquement
        $agences = Cache::remember('filter_agences_mes_stagiaires', 1800, fn () => \App\Models\Reference\Agence::orderBy('nom')->pluck('nom', 'id')->toArray());

        // Filter entreprises for the logged-in user's agency like in legacy
        $entreprises = Cache::remember('filter_entreprises_mes_stagiaires_' . ($user->id ?? '0'), 1800, fn () =>
            \App\Models\Company\Entreprise::when($user && $user->agence_id, function ($q) use ($user) {
                $q->where('agence_id', $user->agence_id);
            })->orderBy('raison_sociale')->pluck('raison_sociale', 'id')->toArray()
        );

        $typesfinancements = Cache::remember('filter_typesfinancements_mes_stagiaires', 1800, fn () => \App\Models\Reference\SourceFinancement::orderBy('nom')->pluck('nom', 'id')->toArray());
        $typestages = Cache::remember('filter_typestages_mes_stagiaires', 1800, fn () => \App\Models\Reference\TypeStage::orderBy('nom')->pluck('nom', 'id')->toArray());
        $typestructures = Cache::remember('filter_typestructures_mes_stagiaires', 1800, fn () => \App\Models\Reference\TypeStructure::orderBy('nom')->pluck('nom', 'id')->toArray());
        $etapes = Cache::remember('filter_etapes_mes_stagiaires', 1800, fn () => \App\Models\Workflow\EtapeParcours::orderBy('nom')->pluck('nom', 'id')->toArray());

        // stages.situation_stage stocke le code (dénormalisé) et non l'id de la table de référence,
        // donc on indexe les options du filtre par code pour que la comparaison reste cohérente.
        $situationstages = Cache::remember('filter_situationstages_mes_stagiaires', 1800, fn () => \App\Models\Reference\SituationStage::orderBy('nom')->pluck('nom', 'code')->toArray());

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
