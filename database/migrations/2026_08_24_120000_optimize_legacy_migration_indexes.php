<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('progressions_migration_legacy', function (Blueprint $table): void {
            $table->id();
            $table->string('phase', 120)->unique();
            $table->string('version_source', 100);
            $table->unsignedBigInteger('dernier_id_source')->default(0);
            $table->string('statut', 30)->default('EN_COURS');
            $table->foreignId('execution_migration_id')->nullable()->constrained('executions_migration')->nullOnDelete();
            $table->timestampTz('terminee_le')->nullable();
            $table->timestamps();
            $table->index(['version_source', 'statut'], 'progressions_version_statut_index');
        });

        Schema::table('anomalies_migration', function (Blueprint $table): void {
            $table->char('cle_idempotence', 64)->nullable()->after('id');
        });

        $seen = [];
        DB::table('anomalies_migration')
            ->select('id', 'code', 'table_source', 'id_source', 'statut')
            ->orderBy('id')
            ->chunkById(1000, function ($rows) use (&$seen): void {
                foreach ($rows as $row) {
                    $naturalKey = implode('|', [
                        $row->code,
                        $row->table_source,
                        $row->id_source ?? '<null>',
                        $row->statut,
                    ]);
                    $key = hash('sha256', $naturalKey);

                    if (isset($seen[$key])) {
                        $key = hash('sha256', $naturalKey.'|duplicate|'.$row->id);
                    }
                    $seen[$key] = true;

                    DB::table('anomalies_migration')->where('id', $row->id)->update([
                        'cle_idempotence' => $key,
                    ]);
                }
            });

        Schema::table('anomalies_migration', function (Blueprint $table): void {
            $table->unique('cle_idempotence', 'anomalies_migration_cle_idempotence_unique');
            $table->index(
                ['execution_migration_id', 'statut', 'code'],
                'anomalies_migration_execution_statut_code_index'
            );
        });

        Schema::table('correspondances_ancien_systeme', function (Blueprint $table): void {
            $table->index(
                ['execution_migration_id', 'table_cible'],
                'correspondances_execution_table_cible_index'
            );
        });

        Schema::table('conservations_contrats_pae', function (Blueprint $table): void {
            $table->index('execution_migration_id', 'conservations_execution_index');
        });

        Schema::table('executions_migration', function (Blueprint $table): void {
            $table->index(['version_source', 'statut'], 'executions_version_source_statut_index');
        });

        Schema::table('dossiers_paiement', function (Blueprint $table): void {
            $table->index('ordre_paiement_id', 'dossiers_paiement_ordre_index');
        });

        Schema::table('pointages', function (Blueprint $table): void {
            $table->index('stage_id', 'pointages_stage_index');
        });

        Schema::table('ordre_paiements', function (Blueprint $table): void {
            $table->index('bordereau_paiement_id', 'ordre_paiements_bordereau_index');
        });

        Schema::table('lignes_dossiers_paiement', function (Blueprint $table): void {
            $table->index(
                ['dossier_paiement_id', 'paiement_id'],
                'lignes_dossiers_paiement_dossier_paiement_index'
            );
        });

        Schema::table('lignes_dossiers_groupes', function (Blueprint $table): void {
            $table->index(
                ['dossier_groupe_id', 'dossier_paiement_id'],
                'lignes_dossiers_groupes_groupe_dossier_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('progressions_migration_legacy');

        Schema::table('lignes_dossiers_groupes', function (Blueprint $table): void {
            $table->dropIndex('lignes_dossiers_groupes_groupe_dossier_index');
        });
        Schema::table('lignes_dossiers_paiement', function (Blueprint $table): void {
            $table->dropIndex('lignes_dossiers_paiement_dossier_paiement_index');
        });
        Schema::table('ordre_paiements', function (Blueprint $table): void {
            $table->dropIndex('ordre_paiements_bordereau_index');
        });
        Schema::table('dossiers_paiement', function (Blueprint $table): void {
            $table->dropIndex('dossiers_paiement_ordre_index');
        });
        Schema::table('pointages', function (Blueprint $table): void {
            $table->dropIndex('pointages_stage_index');
        });
        Schema::table('executions_migration', function (Blueprint $table): void {
            $table->dropIndex('executions_version_source_statut_index');
        });
        Schema::table('conservations_contrats_pae', function (Blueprint $table): void {
            $table->dropIndex('conservations_execution_index');
        });
        Schema::table('correspondances_ancien_systeme', function (Blueprint $table): void {
            $table->dropIndex('correspondances_execution_table_cible_index');
        });
        Schema::table('anomalies_migration', function (Blueprint $table): void {
            $table->dropIndex('anomalies_migration_execution_statut_code_index');
            $table->dropUnique('anomalies_migration_cle_idempotence_unique');
            $table->dropColumn('cle_idempotence');
        });
    }
};
