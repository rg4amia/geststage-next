<?php

namespace App\Http\Controllers\Daicg;

use App\Domain\Workflow\Services\CorbeilleParcoursQueryService;
use App\Enums\CorbeilleEnum;
use App\Http\Controllers\Controller;
use Inertia\Inertia;

class StagiaireDaicgController extends Controller
{
    public function __construct(private CorbeilleParcoursQueryService $corbeilles) {}

    public function index()
    {
        return Inertia::render('Daicg/Stagiaires/Index', [
            'validesCA' => $this->corbeilles->instanceRows(CorbeilleEnum::DAICG_VALIDES_CA, 'Validé CA'),
            'validesDESSE' => $this->corbeilles->instanceRows(CorbeilleEnum::DAICG_VALIDES_DESSE, 'Validé DESSE'),
            'sansContrat' => $this->corbeilles->instanceRows(CorbeilleEnum::DAICG_SANS_CONTRAT, 'Sans Contrat'),
        ]);
    }
}
