<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('definitions_parcours', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 100);
            $table->string('nom');
            $table->unsignedInteger('version');
            $table->boolean('active')->default(false);
            $table->timestampTz('publiee_le')->nullable();
            $table->timestamps();
            $table->unique(['code', 'version']);
        });

        Schema::create('etapes_parcours', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('definition_parcours_id')->constrained('definitions_parcours')->restrictOnDelete();
            $table->foreignId('role_responsable_id')->nullable()->constrained('roles')->restrictOnDelete();
            $table->unsignedBigInteger('ancien_id')->nullable();
            $table->string('code', 120);
            $table->string('nom');
            $table->string('code_corbeille', 120)->nullable();
            $table->boolean('initiale')->default(false);
            $table->boolean('finale')->default(false);
            $table->unsignedInteger('ordre')->default(0);
            $table->timestamps();
            $table->unique(['definition_parcours_id', 'code']);
            $table->unique(['definition_parcours_id', 'ancien_id']);
            $table->index(['role_responsable_id', 'code_corbeille']);
        });

        Schema::create('transitions_parcours', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('definition_parcours_id')->constrained('definitions_parcours')->restrictOnDelete();
            $table->foreignId('etape_source_id')->constrained('etapes_parcours')->restrictOnDelete();
            $table->foreignId('etape_cible_id')->constrained('etapes_parcours')->restrictOnDelete();
            $table->foreignId('role_autorise_id')->constrained('roles')->restrictOnDelete();
            $table->string('code', 120);
            $table->string('nom');
            $table->jsonb('preconditions')->nullable();
            $table->jsonb('effets_metier')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['definition_parcours_id', 'code']);
            $table->index(['etape_source_id', 'active']);
        });

        Schema::create('instances_parcours', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid_public')->unique();
            $table->foreignId('definition_parcours_id')->constrained('definitions_parcours')->restrictOnDelete();
            $table->foreignId('etape_courante_id')->constrained('etapes_parcours')->restrictOnDelete();
            $table->foreignId('stage_id')->nullable()->unique()->constrained('stages')->restrictOnDelete();
            $table->foreignId('contrat_id')->nullable()->unique()->constrained('contrats')->restrictOnDelete();
            $table->foreignId('document_id')->nullable()->unique()->constrained('documents')->restrictOnDelete();
            $table->foreignId('pointage_id')->nullable()->unique()->constrained('pointages')->restrictOnDelete();
            $table->foreignId('paiement_id')->nullable()->unique()->constrained('paiements')->restrictOnDelete();
            $table->foreignId('dossier_paiement_id')->nullable()->unique()->constrained('dossiers_paiement')->restrictOnDelete();
            $table->foreignId('ordre_paiement_id')->nullable()->unique()->constrained('ordres_paiement')->restrictOnDelete();
            $table->foreignId('bordereau_paiement_id')->nullable()->unique()->constrained('bordereaux_paiement')->restrictOnDelete();
            $table->unsignedInteger('version_verrouillage')->default(0);
            $table->timestampTz('demarree_le')->useCurrent();
            $table->timestampTz('terminee_le')->nullable();
            $table->timestamps();
            $table->index(['etape_courante_id', 'terminee_le']);
        });

        Schema::create('taches_parcours', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid_public')->unique();
            $table->foreignId('instance_parcours_id')->constrained('instances_parcours')->restrictOnDelete();
            $table->foreignId('etape_parcours_id')->constrained('etapes_parcours')->restrictOnDelete();
            $table->foreignId('role_responsable_id')->constrained('roles')->restrictOnDelete();
            $table->foreignId('agence_id')->nullable()->constrained('agences')->restrictOnDelete();
            $table->foreignId('utilisateur_assigne_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('code_corbeille', 120);
            $table->string('statut', 30)->default('OUVERTE');
            $table->unsignedSmallInteger('priorite')->default(0);
            $table->timestampTz('ouverte_le')->useCurrent();
            $table->timestampTz('revendiquee_le')->nullable();
            $table->timestampTz('fermee_le')->nullable();
            $table->timestamps();
            $table->index(['code_corbeille', 'statut', 'agence_id', 'ouverte_le'], 'corbeille_taches_ouvertes_index');
            $table->index(['role_responsable_id', 'statut']);
        });

        Schema::create('evenements_parcours', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid_public')->unique();
            $table->foreignId('instance_parcours_id')->constrained('instances_parcours')->restrictOnDelete();
            $table->foreignId('transition_parcours_id')->nullable()->constrained('transitions_parcours')->restrictOnDelete();
            $table->foreignId('etape_source_id')->nullable()->constrained('etapes_parcours')->restrictOnDelete();
            $table->foreignId('etape_cible_id')->constrained('etapes_parcours')->restrictOnDelete();
            $table->foreignId('auteur_id')->constrained('users')->restrictOnDelete();
            $table->string('type', 100);
            $table->string('cle_idempotence', 150)->unique();
            $table->jsonb('donnees')->nullable();
            $table->timestampTz('survenu_le')->useCurrent();
            $table->timestamps();
            $table->index(['instance_parcours_id', 'survenu_le']);
        });

        Schema::create('ajournements', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid_public')->unique();
            $table->unsignedBigInteger('ancien_id')->nullable()->unique();
            $table->foreignId('instance_parcours_id')->constrained('instances_parcours')->restrictOnDelete();
            $table->foreignId('periode_id')->nullable()->constrained('periodes')->restrictOnDelete();
            $table->foreignId('etape_origine_id')->constrained('etapes_parcours')->restrictOnDelete();
            $table->foreignId('etape_correction_id')->constrained('etapes_parcours')->restrictOnDelete();
            $table->foreignId('etape_retour_id')->constrained('etapes_parcours')->restrictOnDelete();
            $table->foreignId('motif_ajournement_id')->constrained('motifs_ajournement')->restrictOnDelete();
            $table->foreignId('role_correcteur_id')->constrained('roles')->restrictOnDelete();
            $table->foreignId('auteur_id')->constrained('users')->restrictOnDelete();
            $table->string('code_corbeille_origine', 120);
            $table->string('code_corbeille_retour', 120);
            $table->text('motif_detaille');
            $table->text('correction_attendue');
            $table->unsignedInteger('numero_cycle');
            $table->string('statut', 30)->default('OUVERT');
            $table->timestampTz('ouvert_le')->useCurrent();
            $table->timestampTz('resoumis_le')->nullable();
            $table->timestampTz('clos_le')->nullable();
            $table->timestamps();
            $table->unique(['instance_parcours_id', 'numero_cycle']);
            $table->index(['role_correcteur_id', 'statut', 'created_at']);
        });

        Schema::create('exigences_ajournements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ajournement_id')->constrained('ajournements')->restrictOnDelete();
            $table->foreignId('type_document_id')->nullable()->constrained('types_document')->restrictOnDelete();
            $table->string('champ_cible', 150)->nullable();
            $table->text('description');
            $table->boolean('obligatoire')->default(true);
            $table->timestampTz('satisfaite_le')->nullable();
            $table->timestamps();
        });

        Schema::create('corrections_ajournements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ajournement_id')->constrained('ajournements')->restrictOnDelete();
            $table->foreignId('auteur_id')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('numero_version');
            $table->text('description');
            $table->jsonb('valeurs_corrigees')->nullable();
            $table->timestampTz('soumise_le')->useCurrent();
            $table->timestamps();
            $table->unique(['ajournement_id', 'numero_version']);
        });

        $this->createConstraints();
    }

    private function createConstraints(): void
    {
        if (in_array(DB::getDriverName(), ['pgsql', 'sqlite'], true)) {
            DB::statement('CREATE UNIQUE INDEX definition_parcours_active_unique ON definitions_parcours (code) WHERE active = true');
            DB::statement('CREATE UNIQUE INDEX etape_parcours_initiale_unique ON etapes_parcours (definition_parcours_id) WHERE initiale = true');
            DB::statement("CREATE UNIQUE INDEX parcours_une_tache_ouverte ON taches_parcours (instance_parcours_id) WHERE statut IN ('OUVERTE', 'REVENDIQUEE')");
        }

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE instances_parcours ADD CONSTRAINT parcours_exactement_un_objet CHECK (num_nonnulls(stage_id, contrat_id, document_id, pointage_id, paiement_id, dossier_paiement_id, ordre_paiement_id, bordereau_paiement_id) = 1)');
        DB::statement('ALTER TABLE instances_parcours ADD CONSTRAINT parcours_version_positive CHECK (version_verrouillage >= 0)');
        DB::statement("ALTER TABLE taches_parcours ADD CONSTRAINT taches_statut_valide CHECK (statut IN ('OUVERTE', 'REVENDIQUEE', 'TERMINEE', 'ANNULEE'))");
        DB::statement("ALTER TABLE taches_parcours ADD CONSTRAINT taches_dates_coherentes CHECK ((statut IN ('OUVERTE', 'REVENDIQUEE') AND fermee_le IS NULL) OR (statut IN ('TERMINEE', 'ANNULEE') AND fermee_le IS NOT NULL))");
        DB::statement('ALTER TABLE ajournements ADD CONSTRAINT ajournements_cycle_positif CHECK (numero_cycle > 0)');
        DB::statement("ALTER TABLE ajournements ADD CONSTRAINT ajournements_statut_valide CHECK (statut IN ('OUVERT', 'RESOUMIS', 'CLOS', 'ANNULE'))");
        DB::unprepared(<<<'SQL'
            CREATE FUNCTION interdire_mutation_evenement_parcours() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'Les evenements_parcours sont immuables';
            END;
            $$ LANGUAGE plpgsql;
            CREATE TRIGGER evenements_parcours_immuables
            BEFORE UPDATE OR DELETE ON evenements_parcours
            FOR EACH ROW EXECUTE FUNCTION interdire_mutation_evenement_parcours();
        SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP FUNCTION IF EXISTS interdire_mutation_evenement_parcours() CASCADE');
        }

        foreach (['corrections_ajournements', 'exigences_ajournements', 'ajournements', 'evenements_parcours', 'taches_parcours', 'instances_parcours', 'transitions_parcours', 'etapes_parcours', 'definitions_parcours'] as $tableName) {
            Schema::dropIfExists($tableName);
        }
    }
};
