<?php

namespace Tests\Feature\ParametreAides;

use App\Domain\Payment\Services\Prime\PrimeConfigurationService;
use App\Models\Payment\PrimeConfigurationOverride;
use App\Models\User;

/**
 * Écran « Barème des primes » : lecture réservée à `voir_parametres_systeme`,
 * écriture à `gerer_parametres_systeme`, et simulateur qui rejoue le barème
 * enregistré sans rien modifier.
 */
class PrimeBaremeTest extends ParametreAidesTestCase
{
    public function test_utilisateur_sans_droit_ne_peut_pas_voir_le_bareme(): void
    {
        $utilisateur = User::factory()->create();
        $utilisateur->assignRole('cip');

        $this->actingAs($utilisateur)->get('/parametre-aides/primes')->assertForbidden();
    }

    public function test_l_ecran_expose_le_bareme_les_defauts_et_les_regles(): void
    {
        $response = $this->actingAs($this->creerAdministrateur())->get('/parametre-aides/primes');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('ParametreAides/Primes/Index')
            ->where('configuration.pae.45000.full', 45000)
            ->where('defauts.pae.45000.full', 45000)
            ->where('personnalise', false)
            ->where('peutGerer', true)
            ->has('strategies', 4)
        );
    }

    public function test_enregistrement_du_bareme_et_trace_de_l_auteur(): void
    {
        $admin = $this->creerAdministrateur();

        $this->actingAs($admin)
            ->put('/parametre-aides/primes', [
                'configuration' => ['pae' => ['45000' => ['full' => 50000]]],
            ])
            ->assertRedirect();

        $override = PrimeConfigurationOverride::query()->find(1);

        $this->assertNotNull($override);
        $this->assertSame(50000, $override->configuration['pae']['45000']['full']);
        $this->assertSame($admin->id, $override->modifie_par);

        // La surcharge est fusionnée sur les défauts : les autres grilles restent.
        $this->assertSame(75000, app(PrimeConfigurationService::class)->get('pae.75000.full'));
    }

    public function test_un_montant_negatif_est_refuse(): void
    {
        $this->actingAs($this->creerAdministrateur())
            ->put('/parametre-aides/primes', [
                'configuration' => ['pae' => ['45000' => ['full' => -1]]],
            ])
            ->assertSessionHasErrors('configuration');

        $this->assertNull(PrimeConfigurationOverride::query()->find(1));
    }

    public function test_une_date_mal_formee_est_refusee(): void
    {
        $this->actingAs($this->creerAdministrateur())
            ->put('/parametre-aides/primes', [
                'configuration' => ['qualification' => ['budget_aej_effective_date' => '01/01/2026']],
            ])
            ->assertSessionHasErrors('configuration');
    }

    public function test_lecture_seule_ne_permet_pas_d_enregistrer(): void
    {
        $utilisateur = User::factory()->create();
        $utilisateur->givePermissionTo('voir_parametres_systeme');

        $this->actingAs($utilisateur)->get('/parametre-aides/primes')->assertOk();
        $this->actingAs($utilisateur)
            ->put('/parametre-aides/primes', ['configuration' => ['smig' => ['default' => 1]]])
            ->assertForbidden();
    }

    public function test_reinitialisation_supprime_la_surcharge(): void
    {
        $admin = $this->creerAdministrateur();

        $this->actingAs($admin)->put('/parametre-aides/primes', [
            'configuration' => ['pae' => ['45000' => ['full' => 50000]]],
        ]);

        $this->actingAs($admin)->post('/parametre-aides/primes/reinitialiser')->assertRedirect();

        $this->assertSame(45000, app(PrimeConfigurationService::class)->get('pae.45000.full'));
    }

    public function test_le_simulateur_renvoie_la_prime_du_mois_et_la_regle_appliquee(): void
    {
        $response = $this->actingAs($this->creerAdministrateur())
            ->postJson('/parametre-aides/primes/simuler', [
                'type_stage_legacy_id' => 1,
                'source_financement_legacy_id' => 1,
                'date_debut' => '2024-03-10',
                'date_fin' => '2024-05-09',
                'mois' => '2024-03',
            ]);

        $response->assertOk();
        $response->assertJson([
            'montant' => 31500,
            'mois' => '2024-03',
            'strategie' => 'Prime de qualification',
        ]);
    }

    public function test_le_simulateur_n_ecrit_rien(): void
    {
        $this->actingAs($this->creerAdministrateur())
            ->postJson('/parametre-aides/primes/simuler', [
                'type_stage_legacy_id' => 2,
                'source_financement_legacy_id' => 1,
                'date_debut' => '2024-03-10',
                'date_fin' => '2024-06-09',
                'mois' => '2024-04',
            ])
            ->assertOk()
            ->assertJson(['montant' => 15000]);

        $this->assertNull(PrimeConfigurationOverride::query()->find(1));
    }
}
