<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Date de validation du démarrage par l'agence régionale (chef d'agence).
 *
 * Le legacy la portait sur `contrats_pae.date_chef_agence`, et c'est elle — et non la
 * date de début de stage — qui borne les écrans régionaux : le tableau statistique
 * (`Tableau_Statistique`) comme la liste `liste-stagiaire-pae` filtrent tous les deux
 * sur `date(date_chef_agence)`. Sans cette colonne, l'écran de supervision AR ne peut
 * ni reproduire les compteurs legacy, ni offrir le filtre `date_valid_ar_debut/fin`.
 *
 * `stages.visa_desse` reste la source de vérité du statut DESSE : cette colonne ne
 * porte qu'une date, jamais une décision.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stages', function (Blueprint $table) {
            $table->timestamp('date_validation_ar')->nullable()->after('visa_desse_par_id');

            $table->index(['date_validation_ar', 'agence_id']);
        });
    }

    public function down(): void
    {
        Schema::table('stages', function (Blueprint $table) {
            $table->dropIndex(['date_validation_ar', 'agence_id']);
            $table->dropColumn('date_validation_ar');
        });
    }
};
