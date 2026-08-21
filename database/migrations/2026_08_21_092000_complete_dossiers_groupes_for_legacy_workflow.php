<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dossiers_groupes', function (Blueprint $table): void {
            $table->foreignId('source_financement_id')->nullable()->constrained('sources_financement')->restrictOnDelete();
            $table->foreignId('cree_par_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('nature', 10)->nullable();
            $table->text('observation')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('dossiers_groupes', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('cree_par_id');
            $table->dropConstrainedForeignId('source_financement_id');
            $table->dropColumn(['nature', 'observation']);
        });
    }
};
