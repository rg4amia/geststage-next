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
        Schema::create('ordre_paiements', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid_public')->unique();
            $table->string('numero', 50)->unique();
            $table->foreignId('periode_id')->constrained('periodes')->onDelete('cascade');
            $table->decimal('montant_total', 15, 2)->default(0);
            $table->string('statut', 30)->default('BROUILLON');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ordre_paiements');
    }
};
