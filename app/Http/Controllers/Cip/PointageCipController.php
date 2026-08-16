<?php

namespace App\Http\Controllers\Cip;

use App\Domain\Attendance\Services\PointageService;
use App\Domain\Workflow\Services\WorkflowTransitionService;
use App\Enums\CorbeilleEnum;
use App\Http\Controllers\Controller;
use App\Models\Attendance\DecisionPointage;
use App\Models\Attendance\Pointage;
use App\Models\Workflow\InstanceParcours;
use App\Models\Internship\Stage;
use App\Models\Reference\Periode;
use App\Models\Reference\SourceFinancement;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;

class PointageCipController extends Controller
{
    protected $pointageService;
    protected $workflowService;
    private const PEJEDEC_SOURCE_FINANCEMENT_ID = 3;

    public function __construct(
        PointageService $pointageService,
        WorkflowTransitionService $workflowService
    ) {
        $this->pointageService = $pointageService;
        $this->workflowService = $workflowService;
    }

    public function stagiaireAttentePointage(Request $request)
    {
        return $this->renderPointages($request);
    }

    public function stagiaireAttentePointagePejedec(Request $request)
    {
        return $this->renderPointages($request, self::PEJEDEC_SOURCE_FINANCEMENT_ID, true);
    }

    private function renderPointages(Request $request, ?int $sourceFinancementId = null, bool $pejedec = false)
    {
        $mois = $request->query('mois', Carbon::now()->format('Y-m'));
        $periode = Periode::where('code', $mois)->first();

        $attenteQuery = InstanceParcours::with(['stage.beneficiaire', 'stage.entreprise', 'stage.agence', 'stage.sourceFinancement', 'stage.pointages'])
            ->where('corbeille_actuelle', CorbeilleEnum::EN_STAGE)
            ->whereDoesntHave('stage.pointages', function($q) use ($mois) {
                $q->whereHas('periode', function($p) use ($mois) {
                    $p->where('code', 'like', "%$mois%");
                });
            })
            ->when($sourceFinancementId !== null, function ($query) use ($sourceFinancementId) {
                $query->whereHas('stage', function ($stageQuery) use ($sourceFinancementId) {
                    $stageQuery->where('source_financement_id', $sourceFinancementId);
                });
            });

        $attente = $attenteQuery->get();

        $effectues = Pointage::with(['stage.beneficiaire', 'stage.entreprise', 'stage.agence', 'stage.sourceFinancement', 'versionCourante'])
            ->whereIn('statut', ['SOUMIS', 'VALIDE', 'CORRIGE_CIP'])
            ->whereHas('periode', function ($q) use ($mois) {
                $q->where('code', 'like', "%$mois%");
            })
            ->when($sourceFinancementId !== null, function ($query) use ($sourceFinancementId) {
                $query->whereHas('stage', function ($stageQuery) use ($sourceFinancementId) {
                    $stageQuery->where('source_financement_id', $sourceFinancementId);
                });
            })
            ->get();

        $ajournesCA = Pointage::with(['stage.beneficiaire', 'stage.entreprise', 'stage.agence', 'stage.sourceFinancement', 'versionCourante', 'decisions'])
            ->ajourneParCA()
            ->whereHas('periode', function ($q) use ($mois) {
                $q->where('code', 'like', "%$mois%");
            })
            ->when($sourceFinancementId !== null, function ($query) use ($sourceFinancementId) {
                $query->whereHas('stage', function ($stageQuery) use ($sourceFinancementId) {
                    $stageQuery->where('source_financement_id', $sourceFinancementId);
                });
            })
            ->get();

        $ajournesDMG = Pointage::with(['stage.beneficiaire', 'stage.entreprise', 'stage.agence', 'stage.sourceFinancement', 'versionCourante', 'decisions'])
            ->where('statut', 'AJOURNE_DMG')
            ->whereHas('periode', function ($q) use ($mois) {
                $q->where('code', 'like', "%$mois%");
            })
            ->when($sourceFinancementId !== null, function ($query) use ($sourceFinancementId) {
                $query->whereHas('stage', function ($stageQuery) use ($sourceFinancementId) {
                    $stageQuery->where('source_financement_id', $sourceFinancementId);
                });
            })
            ->get();

        $moisManques = Periode::where('actif', true)->pluck('code', 'id');
        $sourceFinancement = $sourceFinancementId !== null
            ? SourceFinancement::find($sourceFinancementId)
            : null;

        return Inertia::render($pejedec ? 'Cip/Pointages/Pejedec' : 'Cip/Pointages/Index', [
            'attente' => $attente,
            'effectues' => $effectues,
            'ajournesCA' => $ajournesCA,
            'ajournesDMG' => $ajournesDMG,
            'moisManques' => $moisManques,
            'moisActuel' => $mois,
            'periode' => $periode,
            'sourceFinancement' => $sourceFinancement ? [
                'id' => $sourceFinancement->id,
                'code' => $sourceFinancement->code,
                'nom' => $sourceFinancement->nom,
            ] : null,
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

    public function corrigerAjournementDmg(Request $request, $id)
    {
        $request->validate([
            'motif' => 'nullable|string|max:500',
        ]);

        $pointage = Pointage::with('versionCourante')->findOrFail($id);

        if ($pointage->statut !== 'AJOURNE_DMG') {
            abort(409, 'Le pointage ne peut pas être corrigé dans cet état.');
        }

        if (! $pointage->versionCourante) {
            abort(422, 'La version courante du pointage est introuvable.');
        }

        $this->workflowService->cipCorrigeAjournementDmg($pointage);

        DecisionPointage::create([
            'pointage_id' => $pointage->id,
            'version_pointage_id' => $pointage->versionCourante->id,
            'auteur_id' => $request->user()->id,
            'decision' => 'CORRIGE_CIP',
            'motif' => $request->input('motif'),
        ]);

        return redirect()->back()->with('success', 'Pointage corrigé et renvoyé au CA.');
    }
}
