<?php

namespace App\Http\Controllers\ChefAgence;

use App\Domain\Workflow\Services\WorkflowTransitionService;
use App\Enums\CorbeilleEnum;
use App\Http\Controllers\Controller;
use App\Models\Workflow\InstanceParcours;
use App\Models\Reference\Agence;
use App\Models\Company\Entreprise;
use App\Models\Reference\SourceFinancement;
use App\Models\Reference\TypeStage;
use App\Models\Reference\TypeStructure;
use App\Models\Reference\Periode;
use Illuminate\Http\Request;
use Inertia\Inertia;

class IndexChefAgenceController extends Controller
{
    protected $workflowService;

    public function __construct(WorkflowTransitionService $workflowService)
    {
        $this->workflowService = $workflowService;
    }

    public function listeStagiaireAttenteValidation(Request $request)
    {
        // Chargement des référentiels pour les filtres
        $agences = Agence::orderBy('nom')->pluck('nom', 'id');
        $entreprises = Entreprise::orderBy('raison_sociale')->pluck('raison_sociale', 'id');
        $typesfinancements = SourceFinancement::orderBy('nom')->pluck('nom', 'id');
        $typestages = TypeStage::orderBy('nom')->pluck('nom', 'id');
        $typestructures = TypeStructure::orderBy('nom')->pluck('nom', 'id');
        $periodes = Periode::orderByDesc('code')->pluck('code', 'id');

        // Requête de base avec les filtres
        $queryBase = InstanceParcours::with([
            'stage.beneficiaire', 
            'stage.entreprise', 
            'stage.agence', 
            'stage.contrats'
        ]);

        if ($request->filled('agence_id')) {
            $queryBase->whereHas('stage', function($q) use ($request) {
                $q->where('agence_id', $request->agence_id);
            });
        }
        if ($request->filled('entreprise_id')) {
            $queryBase->whereHas('stage', function($q) use ($request) {
                $q->where('entreprise_id', $request->entreprise_id);
            });
        }
        if ($request->filled('typesfinancement_id')) {
            $queryBase->whereHas('stage.contrats', function($q) use ($request) {
                $q->where('source_financement_id', $request->typesfinancement_id);
            });
        }
        if ($request->filled('typestage_id')) {
            $queryBase->whereHas('stage', function($q) use ($request) {
                $q->where('type_stage_id', $request->typestage_id);
            });
        }
        if ($request->filled('type_structure_id')) {
            $queryBase->whereHas('stage.entreprise', function($q) use ($request) {
                $q->where('type_structure_id', $request->type_structure_id);
            });
        }
        if ($request->filled('created_begin')) {
            $queryBase->whereDate('created_at', '>=', $request->created_begin);
        }
        if ($request->filled('created_end')) {
            $queryBase->whereDate('created_at', '<=', $request->created_end);
        }

        // 1. Démarrage
        $demarrage = (clone $queryBase)->where('corbeille_actuelle', CorbeilleEnum::CA_ATTENTE_VALIDATION_DEMARRAGE)->get();

        // 2. Démarrage Omis
        $demarrageOmis = clone $queryBase;
        if ($request->filled('periode_id')) {
            // Logique de filtre par période si nécessaire
        }
        $demarrageOmis = $demarrageOmis->where('corbeille_actuelle', CorbeilleEnum::CA_ATTENTE_VALIDATION_OMIS)->get();

        // 3. Retour d'Ajournement
        $retourAjournement = (clone $queryBase)->where('corbeille_actuelle', CorbeilleEnum::CA_RETOUR_AJOURNEMENT)->get();

        return Inertia::render('ChefAgence/ValidationDemarrage/Index', [
            'demarrage' => $demarrage,
            'demarrageOmis' => $demarrageOmis,
            'retourAjournement' => $retourAjournement,
            'agences' => $agences,
            'entreprises' => $entreprises,
            'typesfinancements' => $typesfinancements,
            'typestages' => $typestages,
            'typestructures' => $typestructures,
            'periodes' => $periodes,
            'filters' => $request->all()
        ]);
    }

    public function validerDemarrage(Request $request, $id)
    {
        $instance = InstanceParcours::findOrFail($id);
        $this->workflowService->caValideDemarrage($instance);
        return redirect()->back()->with('success', 'Dossier validé et transmis à la DMG.');
    }

    public function validerDemarrageOmis(Request $request, $id)
    {
        $instance = InstanceParcours::findOrFail($id);
        $this->workflowService->caValideDemarrageOmis($instance);
        return redirect()->back()->with('success', 'Dossier Démarrage Omis validé, transmis au CIP pour pointage.');
    }

    public function validerGroup(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:instances_parcours,id',
            'type' => 'required|in:demarrage,demarrageOmis,retourAjournement'
        ]);

        $instances = InstanceParcours::whereIn('id', $request->ids)->get();
        foreach ($instances as $instance) {
            if ($request->type === 'demarrage') {
                $this->workflowService->caValideDemarrage($instance);
            } elseif ($request->type === 'demarrageOmis') {
                $this->workflowService->caValideDemarrageOmis($instance);
            } else {
                // Retour ajournement validation can go to DMG or CIP pointage depending on logic
                // we assume caValideDemarrage for now or add a new workflow service method later
                $this->workflowService->caValideDemarrage($instance);
            }
        }

        return redirect()->back()->with('success', count($request->ids) . ' dossier(s) validé(s) avec succès.');
    }

    public function ajournerGroup(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:instances_parcours,id',
            'motif' => 'required|string|max:1000'
        ]);

        $instances = InstanceParcours::whereIn('id', $request->ids)->get();
        foreach ($instances as $instance) {
            // This is a placeholder as workflow transition for adjournment may require a custom method
            // example: $this->workflowService->caAjourneDossier($instance, $request->motif);
            // Updating directly for UI integration demonstration:
            $instance->update(['corbeille_actuelle' => CorbeilleEnum::CIP_AJOURNE_CA]);
        }

        return redirect()->back()->with('success', count($request->ids) . ' dossier(s) ajourné(s) avec succès.');
    }

    public function genererAddGroup(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:instances_parcours,id'
        ]);

        // Placeholder for PDF generation
        // Normally you would generate the PDF here and return it as a download
        // e.g., return PDF::loadView('...')->download('attestation_demarrage.pdf');

        return redirect()->back()->with('success', 'La génération des ADD a été simulée avec succès pour ' . count($request->ids) . ' dossier(s).');
    }
}
