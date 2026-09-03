<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Situation du stage au moment du pointage mensuel (legacy : `pointage_models.situationstage_id`),
     * distincte de `stages.situation_stage` qui ne porte que la situation courante du stage.
     * Un pointage « réactivation » ou « fin de stage » n'entre pas dans la file DMG côté legacy
     * (PaiementDmgService::attentePaiementValidation() exige `situationstage_id = 1`), même s'il a
     * été validé par le CIP et le Chef d'Agence.
     */
    public function up(): void
    {
        Schema::table('pointages', function (Blueprint $table): void {
            $table->foreignId('situation_stage_id')->nullable()->after('nature')
                ->constrained('situations_stage')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pointages', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('situation_stage_id');
        });
    }
};
