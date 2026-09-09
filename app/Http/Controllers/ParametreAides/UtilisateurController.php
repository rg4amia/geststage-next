<?php

namespace App\Http\Controllers\ParametreAides;

use App\Enums\RoleEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\ParametreAides\StoreUtilisateurRequest;
use App\Http\Requests\ParametreAides\UpdateUtilisateurRequest;
use App\Models\Audit\JournalAudit;
use App\Models\Reference\Agence;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

/**
 * Comptes utilisateurs (legacy `UserController@comptes`, GET `/comptes`).
 *
 * Le listing DataTables server-side du legacy est remplacé par une pagination
 * serveur Inertia : mêmes colonnes (nom, contact, rôles, agence, statut) et
 * mêmes filtres, sans charger l'intégralité des comptes en mémoire.
 */
class UtilisateurController extends Controller
{
    /** Clé de session portant l'identité réelle pendant une usurpation. */
    public const CLE_USURPATION = 'parametre_aides.usurpateur_id';

    /** Valeur du filtre « rôle » isolant les comptes sans aucun rôle attribué. */
    public const FILTRE_SANS_ROLE = 'sans_role';

    /**
     * Rôles proposés à l'attribution : ceux réellement présents en base, enrichis de leur
     * libellé métier et des types d'utilisateur legacy qu'ils remplacent (RoleEnum). Un rôle
     * créé hors de l'enum reste proposé, simplement sans correspondance legacy.
     *
     * @return list<array{name: string, label: string, description: string, types_legacy: list<string>}>
     */
    private function catalogueRoles(): array
    {
        $catalogue = collect(RoleEnum::catalogue())->keyBy('name');
        $ordre = $catalogue->keys()->flip();

        return Role::query()->orderBy('name')->pluck('name')
            ->map(fn (string $nom): array => $catalogue->get($nom, [
                'name' => $nom,
                'label' => $nom,
                'description' => '',
                'types_legacy' => [],
            ]))
            // Ordre de l'enum (hiérarchie métier) ; les rôles hors enum ferment la liste.
            ->sortBy(fn (array $role): int => $ordre->get($role['name'], PHP_INT_MAX))
            ->values()
            ->all();
    }

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', User::class);

        $utilisateurs = User::query()
            ->with(['roles:id,name', 'perimetresAgences:id,nom'])
            ->withExists('conseiller as est_conseiller')
            ->when($request->string('search')->toString(), function ($query, string $recherche): void {
                $query->where(function ($sousRequete) use ($recherche): void {
                    $sousRequete->where('nom', 'ilike', "%{$recherche}%")
                        ->orWhere('email', 'ilike', "%{$recherche}%")
                        ->orWhere('telephone', 'ilike', "%{$recherche}%");
                });
            })
            ->when($request->string('role')->toString(), function ($query, string $role): void {
                // Les types d'utilisateur legacy sans équivalent (DIC, DPF, Cabinet...) ont
                // produit des comptes sans aucun rôle : ce filtre permet de les retrouver
                // pour leur attribuer un rôle applicatif.
                if ($role === self::FILTRE_SANS_ROLE) {
                    $query->doesntHave('roles');

                    return;
                }

                $query->whereHas('roles', fn ($sousRequete) => $sousRequete->where('name', $role));
            })
            ->when($request->filled('agence_id'), function ($query) use ($request): void {
                $query->whereHas('perimetresAgences', fn ($sousRequete) => $sousRequete->where('agences.id', $request->integer('agence_id')));
            })
            ->when($request->filled('actif'), function ($query) use ($request): void {
                $query->where('actif', $request->boolean('actif'));
            })
            ->orderBy('nom')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('ParametreAides/Comptes/Index', [
            'utilisateurs' => $utilisateurs,
            'roles' => $this->catalogueRoles(),
            'nombreComptesSansRole' => User::query()->doesntHave('roles')->count(),
            'agences' => Agence::query()->where('actif', true)->orderBy('nom')->get(['id', 'nom']),
            'filters' => $request->only(['search', 'role', 'agence_id', 'actif']),
            'peutGerer' => $request->user()->can('create', User::class),
            'peutUsurper' => $request->user()->can('usurper_identite'),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', User::class);

        return Inertia::render('ParametreAides/Comptes/Create', [
            'roles' => $this->catalogueRoles(),
            'agences' => Agence::query()->where('actif', true)->orderBy('nom')->get(['id', 'nom']),
        ]);
    }

    public function store(StoreUtilisateurRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $utilisateur = User::create([
                'nom' => $request->validated('nom'),
                'email' => $request->validated('email'),
                'telephone' => $request->validated('telephone'),
                'password' => $request->validated('password'),
                'actif' => $request->boolean('actif', true),
            ]);

            $utilisateur->syncRoles($request->validated('roles', []));
            $utilisateur->perimetresAgences()->sync($request->validated('agences', []));
        });

