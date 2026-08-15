<?php

namespace App\Http\Controllers\Cip;

use App\Enums\CorbeilleEnum;
use App\Http\Controllers\Controller;
use App\Models\Workflow\InstanceParcours;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MesStagiairesCipController extends Controller
{
    /**
     * Corbeille : Mes Stagiaires
     */
    public function index(Request $request)
    {
        $instances = InstanceParcours::with(['stage.beneficiaire', 'stage.entreprise', 'stage.agence'])
            ->where('corbeille_actuelle', CorbeilleEnum::CIP_MES_STAGIAIRES)
            ->get();

        return Inertia::render('Cip/MesStagiaires/Index', [
            'instances' => $instances
        ]);
    }

    /**
     * Corbeille : Pointage Ajourné par DMG
     */
    public function pointageAjourneDmg(Request $request)
    {
        $instances = InstanceParcours::with(['stage.beneficiaire', 'stage.entreprise', 'stage.agence'])
            ->where('corbeille_actuelle', CorbeilleEnum::CIP_POINTAGE_AJOURNE_DMG)
            ->get();

        return Inertia::render('Cip/Pointages/AjourneDmg', [
            'instances' => $instances
        ]);
    }
}
