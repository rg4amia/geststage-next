<?php

namespace Tests\Feature\Database;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class InitialSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_initial_schema_contains_every_requested_domain(): void
    {
        $tables = [
            'users', 'roles', 'permissions', 'regions', 'agences', 'communes',
            'conseillers', 'periodes', 'types_stage', 'types_structure',
            'sources_financement', 'programmes', 'entreprises', 'offres_emploi',
            'beneficiaires', 'stages', 'contrats', 'documents', 'versions_documents',
            'definitions_parcours', 'etapes_parcours', 'transitions_parcours',
            'instances_parcours', 'taches_parcours', 'evenements_parcours',
            'ajournements', 'exigences_ajournements', 'corrections_ajournements',
            'pointages', 'droits_paiement', 'paiements', 'dossiers_paiement',
            'dossiers_groupes', 'ordre_paiements', 'bordereau_paiements',
            'conservations_contrats_pae', 'correspondances_colonnes_contrats_pae',
            'conservations_referentiels_legacy', 'correspondances_valeurs_referentiels',
            'model_has_roles', 'model_has_permissions', 'role_has_permissions',
        ];

        foreach ($tables as $table) {
            $this->assertTrue(Schema::hasTable($table), "La table {$table} doit exister.");
        }

        $this->assertFalse(Schema::hasTable('utilisateurs'));
    }

    public function test_workflow_position_has_a_single_schema_source_of_truth(): void
    {
        $this->assertTrue(Schema::hasColumn('instances_parcours', 'etape_courante_id'));
        $this->assertFalse(Schema::hasColumn('stages', 'etape_courante_id'));
        $this->assertFalse(Schema::hasColumn('stages', 'statut_workflow'));
        $this->assertFalse(Schema::hasColumn('contrats', 'etape_courante_id'));
    }

    public function test_all_155_legacy_contract_columns_have_a_conservation_strategy(): void
    {
        $this->seed(DatabaseSeeder::class);

        $mappings = DB::table('correspondances_colonnes_contrats_pae')->get();

        $this->assertCount(155, $mappings);
        $this->assertSame(155, $mappings->pluck('nom_colonne_source')->unique()->count());
        $this->assertSame(0, $mappings->whereNotIn('strategie_conservation', [
            'NORMALISEE',
            'ARCHIVEE',
            'NORMALISEE_ET_ARCHIVEE',
            'A_RECONCILIER',
        ])->count());
    }

    public function test_an_instance_cannot_have_two_active_tasks(): void
    {
        $ids = $this->createWorkflowInstance();

        DB::table('taches_parcours')->insert([
            'uuid_public' => (string) Str::uuid(),
            'instance_parcours_id' => $ids['instance'],
            'etape_parcours_id' => $ids['etape'],
            'role_responsable_id' => $ids['role'],
            'code_corbeille' => 'CIP_MES_STAGIAIRES',
            'statut' => 'OUVERTE',
            'ouverte_le' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        DB::table('taches_parcours')->insert([
            'uuid_public' => (string) Str::uuid(),
            'instance_parcours_id' => $ids['instance'],
            'etape_parcours_id' => $ids['etape'],
            'role_responsable_id' => $ids['role'],
            'code_corbeille' => 'CA_ATTENTE_VALIDATION',
            'statut' => 'REVENDIQUEE',
            'ouverte_le' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_adjournments_require_origin_correction_return_reason_and_cycle_fields(): void
    {
        $this->assertTrue(Schema::hasColumns('ajournements', [
            'etape_origine_id',
            'code_corbeille_origine',
            'motif_ajournement_id',
            'motif_detaille',
            'correction_attendue',
            'etape_correction_id',
            'etape_retour_id',
            'code_corbeille_retour',
            'numero_cycle',
        ]));
    }

    /**
     * @return array{instance: int, etape: int, role: int}
     */
    private function createWorkflowInstance(): array
    {
        $utilisateur = User::factory()->create();
        $this->seed(DatabaseSeeder::class);
        $role = DB::table('roles')->where('name', 'cip')->value('id');
        $region = DB::table('regions')->insertGetId([
            'code' => 'ABJ',
            'nom' => 'Abidjan',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $agence = DB::table('agences')->insertGetId([
            'region_id' => $region,
            'code' => 'AG-TEST',
            'nom' => 'Agence de test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $typeStructure = $this->createReference('types_structure', 'ENTREPRISE');
        $typeStage = $this->createReference('types_stage', 'PAE');
        $financement = $this->createReference('sources_financement', 'BUDGET_ETAT');
        $entreprise = DB::table('entreprises')->insertGetId([
            'uuid_public' => (string) Str::uuid(),
            'agence_id' => $agence,
            'type_structure_id' => $typeStructure,
            'raison_sociale' => 'Entreprise de test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $beneficiaire = DB::table('beneficiaires')->insertGetId([
            'uuid_public' => (string) Str::uuid(),
            'nom' => 'KOFFI',
            'prenoms' => 'Awa',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $stage = DB::table('stages')->insertGetId([
            'uuid_public' => (string) Str::uuid(),
            'beneficiaire_id' => $beneficiaire,
            'entreprise_id' => $entreprise,
            'agence_id' => $agence,
            'type_stage_id' => $typeStage,
            'source_financement_id' => $financement,
            'intitule_poste' => 'Assistante',
            'date_debut' => '2026-08-01',
            'date_fin_prevue' => '2027-01-31',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $definition = DB::table('definitions_parcours')->insertGetId([
            'code' => 'STAGE',
            'nom' => 'Parcours stage',
            'version' => 1,
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $etape = DB::table('etapes_parcours')->insertGetId([
            'definition_parcours_id' => $definition,
            'role_responsable_id' => $role,
            'code' => 'CIP_PREPARATION_STAGE',
            'nom' => 'Préparation du stage',
            'code_corbeille' => 'CIP_MES_STAGIAIRES',
            'initiale' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $instance = DB::table('instances_parcours')->insertGetId([
            'uuid_public' => (string) Str::uuid(),
            'definition_parcours_id' => $definition,
            'etape_courante_id' => $etape,
            'stage_id' => $stage,
            'demarree_le' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertNotNull($utilisateur->id);

        return ['instance' => $instance, 'etape' => $etape, 'role' => $role];
    }

    private function createReference(string $table, string $code): int
    {
        return DB::table($table)->insertGetId([
            'code' => $code,
            'nom' => $code,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
