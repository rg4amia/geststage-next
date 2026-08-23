<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pointages', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid_public')->unique();
            $table->unsignedBigInteger('ancien_id')->nullable()->unique();
            $table->foreignId('stage_id')->constrained('stages')->restrictOnDelete();
            $table->foreignId('periode_id')->constrained('periodes')->restrictOnDelete();
            $table->string('nature', 30);
            $table->string('statut', 40)->default('BROUILLON');
            $table->unsignedInteger('version_courante')->default(1);
            $table->unsignedInteger('version_verrouillage')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['periode_id', 'nature', 'statut']);
        });

        Schema::create('versions_pointages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pointage_id')->constrained('pointages')->restrictOnDelete();
            $table->foreignId('saisi_par_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('numero_version');
            $table->string('presence', 30);
            $table->unsignedInteger('jours_presents')->default(0);
            $table->unsignedInteger('jours_absents')->default(0);
            $table->text('observation')->nullable();
            $table->jsonb('donnees_complementaires')->nullable();
            // Rattache chaque resoumission legacy (pointage_models) à sa version, pour que
            // la migration reste idempotente (un stagiaire pouvant avoir plusieurs
            // pointage_models pour le même stage/période à cause des corrections/ajournements).
            $table->unsignedBigInteger('ancien_id')->nullable()->unique();
            $table->timestampTz('saisi_le')->useCurrent();
            $table->timestamps();
            $table->unique(['pointage_id', 'numero_version']);
        });

        Schema::create('decisions_pointages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pointage_id')->constrained('pointages')->restrictOnDelete();
            $table->foreignId('version_pointage_id')->constrained('versions_pointages')->restrictOnDelete();
            $table->foreignId('auteur_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('decision', 40);
            $table->text('motif')->nullable();
            $table->timestampTz('decide_le')->useCurrent();
            $table->timestamps();
        });

        Schema::create('droits_paiement', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid_public')->unique();
            $table->unsignedBigInteger('ancien_id')->nullable()->unique();
            $table->foreignId('stage_id')->constrained('stages')->restrictOnDelete();
            $table->foreignId('pointage_id')->nullable()->constrained('pointages')->restrictOnDelete();
            $table->foreignId('periode_id')->constrained('periodes')->restrictOnDelete();
            $table->foreignId('source_financement_id')->constrained('sources_financement')->restrictOnDelete();
            $table->string('nature', 30);
            $table->decimal('montant', 15, 2);
            $table->string('statut', 40)->default('OUVERT');
            $table->timestampTz('ouvert_le')->useCurrent();
            $table->timestampTz('annule_le')->nullable();
            $table->text('motif_annulation')->nullable();
            $table->timestamps();
            $table->index(['periode_id', 'nature', 'statut']);
            $table->index('stage_id');
            $table->index('pointage_id');
            $table->index('source_financement_id');
        });

        Schema::create('paiements', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid_public')->unique();
            $table->unsignedBigInteger('ancien_id')->nullable()->unique();
            $table->foreignId('droit_paiement_id')->constrained('droits_paiement')->restrictOnDelete();
            $table->foreignId('compte_paiement_beneficiaire_id')->nullable()->constrained('comptes_paiement_beneficiaires')->restrictOnDelete();
            $table->decimal('montant', 15, 2);
            $table->string('statut', 40)->default('A_TRAITER');
            $table->string('reference_externe')->nullable()->unique();
            $table->timestampTz('paye_le')->nullable();
            $table->string('corbeille_actuelle', 50)->nullable();
            $table->string('statut_dossier_physique', 30)->nullable();
            $table->foreignId('dossier_physique_marque_par_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('dossier_physique_marque_le')->nullable();
            $table->unsignedInteger('version_verrouillage')->default(0);
            $table->timestamps();
            $table->index(['statut', 'created_at']);
            $table->index('droit_paiement_id');
            $table->index('corbeille_actuelle');
            $table->index('statut_dossier_physique');
        });

        Schema::create('bordereau_paiements', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid_public')->unique();
            $table->unsignedBigInteger('ancien_id')->nullable()->unique();
            $table->string('numero', 50)->unique();
            $table->foreignId('periode_id')->constrained('periodes')->onDelete('cascade');
            $table->foreignId('source_financement_id')->nullable()->constrained('sources_financement')->restrictOnDelete();
            $table->decimal('montant_total', 15, 2)->default(0);
            $table->string('statut', 30)->default('TRANSMIS_AC');
            $table->timestamps();
        });

        Schema::create('ordre_paiements', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid_public')->unique();
            $table->unsignedBigInteger('ancien_id')->nullable()->unique();
            $table->string('numero', 50)->unique();
            $table->foreignId('periode_id')->constrained('periodes')->onDelete('cascade');
            $table->foreignId('source_financement_id')->nullable()->constrained('sources_financement')->restrictOnDelete();
            $table->foreignId('bordereau_paiement_id')->nullable()->constrained('bordereau_paiements')->onDelete('set null');
            $table->decimal('montant_total', 15, 2)->default(0);
            $table->string('statut', 30)->default('BROUILLON');
            $table->timestamps();
        });

        Schema::create('dossiers_paiement', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid_public')->unique();
            $table->unsignedBigInteger('ancien_id')->nullable()->unique();
            $table->foreignId('periode_id')->constrained('periodes')->restrictOnDelete();
            // Nullable : ~1 624 dossiers legacy sont multi-agences et n'ont pas d'agence
            // unique portée par le dossier (voir MigrateLegacyDataCommand).
            $table->foreignId('agence_id')->nullable()->constrained('agences')->restrictOnDelete();
            $table->foreignId('source_financement_id')->constrained('sources_financement')->restrictOnDelete();
            $table->foreignId('ordre_paiement_id')->nullable()->constrained('ordre_paiements')->onDelete('set null');
            $table->string('numero')->unique();
            $table->string('nature', 10);
            $table->string('statut', 40)->default('BROUILLON');
            $table->decimal('montant_total', 15, 2)->default(0);
            $table->timestamps();
            $table->index(['periode_id', 'agence_id', 'nature', 'statut']);
            $table->index('agence_id');
        });

        Schema::create('lignes_dossiers_paiement', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('dossier_paiement_id')->constrained('dossiers_paiement')->restrictOnDelete();
            $table->foreignId('paiement_id')->constrained('paiements')->restrictOnDelete();
            $table->decimal('montant', 15, 2);
            $table->timestampTz('ajoute_le')->useCurrent();
            $table->timestampTz('retire_le')->nullable();
            $table->text('motif_retrait')->nullable();
            $table->timestamps();
            $table->index(['dossier_paiement_id', 'retire_le']);
        });

        Schema::create('dossiers_groupes', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid_public')->unique();
            $table->unsignedBigInteger('ancien_id')->nullable()->unique();
            $table->foreignId('periode_id')->constrained('periodes')->restrictOnDelete();
            $table->foreignId('source_financement_id')->nullable()->constrained('sources_financement')->restrictOnDelete();
            $table->foreignId('cree_par_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('numero')->unique();
            $table->string('nature', 10)->nullable();
            $table->string('statut', 40)->default('BROUILLON');
            $table->decimal('montant_total', 15, 2)->default(0);
            $table->text('observation')->nullable();
            $table->string('attestation_path')->nullable();
            $table->string('etat_financier_path')->nullable();
            $table->timestamps();
        });

        Schema::create('lignes_dossiers_groupes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('dossier_groupe_id')->constrained('dossiers_groupes')->restrictOnDelete();
            $table->foreignId('dossier_paiement_id')->constrained('dossiers_paiement')->restrictOnDelete();
            $table->timestampTz('ajoute_le')->useCurrent();
            $table->timestampTz('retire_le')->nullable();
            $table->text('motif_retrait')->nullable();
            $table->timestamps();
            $table->index(['dossier_groupe_id', 'retire_le']);
        });

        Schema::create('decisions_paiements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('paiement_id')->constrained('paiements')->restrictOnDelete();
            $table->foreignId('auteur_id')->constrained('users')->restrictOnDelete();
            $table->string('decision', 60);
            $table->string('statut_avant', 40)->nullable();
            $table->string('statut_apres', 40)->nullable();
            $table->text('motif')->nullable();
            $table->timestampTz('decide_le')->useCurrent();
            $table->timestamps();
            $table->index(['paiement_id', 'decide_le']);
        });

        $this->createPartialIndexes();
        $this->createPostgreSqlChecks();
    }

    private function createPartialIndexes(): void
    {
        if (in_array(DB::getDriverName(), ['pgsql', 'sqlite'], true)) {
            DB::statement('CREATE UNIQUE INDEX pointage_unique_periode_nature ON pointages (stage_id, periode_id, nature) WHERE deleted_at IS NULL');
            DB::statement('CREATE UNIQUE INDEX droit_paiement_unique_periode_nature ON droits_paiement (stage_id, periode_id, nature) WHERE annule_le IS NULL');
            DB::statement('CREATE UNIQUE INDEX paiement_un_dossier_actif ON lignes_dossiers_paiement (paiement_id) WHERE retire_le IS NULL');
            DB::statement('CREATE UNIQUE INDEX dossier_un_groupe_actif ON lignes_dossiers_groupes (dossier_paiement_id) WHERE retire_le IS NULL');
        }
    }

    private function createPostgreSqlChecks(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("ALTER TABLE pointages ADD CONSTRAINT pointages_nature_valide CHECK (nature IN ('DEMARRAGE', 'PRESENCE'))");
        DB::statement("ALTER TABLE droits_paiement ADD CONSTRAINT droits_nature_montant_valides CHECK (nature IN ('DEMARRAGE', 'PRESENCE') AND montant >= 0)");
        DB::statement('ALTER TABLE paiements ADD CONSTRAINT paiements_montant_valide CHECK (montant >= 0)');
        DB::statement("ALTER TABLE dossiers_paiement ADD CONSTRAINT dossiers_nature_montant_valides CHECK (nature IN ('DM', 'PS') AND montant_total >= 0)");
        DB::statement('ALTER TABLE lignes_dossiers_paiement ADD CONSTRAINT lignes_dossiers_montant_valide CHECK (montant >= 0)');
        DB::statement('ALTER TABLE dossiers_groupes ADD CONSTRAINT dossiers_groupes_montant_valide CHECK (montant_total >= 0)');
        DB::statement('ALTER TABLE ordre_paiements ADD CONSTRAINT ordre_paiements_montant_valide CHECK (montant_total >= 0)');
        DB::statement('ALTER TABLE bordereau_paiements ADD CONSTRAINT bordereau_paiements_montant_valide CHECK (montant_total >= 0)');
    }

    public function down(): void
    {
        foreach ([
            'decisions_paiements',
            'lignes_dossiers_groupes',
            'dossiers_groupes',
            'lignes_dossiers_paiement',
            'dossiers_paiement',
            'ordre_paiements',
            'bordereau_paiements',
            'paiements',
            'droits_paiement',
            'decisions_pointages',
            'versions_pointages',
            'pointages',
        ] as $tableName) {
            Schema::dropIfExists($tableName);
        }
    }
};
