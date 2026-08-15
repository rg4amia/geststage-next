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
        Schema::create('journaux_audit', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');
            $table->string('modele_type');
            $table->unsignedBigInteger('modele_id');
            $table->json('anciennes_donnees')->nullable();
            $table->json('nouvelles_donnees')->nullable();
            $table->string('adresse_ip')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();

            $table->index(['modele_type', 'modele_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('journaux_audit');
    }
};
