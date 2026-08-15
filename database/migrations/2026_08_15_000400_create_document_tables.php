<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid_public')->unique();
            $table->unsignedBigInteger('ancien_id')->nullable()->unique();
            $table->foreignId('type_document_id')->constrained('types_document')->restrictOnDelete();
            $table->foreignId('beneficiaire_id')->nullable()->constrained('beneficiaires')->restrictOnDelete();
            $table->foreignId('stage_id')->nullable()->constrained('stages')->restrictOnDelete();
            $table->foreignId('contrat_id')->nullable()->constrained('contrats')->restrictOnDelete();
            $table->foreignId('cree_par_id')->nullable()->constrained('utilisateurs')->nullOnDelete();
            $table->string('nom');
            $table->string('statut', 40)->default('BROUILLON');
            $table->boolean('prive')->default(true);
            $table->timestamps();
            $table->index(['type_document_id', 'statut']);
            $table->index(['stage_id', 'contrat_id']);
        });

        Schema::create('versions_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('document_id')->constrained('documents')->restrictOnDelete();
            $table->foreignId('depose_par_id')->nullable()->constrained('utilisateurs')->nullOnDelete();
            $table->unsignedInteger('numero_version');
            $table->string('disque', 100);
            $table->string('chemin');
            $table->string('nom_original');
            $table->string('type_mime', 150);
            $table->unsignedBigInteger('taille_octets');
            $table->char('empreinte_sha256', 64);
            $table->timestampTz('depose_le')->useCurrent();
            $table->timestamps();
            $table->unique(['document_id', 'numero_version']);
            $table->unique(['document_id', 'empreinte_sha256']);
        });

        Schema::create('exigences_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('type_document_id')->constrained('types_document')->restrictOnDelete();
            $table->foreignId('type_stage_id')->nullable()->constrained('types_stage')->restrictOnDelete();
            $table->foreignId('source_financement_id')->nullable()->constrained('sources_financement')->restrictOnDelete();
            $table->string('contexte', 100);
            $table->boolean('obligatoire')->default(true);
            $table->boolean('actif')->default(true);
            $table->timestamps();
            $table->unique(['type_document_id', 'type_stage_id', 'source_financement_id', 'contexte'], 'exigence_document_contexte_unique');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE documents ADD CONSTRAINT documents_objet_rattache CHECK (num_nonnulls(beneficiaire_id, stage_id, contrat_id) >= 1)');
            DB::statement('ALTER TABLE versions_documents ADD CONSTRAINT versions_documents_taille_positive CHECK (taille_octets > 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('exigences_documents');
        Schema::dropIfExists('versions_documents');
        Schema::dropIfExists('documents');
    }
};
