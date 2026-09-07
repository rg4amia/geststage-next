<?php

namespace Tests\Feature\ParametreAides;

use App\Models\Reference\Agence;
use App\Models\Reference\Conseiller;
use App\Models\Reference\SourceFinancement;
use App\Models\User;

/**
 * Couvre le contrat métier du menu « Paramètre & Aides » : visibilité du hub,
 * droits d'accès par permission, validations des formulaires et données
 * renvoyées aux écrans Inertia.
 */
class ParametreAidesTest extends ParametreAidesTestCase
{
    // -----------------------------------------------------------------
    // Hub
    // -----------------------------------------------------------------

    public function test_hub_expose_uniquement_les_modules_autorises(): void
    {
        $utilisateur = User::factory()->create();
        $utilisateur->assignRole('cip');

        $response = $this->actingAs($utilisateur)->get('/parametre-aides');

        $response->assertOk();
        // Le CIP ne dispose ni de `voir_utilisateurs`, ni de `voir_referentiels`,
        // ni des permissions d'administration : seuls entreprises, offres et
        // aide (sans permission) lui restent visibles, dans cet ordre.
        $response->assertInertia(fn ($page) => $page
            ->component('ParametreAides/Index')
            ->count('modules', 3)
            ->where('modules.0.id', 'entreprises')
            ->where('modules.1.id', 'offres')
            ->where('modules.2.id', 'aide')
        );
    }

