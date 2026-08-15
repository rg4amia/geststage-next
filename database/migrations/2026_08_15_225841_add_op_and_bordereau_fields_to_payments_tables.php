<?php

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
        Schema::table('dossiers_paiement', function (Blueprint $table) {
            $table->foreignId('ordre_paiement_id')->nullable()->constrained('ordre_paiements')->onDelete('set null');
        });

        Schema::table('ordre_paiements', function (Blueprint $table) {
            $table->foreignId('bordereau_paiement_id')->nullable()->constrained('bordereau_paiements')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ordre_paiements', function (Blueprint $table) {
            $table->dropForeign(['bordereau_paiement_id']);
            $table->dropColumn('bordereau_paiement_id');
        });

        Schema::table('dossiers_paiement', function (Blueprint $table) {
            $table->dropForeign(['ordre_paiement_id']);
            $table->dropColumn('ordre_paiement_id');
        });
    }
};
