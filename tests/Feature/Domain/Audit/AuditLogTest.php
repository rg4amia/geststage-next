<?php

namespace Tests\Feature\Domain\Audit;

use App\Models\Audit\JournalAudit;
use App\Models\Company\Entreprise;
use App\Models\Reference\Agence;
use App\Models\Reference\Region;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_model_creates_an_audit_log(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $region = Region::create(['code' => 'TEST', 'nom' => 'Region Test']);
        $agence = Agence::create(['region_id' => $region->id, 'code' => 'AG1', 'nom' => 'Agence Test']);

        $entreprise = Entreprise::create([
            'uuid_public' => (string) Str::uuid(),
            'agence_id' => $agence->id,
            'raison_sociale' => 'Ma Belle Entreprise',
        ]);

        $this->assertDatabaseHas('journaux_audit', [
            'action' => 'created',
            'modele_type' => Entreprise::class,
            'modele_id' => $entreprise->id,
            'user_id' => $user->id,
        ]);

        $log = JournalAudit::where('modele_type', Entreprise::class)->first();
        $this->assertNotNull($log->nouvelles_donnees);
        $this->assertNull($log->anciennes_donnees);
        $this->assertEquals('Ma Belle Entreprise', $log->nouvelles_donnees['raison_sociale']);
    }

    public function test_updating_a_model_creates_an_audit_log_with_changes(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $region = Region::create(['code' => 'TEST', 'nom' => 'Region Test']);
        $agence = Agence::create(['region_id' => $region->id, 'code' => 'AG1', 'nom' => 'Agence Test']);

        $entreprise = Entreprise::create([
            'uuid_public' => (string) Str::uuid(),
            'agence_id' => $agence->id,
            'raison_sociale' => 'Ma Belle Entreprise',
        ]);

        $entreprise->update(['raison_sociale' => 'Mon Entreprise Modifiée']);

        $this->assertDatabaseHas('journaux_audit', [
            'action' => 'updated',
            'modele_type' => Entreprise::class,
            'modele_id' => $entreprise->id,
        ]);

        $log = JournalAudit::where('modele_type', Entreprise::class)->where('action', 'updated')->first();
        $this->assertEquals('Ma Belle Entreprise', $log->anciennes_donnees['raison_sociale']);
        $this->assertEquals('Mon Entreprise Modifiée', $log->nouvelles_donnees['raison_sociale']);
    }

    public function test_deleting_a_model_creates_an_audit_log(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $region = Region::create(['code' => 'TEST', 'nom' => 'Region Test']);
        $agence = Agence::create(['region_id' => $region->id, 'code' => 'AG1', 'nom' => 'Agence Test']);

        $entreprise = Entreprise::create([
            'uuid_public' => (string) Str::uuid(),
            'agence_id' => $agence->id,
            'raison_sociale' => 'Ma Belle Entreprise',
        ]);

        $entreprise->delete();

        $this->assertDatabaseHas('journaux_audit', [
            'action' => 'deleted',
            'modele_type' => Entreprise::class,
            'modele_id' => $entreprise->id,
        ]);
    }
}
