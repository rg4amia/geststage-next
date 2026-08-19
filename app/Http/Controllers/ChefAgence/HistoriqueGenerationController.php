<?php

declare(strict_types=1);

namespace App\Http\Controllers\ChefAgence;

use App\Http\Controllers\Controller;
use App\Models\HistoriqueGeneration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HistoriqueGenerationController extends Controller
{
    /**
     * Affiche la page de l'historique
     */
    public function page(): Response
    {
        return Inertia::render('ChefAgence/HistoriqueGeneration/Index');
    }

    /**
     * Liste l'historique des générations de documents
     */
    public function index(Request $request): JsonResponse
    {
        $query = HistoriqueGeneration::with(['stage.beneficiaire', 'user'])
            ->orderBy('created_at', 'desc');

        // Filtres optionnels
        if ($request->has('type_document')) {
            $query->where('type_document', $request->type_document);
        }

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->has('date_debut') && $request->has('date_fin')) {
            $query->whereBetween('created_at', [
                $request->date_debut,
                $request->date_fin,
            ]);
        }

        $historiques = $query->paginate(20);

        return response()->json($historiques);
    }

    /**
     * Détails d'une génération spécifique
     */
    public function show(string $uuid): JsonResponse
    {
        $historique = HistoriqueGeneration::where('uuid_public', $uuid)
            ->with(['stage.beneficiaire', 'stage.entreprise', 'instanceParcours', 'user'])
            ->firstOrFail();

        return response()->json($historique);
    }

    /**
     * Statistiques de génération
     */
    public function statistiques(Request $request): JsonResponse
    {
        $stats = [
            'total_generations' => HistoriqueGeneration::count(),
            'par_type' => HistoriqueGeneration::selectRaw('type_document, COUNT(*) as total, SUM(nombre_stagiaires) as total_stagiaires')
                ->groupBy('type_document')
                ->get(),
            'par_utilisateur' => HistoriqueGeneration::selectRaw('user_id, COUNT(*) as total')
                ->with('user:id,name')
                ->groupBy('user_id')
                ->orderBy('total', 'desc')
                ->limit(10)
                ->get(),
            'dernieres_24h' => HistoriqueGeneration::where('created_at', '>=', now()->subDay())->count(),
            'derniere_semaine' => HistoriqueGeneration::where('created_at', '>=', now()->subWeek())->count(),
        ];

        // Par source de financement
        if ($request->boolean('include_financements')) {
            $stats['par_financement'] = HistoriqueGeneration::selectRaw('source_financement, COUNT(*) as total')
                ->whereNotNull('source_financement')
                ->groupBy('source_financement')
                ->get();
        }

        return response()->json($stats);
    }

    /**
     * Recherche dans l'historique
     */
    public function search(Request $request): JsonResponse
    {
        $query = HistoriqueGeneration::with(['stage.beneficiaire', 'user']);

        // Recherche par nom de fichier
        if ($request->has('nom_fichier')) {
            $query->where('nom_fichier', 'like', '%'.$request->nom_fichier.'%');
        }

        // Recherche par matricule stagiaire
        if ($request->has('matricule')) {
            $query->whereHas('stage.beneficiaire', function ($q) use ($request) {
                $q->where('numero_aej', 'like', '%'.$request->matricule.'%');
            });
        }

        $resultats = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json($resultats);
    }
}
