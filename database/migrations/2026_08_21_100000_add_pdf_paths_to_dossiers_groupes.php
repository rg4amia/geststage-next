<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dossiers_groupes', function (Blueprint $table): void {
            $table->string('attestation_path')->nullable()->after('observation');
            $table->string('etat_financier_path')->nullable()->after('attestation_path');
        });
    }

    public function down(): void
    {
        Schema::table('dossiers_groupes', function (Blueprint $table): void {
            $table->dropColumn(['attestation_path', 'etat_financier_path']);
        });
    }
};
