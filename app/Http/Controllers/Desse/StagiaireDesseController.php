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
            CorbeilleEnum::DESSE_ATTENTE_VERIFICATION_DMG,
            'Attente de vérification'
        );
        $doublons = $this->corbeilles->instanceRows(
            CorbeilleEnum::DESSE_DOUBLONS_A_TRAITER,
            'Doublon suspecté'
        );

        return Inertia::render('Desse/Stagiaires/Index', [
            'attenteValidation' => $attenteValidation,
            'doublons' => $doublons,
            'statistiques' => [
                'attente_validation' => $attenteValidation->count(),
                'doublons' => $doublons->count(),
                'total' => $attenteValidation->count() + $doublons->count(),
            ],
        ]);
    }
}
