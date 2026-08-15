<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('ancien_id')->nullable()->unique();
            $table->string('code', 50)->unique();
            $table->string('nom');
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });

        Schema::create('communes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('ancien_id')->nullable()->unique();
            $table->foreignId('region_id')->nullable()->constrained('regions')->restrictOnDelete();
            $table->string('code', 50)->unique();
            $table->string('nom');
            $table->boolean('actif')->default(true);
            $table->timestamps();
            $table->index(['region_id', 'actif']);
        });

        Schema::create('agences', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('ancien_id')->nullable()->unique();
            $table->foreignId('region_id')->nullable()->constrained('regions')->restrictOnDelete();
            $table->foreignId('commune_id')->nullable()->constrained('communes')->restrictOnDelete();
            $table->string('code', 50)->unique();
            $table->string('nom');
            $table->string('adresse')->nullable();
            $table->boolean('actif')->default(true);
            $table->timestamps();
            $table->index(['region_id', 'actif']);
        });

        Schema::create('conseillers', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('ancien_id')->nullable()->unique();
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            $table->foreignId('agence_id')->constrained('agences')->restrictOnDelete();
            $table->string('matricule', 100)->nullable()->unique();
            $table->string('nom');
            $table->string('prenoms')->nullable();
            $table->boolean('actif')->default(true);
            $table->timestamps();
            $table->index(['agence_id', 'actif']);
        });

        Schema::create('perimetres_agences_utilisateurs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('agence_id')->constrained('agences')->restrictOnDelete();
            $table->timestampTz('valide_du')->useCurrent();
            $table->timestampTz('valide_au')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'agence_id', 'valide_du'], 'perimetre_agence_utilisateur_unique');
        });

        Schema::create('periodes', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 7)->unique();
            $table->date('date_debut')->unique();
            $table->date('date_fin')->unique();
            $table->boolean('ouverte_pointage')->default(false);
            $table->boolean('ouverte_paiement')->default(false);
            $table->timestamps();
        });

        $this->createCodeReference('types_stage');
        $this->createCodeReference('types_structure');
        $this->createCodeReference('sources_financement');
        $this->createCodeReference('programmes');
        $this->createCodeReference('situations_stage');
        $this->createCodeReference('statuts_stage');
        $this->createCodeReference('types_paiement');
        $this->createCodeReference('liens_parente');
        $this->createCodeReference('types_enseignement');
        $this->createCodeReference('handicaps');
        $this->createCodeReference('types_handicap');
        $this->createCodeReference('types_piece_identite');
        $this->createCodeReference('specialites_diplome');
        $this->createCodeReference('niveaux_etude');
        $this->createCodeReference('diplomes');
        $this->createCodeReference('origines_stagiaire');
        $this->createCodeReference('etats_validation');
        $this->createCodeReference('types_document');

        Schema::create('motifs_ajournement', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('ancien_id')->nullable()->unique();
            $table->string('code', 100)->unique();
            $table->string('nom');
            $table->string('domaine', 100)->index();
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE periodes ADD CONSTRAINT periodes_dates_valides CHECK (date_fin >= date_debut)');
            DB::statement('ALTER TABLE perimetres_agences_utilisateurs ADD CONSTRAINT perimetres_dates_valides CHECK (valide_au IS NULL OR valide_au > valide_du)');
        }
    }

    private function createCodeReference(string $tableName): void
    {
        Schema::create($tableName, function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('ancien_id')->nullable()->unique();
            $table->string('code', 100)->unique();
            $table->string('nom');
            $table->text('description')->nullable();
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach ([
            'motifs_ajournement', 'types_document', 'etats_validation', 'origines_stagiaire', 'diplomes', 'niveaux_etude', 'specialites_diplome',
            'types_piece_identite', 'types_handicap', 'handicaps', 'types_enseignement', 'liens_parente',
            'types_paiement', 'statuts_stage', 'situations_stage', 'programmes',
            'sources_financement', 'types_structure', 'types_stage', 'periodes',
            'perimetres_agences_utilisateurs', 'conseillers', 'agences', 'communes', 'regions',
        ] as $tableName) {
            Schema::dropIfExists($tableName);
        }
    }
};
