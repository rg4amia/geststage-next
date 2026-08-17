<?php

namespace App\Http\Controllers\Registration;

use App\Domain\Registration\Services\InscriptionStagiaireService;
use App\Http\Controllers\Controller;
use App\Models\Company\OffreEmploi;
use App\Models\Reference\Agence;
use App\Models\Reference\Commune;
use App\Models\Reference\Diplome;
use App\Models\Reference\Handicap;
use App\Models\Reference\LienParente;
use App\Models\Reference\NiveauEtude;
use App\Models\Reference\OrigineStagiaire;
use App\Models\Reference\SourceFinancement;
use App\Models\Reference\TypeEnseignement;
use App\Models\Reference\TypeHandicap;
use App\Models\Reference\TypePaiement;
use App\Models\Reference\TypeStage;
use App\Models\Workflow\InstanceParcours;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class InscriptionController extends Controller
{
    public function __construct(
        private readonly InscriptionStagiaireService $inscriptionService
    ) {}

    public function index()
    {
        $user = Auth::user();

        $instances = InstanceParcours::with(['stage.beneficiaire', 'stage.entreprise', 'taches_ouvertes'])
            ->whereHas('taches_ouvertes', function ($q) {
                // Seulement les tâches de la corbeille CIP, assignables à ce rôle
                // Simplification : instances dont l'utilisateur est concerné
            })
            ->get();

        return Inertia::render('Inscriptions/Index', [
            'instances' => $instances,
        ]);
    }

    public function create()
    {
        // Données pour remplir les selects du formulaire
        $offres = OffreEmploi::with(['entreprise', 'agence', 'typeStage', 'sourceFinancement'])
            ->where('statut', 'PUBLIEE')
            ->get();

        $agences = Agence::where('actif', true)->get();
        $communes = Commune::where('actif', true)->get();
        $typesStage = TypeStage::where('actif', true)->get();
        $originesStagiaire = OrigineStagiaire::where('actif', true)->get();
        $liensParente = LienParente::where('actif', true)->get();
        $niveauxEtude = NiveauEtude::where('actif', true)->get();
        $diplomes = Diplome::where('actif', true)->get();
        $typesEnseignement = TypeEnseignement::where('actif', true)->get();
        $handicaps = Handicap::where('actif', true)->get();
        $typesHandicap = TypeHandicap::where('actif', true)->get();
        $typesPaiement = TypePaiement::where('actif', true)->get();
        $sourcesFinancement = SourceFinancement::where('actif', true)->get();

        return Inertia::render('Inscriptions/Create', [
            'offres' => $offres,
            'agences' => $agences,
            'communes' => $communes,
            'typesStage' => $typesStage,
            'originesStagiaire' => $originesStagiaire,
            'liensParente' => $liensParente,
            'niveauxEtude' => $niveauxEtude,
            'diplomes' => $diplomes,
            'typesEnseignement' => $typesEnseignement,
            'handicaps' => $handicaps,
            'typesHandicap' => $typesHandicap,
            'typesPaiement' => $typesPaiement,
            'sourcesFinancement' => $sourcesFinancement,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'beneficiaire' => 'required|array',
            'stage' => 'required|array',
            'contrat' => 'required|array',
            'documents' => 'nullable|array',
            'documents.*' => 'nullable|file|max:10240', // 10MB limit per file
        ]);

        $instance = $this->inscriptionService->inscrire(
            $validated['beneficiaire'],
            $validated['stage'],
            $validated['contrat'],
            $request->file('documents') ?? [],
            Auth::user()
        );

        return redirect()->route('inscriptions.index')->with('success', 'Stagiaire inscrit et dossier initié avec succès.');
    }

    public function show($id)
    {
        $instance = InstanceParcours::with([
            'stage.beneficiaire.communeResidence',
            'stage.beneficiaire.typePaiement',
            'stage.beneficiaire.niveauEtude',
            'stage.beneficiaire.handicap',
            'stage.beneficiaire.typeHandicap',
            'stage.typeStage',
            'stage.sourceFinancement',
            'stage.programme',
            'stage.beneficiaire.diplome',
            'stage.entreprise',
            'stage.agence',
            'stage.contrats',
            'stage.documents.versions',
            'stage.documents.typeDocument',
            'etapeCourante',
            'evenements.acteur',
            'evenements.etapeSource',
            'evenements.etapeCible',
            'taches_ouvertes',
        ])->findOrFail($id);

        return Inertia::render('Inscriptions/Show', [
            'instance' => $instance,
        ]);
    }
}
