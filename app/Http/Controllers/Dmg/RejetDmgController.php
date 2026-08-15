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
        return Inertia::render('Dmg/Rejets/Index', [
            'ajournesCB' => $this->corbeilles->instanceRows(CorbeilleEnum::CB_ETAT_PAIEMENT_AJOURNE, 'Ajourné par CB'),
            'rejetesAC' => $this->corbeilles->instanceRows(CorbeilleEnum::DMG_OP_REJETE_AC, 'Rejeté par AC'),
            'differesAC' => $this->corbeilles->instanceRows(CorbeilleEnum::DMG_OP_DIFFERE_AC, 'Différé par AC'),
        ]);
    }
}
