<?php

namespace App\Http\Controllers\ChefAgence;

use App\Http\Controllers\Controller;
use App\Models\Workflow\InstanceParcours;
use Illuminate\Http\Request;
use Inertia\Inertia;

class IndexChefAgenceController extends Controller
{
    public function listeStagiaireAttenteValidation(Request $request)
    {
        // En Eloquent, on filtre par l'étape courante "Validation CA" ou code_corbeille associé
        $instances = InstanceParcours::with([
            'stage.beneficiaire', 
            'stage.entreprise', 
            'stage.agence', 
            'stage.contrats',
            'etapeCourante'
        ])->whereHas('etapeCourante', function($q) {
            $q->where('nom', 'Validation CA');
        })->get();

        return Inertia::render('ChefAgence/ValidationDemarrage/Index', [
            'instances' => $instances
        ]);
    }
}
