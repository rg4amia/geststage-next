<?php

namespace App\Http\Controllers\ParametreAides;

use App\Http\Controllers\Controller;
use App\Http\Requests\ParametreAides\StoreAgenceRequest;
use App\Http\Requests\ParametreAides\UpdateAgenceRequest;
use App\Models\Reference\Agence;
use App\Models\Reference\Commune;
use App\Models\Reference\Region;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Agences (legacy `AgenceController@agences`, GET `/Agences`).
 *
 * Colonnes reprises du legacy : nom de l'agence, nom du chef d'agence, contact.
 * L'édition reste volontairement restreinte — elle exige `gerer_agences`, une
 * permission qu'aucun rôle métier ne porte : seul l'administrateur y accède via
 * le bypass `Gate::before`.
 */
class AgenceController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('voir_referentiels'), 403);

        $agences = Agence::query()
            ->with(['region:id,nom'])
            ->when($request->string('search')->toString(), function ($query, string $recherche): void {
                $query->where(function ($sousRequete) use ($recherche): void {
                    $sousRequete->where('nom', 'ilike', "%{$recherche}%")
                        ->orWhere('code', 'ilike', "%{$recherche}%");
                });
            })
            ->when($request->filled('region_id'), fn ($query) => $query->where('region_id', $request->integer('region_id')))
            ->when($request->filled('actif'), fn ($query) => $query->where('actif', $request->boolean('actif')))
            ->orderBy('nom')
            ->paginate(15)
            ->withQueryString();

        // `chef_agence` est un accesseur : il est résolu page par page, jamais
        // sur l'ensemble du référentiel.
        $agences->getCollection()->transform(fn (Agence $agence): array => [
            'id' => $agence->id,
            'code' => $agence->code,
            'nom' => $agence->nom,
            'contact_agence' => $agence->contact_agence,
            'chef_agence_nom' => $agence->chef_agence_nom,
            'adresse' => $agence->adresse,
            'actif' => $agence->actif,
            'region' => $agence->region?->only(['id', 'nom']),
            'chef_agence' => $agence->chef_agence,
        ]);

        return Inertia::render('ParametreAides/Agences/Index', [
            'agences' => $agences,
            'regions' => Region::query()->orderBy('nom')->get(['id', 'nom']),
            'filters' => $request->only(['search', 'region_id', 'actif']),
            'peutGerer' => $request->user()->can('gerer_agences'),
        ]);
    }

    public function create(Request $request): Response
    {
        abort_unless($request->user()->can('gerer_agences'), 403);

        return Inertia::render('ParametreAides/Agences/Create', $this->referentiels());
    }

    public function store(StoreAgenceRequest $request): RedirectResponse
    {
        Agence::create($request->validated() + ['actif' => $request->boolean('actif', true)]);

        return redirect()->route('parametre-aides.agences.index')
            ->with('success', 'Agence créée avec succès.');
    }

    public function edit(Request $request, Agence $agence): Response
    {
        abort_unless($request->user()->can('gerer_agences'), 403);

        return Inertia::render('ParametreAides/Agences/Edit', [
            'agence' => $agence->only(['id', 'code', 'nom', 'contact_agence', 'chef_agence_nom', 'longitude', 'latitude', 'region_id', 'commune_id', 'adresse', 'actif']),
            ...$this->referentiels(),
        ]);
    }

    public function update(UpdateAgenceRequest $request, Agence $agence): RedirectResponse
    {
        $agence->update($request->validated() + ['actif' => $request->boolean('actif', true)]);

        return redirect()->route('parametre-aides.agences.index')
            ->with('success', 'Agence mise à jour.');
    }

    /**
     * @return array<string, mixed>
     */
    private function referentiels(): array
    {
        return [
            'regions' => Region::query()->orderBy('nom')->get(['id', 'nom']),
            'communes' => Commune::query()->where('actif', true)->orderBy('nom')->get(['id', 'nom', 'region_id']),
        ];
    }
}
