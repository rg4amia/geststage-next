<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('paiements', function (Blueprint $table): void {
            $table->string('statut_dossier_physique', 30)->nullable()->index();
            $table->foreignId('dossier_physique_marque_par_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('dossier_physique_marque_le')->nullable();
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
    }

    public function down(): void
    {
        Schema::dropIfExists('decisions_paiements');
        Schema::table('paiements', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('dossier_physique_marque_par_id');
            $table->dropColumn(['statut_dossier_physique', 'dossier_physique_marque_le']);
        });
    }
};
