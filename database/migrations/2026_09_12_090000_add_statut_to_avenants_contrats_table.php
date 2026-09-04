<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cycle de vie d'un renouvellement.
 *
 * Le legacy le portait sur `contrats_pae` via quatre drapeaux couplés
 * (`etatrenouvellement_id`, `etapetraitement_id`, `etat_chef_agence`, `active_chef_agence`).
 * Ici l'avenant existe dès que le CIP propose le renouvellement, et son `statut` porte
 * seul la décision du chef d'agence.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('avenants_contrats', function (Blueprint $table) {
            $table->string('statut')->default('VALIDE')->after('motif');
            $table->text('motif_ajournement')->nullable()->after('statut');
            $table->timestamp('decide_le')->nullable()->after('motif_ajournement');
            $table->foreignId('decideur_id')->nullable()->after('decide_le')->constrained('users')->nullOnDelete();

            $table->index(['statut', 'contrat_id']);
        });
    }

    public function down(): void
    {
        Schema::table('avenants_contrats', function (Blueprint $table) {
            $table->dropIndex(['statut', 'contrat_id']);
            $table->dropConstrainedForeignId('decideur_id');
            $table->dropColumn(['statut', 'motif_ajournement', 'decide_le']);
        });
    }
};
