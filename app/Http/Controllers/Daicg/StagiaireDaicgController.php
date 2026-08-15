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
            'validesCA' => $this->corbeilles->instanceRows(CorbeilleEnum::DMG_ATTENTE_PAIEMENT_DEMARRAGE, 'Validé CA'),
            'validesDESSE' => $this->corbeilles->instanceRows(CorbeilleEnum::DMG_ATTENTE_PAIEMENT_PRESENCE, 'Valide DESSE'),
            'sansContrat' => collect(),
        ]);
    }
}
