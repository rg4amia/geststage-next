<?php

namespace App\Http\Controllers\ParametreAides;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Aide / guide utilisateur (legacy `Help\IndexHelpController@indexGuideUser`).
 *
 * Le legacy lit une table `manuel_users` qui n'existe pas dans le schéma cible :
 * le guide est ici décrit en dur, par parcours métier, et pointe vers les écrans
 * réels de l'application.
 */
class AideController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('ParametreAides/Aide/Index', [
            'rubriques' => [
                [
                    'id' => 'cip',
                    'titre' => 'Espace CIP',
                    'icone' => 'ri-team-line',
                    'resume' => 'Inscrire un demandeur, monter son dossier de stage, saisir les pointages mensuels.',
                    'etapes' => [
                        'Inscrire le demandeur puis lui rattacher une offre et une entreprise d’accueil.',
                        'Générer le contrat, téléverser les pièces, puis transmettre au chef d’agence.',
                        'Saisir le pointage du mois et le soumettre à validation avant la clôture de la période.',
                        'Traiter les ajournements DMG depuis « Pointage Ajourné (DMG) » et retransmettre.',
                    ],
                    'liens' => [
                        ['libelle' => 'Mes stagiaires', 'href' => '/cip/mes-stagiaires'],
                        ['libelle' => 'Présence - Pointages', 'href' => '/cip/pointages'],
                        ['libelle' => 'Renouvellements', 'href' => '/cip/renouvellements'],
                    ],
                ],
                [
                    'id' => 'chef-agence',
                    'titre' => 'Espace Chef d’agence',
                    'icone' => 'ri-checkbox-circle-line',
                    'resume' => 'Valider les démarrages de stage et les pointages transmis par les CIP.',
                    'etapes' => [
                        'Contrôler les dossiers en attente de validation, individuellement ou en groupe.',
                        'Valider ou ajourner les pointages du mois en motivant chaque ajournement.',
                        'Générer les attestations et suivre l’historique des générations.',
                    ],
                    'liens' => [
                        ['libelle' => 'Attente de validation', 'href' => '/chefagence/validations'],
                        ['libelle' => 'Validation des pointages', 'href' => '/chefagence/pointages'],
                    ],
                ],
                [
                    'id' => 'chaine-financiere',
                    'titre' => 'Chaîne financière (DMG, CB, AC)',
                    'icone' => 'ri-money-dollar-circle-line',
                    'resume' => 'Constituer les dossiers de paiement, élaborer les OP puis viser les bordereaux.',
                    'etapes' => [
                        'DMG : contrôler les paiements en attente, ajourner ou générer les dossiers.',
                        'DMG : élaborer les ordres de paiement, créer le bordereau et le transmettre.',
                        'CB : contrôler les dossiers reçus, valider ou ajourner.',
                        'Agent comptable : viser, différer ou rejeter les bordereaux.',
                    ],
                    'liens' => [
                        ['libelle' => 'Paiements DMG', 'href' => '/dmg/paiements'],
                        ['libelle' => 'Contrôle des paiements (CB)', 'href' => '/cb/paiements'],
                        ['libelle' => 'Visas des bordereaux (AC)', 'href' => '/agent-comptable/paiements'],
                    ],
                ],
                [
                    'id' => 'supervision',
                    'titre' => 'Supervision (DESSE, agence régionale, DAICG)',
                    'icone' => 'ri-shield-check-line',
                    'resume' => 'Viser les dossiers, trancher les doublons et suivre la vue globale.',
                    'etapes' => [
                        'DESSE : traiter les doublons signalés et valider les dossiers.',
                        'Agence régionale : viser ou rejeter les dossiers de son périmètre, exporter les états.',
                        'DAICG : consulter la vue globale des stagiaires sans pouvoir de décision.',
                    ],
                    'liens' => [
                        ['libelle' => 'Validation et doublons', 'href' => '/desse/stagiaires'],
                        ['libelle' => 'Supervision régionale', 'href' => '/agence-regionale/visas'],
                        ['libelle' => 'Vue globale stagiaires', 'href' => '/daicg/stagiaires'],
                    ],
                ],
                [
                    'id' => 'administration',
                    'titre' => 'Administration',
                    'icone' => 'ri-settings-4-line',
                    'resume' => 'Gérer les comptes, les référentiels et les paramètres de calcul.',
                    'etapes' => [
                        'Créer un compte, lui affecter un rôle et un périmètre d’agence.',
                        'Créer un conseiller puis lui rattacher un compte utilisateur.',
                        'Déclarer une règle de prélèvement CMU en veillant à ne pas chevaucher une règle active.',
                        'Consulter les journaux d’activité pour retracer une modification.',
                    ],
                    'liens' => [
                        ['libelle' => 'Comptes utilisateurs', 'href' => '/parametre-aides/comptes'],
                        ['libelle' => 'Paramètres système', 'href' => '/parametre-aides/parametres-systeme'],
                        ['libelle' => 'Journaux d’activité', 'href' => '/parametre-aides/journaux'],
                    ],
                ],
            ],
        ]);
    }
}