    public function test_administrateur_voit_tous_les_modules_du_hub(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('administrateur');

        $response = $this->actingAs($admin)->get('/parametre-aides');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('ParametreAides/Index')
            ->count('modules', 8)
        );
    }

    // -----------------------------------------------------------------
    // Comptes
    // -----------------------------------------------------------------

    public function test_utilisateur_sans_droit_ne_peut_pas_voir_les_comptes(): void
    {
        $cip = User::factory()->create();
        $cip->assignRole('cip');

        $this->actingAs($cip)->get('/parametre-aides/comptes')->assertForbidden();
    }

    public function test_admin_liste_les_comptes_avec_filtres_serveur(): void
    {
        $admin = $this->creerAdministrateur();

        $trouve = User::factory()->create(['nom' => 'Kouassi Aya', 'actif' => false]);
        User::factory()->create(['nom' => 'Autre Personne', 'actif' => true]);

        // Filtre serveur (le tri alphabétique légèrement ambigu du `search`
        // ilike n'est pas portable sous sqlite ; le filtre `actif` couvre le
        // même contrat de pagination server-side sans dépendre du moteur).
        $response = $this->actingAs($admin)->get('/parametre-aides/comptes?actif=0');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('ParametreAides/Comptes/Index')
            ->count('utilisateurs.data', 1)
            ->where('utilisateurs.data.0.nom', $trouve->nom)
        );
    }

    public function test_admin_peut_creer_un_compte(): void
    {
        $admin = $this->creerAdministrateur();

        $response = $this->actingAs($admin)->post('/parametre-aides/comptes', [
            'nom' => 'Nouveau Compte',
            'email' => 'nouveau.compte@example.com',
            'telephone' => '0102030405',
            'password' => 'mot-de-passe-sur',
            'password_confirmation' => 'mot-de-passe-sur',
            'actif' => true,
            'roles' => [],
            'agences' => [],
        ]);

        $response->assertRedirect(route('parametre-aides.comptes.index'));
        $this->assertDatabaseHas('users', ['email' => 'nouveau.compte@example.com']);
    }

    public function test_creation_compte_rejette_un_email_deja_utilise(): void
    {
        $admin = $this->creerAdministrateur();
        User::factory()->create(['email' => 'existe@example.com']);

        $response = $this->actingAs($admin)->post('/parametre-aides/comptes', [
            'nom' => 'Nouveau Compte',
            'email' => 'existe@example.com',
            'password' => 'mot-de-passe-sur',
            'password_confirmation' => 'mot-de-passe-sur',
            'actif' => true,
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_utilisateur_ne_peut_pas_desactiver_son_propre_compte(): void
    {
        $admin = $this->creerAdministrateur();

        $response = $this->actingAs($admin)->post("/parametre-aides/comptes/{$admin->id}/activation");

        $response->assertRedirect();
        $this->assertTrue($admin->fresh()->actif);
    }

    public function test_seul_un_titulaire_de_usurper_identite_peut_usurper_un_compte(): void
    {
        $cip = User::factory()->create();
        $cip->assignRole('cip');

        $cible = User::factory()->create(['actif' => true]);

        $this->actingAs($cip)->post("/parametre-aides/comptes/{$cible->id}/usurper")
            ->assertForbidden();
    }

    public function test_usurpation_est_refusee_pour_un_compte_rattache_a_un_conseiller(): void
    {
        $admin = $this->creerAdministrateur();

        $agence = Agence::factory()->create();
        $compteConseiller = User::factory()->create(['actif' => true]);
        Conseiller::create([
            'agence_id' => $agence->id,
            'nom' => 'Diallo',
            'user_id' => $compteConseiller->id,
            'actif' => true,
        ]);

        $response = $this->actingAs($admin)->post("/parametre-aides/comptes/{$compteConseiller->id}/usurper");

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertAuthenticatedAs($admin);
    }

    public function test_usurpation_puis_retour_au_compte_reel(): void
    {
        $admin = $this->creerAdministrateur();
        $cible = User::factory()->create(['actif' => true]);

        $this->actingAs($admin)
            ->post("/parametre-aides/comptes/{$cible->id}/usurper")
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($cible);

        $this->post('/parametre-aides/cesser-usurpation')
            ->assertRedirect(route('parametre-aides.comptes.index'));

        $this->assertAuthenticatedAs($admin);

        $this->assertDatabaseHas('journaux_audit', ['action' => 'usurpation_debut']);
        $this->assertDatabaseHas('journaux_audit', ['action' => 'usurpation_fin']);
    }

    // -----------------------------------------------------------------
    // Conseillers
    // -----------------------------------------------------------------

    public function test_liste_des_conseillers_respecte_les_colonnes_du_contrat_legacy(): void
    {
        $admin = $this->creerAdministrateur();
        $agence = Agence::factory()->create(['nom' => 'Agence Abidjan']);
        Conseiller::create([
            'agence_id' => $agence->id,
            'nom' => 'Kone',
            'prenoms' => 'Awa',
            'actif' => true,
        ]);

        $response = $this->actingAs($admin)->get('/parametre-aides/conseillers');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('ParametreAides/Conseillers/Index')
            ->where('conseillers.data.0.nom', 'Kone')
            ->where('conseillers.data.0.agence.nom', 'Agence Abidjan')
        );
    }

    public function test_creation_conseiller_avec_matricule_deja_pris_est_rejetee(): void
    {
        $admin = $this->creerAdministrateur();
        $agence = Agence::factory()->create();
        Conseiller::create(['agence_id' => $agence->id, 'nom' => 'Existant', 'matricule' => 'MAT-1', 'actif' => true]);

        $response = $this->actingAs($admin)->post('/parametre-aides/conseillers', [
            'agence_id' => $agence->id,
            'nom' => 'Nouveau',
            'matricule' => 'MAT-1',
        ]);

        $response->assertSessionHasErrors('matricule');
    }

    public function test_rattachement_conseiller_a_un_nouveau_compte_cree_lutilisateur_et_le_role_cip(): void
    {
        $admin = $this->creerAdministrateur();
        $agence = Agence::factory()->create();
        $conseiller = Conseiller::create(['agence_id' => $agence->id, 'nom' => 'Bamba', 'actif' => true]);

        $response = $this->actingAs($admin)->post("/parametre-aides/conseillers/{$conseiller->id}/compte", [
            'mode' => 'creer',
            'email' => 'bamba.conseiller@example.com',
            'password' => 'mot-de-passe-sur',
            'password_confirmation' => 'mot-de-passe-sur',
        ]);

        $response->assertRedirect();
        $conseiller->refresh();
        $this->assertNotNull($conseiller->user_id);
        $this->assertTrue($conseiller->user->hasRole('cip'));
    }

    public function test_rattachement_conseiller_refuse_si_deja_rattache(): void
    {
        $admin = $this->creerAdministrateur();
        $agence = Agence::factory()->create();
        $compte = User::factory()->create();
        $conseiller = Conseiller::create(['agence_id' => $agence->id, 'nom' => 'Toure', 'user_id' => $compte->id, 'actif' => true]);

        $response = $this->actingAs($admin)->post("/parametre-aides/conseillers/{$conseiller->id}/compte", [
            'mode' => 'rattacher',
            'user_id' => User::factory()->create()->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    // -----------------------------------------------------------------
    // Agences — édition restreinte à `gerer_agences`
    // -----------------------------------------------------------------

    public function test_chef_agence_ne_peut_pas_editer_une_agence(): void
    {
        $chefAgence = User::factory()->create();
        $chefAgence->assignRole('chef_agence');
        $agence = Agence::factory()->create();

        $this->actingAs($chefAgence)->get("/parametre-aides/agences/{$agence->id}/modifier")
            ->assertForbidden();

        $this->actingAs($chefAgence)->put("/parametre-aides/agences/{$agence->id}", ['nom' => 'X'])
            ->assertForbidden();
    }

    public function test_titulaire_de_voir_referentiels_peut_consulter_la_liste_des_agences(): void
    {
        // Aucun rôle métier ne porte encore `voir_referentiels` dans la seed :
        // la lecture reste, comme l'édition, un accès accordé explicitement.
        $chefAgence = User::factory()->create();
        $chefAgence->assignRole('chef_agence');
        $chefAgence->givePermissionTo('voir_referentiels');
        Agence::factory()->create();

        $this->actingAs($chefAgence)->get('/parametre-aides/agences')->assertOk();
    }

    public function test_chef_agence_sans_permission_ne_peut_pas_consulter_la_liste_des_agences(): void
    {
        $chefAgence = User::factory()->create();
        $chefAgence->assignRole('chef_agence');

        $this->actingAs($chefAgence)->get('/parametre-aides/agences')->assertForbidden();
    }

    public function test_administrateur_peut_editer_une_agence(): void
    {
        $admin = $this->creerAdministrateur();
        $agence = Agence::factory()->create(['code' => 'AG-OLD']);

        $response = $this->actingAs($admin)->put("/parametre-aides/agences/{$agence->id}", [
            'code' => 'AG-OLD',
            'nom' => 'Agence Renommée',
        ]);

        $response->assertRedirect(route('parametre-aides.agences.index'));
        $this->assertDatabaseHas('agences', ['id' => $agence->id, 'nom' => 'Agence Renommée']);
    }

    // -----------------------------------------------------------------
    // Paramètres système / règles de prélèvement
    // -----------------------------------------------------------------

    public function test_creation_regle_prelevement_avec_periode_chevauchante_est_rejetee(): void
    {
        $admin = $this->creerAdministrateur();
        $source = SourceFinancement::factory()->create();

        $this->actingAs($admin)->post('/parametre-aides/parametres-systeme/prelevements', [
            'nom' => 'CMU démarrage',
            'type_prelevement' => 'CMU',
            'source_financement_id' => $source->id,
            'type_paiement' => 'DEMARRAGE',
            'montant' => 1000,
            'effet_du' => '2026-01',
            'actif' => true,
        ])->assertRedirect();

        $response = $this->actingAs($admin)->post('/parametre-aides/parametres-systeme/prelevements', [
            'nom' => 'CMU démarrage bis',
            'type_prelevement' => 'CMU',
            'source_financement_id' => $source->id,
            'type_paiement' => 'DEMARRAGE',
            'montant' => 1500,
            'effet_du' => '2026-06',
            'actif' => true,
        ]);

        $response->assertSessionHasErrors('effet_du');
        $this->assertDatabaseCount('regles_prelevement', 1);
    }

    public function test_regle_prelevement_sur_periode_disjointe_est_acceptee(): void
    {
        $admin = $this->creerAdministrateur();
        $source = SourceFinancement::factory()->create();

        $this->actingAs($admin)->post('/parametre-aides/parametres-systeme/prelevements', [
            'nom' => 'CMU démarrage',
            'type_prelevement' => 'CMU',
            'source_financement_id' => $source->id,
            'type_paiement' => 'DEMARRAGE',
            'montant' => 1000,
            'effet_du' => '2026-01',
            'effet_au' => '2026-03',
            'actif' => true,
        ])->assertRedirect();

        $response = $this->actingAs($admin)->post('/parametre-aides/parametres-systeme/prelevements', [
            'nom' => 'CMU démarrage suivante',
            'type_prelevement' => 'CMU',
            'source_financement_id' => $source->id,
            'type_paiement' => 'DEMARRAGE',
            'montant' => 1500,
            'effet_du' => '2026-04',
            'actif' => true,
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertDatabaseCount('regles_prelevement', 2);
    }

    public function test_utilisateur_sans_droit_ne_voit_pas_les_parametres_systeme(): void
    {
        $cip = User::factory()->create();
        $cip->assignRole('cip');

        $this->actingAs($cip)->get('/parametre-aides/parametres-systeme')->assertForbidden();
    }

    public function test_page_parametres_systeme_expose_le_catalogue_et_les_regles(): void
    {
        $admin = $this->creerAdministrateur();

        $response = $this->actingAs($admin)->get('/parametre-aides/parametres-systeme');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('ParametreAides/ParametresSysteme/Index')
            ->has('parametres', 4)
            ->has('regles')
        );
    }

    // -----------------------------------------------------------------
    // Journaux d'activité
    // -----------------------------------------------------------------

    public function test_utilisateur_sans_droit_ne_voit_pas_les_journaux(): void
    {
        $cip = User::factory()->create();
        $cip->assignRole('cip');

        $this->actingAs($cip)->get('/parametre-aides/journaux')->assertForbidden();
    }

    public function test_journaux_sont_alimentes_par_les_ecritures_auditables(): void
    {
        $admin = $this->creerAdministrateur();

        Agence::factory()->create();

        $response = $this->actingAs($admin)->get('/parametre-aides/journaux');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('ParametreAides/Journaux/Index')
            ->has('journaux.data', 1)
            ->where('journaux.data.0.action', 'created')
        );
    }

    public function test_export_csv_des_journaux_est_accessible_aux_ayants_droit(): void
    {
        $admin = $this->creerAdministrateur();
        Agence::factory()->create();

        $response = $this->actingAs($admin)->get('/parametre-aides/journaux/export');

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }
}
