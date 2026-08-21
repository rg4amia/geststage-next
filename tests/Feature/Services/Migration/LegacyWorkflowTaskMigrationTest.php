<?php

namespace Tests\Feature\Services\Migration;

use App\Console\Commands\MigrateLegacyDataCommand;
use App\Enums\CorbeilleEnum;
use App\Models\Internship\Stage;
use App\Models\Workflow\DefinitionParcours;
use App\Models\Workflow\EtapeParcours;
use App\Models\Workflow\InstanceParcours;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LegacyWorkflowTaskMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_keeps_one_open_task_and_closes_it_for_a_terminal_instance(): void
    {
        $chefAgence = Role::create(['name' => 'chef_agence', 'guard_name' => 'web']);
        $dmg = Role::create(['name' => 'dmg', 'guard_name' => 'web']);
        $definition = DefinitionParcours::create([
            'code' => 'STAGE_LEGACY_TEST',
            'nom' => 'Stage legacy test',
            'version' => 1,
            'active' => true,
        ]);
        $etapeCa = EtapeParcours::create([
            'definition_parcours_id' => $definition->id,
            'role_responsable_id' => $chefAgence->id,
            'code' => 'CA_ATTENTE_VALIDATION_DEMARRAGE',
            'nom' => 'CA attente',
        ]);
        $etapeDmg = EtapeParcours::create([
            'definition_parcours_id' => $definition->id,
            'role_responsable_id' => $dmg->id,
            'code' => 'DMG_ATTENTE_PAIEMENT_DEMARRAGE',
            'nom' => 'DMG attente',
        ]);
        $stage = Stage::factory()->create(['ancien_id' => 701]);
        $instance = InstanceParcours::create([
            'definition_parcours_id' => $definition->id,
            'etape_courante_id' => $etapeCa->id,
            'stage_id' => $stage->id,
            'corbeille_actuelle' => CorbeilleEnum::CA_ATTENTE_VALIDATION_DEMARRAGE->value,
        ]);

        $method = new ReflectionMethod(MigrateLegacyDataCommand::class, 'syncOpenTask');
        $command = app(MigrateLegacyDataCommand::class);

        $method->invoke(
            $command,
            $instance,
            $etapeCa,
            CorbeilleEnum::CA_ATTENTE_VALIDATION_DEMARRAGE,
            $stage->agence_id,
            false,
        );
        $method->invoke(
            $command,
            $instance,
            $etapeCa,
            CorbeilleEnum::CA_ATTENTE_VALIDATION_DEMARRAGE,
            $stage->agence_id,
            false,
        );
        $this->assertSame(1, $instance->taches()->whereIn('statut', ['OUVERTE', 'REVENDIQUEE'])->count());

        $instance->update([
            'etape_courante_id' => $etapeDmg->id,
            'corbeille_actuelle' => CorbeilleEnum::DMG_ATTENTE_PAIEMENT_DEMARRAGE->value,
        ]);
        $method->invoke(
            $command,
            $instance,
            $etapeDmg,
            CorbeilleEnum::DMG_ATTENTE_PAIEMENT_DEMARRAGE,
            $stage->agence_id,
            false,
        );

        $this->assertSame(1, $instance->taches()->whereIn('statut', ['OUVERTE', 'REVENDIQUEE'])->count());
        $this->assertDatabaseHas('taches_parcours', [
            'instance_parcours_id' => $instance->id,
            'code_corbeille' => CorbeilleEnum::CA_ATTENTE_VALIDATION_DEMARRAGE->value,
            'statut' => 'ANNULEE',
        ]);

        $method->invoke(
            $command,
            $instance,
            $etapeDmg,
            CorbeilleEnum::DMG_ATTENTE_PAIEMENT_DEMARRAGE,
            $stage->agence_id,
            true,
        );

        $this->assertSame(0, $instance->taches()->whereIn('statut', ['OUVERTE', 'REVENDIQUEE'])->count());
        $this->assertDatabaseHas('taches_parcours', [
            'instance_parcours_id' => $instance->id,
            'code_corbeille' => CorbeilleEnum::DMG_ATTENTE_PAIEMENT_DEMARRAGE->value,
            'statut' => 'TERMINEE',
        ]);
    }
}
