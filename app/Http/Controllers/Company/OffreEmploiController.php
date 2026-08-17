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
use Illuminate\Http\Request;
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
