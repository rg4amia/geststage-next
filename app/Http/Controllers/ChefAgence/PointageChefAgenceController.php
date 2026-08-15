<?php

namespace App\Http\Controllers\ChefAgence;

use App\Domain\Attendance\Services\PointageService;
use App\Http\Controllers\Controller;
use App\Models\Attendance\Pointage;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;

class PointageChefAgenceController extends Controller
{
    protected $pointageService;

    public function __construct(PointageService $pointageService)
    {
        $this->pointageService = $pointageService;
    }

    public function pointageAttenteValidationByChefAgence(Request $request)
    {
        $mois = $request->query('mois', Carbon::now()->format('Y-m'));

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

        return Inertia::render('ChefAgence/Pointages/Index', [
            'pointagesAValider' => $pointagesAValider,
            'moisActuel' => $mois
        ]);
    }

    public function valider(Request $request, $id)
    {
        $pointage = Pointage::findOrFail($id);
        $this->pointageService->validerMensuel($pointage, $request->user());

        return redirect()->back()->with('success', 'Pointage validé. Droit au paiement généré.');
    }

    public function ajourner(Request $request, $id)
    {
        $request->validate([
            'motif' => 'required|string|min:5'
        ]);

        $pointage = Pointage::findOrFail($id);
        
        // Simuler un rejet CA (dans PointageService logiquement, simplifié ici)
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
}
