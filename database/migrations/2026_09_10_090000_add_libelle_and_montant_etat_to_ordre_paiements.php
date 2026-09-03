<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Informations saisies à la création d'un ordre de paiement dans l'ancien Gestage
     * (modale « Créer une opération » de `dmg/operation/wait-op-generer`) : le titre libre
     * repris sur l'OP papier et le montant de l'état de financement mobilisé, qui peut
     * différer du cumul des dossiers rattachés.
     */
    public function up(): void
    {
        Schema::table('ordre_paiements', function (Blueprint $table): void {
            $table->string('libelle')->nullable()->after('numero');
            $table->decimal('montant_etat_financement', 15, 2)->nullable()->after('montant_total');
        });
    }

    public function down(): void
    {
        Schema::table('ordre_paiements', function (Blueprint $table): void {
            $table->dropColumn(['libelle', 'montant_etat_financement']);
        });
    }
};
