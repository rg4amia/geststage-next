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
        Schema::table('agences', function (Blueprint $table): void {
            $table->string('contact_agence')->nullable()->after('nom');
            $table->string('chef_agence_nom')->nullable()->after('contact_agence');
            $table->decimal('longitude', 10, 7)->nullable()->after('chef_agence_nom');
            $table->decimal('latitude', 10, 7)->nullable()->after('longitude');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agences', function (Blueprint $table): void {
            $table->dropColumn(['contact_agence', 'chef_agence_nom', 'longitude', 'latitude']);
        });
    }
};
