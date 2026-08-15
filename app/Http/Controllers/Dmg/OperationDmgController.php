<?php

namespace App\Http\Controllers\Dmg;

use App\Domain\Workflow\Services\CorbeilleParcoursQueryService;
use App\Enums\CorbeilleEnum;
use App\Http\Controllers\Controller;
use Inertia\Inertia;

class OperationDmgController extends Controller
{
    public function __construct(private CorbeilleParcoursQueryService $corbeilles) {}

    public function index()
    {
        return Inertia::render('Dmg/Operations/Index', [
            'elaborationOp' => $this->corbeilles->instanceRows(CorbeilleEnum::DMG_ELABORATION_OP, 'Élaboration OP'),
            'bordereaux' => $this->corbeilles->instanceRows(CorbeilleEnum::DMG_OP_ATTENTE_BORDEREAU, 'Bordereau en attente'),
            'fichierCut' => collect(),
        ]);
    }
}
