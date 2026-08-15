<?php

namespace App\Http\Controllers\Registration;

use App\Domain\Registration\Services\InscriptionStagiaireService;
use App\Http\Controllers\Controller;
use App\Models\Company\OffreEmploi;
use App\Models\Workflow\InstanceParcours;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;

class InscriptionController extends Controller
{
    public function __construct(
        private readonly InscriptionStagiaireService $inscriptionService
    ) {}

    public function index()
    {
        $user = Auth::user();
        
        $instances = InstanceParcours::with(['stage.beneficiaire', 'stage.entreprise', 'taches_ouvertes'])
            ->whereHas('taches_ouvertes', function ($q) use ($user) {
                // Seulement les tâches de la corbeille CIP, assignables à ce rôle
                // Simplification : instances dont l'utilisateur est concerné
            })
            ->get();

        return Inertia::render('Inscriptions/Index', [
            'instances' => $instances
        ]);
    }

    public function create()
    {
        // Données pour remplir les selects du formulaire
        $offres = OffreEmploi::with(['entreprise', 'agence', 'typeStage', 'sourceFinancement'])
            ->where('statut', 'PUBLIEE')
            ->get();

        return Inertia::render('Inscriptions/Create', [
            'offres' => $offres,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'beneficiaire' => 'required|array',
            'stage' => 'required|array',
            'contrat' => 'required|array',
        ]);

        $instance = $this->inscriptionService->inscrire(
            $validated['beneficiaire'],
            $validated['stage'],
            $validated['contrat'],
            Auth::user()
        );

        return redirect()->route('inscriptions.index')->with('success', 'Stagiaire inscrit et dossier initié avec succès.');
    }
}
