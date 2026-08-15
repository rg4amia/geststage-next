<?php

namespace App\Http\Controllers\ChefAgence;

use App\Domain\Attendance\Services\PointageService;
use App\Domain\Workflow\Services\WorkflowTransitionService;
use App\Http\Controllers\Controller;
use App\Models\Attendance\Pointage;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;

class PointageChefAgenceController extends Controller
{
    protected $pointageService;
    protected $workflowService;

    public function __construct(PointageService $pointageService, WorkflowTransitionService $workflowService)
    {
        $this->pointageService = $pointageService;
        $this->workflowService = $workflowService;
    }

    public function pointageAttenteValidationByChefAgence(Request $request)
    {
        $mois = $request->query('mois', Carbon::now()->format('Y-m'));

        // Corbeille dynamique: Pointages SOUMIS
        $pointagesAValider = Pointage::with([
            'stage.beneficiaire', 
            'stage.entreprise', 
            'stage.agence', 
            'periode', 
            'versionCourante.saisiPar'
        ])
        ->where('statut', 'SOUMIS')
        ->whereHas('periode', function ($q) use ($mois) {
            $q->where('nom', 'like', "%$mois%");
        })
        ->get();

        // Corbeille dynamique: Pointage corrigés après ajournement DMG
        $pointagesAjournesAdp = Pointage::with([
            'stage.beneficiaire', 
            'stage.entreprise', 
            'stage.agence', 
            'periode', 
            'versionCourante.saisiPar'
        ])
        ->where('statut', 'CORRIGE_CIP')
        ->whereHas('periode', function ($q) use ($mois) {
            $q->where('nom', 'like', "%$mois%");
        })
        ->get();

        return Inertia::render('ChefAgence/Pointages/Index', [
            'pointagesAValider' => $pointagesAValider,
            'pointagesAjournesAdp' => $pointagesAjournesAdp,
            'moisActuel' => $mois
        ]);
    }

    public function valider(Request $request, $id)
    {
        $pointage = Pointage::findOrFail($id);
        $this->pointageService->validerMensuel($pointage, $request->user());
        
        // Notification au Workflow pour passage à la DMG (Presence)
        $this->workflowService->caValidePointage($pointage->stage->instanceParcours);

        return redirect()->back()->with('success', 'Pointage validé. Transmis à la DMG.');
    }

    public function ajourner(Request $request, $id)
    {
        $request->validate([
            'motif' => 'required|string|min:5'
        ]);

        $pointage = Pointage::findOrFail($id);
        
        $pointage->update(['statut' => 'AJOURNE_CA']);
        
        \App\Models\Attendance\DecisionPointage::create([
            'pointage_id' => $pointage->id,
            'version_pointage_id' => $pointage->versionCourante->id,
            'auteur_id' => $request->user()->id,
            'decision' => 'AJOURNE',
            'motif' => $request->motif
        ]);

        return redirect()->back()->with('success', 'Pointage ajourné vers le CIP.');
    }

    public function validerAjournementAdp(Request $request, $id)
    {
        $pointage = Pointage::findOrFail($id);
        $this->workflowService->caValideAjournementAdp($pointage);
        return redirect()->back()->with('success', 'Correction acceptée, le pointage a été resoumis.');
    }

    public function rejeterAjournementAdp(Request $request, $id)
    {
        $pointage = Pointage::findOrFail($id);
        $this->workflowService->caRejetteAjournementAdp($pointage);
        return redirect()->back()->with('success', 'Dossier rejeté, renvoyé dans la corbeille "Mes Stagiaires" du CIP pour correction du contrat.');
    }
}
