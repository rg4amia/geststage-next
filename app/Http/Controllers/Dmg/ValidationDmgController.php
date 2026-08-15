<?php

namespace App\Http\Controllers\Dmg;

use App\Domain\Workflow\Services\CorbeilleParcoursQueryService;
use App\Enums\CorbeilleEnum;
use App\Http\Controllers\Controller;
use Inertia\Inertia;

class ValidationDmgController extends Controller
{
    public function __construct(private CorbeilleParcoursQueryService $corbeilles) {}

    public function index()
    {
        return Inertia::render('Dmg/Validation/Index', [
            'attenteVerification' => $this->corbeilles->instanceRows(
                CorbeilleEnum::DMG_ATTENTE_PAIEMENT_DEMARRAGE,
                'Attente vérification'
            ),
            'valides' => $this->corbeilles->instanceRows(CorbeilleEnum::DMG_ELABORATION_OP, 'Vérifié'),
        ]);
    }
}
