<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conservations_contrats_pae', function (Blueprint $table): void {
            $table->dropUnique(['contrat_pae_ancien_id']);
        });

        Schema::table('ordre_paiements', function (Blueprint $table): void {
            $table->unsignedBigInteger('ancien_id')->nullable()->unique();
        });

        Schema::table('bordereau_paiements', function (Blueprint $table): void {
            $table->unsignedBigInteger('ancien_id')->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('bordereau_paiements', function (Blueprint $table): void {
            $table->dropColumn('ancien_id');
        });

        Schema::table('ordre_paiements', function (Blueprint $table): void {
            $table->dropColumn('ancien_id');
        });

        Schema::table('conservations_contrats_pae', function (Blueprint $table): void {
            $table->unique('contrat_pae_ancien_id');
        });
    }
};
