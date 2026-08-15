<?php

namespace App\Http\Controllers\Cip;

use App\Domain\Attendance\Services\PointageService;
use App\Enums\CorbeilleEnum;
use App\Http\Controllers\Controller;
use App\Models\Attendance\Pointage;
use App\Models\Workflow\InstanceParcours;
use App\Models\Internship\Stage;
use App\Models\Reference\Periode;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;

class PointageCipController extends Controller
{
    protected $pointageService;

    public function __construct(PointageService $pointageService)
    {
        $this->pointageService = $pointageService;
    }

    public function stagiaireAttentePointage(Request $request)
    {
        $mois = $request->query('mois', Carbon::now()->format('Y-m'));

        // Corbeille 1 : Attente Pointage (Dynamique: Instances EN_STAGE sans pointage sur ce mois)
        // Note: L'idéal est de passer par le service comme fait précédemment, on l'ajuste pour filtrer par EN_STAGE.
        $attente = InstanceParcours::with(['stage.beneficiaire', 'stage.entreprise', 'stage.agence'])
            ->where('corbeille_actuelle', CorbeilleEnum::EN_STAGE)
            ->whereDoesntHave('stage.pointages', function($q) use ($mois) {
                $q->whereHas('periode', function($p) use ($mois) {
                    $p->where('nom', 'like', "%$mois%");
                });
            })
            ->get();

        // 2. Pointage Effectué (SOUMIS ou VALIDE ou CORRIGE_CIP)
        $effectues = Pointage::with(['stage.beneficiaire', 'stage.entreprise', 'stage.agence', 'versionCourante'])
            ->whereIn('statut', ['SOUMIS', 'VALIDE', 'CORRIGE_CIP'])
            ->whereHas('periode', function ($q) use ($mois) {
                $q->where('nom', 'like', "%$mois%");
            })
            ->get();

        // 3. Pointage Ajourné / Chef Agence
        $ajournesCA = Pointage::with(['stage.beneficiaire', 'stage.entreprise', 'stage.agence', 'versionCourante', 'decisions'])
            ->scopeAjourneParCA()
            ->whereHas('periode', function ($q) use ($mois) {
                $q->where('nom', 'like', "%$mois%");
            })
            ->get();

        // 4. Pointage Ajourné / DMG
        $ajournesDMG = Pointage::with(['stage.beneficiaire', 'stage.entreprise', 'stage.agence', 'versionCourante', 'decisions'])
            ->where('statut', 'AJOURNE_DMG')
            ->whereHas('periode', function ($q) use ($mois) {
                $q->where('nom', 'like', "%$mois%");
            })
            ->get();

        // Pass missing months for select dropdown
        $moisManques = Periode::where('actif', true)->pluck('nom', 'id');

        return Inertia::render('Cip/Pointages/Index', [
            'attente' => $attente,
            'effectues' => $effectues,
            'ajournesCA' => $ajournesCA,
            'ajournesDMG' => $ajournesDMG,
            'moisManques' => $moisManques,
            'moisActuel' => $mois
        ]);
    }

    public function soumettre(Request $request, $stageId)
    {
        $request->validate([
            'periode_id' => 'required|exists:periodes,id',
            'jours_presents' => 'required|integer|min:0|max:31',
            'jours_absents' => 'required|integer|min:0|max:31',
        ]);

        $stage = Stage::findOrFail($stageId);
        $periode = Periode::findOrFail($request->periode_id);

        $this->pointageService->soumettreMensuel(
            $stage,
            $periode,
            $request->jours_presents,
            $request->jours_absents,
            $request->user(),
            $request->observation
        );

        return redirect()->back()->with('success', 'Pointage soumis avec succès.');
    }
}
