<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Barème des primes surchargeable par l'administrateur.
 *
 * `config/primes.php` reste la référence livrée avec le code ; cette table ne
 * porte que la surcharge saisie dans Paramètre & Aides, fusionnée par-dessus
 * les valeurs par défaut. Une seule ligne (id = 1) : le barème est global, il
 * n'est pas décliné par agence ni par exercice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prime_configuration_overrides', function (Blueprint $table): void {
            $table->id();
            $table->json('configuration');
            $table->foreignId('modifie_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prime_configuration_overrides');
    }
};
