<?php

namespace App\Console\Commands;

use App\Models\Reference\Agence;
use App\Models\System\User;
use App\Models\Internship\Stage;
use App\Models\Workflow\InstanceParcours;
use App\Services\Migration\LegacyMapperService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class MigrateLegacyDataCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:legacy-data {--step=all : L\'étape de migration à exécuter (agences, users, stages, all)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migre les données de l\'ancienne base (legacy) vers la nouvelle base PostgreSQL.';

    public function __construct(private LegacyMapperService $mapper)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $step = $this->option('step');
        $this->info("Début de la migration des données (Étape : $step)...");

        try {
            DB::connection('legacy')->getPdo();
        } catch (\Exception $e) {
            $this->error("Impossible de se connecter à la base 'legacy'. Vérifiez votre config/database.php et .env");
            $this->error($e->getMessage());
            return 1;
        }

        if ($step === 'all' || $step === 'agences') {
            $this->migrateAgences();
        }

        if ($step === 'all' || $step === 'users') {
            $this->migrateUsers();
        }

        if ($step === 'all' || $step === 'entreprises') {
            $this->migrateEntreprises();
        }

        if ($step === 'all' || $step === 'beneficiaires') {
            $this->migrateBeneficiaires();
        }

        if ($step === 'all' || $step === 'stages') {
            $this->migrateStages();
        }

        if ($step === 'all' || $step === 'pointages') {
            $this->migratePointages();
        }

        if ($step === 'all' || $step === 'paiements') {
            $this->migratePaiements();
        }

        if ($step === 'all' || $step === 'evenements') {
            $this->migrateEvenements();
        }

        $this->info("Migration terminée !");
        return 0;
    }

    private function migrateAgences()
    {
        $this->info("Migration des agences...");
        $agences = DB::connection('legacy')->table('agences')->get();

        $bar = $this->output->createProgressBar(count($agences));
        $bar->start();

        foreach ($agences as $legacyAgence) {
            Agence::updateOrCreate(
                ['legacy_id' => $legacyAgence->id], // Suppose qu'on a ajouté 'legacy_id' dans la nouvelle table
                [
                    'nom' => $legacyAgence->nom_agence ?? 'Agence Inconnue',
                    'code' => $legacyAgence->code_agence ?? 'CODE-' . $legacyAgence->id,
                    // Mapper les autres champs (ville_id, adresse...)
                ]
            );
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
    }

    private function migrateUsers()
    {
        $this->info("Migration des utilisateurs...");
        $users = DB::connection('legacy')->table('users')->get();

        $bar = $this->output->createProgressBar(count($users));
        $bar->start();

        foreach ($users as $legacyUser) {
            $email = $this->mapper->sanitizeEmail($legacyUser->email, $legacyUser->nom ?? 'User', $legacyUser->prenom ?? '', $legacyUser->id);

            $user = User::updateOrCreate(
                ['email' => $email], // On se base sur l'email ou un legacy_id
                [
                    'nom' => $legacyUser->nom ?? 'Inconnu',
                    'prenoms' => $legacyUser->prenom ?? '',
                    'password' => $legacyUser->password, // On garde l'ancien hash
                    // 'agence_id' => Trouver l'ID de la nouvelle agence via legacyAgenceId
                ]
            );

            // Assigner le rôle Spatie
            $roleName = $this->mapper->mapTypeUserToRole($legacyUser->type_user_id);
            if ($roleName && !$user->hasRole($roleName)) {
                // $user->assignRole($roleName); // Décommenter quand les rôles seront créés en base
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
    }

    private function migrateEntreprises()
    {
        $this->info("Migration des entreprises...");
        $entreprises = DB::connection('legacy')->table('entreprises')->get();

        $bar = $this->output->createProgressBar(count($entreprises));
        $bar->start();

        foreach ($entreprises as $legacyEntreprise) {
            \App\Models\Company\Entreprise::updateOrCreate(
                ['ancien_id' => $legacyEntreprise->id],
                [
                    'raison_sociale' => $legacyEntreprise->libelle_entreprise ?? 'Inconnu',
                    'sigle' => $legacyEntreprise->sigle,
                    'numero_cnps' => $legacyEntreprise->cnps,
                    'numero_rccm' => $legacyEntreprise->rccm,
                    'compte_contribuable' => $legacyEntreprise->compte_contri,
                    'telephone_principal' => $legacyEntreprise->contact,
                    'email' => $legacyEntreprise->mail,
                    'adresse_geographique' => $legacyEntreprise->adresse,
                    'nom_directeur' => $legacyEntreprise->dg,
                    'agence_id' => $legacyEntreprise->agence_id ?? 1,
                    // 'commune_id' => Peut nécessiter une jointure sur le libellé $legacyEntreprise->ville
                ]
            );
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
    }

    private function migrateBeneficiaires()
    {
        $this->info("Migration des bénéficiaires (beneficiaire_stages)...");
        // On suppose que la table principale historique s'appelait beneficiaire_stages ou dossiers_stagiaires
        $beneficiaires = DB::connection('legacy')->table('beneficiaire_stages')->get();

        $bar = $this->output->createProgressBar(count($beneficiaires));
        $bar->start();

        foreach ($beneficiaires as $legacyBen) {
            \App\Models\Beneficiary\Beneficiaire::updateOrCreate(
                ['ancien_id' => $legacyBen->id],
                [
                    'numero_aej' => $legacyBen->numero_aej,
                    'nom' => $legacyBen->nom ?? 'Inconnu',
                    'prenoms' => $legacyBen->prenoms ?? '',
                    'date_naissance' => $legacyBen->date_naissance,
                    'lieu_naissance' => $legacyBen->lieu_naissance,
                    'sexe' => $legacyBen->sexe,
                    'telephone_principal' => $legacyBen->contact_tel1,
                    'telephone_secondaire' => $legacyBen->contact_tel2,
                    'nature_piece_identite' => $legacyBen->nature_pieceidentite,
                    'numero_piece_identite' => $legacyBen->num_pieceidentite,
                    'niveau_etude_declare' => $legacyBen->niveau_etude,
                    'est_handicape' => !empty($legacyBen->handicap) && strtolower($legacyBen->handicap) !== 'non',
                    'nature_handicap' => $legacyBen->type_handicap,
                ]
            );
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
    }

    private function migrateStages()
    {
        $this->info("Migration complète des contrats/stages (contrats_pae)...");
        $contrats = DB::connection('legacy')->table('contrats_pae')->get();

        $bar = $this->output->createProgressBar(count($contrats));
        $bar->start();

        foreach ($contrats as $legacyContrat) {
            // 1. Création du Stage (ancien contrats_pae)
            $stage = Stage::updateOrCreate(
                ['ancien_id' => $legacyContrat->id],
                [
                    'beneficiaire_id' => $legacyContrat->beneficiaire_id ?? 1,
                    'entreprise_id' => $legacyContrat->id_entreprise ?? 1,
                    'agence_id' => $legacyContrat->id_agence ?? 1,
                    'type_stage_id' => $legacyContrat->id_type_stage ?? 1,
                    'source_financement_id' => $legacyContrat->source_financement ?? 1,
                    'conseiller_id' => $legacyContrat->conseiller_id ?? null,
                    'date_entree_portefeuille' => $legacyContrat->date_entree ?? null,
                    
                    'service_affectation' => $legacyContrat->service_affectation ?? null,
                    'intitule_poste' => $legacyContrat->intitule_poste_stage ?? 'Poste non défini',
                    
                    'localite_stage' => $legacyContrat->lieu_de_stage ?? null,
                    
                    'nom_encadreur' => $legacyContrat->nom_encadreur ?? null,
                    
                    'date_debut' => $legacyContrat->date_debut ?? now(),
                    'date_fin_prevue' => $legacyContrat->date_fin ?? now()->addMonths(6),
                    'observations' => $legacyContrat->observation ?? null,
                ]
            );

            // 2. Gérer le Contrat Financier lié
            \App\Models\Contract\Contrat::updateOrCreate(
                ['ancien_id' => $legacyContrat->id],
                [
                    'stage_id' => $stage->id,
                    'numero' => 'CT-' . str_pad($legacyContrat->id, 5, '0', STR_PAD_LEFT),
                    'date_debut' => $legacyContrat->date_debut ?? now(),
                    'date_fin' => $legacyContrat->date_fin ?? now()->addMonths(6),
                    'prime_mensuelle' => $legacyContrat->montant_du ?? 45000,
                    'statut' => 'SIGNE', // Les anciens contrats étaient signés
                ]
            );

            // 3. Gérer le Workflow via contrat_etape / etape_traitement
            $statutLegacy = $legacyContrat->etapetraitement_id ?? $legacyContrat->id_statut_stage;
            $corbeilleEnum = $this->mapper->mapStatutStageToCorbeille($statutLegacy ?? 1);

            InstanceParcours::updateOrCreate(
                ['stage_id' => $stage->id],
                [
                    'corbeille_actuelle' => $corbeilleEnum->value,
                ]
            );

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
    }

    private function migratePointages()
    {
        $this->info("Migration des pointages (pointage_models)...");
        $pointages = DB::connection('legacy')->table('pointage_models')->get();

        $bar = $this->output->createProgressBar(count($pointages));
        $bar->start();

        foreach ($pointages as $legacyPointage) {
            $stage = Stage::where('ancien_id', $legacyPointage->stagiaire_id)->first();

            if ($stage) {
                // Mapper le statut du pointage
                $statut = 'SOUMIS';
                if ($legacyPointage->status_dmg == 2) $statut = 'AJOURNE_DMG';
                if ($legacyPointage->status_ca == 2) $statut = 'AJOURNE_CA';
                if ($legacyPointage->status_dmg == 1 && $legacyPointage->status_ca == 1) $statut = 'VALIDE';

                \App\Models\Attendance\Pointage::updateOrCreate(
                    ['id' => $legacyPointage->id],
                    [
                        'stage_id' => $stage->id,
                        'periode_id' => 1, 
                        'statut' => $statut,
                        'commentaire' => $legacyPointage->commentaire,
                        'soumis_le' => clone new \DateTime($legacyPointage->created_at ?? 'now'),
                    ]
                );
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
    }

    private function migratePaiements()
    {
        $this->info("Migration des paiements (paiement_models)...");
        $paiements = DB::connection('legacy')->table('paiement_models')->get();

        $bar = $this->output->createProgressBar(count($paiements));
        $bar->start();

        foreach ($paiements as $legacyPaiement) {
            // Créer le droit de paiement (la base du paiement dans la nouvelle architecture)
            $stage_id = Stage::where('ancien_id', $legacyPaiement->stagiaire_id)->value('id') ?? 1;
            $droit = \App\Models\Payment\DroitPaiement::updateOrCreate(
                ['ancien_id' => $legacyPaiement->id],
                [
                    'stage_id' => $stage_id,
                    'periode_id' => 1,
                    'nature' => 'PRESENCE',
                    'montant_calcule' => $legacyPaiement->montant,
                    'statut' => 'CALCULE',
                ]
            );

            // Créer le paiement réel
            \App\Models\Payment\Paiement::updateOrCreate(
                ['ancien_id' => $legacyPaiement->id],
                [
                    'droit_paiement_id' => $droit->id,
                    'montant' => $legacyPaiement->montant,
                    'statut' => 'A_TRAITER',
                ]
            );

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
    }

    private function migrateEvenements()
    {
        $this->info("Migration de l'historique (contrat_etape)...");
        $historique = DB::connection('legacy')->table('contrat_etape')->get();

        $bar = $this->output->createProgressBar(count($historique));
        $bar->start();

        foreach ($historique as $legacyEvent) {
            $stage_id = Stage::where('ancien_id', $legacyEvent->contrat_id)->value('id');
            if ($stage_id) {
                $instance = InstanceParcours::where('stage_id', $stage_id)->first();
                if ($instance) {
                    \App\Models\Workflow\EvenementParcours::updateOrCreate(
                        [
                            'instance_parcours_id' => $instance->id,
                            'survenu_le' => $legacyEvent->created_at, // Clé composite approximative pour ne pas dupliquer
                        ],
                        [
                            'type_evenement' => 'MIGRATION_STATUT',
                            'description' => "Passage à l'étape legacy ID : " . $legacyEvent->etape_id,
                            'donnees' => json_encode(['commentaire' => $legacyEvent->commentaire]),
                            'acteur_id' => 1, // À résoudre depuis legacy user_id
                        ]
                    );
                }
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
    }
}
