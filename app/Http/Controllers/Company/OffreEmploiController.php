<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOffreEmploiRequest;
use App\Http\Requests\UpdateOffreEmploiRequest;
use App\Models\Company\Entreprise;
use App\Models\Company\OffreEmploi;
use App\Models\Reference\Agence;
use App\Models\Reference\Programme;
use App\Models\Reference\SourceFinancement;
use App\Models\Reference\TypeStage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;

class OffreEmploiController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', OffreEmploi::class);

        $offres = OffreEmploi::with(['entreprise', 'agence', 'typeStage', 'sourceFinancement'])
            ->when($request->search, function ($query, $search) {
                $query->where('intitule', 'ilike', "%{$search}%")
                    ->orWhere('numero', 'ilike', "%{$search}%");
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Offres/Index', [
            'offres' => $offres,
            'filters' => $request->only(['search']),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $this->authorize('create', OffreEmploi::class);

        return Inertia::render('Offres/Create', [
            'entreprises' => Entreprise::orderBy('raison_sociale')->get(),
            'agences' => Agence::all(),
            'typesStage' => TypeStage::all(),
            'sourcesFinancement' => SourceFinancement::all(),
            'programmes' => Programme::all(),
        ]);
    }

    /** Pre-remplit une offre a partir de sa reference AEJ, comme dans le legacy. */
    public function lookupByReference(string $reference): JsonResponse
    {
        $this->authorize('create', OffreEmploi::class);

        $reference = trim($reference);
        if ($reference === '') {
            return response()->json(['message' => "La reference de l'offre est obligatoire."], 422);
        }

        $url = rtrim((string) config('services.agence_emploi_jeunes.offers_url'), '/');
        $token = config('services.agence_emploi_jeunes.offers_token');
        if ($url === '' || ! is_string($token) || trim($token) === '') {
            return response()->json(['message' => 'Le service de reference AEJ n’est pas configure.'], 503);
        }

        try {
            $response = Http::acceptJson()
                ->timeout(10)
                ->get($url.'/'.rawurlencode($reference).'/'.rawurlencode($token));

            if ($response->failed()) {
                return response()->json(['message' => 'Offre introuvable ou service AEJ indisponible.'], $response->status() ?: 502);
            }

            $data = $response->json('data') ?? $response->json();

            return response()->json(['data' => [
                'reference' => $data['noreference'] ?? $reference,
                'intitule' => $data['intitule'] ?? null,
                'nombre_places' => $data['nombreposte'] ?? null,
                'type_stage' => $data['typestage'] ?? null,
                'entreprise' => $data['nomentreprise'] ?? null,
                'publiee_le' => $data['datepublication'] ?? null,
                'valide_au' => $data['dateexpiration'] ?? null,
            ]]);
        } catch (\Throwable) {
            return response()->json(['message' => 'Erreur lors de la recuperation de l’offre AEJ.'], 502);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreOffreEmploiRequest $request)
    {
        OffreEmploi::create($request->validated());

        return redirect()->route('offres.index')
            ->with('success', 'Offre d\'emploi créée avec succès.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(OffreEmploi $offreEmploi)
    {
        $this->authorize('update', $offreEmploi);

        return Inertia::render('Offres/Edit', [
            'offre' => $offreEmploi,
            'entreprises' => Entreprise::orderBy('raison_sociale')->get(),
            'agences' => Agence::all(),
            'typesStage' => TypeStage::all(),
            'sourcesFinancement' => SourceFinancement::all(),
            'programmes' => Programme::all(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateOffreEmploiRequest $request, OffreEmploi $offreEmploi)
    {
        $offreEmploi->update($request->validated());

        return redirect()->route('offres.index')
            ->with('success', 'Offre d\'emploi mise à jour avec succès.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(OffreEmploi $offreEmploi)
    {
        $this->authorize('delete', $offreEmploi);

        $offreEmploi->delete();

        return redirect()->route('offres.index')
            ->with('success', 'Offre d\'emploi supprimée avec succès.');
    }
}
