<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dossiers_paiement', function (Blueprint $table): void {
            // 1 624 dossiers legacy n'ont aucune agence portée par le dossier.
            // Les dossiers multi-agences doivent rester sans agence unique plutôt
            // que d'être rattachés arbitrairement à l'une de leurs agences.
            $table->unsignedBigInteger('agence_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('dossiers_paiement', function (Blueprint $table): void {
            $table->unsignedBigInteger('agence_id')->nullable(false)->change();
        });
    }
};
