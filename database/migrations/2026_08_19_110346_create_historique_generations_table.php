<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('historique_generations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid_public')->unique();

            // Type de document généré
            $table->enum('type_document', ['CONTRAT', 'TRESOR_MONEY', 'ADD'])->index();

            // Références
            $table->foreignId('stage_id')->nullable()->constrained('stages')->onDelete('set null');
            $table->foreignId('instance_parcours_id')->nullable()->constrained('instances_parcours')->onDelete('set null');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');

            // Informations de génération
            $table->string('nom_fichier')->nullable();
            $table->string('chemin_fichier')->nullable(); // Si on stocke le fichier
            $table->json('parametres')->nullable(); // Fonction, montant, etc.

            // Métadonnées
            $table->string('source_financement')->nullable(); // Pour contrat
            $table->string('type_stage')->nullable(); // Pour contrat
            $table->integer('nombre_stagiaires')->default(1); // Pour Trésor Money groupé
            $table->text('note')->nullable(); // Note libre

            $table->timestamps();

            // Index pour les recherches courantes
            $table->index(['type_document', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index('stage_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('historique_generations');
    }
};
