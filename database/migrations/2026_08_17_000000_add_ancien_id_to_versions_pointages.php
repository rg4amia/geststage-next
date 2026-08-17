<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('versions_pointages', function (Blueprint $table): void {
            // Permet de rattacher chaque resoumission legacy (pointage_models) à sa version,
            // pour que la migration reste idempotente (un stagiaire pouvant avoir plusieurs
            // pointage_models pour le même stage/période à cause des corrections/ajournements).
            $table->unsignedBigInteger('ancien_id')->nullable()->unique()->after('pointage_id');
        });
    }

    public function down(): void
    {
        Schema::table('versions_pointages', function (Blueprint $table): void {
            $table->dropColumn('ancien_id');
        });
    }
};
