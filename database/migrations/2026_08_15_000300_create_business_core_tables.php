<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entreprises', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid_public')->unique();
            $table->unsignedBigInteger('ancien_id')->nullable()->unique();
            $table->foreignId('agence_id')->constrained('agences')->restrictOnDelete();
            $table->foreignId('commune_id')->nullable()->constrained('communes')->restrictOnDelete();
            $table->foreignId('type_structure_id')->nullable()->constrained('types_structure')->restrictOnDelete();
            $table->string('raison_sociale');
            $table->string('sigle', 100)->nullable();
            $table->string('numero_contribuable', 100)->nullable()->unique();
            $table->string('registre_commerce', 100)->nullable()->unique();
            $table->string('adresse')->nullable();
            $table->string('telephone', 30)->nullable();
            $table->string('email')->nullable();
            $table->boolean('actif')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['agence_id', 'actif']);
        });

        Schema::create('offres_emploi', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid_public')->unique();
            $table->unsignedBigInteger('ancien_id')->nullable()->unique();
            $table->foreignId('entreprise_id')->constrained('entreprises')->restrictOnDelete();
            $table->foreignId('agence_id')->constrained('agences')->restrictOnDelete();
            $table->foreignId('type_stage_id')->constrained('types_stage')->restrictOnDelete();
            $table->foreignId('source_financement_id')->constrained('sources_financement')->restrictOnDelete();
            $table->foreignId('programme_id')->nullable()->constrained('programmes')->restrictOnDelete();
            $table->string('numero')->unique();
            $table->string('intitule');
            $table->text('description')->nullable();
            $table->unsignedInteger('nombre_places');
            $table->date('publiee_le')->nullable();
            $table->date('valide_du')->nullable();
            $table->date('valide_au')->nullable();
            $table->string('statut', 40)->default('BROUILLON');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['agence_id', 'statut']);
            $table->index(['entreprise_id', 'statut']);
        });

        Schema::create('postes_offres_emploi', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('offre_emploi_id')->constrained('offres_emploi')->cascadeOnDelete();
            $table->string('intitule');
            $table->unsignedInteger('nombre_places');
            $table->text('profil_recherche')->nullable();
            $table->timestamps();
        });

        Schema::create('beneficiaires', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid_public')->unique();
            $table->unsignedBigInteger('ancien_id')->nullable()->unique();
            $table->string('numero_aej', 100)->nullable()->unique();
            $table->string('nom');
            $table->string('prenoms');
            $table->date('date_naissance')->nullable();
            $table->string('lieu_naissance')->nullable();
            $table->string('sous_prefecture_naissance')->nullable();
            $table->string('sexe', 20)->nullable();
            $table->string('telephone_principal', 30)->nullable();
            $table->string('telephone_secondaire', 30)->nullable();
            $table->string('email')->nullable();
            
            $table->foreignId('commune_residence_id')->nullable()->constrained('communes')->restrictOnDelete();
            $table->string('sous_prefecture_residence')->nullable();
            
            $table->string('nature_piece_identite')->nullable();
            $table->string('numero_piece_identite')->nullable();
            $table->string('numero_cmu')->nullable();
            
            $table->string('personne_urgence')->nullable();
            $table->foreignId('lien_parente_id')->nullable()->constrained('liens_parente')->restrictOnDelete();
            $table->string('contact_urgence_1', 30)->nullable();
            $table->string('contact_urgence_2', 30)->nullable();
            
            $table->foreignId('niveau_etude_id')->nullable()->constrained('niveaux_etude')->restrictOnDelete();
            $table->foreignId('diplome_id')->nullable()->constrained('diplomes')->restrictOnDelete();
            $table->string('autre_diplome')->nullable();
            $table->string('specialite')->nullable();
            $table->year('annee_diplome')->nullable();
            $table->string('etablissement_frequente')->nullable();
            
            $table->foreignId('type_enseignement_id')->nullable()->constrained('types_enseignement')->restrictOnDelete();
            $table->foreignId('handicap_id')->nullable()->constrained('handicaps')->restrictOnDelete();
            $table->foreignId('type_handicap_id')->nullable()->constrained('types_handicap')->restrictOnDelete();
            $table->string('autre_handicap')->nullable();

            $table->foreignId('type_paiement_id')->nullable()->constrained('types_paiement')->restrictOnDelete();
            $table->string('numero_tresor_money')->nullable();
            $table->string('numero_wave')->nullable();
            
            $table->boolean('actif')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['nom', 'prenoms', 'date_naissance']);
        });

        Schema::create('identifiants_beneficiaires', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('beneficiaire_id')->constrained('beneficiaires')->cascadeOnDelete();
            $table->foreignId('type_piece_identite_id')->constrained('types_piece_identite')->restrictOnDelete();
            $table->string('numero');
            $table->date('delivre_le')->nullable();
            $table->date('expire_le')->nullable();
            $table->boolean('principal')->default(false);
            $table->timestamps();
            $table->unique(['type_piece_identite_id', 'numero']);
        });

        Schema::create('comptes_paiement_beneficiaires', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('beneficiaire_id')->constrained('beneficiaires')->cascadeOnDelete();
            $table->foreignId('type_paiement_id')->constrained('types_paiement')->restrictOnDelete();
            $table->string('numero_compte');
            $table->string('titulaire')->nullable();
            $table->boolean('principal')->default(false);
            $table->timestampTz('verifie_le')->nullable();
            $table->timestamps();
            $table->unique(['type_paiement_id', 'numero_compte']);
        });

        Schema::create('stages', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid_public')->unique();
            $table->unsignedBigInteger('ancien_id')->nullable()->unique();
            $table->foreignId('beneficiaire_id')->constrained('beneficiaires')->restrictOnDelete();
            $table->foreignId('offre_emploi_id')->nullable()->constrained('offres_emploi')->restrictOnDelete();
            $table->foreignId('entreprise_id')->constrained('entreprises')->restrictOnDelete();
            $table->foreignId('agence_id')->constrained('agences')->restrictOnDelete();
            $table->foreignId('conseiller_id')->nullable()->constrained('conseillers')->restrictOnDelete();
            
            $table->foreignId('origine_stagiaire_id')->nullable()->constrained('origines_stagiaire')->restrictOnDelete();
            $table->date('date_entree_portefeuille')->nullable();
            
            $table->foreignId('type_stage_id')->constrained('types_stage')->restrictOnDelete();
            $table->foreignId('source_financement_id')->constrained('sources_financement')->restrictOnDelete();
            $table->foreignId('programme_id')->nullable()->constrained('programmes')->restrictOnDelete();
            
            $table->string('service_affectation')->nullable();
            $table->string('intitule_poste');
            
            $table->string('localite_stage')->nullable();
            $table->string('commune_stage')->nullable();
            $table->string('sous_prefecture_stage')->nullable();
            
            $table->string('nom_encadreur')->nullable();
            $table->string('fonction_encadreur')->nullable();
            $table->string('contact_encadreur', 30)->nullable();
            
            $table->string('statut_stage')->nullable();
            $table->string('situation_stage')->nullable();
            
            $table->integer('nbr_mois_capitaliser')->default(0);
            $table->date('date_demarrage_capitalisation')->nullable();
            $table->date('date_demarrage_capitalisation_sans_financiere')->nullable();

            $table->text('observations')->nullable();
            
            $table->date('date_debut');
            $table->date('date_fin_prevue');
            $table->date('date_fin_effective')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['agence_id', 'conseiller_id']);
            $table->index(['source_financement_id', 'type_stage_id']);
        });

        Schema::create('situations_stages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('stage_id')->constrained('stages')->restrictOnDelete();
            $table->foreignId('situation_stage_id')->constrained('situations_stage')->restrictOnDelete();
            $table->foreignId('auteur_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('debute_le');
            $table->timestampTz('termine_le')->nullable();
            $table->text('motif')->nullable();
            $table->timestamps();
            $table->index(['stage_id', 'termine_le']);
        });

        Schema::create('contrats', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid_public')->unique();
            $table->unsignedBigInteger('ancien_id')->nullable()->unique();
            $table->foreignId('stage_id')->constrained('stages')->restrictOnDelete();
            $table->string('numero')->unique();
            $table->date('date_debut');
            $table->date('date_fin');
            $table->decimal('prime_mensuelle', 15, 2)->default(0);
            $table->string('statut', 40)->default('BROUILLON');
            $table->unsignedInteger('version_verrouillage')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['stage_id', 'statut']);
        });

        Schema::create('avenants_contrats', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid_public')->unique();
            $table->foreignId('contrat_id')->constrained('contrats')->restrictOnDelete();
            $table->string('numero')->unique();
            $table->date('date_effet');
            $table->date('nouvelle_date_fin')->nullable();
            $table->decimal('nouvelle_prime_mensuelle', 15, 2)->nullable();
            $table->text('motif');
            $table->timestamps();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE offres_emploi ADD CONSTRAINT offres_places_positives CHECK (nombre_places > 0)');
            DB::statement('ALTER TABLE offres_emploi ADD CONSTRAINT offres_dates_valides CHECK (valide_au IS NULL OR valide_du IS NULL OR valide_au >= valide_du)');
            DB::statement('ALTER TABLE stages ADD CONSTRAINT stages_dates_valides CHECK (date_fin_prevue >= date_debut AND (date_fin_effective IS NULL OR date_fin_effective >= date_debut))');
            DB::statement('ALTER TABLE situations_stages ADD CONSTRAINT situations_dates_valides CHECK (termine_le IS NULL OR termine_le >= debute_le)');
            DB::statement('ALTER TABLE contrats ADD CONSTRAINT contrats_dates_montants_valides CHECK (date_fin >= date_debut AND prime_mensuelle >= 0)');
            DB::statement('ALTER TABLE avenants_contrats ADD CONSTRAINT avenants_montants_valides CHECK (nouvelle_prime_mensuelle IS NULL OR nouvelle_prime_mensuelle >= 0)');
        }
    }

    public function down(): void
    {
        foreach (['avenants_contrats', 'contrats', 'situations_stages', 'stages', 'comptes_paiement_beneficiaires', 'identifiants_beneficiaires', 'beneficiaires', 'postes_offres_emploi', 'offres_emploi', 'entreprises'] as $tableName) {
            Schema::dropIfExists($tableName);
        }
    }
};
