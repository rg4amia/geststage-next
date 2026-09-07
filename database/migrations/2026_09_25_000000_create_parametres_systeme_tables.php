<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Écran « Paramètres système » du menu Paramètre & Aides.
 *
 * Reprend le contrat legacy `settings/systeme/general` :
 *  - `system_settings`         -> `parametres_systeme` (clé/valeur typée)
 *  - `payment_deduction_rules` -> `regles_prelevement` (prélèvements CMU datés)
 *
 * Les noms sont francisés pour rester homogènes avec le reste du schéma cible
 * (`journaux_audit`, `droits_paiement`, `sources_financement`...).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parametres_systeme', function (Blueprint $table): void {
            $table->id();
            $table->string('cle', 100)->unique();
            $table->text('valeur')->nullable();
            $table->string('type', 20)->default('booleen');
            $table->string('libelle');
            $table->text('description')->nullable();
            $table->foreignId('modifie_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('regles_prelevement', function (Blueprint $table): void {
            $table->id();
            $table->string('nom');
            $table->string('type_prelevement', 30)->default('CMU');
            $table->foreignId('source_financement_id')->constrained('sources_financement')->restrictOnDelete();
            $table->foreignId('type_stage_id')->nullable()->constrained('types_stage')->restrictOnDelete();
            $table->string('type_paiement', 30)->default('DEMARRAGE');
            $table->decimal('montant', 12, 2);
            // Bornes au mois : le legacy saisit `Y-m`, on stocke le 1er du mois
            // pour `effet_du` et le dernier jour du mois pour `effet_au`.
            $table->date('effet_du');
            $table->date('effet_au')->nullable();
            $table->boolean('actif')->default(true);
            $table->foreignId('cree_par')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('modifie_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(
                ['source_financement_id', 'type_paiement', 'effet_du', 'effet_au'],
                'regles_prelevement_periode_idx'
            );
            $table->index(['type_stage_id', 'actif'], 'regles_prelevement_stage_actif_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('regles_prelevement');
        Schema::dropIfExists('parametres_systeme');
    }
};
