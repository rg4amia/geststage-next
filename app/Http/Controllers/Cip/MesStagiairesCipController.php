<?php

namespace App\Http\Controllers\Cip;

use App\Enums\CorbeilleEnum;
use App\Http\Controllers\Controller;
use App\Models\Workflow\InstanceParcours;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MesStagiairesCipController extends Controller
{
    /**
     * Corbeille : Mes Stagiaires
     */
    public function index(Request $request)
    {
        $agences = \App\Models\Reference\Agence::orderBy('nom')->pluck('nom', 'id');
        $entreprises = \App\Models\Company\Entreprise::orderBy('raison_sociale')->pluck('raison_sociale', 'id');
        $typesfinancements = \App\Models\Reference\SourceFinancement::orderBy('nom')->pluck('nom', 'id');
        $typestages = \App\Models\Reference\TypeStage::orderBy('nom')->pluck('nom', 'id');
        $typestructures = \App\Models\Reference\TypeStructure::orderBy('nom')->pluck('nom', 'id');
        $etapes = \App\Models\Workflow\EtapeParcours::orderBy('nom')->pluck('nom', 'id');
        $situationstages = \App\Models\Reference\SituationStage::pluck('nom', 'id');

        $query = InstanceParcours::with([
            'stage.beneficiaire', 
            'stage.entreprise.typeStructure', 
            'stage.agence',
            'stage.sourceFinancement',
            'stage.typeStage',
            'stage.contrats'
        ])->where('corbeille_actuelle', CorbeilleEnum::CIP_MES_STAGIAIRES);

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
        // $request->filled('etape_id') and situationstage_id can be added if available on instance

        $instances = $query->get();

        return Inertia::render('Cip/MesStagiaires/Index', [
            'instances' => $instances,
            'agences' => $agences,
            'entreprises' => $entreprises,
            'typesfinancements' => $typesfinancements,
            'typestages' => $typestages,
            'typestructures' => $typestructures,
            'etapes' => $etapes,
            'situationstages' => $situationstages,
            'filters' => $request->all(),
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
            'instances' => $instances
        ]);
    }

    /**
     * Corbeille : Suivi des cas spécifiques
     */
    public function suivi(Request $request, \App\Domain\Workflow\Services\CorbeilleParcoursQueryService $corbeilles)
    {
        return Inertia::render('Cip/Suivi/Index', [
            'differesAC' => $corbeilles->instanceRows(CorbeilleEnum::CIP_DIFFERE_AC, 'Différé AC'),
            'doublonsDESSE' => $corbeilles->instanceRows(CorbeilleEnum::CIP_AJOURNE_DESSE, 'Doublon DESSE'),
            'renouvellements' => $corbeilles->instanceRows(CorbeilleEnum::CIP_FIN_CONTRAT, 'Renouvellement'),
            'suspensionsAbandons' => $corbeilles->instanceRows(CorbeilleEnum::CIP_AJOURNE_AAF, 'Suspension ou abandon'),
        ]);
    }
}
