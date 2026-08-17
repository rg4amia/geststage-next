<?php

namespace Tests\Feature\Domain\Registration;

use App\Domain\Registration\Services\InscriptionStagiaireService;
use App\Domain\Workflow\Services\WorkflowTransitionService;
use App\Models\Beneficiary\Beneficiaire;
use App\Models\Company\Entreprise;
use App\Models\Company\OffreEmploi;
use App\Models\Internship\Stage;
use App\Models\Reference\Agence;
use App\Models\Reference\SourceFinancement;
use App\Models\Reference\TypeStage;
use App\Models\User;
use App\Models\Workflow\DefinitionParcours;
use App\Models\Workflow\EtapeParcours;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InscriptionStagiaireServiceTest extends TestCase
{
    use RefreshDatabase;

    private InscriptionStagiaireService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new InscriptionStagiaireService(new WorkflowTransitionService);
    }

    public function test_inscription_complete_reussie(): void
    {
        $roleCIP = Role::firstOrCreate(['name' => 'CIP']);
        $cip = User::factory()->create();
        $cip->assignRole($roleCIP);

        $definition = DefinitionParcours::factory()->create(['code' => 'PAE', 'active' => true]);
        EtapeParcours::factory()->create([
            'definition_parcours_id' => $definition->id,
            'role_responsable_id' => $roleCIP->id,
            'initiale' => true,
        ]);

        $agence = Agence::factory()->create();
        $entreprise = Entreprise::factory()->create(['agence_id' => $agence->id]);
        $typeStage = TypeStage::factory()->create();
        $source = SourceFinancement::factory()->create();

        $offre = OffreEmploi::factory()->create([
            'entreprise_id' => $entreprise->id,
            'agence_id' => $agence->id,
            'type_stage_id' => $typeStage->id,
            'source_financement_id' => $source->id,
        ]);

        $donneesBeneficiaire = [
            'numero_aej' => 'AEJ-123456',
            'nom' => 'Doe',
            'prenoms' => 'John',
            'date_naissance' => '2000-01-01',
            'sexe' => 'M',
        ];

        $donneesStage = [
            'entreprise_id' => $entreprise->id,
            'agence_id' => $agence->id,
            'type_stage_id' => $typeStage->id,
            'source_financement_id' => $source->id,
            'offre_emploi_id' => $offre->id,
            'intitule_poste' => 'Développeur Web',
            'date_debut' => '2026-09-01',
            'date_fin_prevue' => '2027-02-28',
        ];

        $donneesContrat = [
            'numero' => 'CTR-2026-0001',
            'date_debut' => '2026-09-01',
            'date_fin' => '2027-02-28',
            'prime_mensuelle' => 45000,
        ];

        $instance = $this->service->inscrire($donneesBeneficiaire, $donneesStage, $donneesContrat, $cip);

        $this->assertDatabaseHas('beneficiaires', ['nom' => 'Doe', 'prenoms' => 'John']);

        $beneficiaire = Beneficiaire::where('nom', 'Doe')->first();
        $this->assertDatabaseHas('stages', [
            'beneficiaire_id' => $beneficiaire->id,
            'intitule_poste' => 'Développeur Web',
        ]);

        $stage = Stage::where('beneficiaire_id', $beneficiaire->id)->first();
        $this->assertDatabaseHas('contrats', [
            'stage_id' => $stage->id,
            'numero' => 'CTR-2026-0001',
            'statut' => 'BROUILLON',
        ]);

        $this->assertDatabaseHas('instances_parcours', [
            'id' => $instance->id,
            'stage_id' => $stage->id,
        ]);

        $this->assertDatabaseHas('taches_parcours', [
            'instance_parcours_id' => $instance->id,
            'statut' => 'OUVERTE',
        ]);
    }
}
