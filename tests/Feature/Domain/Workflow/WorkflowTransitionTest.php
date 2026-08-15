<?php

namespace Tests\Feature\Domain\Workflow;

use App\Domain\Workflow\Services\WorkflowTransitionService;
use App\Models\User;
use App\Models\Workflow\EtapeParcours;
use App\Models\Workflow\InstanceParcours;
use App\Models\Workflow\TacheParcours;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WorkflowTransitionTest extends TestCase
{
    use RefreshDatabase;

    private WorkflowTransitionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(WorkflowTransitionService::class);
    }

    public function test_it_can_transition_an_instance_to_a_new_step()
    {
        $role = Role::create(['name' => 'cip', 'domaine' => 'Mes Stagiaires']);
        $acteur = User::factory()->create();

        $definition = \App\Models\Workflow\DefinitionParcours::create([
            'code' => 'STAGE',
            'nom' => 'Stage',
            'version' => 1,
            'active' => true,
        ]);

        $etape1 = EtapeParcours::create([
            'definition_parcours_id' => $definition->id,
            'code' => 'ETAPE_1',
            'nom' => 'Etape Initiale',
            'role_responsable_id' => $role->id,
            'code_corbeille' => 'CORBEILLE_1',
        ]);

        $etape2 = EtapeParcours::create([
            'definition_parcours_id' => $definition->id,
            'code' => 'ETAPE_2',
            'nom' => 'Deuxieme Etape',
            'role_responsable_id' => $role->id,
            'code_corbeille' => 'CORBEILLE_2',
        ]);

        // Creating dummy data for stage
        $region = \App\Models\Reference\Region::create(['code' => 'TEST', 'nom' => 'R']);
        $agence = \App\Models\Reference\Agence::create(['code' => 'A', 'nom' => 'A', 'region_id' => $region->id]);
        $typeStructure = \App\Models\Reference\TypeStructure::create(['code' => 'T', 'nom' => 'T']);
        $typeStage = \App\Models\Reference\TypeStage::create(['code' => 'TS', 'nom' => 'TS']);
        $financement = \App\Models\Reference\SourceFinancement::create(['code' => 'F', 'nom' => 'F']);
        
        $entreprise = \App\Models\Company\Entreprise::create(['raison_sociale' => 'E', 'agence_id' => $agence->id, 'type_structure_id' => $typeStructure->id]);
        $beneficiaire = \App\Models\Beneficiary\Beneficiaire::create(['nom' => 'B', 'prenoms' => 'B']);
        $stage = \App\Models\Internship\Stage::create([
            'entreprise_id' => $entreprise->id,
            'beneficiaire_id' => $beneficiaire->id,
            'agence_id' => $agence->id,
            'type_stage_id' => $typeStage->id,
            'source_financement_id' => $financement->id,
            'intitule_poste' => 'P',
            'date_debut' => now(),
            'date_fin_prevue' => now()->addMonths(6),
        ]);

        $instance = InstanceParcours::create([
            'definition_parcours_id' => $definition->id,
            'stage_id' => $stage->id,
            'etape_courante_id' => $etape1->id,
            'demarree_le' => now(),
        ]);

        $tacheOuverte = TacheParcours::create([
            'instance_parcours_id' => $instance->id,
            'etape_parcours_id' => $etape1->id,
            'role_responsable_id' => $role->id,
            'code_corbeille' => 'CORBEILLE_1',
            'statut' => 'OUVERTE',
            'ouverte_le' => now(),
        ]);

        // Effectuer la transition
        $nouvelleTache = $this->service->transitionner($instance, $etape2, $acteur, ['raison' => 'test']);

        // Vérifications
        $tacheOuverte->refresh();
        $this->assertEquals('TERMINEE', $tacheOuverte->statut);
        $this->assertNotNull($tacheOuverte->fermee_le);

        $instance->refresh();
        $this->assertEquals($etape2->id, $instance->etape_courante_id);

        $this->assertEquals('OUVERTE', $nouvelleTache->statut);
        $this->assertEquals($etape2->id, $nouvelleTache->etape_parcours_id);

        $this->assertDatabaseHas('evenements_parcours', [
            'instance_parcours_id' => $instance->id,
            'etape_source_id' => $etape1->id,
            'etape_cible_id' => $etape2->id,
            'type' => 'TRANSITION',
            'auteur_id' => $acteur->id,
        ]);
    }

    public function test_it_throws_an_exception_if_no_active_task_is_found()
    {
        $role = Role::create(['name' => 'cip', 'domaine' => 'Mes Stagiaires']);
        $acteur = User::factory()->create();

        $definition = \App\Models\Workflow\DefinitionParcours::create([
            'code' => 'STAGE',
            'nom' => 'Stage',
            'version' => 1,
            'active' => true,
        ]);

        $etape1 = EtapeParcours::create([
            'definition_parcours_id' => $definition->id,
            'code' => 'ETAPE_1',
            'nom' => 'Etape Initiale',
            'role_responsable_id' => $role->id,
            'code_corbeille' => 'CORBEILLE_1',
        ]);

        // Creating dummy data for stage
        $region = \App\Models\Reference\Region::create(['code' => 'TEST', 'nom' => 'R']);
        $agence = \App\Models\Reference\Agence::create(['code' => 'A', 'nom' => 'A', 'region_id' => $region->id]);
        $typeStructure = \App\Models\Reference\TypeStructure::create(['code' => 'T', 'nom' => 'T']);
        $typeStage = \App\Models\Reference\TypeStage::create(['code' => 'TS', 'nom' => 'TS']);
        $financement = \App\Models\Reference\SourceFinancement::create(['code' => 'F', 'nom' => 'F']);
        
        $entreprise = \App\Models\Company\Entreprise::create(['raison_sociale' => 'E', 'agence_id' => $agence->id, 'type_structure_id' => $typeStructure->id]);
        $beneficiaire = \App\Models\Beneficiary\Beneficiaire::create(['nom' => 'B', 'prenoms' => 'B']);
        $stage = \App\Models\Internship\Stage::create([
            'entreprise_id' => $entreprise->id,
            'beneficiaire_id' => $beneficiaire->id,
            'agence_id' => $agence->id,
            'type_stage_id' => $typeStage->id,
            'source_financement_id' => $financement->id,
            'intitule_poste' => 'P',
            'date_debut' => now(),
            'date_fin_prevue' => now()->addMonths(6),
        ]);

        $instance = InstanceParcours::create([
            'definition_parcours_id' => $definition->id,
            'stage_id' => $stage->id,
            'etape_courante_id' => $etape1->id,
            'demarree_le' => now(),
        ]);

        $this->expectException(\LogicException::class);

        $this->service->transitionner($instance, $etape1, $acteur);
    }
}