        return redirect()->route('parametre-aides.comptes.index')
            ->with('success', 'Compte utilisateur créé avec succès.');
    }

    public function edit(Request $request, User $utilisateur): Response
    {
        $this->authorize('update', $utilisateur);

        return Inertia::render('ParametreAides/Comptes/Edit', [
            'utilisateur' => [
                'id' => $utilisateur->id,
                'nom' => $utilisateur->nom,
                'email' => $utilisateur->email,
                'telephone' => $utilisateur->telephone,
                'actif' => $utilisateur->actif,
                'roles' => $utilisateur->getRoleNames(),
                'agences' => $utilisateur->perimetresAgences()->pluck('agences.id'),
            ],
            'roles' => $this->catalogueRoles(),
            'agences' => Agence::query()->where('actif', true)->orderBy('nom')->get(['id', 'nom']),
        ]);
    }

    public function update(UpdateUtilisateurRequest $request, User $utilisateur): RedirectResponse
    {
        DB::transaction(function () use ($request, $utilisateur): void {
            $utilisateur->fill([
                'nom' => $request->validated('nom'),
                'email' => $request->validated('email'),
                'telephone' => $request->validated('telephone'),
                'actif' => $request->boolean('actif', true),
            ]);

            if ($request->filled('password')) {
                $utilisateur->password = $request->validated('password');
            }

            $utilisateur->save();

            $utilisateur->syncRoles($request->validated('roles', []));
            $utilisateur->perimetresAgences()->sync($request->validated('agences', []));
        });

        return redirect()->route('parametre-aides.comptes.index')
            ->with('success', 'Compte utilisateur mis à jour.');
    }

    /**
     * Bascule d'activation : le legacy ne supprime jamais un compte, il le
     * désactive, afin de préserver les rattachements historiques (conseiller,
     * pointages, journaux).
     */
    public function basculerActivation(Request $request, User $utilisateur): RedirectResponse
    {
        $this->authorize('update', $utilisateur);

        if ($utilisateur->is($request->user())) {
            return back()->with('error', 'Vous ne pouvez pas désactiver votre propre compte.');
        }

        $utilisateur->update(['actif' => ! $utilisateur->actif]);

        return back()->with('success', $utilisateur->actif
            ? 'Compte réactivé.'
            : 'Compte désactivé.');
    }

    /**
     * Connexion en lieu et place d'un autre compte (legacy `login-as`).
     *
     * Restreinte aux comptes non rattachés à un conseiller, comme dans le
     * legacy, et systématiquement journalisée.
     */
    public function usurper(Request $request, User $utilisateur): RedirectResponse
    {
        abort_unless($request->user()->can('usurper_identite'), 403);

        if ($request->session()->has(self::CLE_USURPATION)) {
            return back()->with('error', 'Une usurpation est déjà en cours. Revenez à votre compte avant d’en démarrer une autre.');
        }

        if ($utilisateur->conseiller()->exists()) {
            return back()->with('error', 'Les comptes rattachés à un conseiller ne peuvent pas être usurpés.');
        }

        if (! $utilisateur->actif) {
            return back()->with('error', 'Ce compte est désactivé.');
        }

        if ($utilisateur->is($request->user())) {
            return back()->with('error', 'Vous êtes déjà connecté avec ce compte.');
        }

        $usurpateur = $request->user();

        $this->journaliserUsurpation('usurpation_debut', $usurpateur, $utilisateur, $request);

        Auth::login($utilisateur);
        $request->session()->put(self::CLE_USURPATION, $usurpateur->id);

        return redirect()->route('dashboard')
            ->with('success', "Vous êtes maintenant connecté en tant que {$utilisateur->nom}.");
    }

    /**
     * Retour à l'identité réelle après une usurpation.
     */
    public function cesserUsurpation(Request $request): RedirectResponse
    {
        $usurpateurId = $request->session()->pull(self::CLE_USURPATION);

        abort_if($usurpateurId === null, 403);

        $usurpateur = User::findOrFail($usurpateurId);

        $this->journaliserUsurpation('usurpation_fin', $usurpateur, $request->user(), $request);

        Auth::login($usurpateur);

        return redirect()->route('parametre-aides.comptes.index')
            ->with('success', 'Vous êtes revenu sur votre compte.');
    }

    private function journaliserUsurpation(string $action, User $usurpateur, User $cible, Request $request): void
    {
        JournalAudit::create([
            'user_id' => $usurpateur->id,
            'action' => $action,
            'modele_type' => User::class,
            'modele_id' => $cible->id,
            'nouvelles_donnees' => [
                'usurpateur' => $usurpateur->email,
                'cible' => $cible->email,
            ],
            'adresse_ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
