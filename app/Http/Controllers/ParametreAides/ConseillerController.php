<?php

namespace App\Http\Controllers\ParametreAides;

use App\Http\Controllers\Controller;
use App\Http\Requests\ParametreAides\StoreConseillerRequest;
use App\Http\Requests\ParametreAides\UpdateConseillerRequest;
use App\Models\Reference\Agence;
use App\Models\Reference\Conseiller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

/**
 * Conseillers (legacy `UserController@leconseiller`, GET `/conseiller`).
 *
 * Reprend les colonnes du DataTables legacy — agence, nom et prénoms, contact,
 * actions — et les trois actions POST : `Save_Conseiller`, `Modif_Conseiller`
 * et `conseiller-to-user` (création ou rattachement d'un compte).
 */
class ConseillerController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('voir_referentiels'), 403);

        $conseillers = Conseiller::query()
            ->with(['agence:id,nom', 'user:id,nom,email,telephone,actif'])
            ->when($request->string('search')->toString(), function ($query, string $recherche): void {
                $query->where(function ($sousRequete) use ($recherche): void {
                    $sousRequete->where('nom', 'ilike', "%{$recherche}%")
                        ->orWhere('prenoms', 'ilike', "%{$recherche}%")
                        ->orWhere('matricule', 'ilike', "%{$recherche}%");
                });
            })
            ->when($request->filled('agence_id'), fn ($query) => $query->where('agence_id', $request->integer('agence_id')))
            ->when($request->filled('actif'), fn ($query) => $query->where('actif', $request->boolean('actif')))
            ->orderBy('nom')
            ->paginate(15)
            ->withQueryString();

        // La liste des utilisateurs dépend du périmètre : admin → tous,
        // chef d'agence → utilisateurs de son périmètre.
        $user = $request->user();
        $utilisateursDisponibles = $user->hasRole('administrateur')
            ? User::query()->where('actif', true)->orderBy('nom')->get(['id', 'nom', 'email', 'telephone'])
            : User::query()->where('actif', true)
                ->whereHas('perimetresAgences', fn ($q) => $q->whereIn(
                    'agences.id',
                    $user->perimetresAgences()->pluck('agences.id'),
                ))
                ->orderBy('nom')
                ->get(['id', 'nom', 'email', 'telephone']);

        return Inertia::render('ParametreAides/Conseillers/Index', [
            'conseillers' => $conseillers,
            'agences' => Agence::query()->where('actif', true)->orderBy('nom')->get(['id', 'nom']),
            'utilisateursDisponibles' => $utilisateursDisponibles,
            'filters' => $request->only(['search', 'agence_id', 'actif']),
            'peutGerer' => $user->can('gerer_referentiels'),
            'peutGererComptes' => $user->can('gerer_utilisateurs'),
        ]);
    }

    public function create(Request $request): Response
    {
        abort_unless($request->user()->can('gerer_referentiels'), 403);

        return Inertia::render('ParametreAides/Conseillers/Create', [
            'agences' => Agence::query()->where('actif', true)->orderBy('nom')->get(['id', 'nom']),
        ]);
    }

    public function store(StoreConseillerRequest $request): RedirectResponse
    {
        Conseiller::create($request->validated() + ['actif' => $request->boolean('actif', true)]);

        return redirect()->route('parametre-aides.conseillers.index')
            ->with('success', 'Conseiller créé avec succès.');
    }

    public function edit(Request $request, Conseiller $conseiller): Response
    {
        abort_unless($request->user()->can('gerer_referentiels'), 403);

        return Inertia::render('ParametreAides/Conseillers/Edit', [
            'conseiller' => $conseiller->only(['id', 'agence_id', 'nom', 'prenoms', 'matricule', 'actif']),
            'agences' => Agence::query()->where('actif', true)->orderBy('nom')->get(['id', 'nom']),
        ]);
    }

    public function update(UpdateConseillerRequest $request, Conseiller $conseiller): RedirectResponse
    {
        $conseiller->update($request->validated() + ['actif' => $request->boolean('actif', true)]);

        return redirect()->route('parametre-aides.conseillers.index')
            ->with('success', 'Conseiller mis à jour.');
    }

    /**
     * Création d'un compte utilisateur pour le conseiller, ou rattachement à un
     * compte existant (legacy `conseiller-to-user`).
     *
     * Le compte créé reçoit le rôle `cip` et le périmètre de l'agence du
     * conseiller, ce qui reproduit le comportement attendu côté legacy sans en
     * dupliquer le code.
     */
    public function rattacherCompte(Request $request, Conseiller $conseiller): RedirectResponse
    {
        abort_unless($request->user()->can('gerer_utilisateurs'), 403);

        if ($conseiller->user_id !== null) {
            return back()->with('error', 'Ce conseiller dispose déjà d’un compte utilisateur.');
        }

        $donnees = $request->validate([
            'mode' => ['required', Rule::in(['creer', 'rattacher'])],
            'user_id' => ['required_if:mode,rattacher', 'nullable', 'integer', 'exists:users,id', 'unique:conseillers,user_id'],
            'email' => ['required_if:mode,creer', 'nullable', 'email', 'max:255', 'unique:users,email'],
            'telephone' => ['nullable', 'string', 'max:30', 'unique:users,telephone'],
            'password' => ['required_if:mode,creer', 'nullable', 'confirmed', Password::defaults()],
        ]);

        DB::transaction(function () use ($donnees, $conseiller): void {
            if ($donnees['mode'] === 'rattacher') {
                $utilisateur = User::findOrFail($donnees['user_id']);
            } else {
                $utilisateur = User::create([
                    'nom' => $conseiller->nom_complet,
                    'email' => $donnees['email'],
                    'telephone' => $donnees['telephone'] ?? null,
                    'password' => $donnees['password'],
                    'actif' => true,
                ]);

                if (Role::query()->where('name', 'cip')->exists()) {
                    $utilisateur->assignRole('cip');
                }
            }

            $utilisateur->perimetresAgences()->syncWithoutDetaching([$conseiller->agence_id]);
            $conseiller->update(['user_id' => $utilisateur->id]);
        });

        return back()->with('success', 'Compte utilisateur rattaché au conseiller.');
    }
}
