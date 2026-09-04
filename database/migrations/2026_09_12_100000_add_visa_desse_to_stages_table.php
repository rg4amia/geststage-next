<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Visa DESSE d'un dossier de stage.
 *
 * Le legacy le portait sur `contrats_pae.etat_desse` (0 en attente, 1 rejeté, 2 visé).
 * Ce n'est pas une étape bloquante du parcours : les 63 890 dossiers legacy « en attente
 * de visa » sont déjà en pointage. C'est donc un état de supervision porté par le stage,
 * en parallèle de `instances_parcours.corbeille_actuelle`, et non une corbeille.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stages', function (Blueprint $table) {
            $table->string('visa_desse')->nullable()->after('statut_stage');
            $table->text('motif_visa_desse')->nullable()->after('visa_desse');
            $table->timestamp('visa_desse_le')->nullable()->after('motif_visa_desse');
            $table->foreignId('visa_desse_par_id')->nullable()->after('visa_desse_le')
                ->constrained('users')->nullOnDelete();

            $table->index(['visa_desse', 'agence_id']);
        });
    }

    public function down(): void
    {
        Schema::table('stages', function (Blueprint $table) {
            $table->dropIndex(['visa_desse', 'agence_id']);
            $table->dropConstrainedForeignId('visa_desse_par_id');
            $table->dropColumn(['visa_desse', 'motif_visa_desse', 'visa_desse_le']);
        });
    }
};
