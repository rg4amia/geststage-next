<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEntrepriseRequest;
use App\Http\Requests\UpdateEntrepriseRequest;
use App\Models\Company\Entreprise;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EntrepriseController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Entreprise::class);

        $entreprises = Entreprise::with(['agence', 'typeStructure'])
            ->when($request->search, function ($query, $search) {
                $query->where('raison_sociale', 'ilike', "%{$search}%")
                      ->orWhere('sigle', 'ilike', "%{$search}%");
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Entreprises/Index', [
            'entreprises' => $entreprises,
            'filters' => $request->only(['search']),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $this->authorize('create', Entreprise::class);

        return Inertia::render('Entreprises/Create', [
            'agences' => \App\Models\Reference\Agence::all(),
            'communes' => \App\Models\Reference\Commune::all(),
            'typesStructure' => \App\Models\Reference\TypeStructure::all(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreEntrepriseRequest $request)
    {
        Entreprise::create($request->validated());

        return redirect()->route('entreprises.index')
            ->with('success', 'Entreprise créée avec succès.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Entreprise $entreprise)
    {
        $this->authorize('update', $entreprise);

        return Inertia::render('Entreprises/Edit', [
            'entreprise' => $entreprise,
            'agences' => \App\Models\Reference\Agence::all(),
            'communes' => \App\Models\Reference\Commune::all(),
            'typesStructure' => \App\Models\Reference\TypeStructure::all(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateEntrepriseRequest $request, Entreprise $entreprise)
    {
        $entreprise->update($request->validated());

        return redirect()->route('entreprises.index')
            ->with('success', 'Entreprise mise à jour avec succès.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Entreprise $entreprise)
    {
        $this->authorize('delete', $entreprise);

        $entreprise->delete();

        return redirect()->route('entreprises.index')
            ->with('success', 'Entreprise supprimée avec succès.');
    }
}
