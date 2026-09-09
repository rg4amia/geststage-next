<?php

namespace Tests\Feature\Workflow;

use App\Enums\CorbeilleEnum;
use App\Models\Attendance\Pointage;
use App\Models\Attendance\VersionPointage;
use App\Models\Contract\Contrat;
use App\Models\Internship\Stage;
use App\Models\Payment\DroitPaiement;
use App\Models\Reference\Periode;
use App\Models\Reference\SourceFinancement;
use App\Models\User;
use App\Models\Workflow\DefinitionParcours;
use App\Models\Workflow\EtapeParcours;
use App\Models\Workflow\InstanceParcours;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PejedecPointageActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_correction_dmg_pejedec_passe_le_pointage_en_corrige_cip(): void
    {
        $user = User::factory()->create();
        $stage = Stage::factory()->create();
        $periode = Periode::create([
            'code' => '2026-08',
            'date_debut' => '2026-08-01',
            'date_fin' => '2026-08-31',
            'ouverte_pointage' => true,
            'ouverte_paiement' => false,
        ]);

        $pointage = Pointage::create([
            'uuid_public' => (string) Str::uuid(),
            'stage_id' => $stage->id,
            'periode_id' => $periode->id,
            'nature' => 'MENSUEL',
            'statut' => 'AJOURNE_DMG',
            'version_courante' => 1,
            'version_verrouillage' => 0,
        ]);

        VersionPointage::create([
            'pointage_id' => $pointage->id,
            'saisi_par_id' => $user->id,
            'numero_version' => 1,
            'presence' => 'PRESENT',
            'jours_presents' => 12,
            'jours_absents' => 8,
            'observation' => 'Correction requise',
        ]);

        $this->actingAs($user)
            ->post("/cip/pointages/corriger-ajournement-dmg/{$pointage->id}", [
                'motif' => 'Données corrigées',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('pointages', [
            'id' => $pointage->id,
            'statut' => 'CORRIGE_CIP',
        ]);

        $this->assertDatabaseHas('decisions_pointages', [
            'pointage_id' => $pointage->id,
            'decision' => 'CORRIGE_CIP',
        ]);
    }

    public function test_l_aaf_valide_un_pointage_pejedec_deja_valide_ca_sans_generer_le_paiement(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('aaf');
        $sourcePejedec = $this->sourcePejedec();
        $periode = $this->periode();
        $stage = Stage::factory()->create(['source_financement_id' => $sourcePejedec->id]);
        Contrat::factory()->create([
            'stage_id' => $stage->id,
            'prime_mensuelle' => 45000,
            'statut' => 'VALIDE',
        ]);
        $pointage = $this->pointage($stage, $periode, 'VALIDE');

        $this->actingAs($user)
            ->post("/pejedec/af/pointages/{$pointage->id}/valider")
            ->assertRedirect();

        $this->assertDatabaseHas('decisions_pointages', [
            'pointage_id' => $pointage->id,
            'decision' => 'VALIDE_AAF',
        ]);

        $this->assertDatabaseHas('droits_paiement', [
            'stage_id' => $stage->id,
            'pointage_id' => $pointage->id,
            'periode_id' => $periode->id,
            'source_financement_id' => $sourcePejedec->id,
            'nature' => 'PRESENCE',
            'montant' => 45000,
            'statut' => 'OUVERT',
        ]);

        $droitId = DroitPaiement::where('pointage_id', $pointage->id)->value('id');
        $this->assertDatabaseMissing('paiements', [
            'droit_paiement_id' => $droitId,
        ]);
    }

    public function test_l_aaf_ne_valide_pas_un_pointage_pejedec_encore_soumis_ca(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('aaf');
        $sourcePejedec = $this->sourcePejedec();
        $periode = $this->periode();
        $stage = Stage::factory()->create(['source_financement_id' => $sourcePejedec->id]);
        Contrat::factory()->create(['stage_id' => $stage->id, 'statut' => 'VALIDE']);
        $pointage = $this->pointage($stage, $periode, 'SOUMIS');

        $this->actingAs($user)
            ->post("/pejedec/af/pointages/{$pointage->id}/valider")
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('decisions_pointages', [
            'pointage_id' => $pointage->id,
            'decision' => 'VALIDE_AAF',
        ]);
        $this->assertDatabaseMissing('droits_paiement', [
            'pointage_id' => $pointage->id,
        ]);
    }

    public function test_l_aaf_genere_le_paiement_apres_ouverture_du_droit_pejedec(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('aaf');
        $sourcePejedec = $this->sourcePejedec();
        $periode = $this->periode();
        $stage = Stage::factory()->create(['source_financement_id' => $sourcePejedec->id]);
        $droit = DroitPaiement::create([
            'uuid_public' => (string) Str::uuid(),
            'stage_id' => $stage->id,
            'pointage_id' => null,
            'periode_id' => $periode->id,
            'source_financement_id' => $sourcePejedec->id,
            'nature' => 'PRESENCE',
            'montant' => 12500,
            'statut' => 'OUVERT',
        ]);

        $this->actingAs($user)
            ->post("/pejedec/af/droits-paiement/{$droit->id}/generer")
            ->assertRedirect();

        $this->assertDatabaseHas('paiements', [
            'droit_paiement_id' => $droit->id,
            'montant' => 12500,
            'statut' => 'A_TRAITER',
        ]);
    }

    public function test_validation_dossier_pejedec_journalise_et_ouvre_le_pointage_pejedec(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('pejedec');
        $sourcePejedec = $this->sourcePejedec();
        $definition = DefinitionParcours::factory()->create(['code' => 'PAE', 'active' => true]);
        $etape = EtapeParcours::factory()->create([
            'definition_parcours_id' => $definition->id,
            'code' => 'EN_STAGE',
            'nom' => 'En stage',
            'code_corbeille' => CorbeilleEnum::EN_STAGE->value,
            'initiale' => true,
        ]);
        $stage = Stage::factory()->create([
            'source_financement_id' => $sourcePejedec->id,
            'date_debut' => '2026-08-12',
        ]);
        Contrat::factory()->create([
            'stage_id' => $stage->id,
            'statut' => 'VALIDE',
        ]);
        $instance = InstanceParcours::create([
            'definition_parcours_id' => $definition->id,
            'etape_courante_id' => $etape->id,
            'stage_id' => $stage->id,
            'corbeille_actuelle' => CorbeilleEnum::EN_STAGE->value,
        ]);

        $this->actingAs($user)
            ->post("/pejedec/dossiers/{$instance->id}/valider")
            ->assertRedirect();

        $this->assertDatabaseHas('instances_parcours', [
            'id' => $instance->id,
            'corbeille_actuelle' => CorbeilleEnum::CIP_POINTAGE_PEJEDEC->value,
        ]);

        $this->assertDatabaseHas('evenements_parcours', [
            'instance_parcours_id' => $instance->id,
            'type' => 'PEJEDEC_VALIDATION',
            'cle_idempotence' => "pejedec-validation:{$instance->id}",
        ]);

        $this->actingAs($user)
            ->get('/pejedec/valides')
            ->assertOk();
    }

    private function sourcePejedec(): SourceFinancement
    {
        return SourceFinancement::firstOrCreate(
            ['ancien_id' => 5],
            ['code' => 'PEJEDEC', 'nom' => 'PEJEDEC', 'actif' => true]
        );
    }

    private function periode(): Periode
    {
        return Periode::firstOrCreate(
            ['code' => '2026-08'],
            [
                'date_debut' => '2026-08-01',
                'date_fin' => '2026-08-31',
                'ouverte_pointage' => true,
                'ouverte_paiement' => true,
            ]
        );
    }

    private function pointage(Stage $stage, Periode $periode, string $statut): Pointage
    {
        $pointage = Pointage::create([
            'uuid_public' => (string) Str::uuid(),
            'stage_id' => $stage->id,
            'periode_id' => $periode->id,
            'nature' => 'MENSUEL',
            'statut' => $statut,
            'version_courante' => 1,
            'version_verrouillage' => 0,
        ]);

        VersionPointage::create([
            'pointage_id' => $pointage->id,
            'numero_version' => 1,
            'presence' => 'PRESENT',
            'jours_presents' => 12,
            'jours_absents' => 8,
        ]);

        return $pointage;
    }
}
