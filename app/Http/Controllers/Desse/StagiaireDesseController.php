<?php

namespace App\Http\Controllers\Desse;

use App\Domain\Workflow\Services\CorbeilleParcoursQueryService;
use App\Enums\CorbeilleEnum;
use App\Http\Controllers\Controller;
use Inertia\Inertia;

class StagiaireDesseController extends Controller
{
    public function __construct(private CorbeilleParcoursQueryService $corbeilles) {}

    public function index()
    {
        $attenteValidation = $this->corbeilles->instanceRows(
            CorbeilleEnum::DMG_ATTENTE_PAIEMENT_DEMARRAGE,
            'Attente validation DESSE'
        );

        return Inertia::render('Desse/Stagiaires/Index', [
            'attenteValidation' => $attenteValidation,
            'doublons' => collect(),
            'statistiques' => [
                'attente_validation' => $attenteValidation->count(),
                'doublons' => 0,
            ],
        ]);
    }
}
