<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('executions_migration', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid_public')->unique();
            $table->string('version_source', 100);
            $table->string('statut', 40)->default('EN_COURS');
            $table->jsonb('compteurs')->nullable();
            $table->timestampTz('demarree_le')->useCurrent();
            $table->timestampTz('terminee_le')->nullable();
            $table->timestamps();
        });

        Schema::create('conservations_contrats_pae', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('execution_migration_id')->constrained('executions_migration')->restrictOnDelete();
            $table->unsignedBigInteger('contrat_pae_ancien_id')->unique();
            $table->foreignId('beneficiaire_id')->nullable()->constrained('beneficiaires')->restrictOnDelete();
            $table->foreignId('stage_id')->nullable()->constrained('stages')->restrictOnDelete();
            $table->foreignId('contrat_id')->nullable()->constrained('contrats')->restrictOnDelete();
            $table->unsignedInteger('nombre_colonnes_source');
            $table->jsonb('donnees_originales');
            $table->char('empreinte_originale', 64);
            $table->string('version_schema_source', 50);
            $table->timestampTz('importe_le')->useCurrent();
            $table->timestamps();
            $table->unique(['contrat_pae_ancien_id', 'empreinte_originale'], 'conservation_contrat_pae_empreinte_unique');
        });

        Schema::create('correspondances_colonnes_contrats_pae', function (Blueprint $table): void {
            $table->id();
            $table->string('nom_colonne_source', 150)->unique();
            $table->string('table_cible', 150)->nullable();
            $table->string('colonne_cible', 150)->nullable();
            $table->string('strategie_conservation', 40);
            $table->boolean('obligatoire')->default(true);
            $table->text('commentaire')->nullable();
            $table->timestampTz('valide_le')->nullable();
            $table->timestamps();
        });

        Schema::create('conservations_referentiels_legacy', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('execution_migration_id')->constrained('executions_migration')->restrictOnDelete();
            $table->string('nom_table_source', 150);
            $table->string('id_source', 100);
            $table->string('table_cible', 150)->nullable();
            $table->unsignedBigInteger('id_cible')->nullable();
            $table->jsonb('donnees_originales');
            $table->char('empreinte_originale', 64);
            $table->timestampTz('importe_le')->useCurrent();
            $table->timestamps();
            $table->unique(['nom_table_source', 'id_source']);
        });

        Schema::create('correspondances_valeurs_referentiels', function (Blueprint $table): void {
            $table->id();
            $table->string('nom_table_source', 150);
            $table->string('id_source', 100);
            $table->string('libelle_source')->nullable();
            $table->string('table_cible', 150);
            $table->unsignedBigInteger('id_cible')->nullable();
            $table->string('statut_correspondance', 40)->default('A_RECONCILIER');
            $table->text('commentaire')->nullable();
            $table->timestampTz('valide_le')->nullable();
            $table->timestamps();
            $table->unique(['nom_table_source', 'id_source', 'table_cible'], 'correspondance_valeur_referentiel_unique');
        });

        Schema::create('correspondances_ancien_systeme', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('execution_migration_id')->constrained('executions_migration')->restrictOnDelete();
            $table->string('table_source', 150);
            $table->string('id_source', 100);
            $table->string('table_cible', 150);
            $table->unsignedBigInteger('id_cible');
            $table->char('empreinte_source', 64)->nullable();
            $table->timestamps();
            $table->unique(['table_source', 'id_source', 'table_cible'], 'correspondance_ancien_systeme_unique');
        });

        Schema::create('anomalies_migration', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('execution_migration_id')->constrained('executions_migration')->restrictOnDelete();
            $table->string('code', 100);
            $table->string('table_source', 150);
            $table->string('id_source', 100)->nullable();
            $table->string('gravite', 30)->default('BLOQUANTE');
            $table->string('statut', 40)->default('A_RECONCILIER');
            $table->text('description');
            $table->jsonb('donnees')->nullable();
            $table->foreignId('resolue_par_id')->nullable()->constrained('utilisateurs')->nullOnDelete();
            $table->timestampTz('resolue_le')->nullable();
            $table->timestamps();
            $table->index(['statut', 'gravite']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE conservations_contrats_pae ADD CONSTRAINT conservation_nombre_colonnes_positif CHECK (nombre_colonnes_source > 0)');
            DB::statement("ALTER TABLE correspondances_colonnes_contrats_pae ADD CONSTRAINT strategie_conservation_valide CHECK (strategie_conservation IN ('NORMALISEE', 'ARCHIVEE', 'NORMALISEE_ET_ARCHIVEE', 'A_RECONCILIER'))");
            DB::statement("ALTER TABLE correspondances_valeurs_referentiels ADD CONSTRAINT statut_correspondance_valide CHECK (statut_correspondance IN ('MIGREE', 'FUSIONNEE', 'ORPHELINE', 'A_RECONCILIER'))");
            DB::statement("ALTER TABLE anomalies_migration ADD CONSTRAINT anomalies_statut_valide CHECK (statut IN ('A_RECONCILIER', 'RESOLUE', 'ACCEPTEE'))");
        }
    }

    public function down(): void
    {
        foreach (['anomalies_migration', 'correspondances_ancien_systeme', 'correspondances_valeurs_referentiels', 'conservations_referentiels_legacy', 'correspondances_colonnes_contrats_pae', 'conservations_contrats_pae', 'executions_migration'] as $tableName) {
            Schema::dropIfExists($tableName);
        }
    }
};
