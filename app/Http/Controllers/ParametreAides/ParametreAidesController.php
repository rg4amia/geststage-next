<?php

namespace App\Http\Controllers\ParametreAides;

use App\Http\Controllers\Controller;
use App\Models\Audit\JournalAudit;
use App\Models\Company\Entreprise;
use App\Models\Company\OffreEmploi;
use App\Models\Payment\ReglePrelevement;
use App\Models\Reference\Agence;
use App\Models\Reference\Conseiller;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Page d'accueil du menu « Paramètre & Aides ».
 *
 * L'écran ne porte aucune logique métier : il oriente vers les modules
 * d'administration, en n'exposant que ceux permis à l'utilisateur courant.
 */
class ParametreAidesController extends Controller
{
    public function index(Request $request): Response
    {
        $utilisateur = $request->user();

        $modules = [
            [
                'id' => 'comptes',
                'titre' => 'Comptes utilisateurs',
                'description' => 'Créer, activer ou désactiver les comptes, gérer leurs rôles et leurs périmètres d’agence.',
                'icone' => 'ri-shield-user-line',
                'couleur' => 'primary',
                'href' => route('parametre-aides.comptes.index'),
                'permission' => 'voir_utilisateurs',
                'compteur' => fn (): int => User::query()->count(),
                'libelleCompteur' => 'comptes',
            ],
            [
                'id' => 'conseillers',
                'titre' => 'Conseillers',
                'description' => 'Référentiel des conseillers (CIP) et rattachement de leur compte utilisateur.',
                'icone' => 'ri-user-star-line',
                'couleur' => 'info',
                'href' => route('parametre-aides.conseillers.index'),
                'permission' => 'voir_referentiels',
                'compteur' => fn (): int => Conseiller::query()->where('actif', true)->count(),
                'libelleCompteur' => 'conseillers actifs',
            ],
            [
                'id' => 'agences',
                'titre' => 'Agences',
                'description' => 'Réseau des agences, leur région de rattachement et leur chef d’agence.',
                'icone' => 'ri-building-4-line',
                'couleur' => 'success',
                'href' => route('parametre-aides.agences.index'),
                'permission' => 'voir_referentiels',
                'compteur' => fn (): int => Agence::query()->where('actif', true)->count(),
                'libelleCompteur' => 'agences actives',
            ],
            [
                'id' => 'entreprises',
                'titre' => 'Entreprises',
                'description' => 'Structures d’accueil des stagiaires : identité, agence et informations administratives.',
                'icone' => 'ri-community-line',
                'couleur' => 'warning',
                'href' => route('entreprises.index'),
                'permission' => 'voir_entreprises',
                'compteur' => fn (): int => Entreprise::query()->count(),
                'libelleCompteur' => 'entreprises',
            ],
            [
                'id' => 'offres',
                'titre' => 'Offres',
                'description' => 'Offres de stage et d’emploi publiées par les entreprises partenaires.',
                'icone' => 'ri-briefcase-4-line',
                'couleur' => 'secondary',
                'href' => route('offres.index'),
                'permission' => 'voir_offres',
                'compteur' => fn (): int => OffreEmploi::query()->count(),
                'libelleCompteur' => 'offres',
            ],
            [
                'id' => 'parametres-systeme',
                'titre' => 'Paramètres système',
                'description' => 'Bascules applicatives et règles de prélèvement CMU datées appliquées aux paiements.',
                'icone' => 'ri-settings-4-line',
                'couleur' => 'danger',
                'href' => route('parametre-aides.parametres-systeme.index'),
                'permission' => 'voir_parametres_systeme',
                'compteur' => fn (): int => ReglePrelevement::query()->where('actif', true)->count(),
                'libelleCompteur' => 'règles actives',
            ],
            [
                'id' => 'primes',
                'titre' => 'Barème des primes',
                'description' => 'Montants et prorata appliqués au calcul de la prime mensuelle des stagiaires.',
                'icone' => 'ri-money-euro-circle-line',
                'couleur' => 'info',
                'href' => route('parametre-aides.primes.index'),
                'permission' => 'voir_parametres_systeme',
                'compteur' => null,
                'libelleCompteur' => null,
            ],
            [
                'id' => 'journaux',
                'titre' => 'Journaux d’activité',
                'description' => 'Traçabilité des créations, modifications et suppressions effectuées dans l’application.',
                'icone' => 'ri-history-line',
                'couleur' => 'dark',
                'href' => route('parametre-aides.journaux.index'),
                'permission' => 'voir_journaux_audit',
                'compteur' => fn (): int => JournalAudit::query()->where('created_at', '>=', now()->subDays(7))->count(),
                'libelleCompteur' => 'actions sur 7 jours',
            ],
            [
                'id' => 'aide',
                'titre' => 'Aide / Guide utilisateur',
                'description' => 'Parcours métier, rôles et procédures pas à pas de la plateforme.',
                'icone' => 'ri-question-answer-line',
                'couleur' => 'primary',
                'href' => route('parametre-aides.aide.index'),
                'permission' => null,
                'compteur' => null,
                'libelleCompteur' => null,
            ],
        ];

        $modulesAutorises = collect($modules)
            ->filter(fn (array $module): bool => $module['permission'] === null || $utilisateur->can($module['permission']))
            ->map(function (array $module): array {
                $module['compteur'] = $module['compteur'] ? ($module['compteur'])() : null;
                unset($module['permission']);

                return $module;
            })
            ->values();

        return Inertia::render('ParametreAides/Index', [
            'modules' => $modulesAutorises,
        ]);
    }
}
