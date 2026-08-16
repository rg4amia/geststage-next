<?php

namespace App\Http\Controllers\Dmg;

use App\Domain\Workflow\Services\CorbeilleParcoursQueryService;
use App\Enums\CorbeilleEnum;
use App\Http\Controllers\Controller;
use Inertia\Inertia;

class RejetDmgController extends Controller
{
    public function __construct(private CorbeilleParcoursQueryService $corbeilles) {}

    public function index()
    {
        $dossiersAjournesCB = \App\Models\Payment\DossierPaiement::with(['agence', 'sourceFinancement', 'periode'])
            ->withCount('paiements')
            ->where('statut', 'AJOURNE_CB')
            ->get();

        return Inertia::render('Dmg/Rejets/Index', [
            'ajournesCB' => $this->corbeilles->dossierRows($dossiersAjournesCB, 'Ajourné par CB'),
            'rejetesAC' => $this->corbeilles->paiementRowsFor(CorbeilleEnum::DMG_OP_REJETE_AC, 'Rejeté par AC'),
            'differesAC' => $this->corbeilles->paiementRowsFor(CorbeilleEnum::DMG_OP_DIFFERE_AC, 'Différé par AC'),
        ]);
    }
}
