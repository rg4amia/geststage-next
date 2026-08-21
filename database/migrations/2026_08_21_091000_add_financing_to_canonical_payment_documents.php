<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ordre_paiements', function (Blueprint $table): void {
            $table->foreignId('source_financement_id')->nullable()->constrained('sources_financement')->restrictOnDelete();
        });
        Schema::table('bordereau_paiements', function (Blueprint $table): void {
            $table->foreignId('source_financement_id')->nullable()->constrained('sources_financement')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bordereau_paiements', fn (Blueprint $table) => $table->dropConstrainedForeignId('source_financement_id'));
        Schema::table('ordre_paiements', fn (Blueprint $table) => $table->dropConstrainedForeignId('source_financement_id'));
    }
};
