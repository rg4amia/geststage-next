<?php

namespace Tests\Feature\Domain\Workflow;

use App\Enums\CorbeilleEnum;
use App\Domain\Workflow\Services\WorkflowTransitionService;
use App\Models\User;
use App\Models\Internship\Stage;
use App\Models\Workflow\DefinitionParcours;
use App\Models\Workflow\EtapeParcours;
use App\Models\Workflow\InstanceParcours;
use App\Models\Workflow\TacheParcours;
use App\Models\Workflow\EvenementParcours;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WorkflowTransitionServiceTest extends TestCase
{
    use RefreshDatabase;

    private WorkflowTransitionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new WorkflowTransitionService();
    }

    public function test_initier_cree_instance_tache_et_evenement(): void
    {
        $roleCIP = Role::firstOrCreate(['name' => 'CIP']);
        $acteur = User::factory()->create();
        $acteur->assignRole($roleCIP);

        $definition = DefinitionParcours::factory()->create(['code' => 'PAE', 'active' => true]);
        
        $etapeInitiale = EtapeParcours::factory()->create([
            'definition_parcours_id' => $definition->id,
            'role_responsable_id' => $roleCIP->id,
            'code' => 'CIP_INSCRIPTION',
            'initiale' => true,
            'code_corbeille' => 'CORBEILLE_CIP'
        ]);

        $stage = \App\Models\Internship\Stage::factory()->create();

        $instance = $this->service->initier(
            $definition,
            $acteur,
            ['stage_id' => $stage->id],
            ['motif' => 'Inscription initiale']
        );

        $this->assertInstanceOf(InstanceParcours::class, $instance);
        $this->assertEquals($etapeInitiale->id, $instance->etape_courante_id);
        $this->assertEquals($stage->id, $instance->stage_id);
        $this->assertNull($instance->terminee_le);

        $tache = TacheParcours::where('instance_parcours_id', $instance->id)->first();
        $this->assertNotNull($tache);
        $this->assertEquals('OUVERTE', $tache->statut);
        $this->assertEquals('CORBEILLE_CIP', $tache->code_corbeille);
        $this->assertEquals($etapeInitiale->id, $tache->etape_parcours_id);

        $evenement = EvenementParcours::where('instance_parcours_id', $instance->id)->first();
        $this->assertNotNull($evenement);
        $this->assertEquals('INITIALISATION', $evenement->type);
        $this->assertEquals($acteur->id, $evenement->auteur_id);
        $this->assertEquals('Inscription initiale', $evenement->donnees['motif']);
    }

    public function test_submit_to_chef_agence_routes_to_demarrage_when_stage_starts_this_month(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-16'));

        $definition = DefinitionParcours::factory()->create(['code' => 'PAE', 'active' => true]);
        $etape = EtapeParcours::factory()->create([
            'definition_parcours_id' => $definition->id,
            'code' => 'CIP_INSCRIPTION',
            'initiale' => true,
            'role_responsable_id' => null,
            'code_corbeille' => CorbeilleEnum::CIP_MES_STAGIAIRES->value,
        ]);
        $stage = Stage::factory()->create([
            'date_debut' => '2026-08-10',
        ]);

        $instance = InstanceParcours::create([
            'definition_parcours_id' => $definition->id,
            'etape_courante_id' => $etape->id,
            'stage_id' => $stage->id,
        ]);

        $this->service->submitToChefAgence($instance->fresh('stage'));

        $this->assertDatabaseHas('instances_parcours', [
            'id' => $instance->id,
            'corbeille_actuelle' => CorbeilleEnum::CA_ATTENTE_VALIDATION_DEMARRAGE->value,
        ]);
    }

    public function test_submit_to_chef_agence_routes_to_omis_when_stage_started_before_this_month(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-16'));

        $definition = DefinitionParcours::factory()->create(['code' => 'PAE', 'active' => true]);
        $etape = EtapeParcours::factory()->create([
            'definition_parcours_id' => $definition->id,
            'code' => 'CIP_INSCRIPTION',
            'initiale' => true,
            'role_responsable_id' => null,
            'code_corbeille' => CorbeilleEnum::CIP_MES_STAGIAIRES->value,
        ]);
        $stage = Stage::factory()->create([
            'date_debut' => '2026-07-10',
        ]);

        $instance = InstanceParcours::create([
            'definition_parcours_id' => $definition->id,
            'etape_courante_id' => $etape->id,
            'stage_id' => $stage->id,
        ]);

        $this->service->submitToChefAgence($instance->fresh('stage'));

        $this->assertDatabaseHas('instances_parcours', [
            'id' => $instance->id,
            'corbeille_actuelle' => CorbeilleEnum::CA_ATTENTE_VALIDATION_OMIS->value,
        ]);
    }
}
