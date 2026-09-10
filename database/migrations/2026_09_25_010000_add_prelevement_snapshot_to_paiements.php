<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Instantané « prélèvement » d'un paiement, équivalent des colonnes ajoutées au
 * legacy sur `paiement_models` (2026_08_31_000001) :
 *
 *  - `montant` reste le montant **net** réellement versé (aucune écriture
 *    existante n'est modifiée, la colonne garde son sens historique) ;
 *  - `montant_brut` porte la prime calculée avant prélèvement ;
 *  - `montant_prelevement` porte la part prélevée (cotisation CMU) ;
 *  - `type_prelevement` / `regle_prelevement_id` tracent la règle appliquée.
 *
 * Les paiements déjà créés (sans prélèvement) restent inchangés : brut et net
 * valent alors le montant historique, prélèvement à zéro.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('paiements', function (Blueprint $table): void {
            $table->decimal('montant_brut', 15, 2)->nullable()->after('montant');
            $table->decimal('montant_prelevement', 15, 2)->default(0)->after('montant_brut');
            $table->string('type_prelevement', 30)->nullable()->after('montant_prelevement');
            $table->foreignId('regle_prelevement_id')->nullable()
                ->constrained('regles_prelevement')->nullOnDelete()->after('type_prelevement');
            $table->index('regle_prelevement_id', 'paiements_regle_prelevement_idx');
        });
    }

    public function down(): void
    {
        Schema::table('paiements', function (Blueprint $table): void {
            $table->dropIndex('paiements_regle_prelevement_idx');
            $table->dropConstrainedForeignId('regle_prelevement_id');
            $table->dropColumn(['montant_brut', 'montant_prelevement', 'type_prelevement']);
        });
    }
};
