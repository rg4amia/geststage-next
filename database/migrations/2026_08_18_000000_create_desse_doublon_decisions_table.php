<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('desse_doublon_decisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('instance_parcours_id')->constrained('instances_parcours')->cascadeOnDelete();
            $table->string('type_doublon', 40);
            $table->string('cle_doublon');
            $table->string('decision', 20);
            $table->text('motif');
            $table->foreignId('decide_par_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('decide_le')->useCurrent();
            $table->timestamps();

            $table->index(['type_doublon', 'cle_doublon']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE desse_doublon_decisions ADD CONSTRAINT desse_doublon_decisions_decision_valide CHECK (decision IN ('avere', 'non_avere'))");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('desse_doublon_decisions');
    }
};
