<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dossiers_paiement', function (Blueprint $table): void {
            $table->foreignId('valide_par_id')->nullable()->after('ordre_paiement_id')
                ->constrained('users')->nullOnDelete();
            $table->string('valideur_initiales', 20)->nullable()->after('valide_par_id');
            $table->uuid('validation_batch_id')->nullable()->after('valideur_initiales');
            $table->string('attestation_path')->nullable()->after('validation_batch_id');
            $table->string('etat_financier_path')->nullable()->after('attestation_path');
            $table->index('validation_batch_id');
        });
    }

    public function down(): void
    {
        Schema::table('dossiers_paiement', function (Blueprint $table): void {
            $table->dropIndex(['validation_batch_id']);
            $table->dropConstrainedForeignId('valide_par_id');
            $table->dropColumn([
                'valideur_initiales',
                'validation_batch_id',
                'attestation_path',
                'etat_financier_path',
            ]);
        });
    }
};
