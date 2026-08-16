<?php

namespace App\Console\Commands;

use App\Models\Reference\Agence;
use App\Models\System\User;
use App\Models\Internship\Stage;
use App\Models\Workflow\InstanceParcours;
use App\Services\Migration\LegacyMapperService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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

        if ($step === 'all' || $step === 'references') {
            $this->migrateReferences();
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

        if ($step === 'all' || $step === 'offres') {
            $this->migrateOffres();
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

    private function migrateReferences()
    {
        $this->info("Migration des données de référence...");
        
        // Regions
        $regions = DB::connection('legacy')->table('regions')->get();
        foreach ($regions as $r) {
            \App\Models\Reference\Region::updateOrCreate(
                ['ancien_id' => $r->id],
                [
                    'code' => $r->code ?? 'REG-' . $r->id,
                    'nom' => $r->libelle ?? 'Region ' . $r->id,
                ]
            );
        }

        // Types de stage
        if (Schema::connection('legacy')->hasTable('type_stage')) {
            $types = DB::connection('legacy')->table('type_stage')->get();
            foreach ($types as $t) {
                \App\Models\Reference\TypeStage::updateOrCreate(
                    ['ancien_id' => $t->id],
                    [
                        'code' => 'TS-' . $t->id,
                        'nom' => $t->libelle_type_stage ?? 'Type ' . $t->id,
                    ]
                );
            }
        }

        // Sources de financement
        if (Schema::connection('legacy')->hasTable('type_financements')) {
            $sources = DB::connection('legacy')->table('type_financements')->get();
            foreach ($sources as $s) {
                \App\Models\Reference\SourceFinancement::updateOrCreate(
                    ['ancien_id' => $s->id],
                    [
                        'code' => 'SF-' . $s->id,
                        'nom' => $s->libelle_financement ?? 'Source ' . $s->id,
                    ]
                );
            }
        }
        
        $this->newLine();
    }

    private function migrateAgences()
    {
        $this->info("Migration des agences...");
        $agences = DB::connection('legacy')->table('agences')->get();

        $bar = $this->output->createProgressBar(count($agences));
        $bar->start();

        foreach ($agences as $legacyAgence) {
            // Find region from agence_region pivot if it exists
            $legacyRegionId = null;
            if (Schema::connection('legacy')->hasTable('agence_region')) {
                $pivot = DB::connection('legacy')->table('agence_region')
                    ->where('agence_id', $legacyAgence->id)
                    ->first();
                if ($pivot) {
                    $legacyRegionId = $pivot->region_id;
                }
            }

            $region_id = null;
            if ($legacyRegionId) {
                $region_id = \App\Models\Reference\Region::where('ancien_id', $legacyRegionId)->value('id');
            }

            \App\Models\Reference\Agence::updateOrCreate(
                ['ancien_id' => $legacyAgence->id],
                [
                    'nom' => $legacyAgence->libelle_agence ?? 'Agence Inconnue',
                    'code' => 'AG-' . str_pad($legacyAgence->id, 3, '0', STR_PAD_LEFT),
                    'region_id' => $region_id,
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
            $email = $this->mapper->sanitizeEmail($legacyUser->email, $legacyUser->nom ?? 'User', $legacyUser->pseudo ?? '', $legacyUser->id);
            
            $user = \App\Models\User::updateOrCreate(
                ['email' => $email],
                [
                    'nom' => $legacyUser->nom ?? 'Inconnu',
                    'prenoms' => $legacyUser->pseudo ?? '',
                    'password' => $legacyUser->password, // On garde l'ancien hash
                ]
            );

            // Assigner le rôle Spatie
            $roleName = $this->mapper->mapTypeUserToRole($legacyUser->type_user_id);
            if ($roleName && !$user->hasRole($roleName)) {
                // $user->assignRole($roleName);
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
            $agence_id = \App\Models\Reference\Agence::where('ancien_id', $legacyEntreprise->agence_id)->value('id');
            
            $numContribuable = $legacyEntreprise->compte_contri ?: null;
            if ($numContribuable) {
                $exists = \App\Models\Company\Entreprise::where('numero_contribuable', $numContribuable)
                    ->where('ancien_id', '!=', $legacyEntreprise->id)
                    ->exists();
                if ($exists) {
                    $numContribuable = $numContribuable . '_' . $legacyEntreprise->id;
                }
            }

            $registreCommerce = $legacyEntreprise->rccm ?: null;
            if ($registreCommerce) {
                $exists = \App\Models\Company\Entreprise::where('registre_commerce', $registreCommerce)
                    ->where('ancien_id', '!=', $legacyEntreprise->id)
                    ->exists();
                if ($exists) {
                    $registreCommerce = $registreCommerce . '_' . $legacyEntreprise->id;
                }
            }

            \App\Models\Company\Entreprise::updateOrCreate(
                ['ancien_id' => $legacyEntreprise->id],
                [
                    'raison_sociale' => $legacyEntreprise->libelle_entreprise ?? 'Inconnu',
                    'sigle' => $legacyEntreprise->sigle,
                    'numero_contribuable' => $numContribuable,
                    'registre_commerce' => $registreCommerce,
                    'telephone' => $legacyEntreprise->contact,
                    'email' => $legacyEntreprise->mail,
                    'adresse' => $legacyEntreprise->adresse,
                    'agence_id' => $agence_id,
                ]
            );
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
    }

    private function migrateOffres()
    {
        $this->info("Migration des offres d'emploi...");
        $offres = DB::connection('legacy')->table('offre')->get();

        $bar = $this->output->createProgressBar(count($offres));
        $bar->start();

        foreach ($offres as $legacyOffre) {
            $entreprise_id = \App\Models\Company\Entreprise::where('ancien_id', $legacyOffre->entreprise_id)->value('id');
            $agence_id = \App\Models\Reference\Agence::where('ancien_id', $legacyOffre->agence_id)->value('id');
            $type_stage_id = \App\Models\Reference\TypeStage::where('ancien_id', $legacyOffre->type_stage_id)->value('id') ?? 1;
            $source_financement_id = \App\Models\Reference\SourceFinancement::where('ancien_id', $legacyOffre->source_financement_id)->value('id') ?? 1;
            
            if ($entreprise_id && $agence_id) {
                $publiee_le = $legacyOffre->date_de_publication;
                if ($publiee_le && (str_starts_with($publiee_le, '-') || str_starts_with($publiee_le, '0000'))) {
                    $publiee_le = null;
                }

                \App\Models\Company\OffreEmploi::updateOrCreate(
                    ['ancien_id' => $legacyOffre->id_offre],
                    [
                        'entreprise_id' => $entreprise_id,
                        'agence_id' => $agence_id,
                        'type_stage_id' => $type_stage_id,
                        'source_financement_id' => $source_financement_id,
                        'numero' => 'OFR-' . str_pad($legacyOffre->id_offre, 5, '0', STR_PAD_LEFT),
                        'intitule' => $legacyOffre->intitule_offre ?? 'Offre non spécifiée',
                        'nombre_places' => max(1, (int)($legacyOffre->nombre_de_place ?? 1)),
                        'publiee_le' => $publiee_le,
                    ]
                );
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
    }

    private function migrateBeneficiaires()
    {
        $this->info("Migration des bénéficiaires (beneficiaire_stages)...");
        // On suppose que la table principale historique s'appelait beneficiaire_stages ou dossiers_stagiaires
        $query = DB::connection('legacy')->table('beneficiaire_stages');
        $total = $query->count();

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $query->orderBy('id')->chunk(1000, function ($beneficiaires) use (&$bar) {
            foreach ($beneficiaires as $legacyBen) {
            $niveau_etude_id = null;
            if (!empty($legacyBen->niveau_etude)) {
                $code = 'NE-' . strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $legacyBen->niveau_etude), 0, 5));
                $niveau = \App\Models\Reference\NiveauEtude::firstOrCreate(
                    ['code' => $code],
                    ['nom' => $legacyBen->niveau_etude]
                );
                $niveau_etude_id = $niveau->id;
            }

            $date_naissance = $legacyBen->date_naissance;
            if ($date_naissance && (str_starts_with($date_naissance, '-') || str_starts_with($date_naissance, '0000'))) {
                $date_naissance = null;
            }

            if (empty($legacyBen->numero_aej)) {
                continue; // Skip if no numero_aej as it's required and unique
            }

            \App\Models\Beneficiary\Beneficiaire::updateOrCreate(
                ['numero_aej' => $legacyBen->numero_aej],
                [
                    'ancien_id' => $legacyBen->id,
                    'nom' => $legacyBen->nom ?? 'Inconnu',
                    'prenoms' => $legacyBen->prenoms ?? '',
                    'date_naissance' => $date_naissance,
                    'lieu_naissance' => $legacyBen->lieu_naissance,
                    'sexe' => $legacyBen->sexe,
                    'telephone_principal' => $legacyBen->contact_tel1,
                    'telephone_secondaire' => $legacyBen->contact_tel2,
                    'nature_piece_identite' => $legacyBen->nature_pieceidentite,
                    'numero_piece_identite' => $legacyBen->num_pieceidentite,
                    'niveau_etude_id' => $niveau_etude_id,
                    'autre_handicap' => !empty($legacyBen->handicap) && strtolower($legacyBen->handicap) !== 'non' ? ($legacyBen->type_handicap ?? 'Handicap signalé') : null,
                ]
            );
            $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
    }

    private function migrateStages()
    {
        $this->info("Migration complète des contrats/stages (contrats_pae)...");
        $query = DB::connection('legacy')->table('contrats_pae');
        $total = $query->count();

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        // Load mappings once to save memory and avoid querying per row
        $agencesMap = \App\Models\Reference\Agence::pluck('id', 'ancien_id')->toArray();
        $typesStageMap = \App\Models\Reference\TypeStage::pluck('id', 'ancien_id')->toArray();
        $entreprisesMap = \App\Models\Company\Entreprise::pluck('id', 'ancien_id')->toArray();
        $sourcesFinancementMap = \App\Models\Reference\SourceFinancement::pluck('id', 'code')->toArray();
        $defaultSourceId = $sourcesFinancementMap['DEF'] ?? 1;

        $query->orderBy('id')->chunk(1000, function ($contrats) use (&$bar, $agencesMap, $typesStageMap, $entreprisesMap, $defaultSourceId) {
            $aejNums = $contrats->pluck('numero_aej')->filter()->unique()->toArray();
            $beneficiairesMap = \App\Models\Beneficiary\Beneficiaire::whereIn('numero_aej', $aejNums)->pluck('id', 'numero_aej')->toArray();

            foreach ($contrats as $legacyContrat) {
                $beneficiaire_id = $beneficiairesMap[$legacyContrat->numero_aej] ?? null;
                $entreprise_id = $entreprisesMap[$legacyContrat->id_entreprise] ?? null;
                $agence_id = $agencesMap[$legacyContrat->id_agence] ?? null;
                $type_stage_id = $typesStageMap[$legacyContrat->id_type_stage] ?? 1;
                $source_financement_id = $sourcesFinancementMap[$legacyContrat->source_financement] ?? $defaultSourceId;

                if (!$beneficiaire_id || !$entreprise_id || !$agence_id) {
                    $bar->advance();
                    continue;
                }

                // 1. Création du Stage (ancien contrats_pae)
                $date_entree = $legacyContrat->date_entree;
                if ($date_entree && (str_starts_with($date_entree, '-') || str_starts_with($date_entree, '0000'))) {
                    $date_entree = null;
                }

                $stage = Stage::updateOrCreate(
                    ['ancien_id' => $legacyContrat->id],
                    [
                        'beneficiaire_id' => $beneficiaire_id,
                        'entreprise_id' => $entreprise_id,
                        'agence_id' => $agence_id,
                        'type_stage_id' => $type_stage_id,
                        'source_financement_id' => $source_financement_id,
                        'conseiller_id' => null, // conseiller mapping needs conseillers table populated
                        'date_entree_portefeuille' => $date_entree,
                        
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
                $corbeilleEnum = $this->mapper->mapChefAgenceCorbeille($legacyContrat);
                if ($corbeilleEnum === \App\Enums\CorbeilleEnum::CIP_MES_STAGIAIRES) {
                    $statutLegacy = (int) ($legacyContrat->etapetraitement_id ?? $legacyContrat->id_statut_stage ?? 1);
                    $corbeilleEnum = $this->mapper->mapStatutStageToCorbeille($statutLegacy);
                }

                $definition = \App\Models\Workflow\DefinitionParcours::firstOrCreate(
                    ['code' => 'STAGE_LEGACY', 'version' => 1],
                    ['nom' => 'Parcours Legacy', 'active' => true]
                );

                $etape = \App\Models\Workflow\EtapeParcours::firstOrCreate(
                    ['definition_parcours_id' => $definition->id, 'code' => 'INIT_LEGACY'],
                    ['nom' => 'Initiale Legacy', 'initiale' => true, 'finale' => false]
                );

                InstanceParcours::updateOrCreate(
                    ['stage_id' => $stage->id],
                    [
                        'definition_parcours_id' => $definition->id,
                        'etape_courante_id' => $etape->id,
                        'corbeille_actuelle' => $corbeilleEnum->value,
                    ]
                );

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
    }

    private function migratePointages()
    {
        $query = DB::connection('legacy')->table('pointage_models');
        $total = $query->count();

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $periodesMap = DB::table('periodes')->pluck('id', 'code')->toArray();

        $query->orderBy('id')->chunk(5000, function ($pointages) use (&$bar, &$periodesMap) {
            $stagiaireIds = $pointages->pluck('stagiaire_id')->filter()->unique()->toArray();
            $stagesMap = Stage::whereIn('ancien_id', $stagiaireIds)->pluck('id', 'ancien_id')->toArray();

            foreach ($pointages as $legacyPointage) {
                $stage_id = $stagesMap[$legacyPointage->stagiaire_id] ?? null;

                if ($stage_id) {
                    // Mapper le statut du pointage
                    $statut = 'SOUMIS';
                    if ($legacyPointage->status_dmg == 2) $statut = 'AJOURNE_DMG';
                    if ($legacyPointage->status_ca == 2) $statut = 'AJOURNE_CA';
                    if ($legacyPointage->status_dmg == 1 && $legacyPointage->status_ca == 1) $statut = 'VALIDE';

                    // Créer dynamiquement une période basée sur la date de création
                    $date = new \DateTime($legacyPointage->created_at ?? 'now');
                    $codePeriode = $date->format('Y-m');

                    if (!isset($periodesMap[$codePeriode])) {
                        $periodesMap[$codePeriode] = DB::table('periodes')->insertGetId([
                            'code' => $codePeriode,
                            'date_debut' => $date->format('Y-m-01'),
                            'date_fin' => $date->format('Y-m-t'),
                            'ouverte_pointage' => false,
                            'ouverte_paiement' => false,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                    $periodeId = $periodesMap[$codePeriode];

                try {
                    $pointage = \App\Models\Attendance\Pointage::updateOrCreate(
                        ['ancien_id' => $legacyPointage->id],
                        [
                            'stage_id' => $stage_id,
                            'periode_id' => $periodeId,
                            'nature' => 'PRESENCE',
                            'statut' => $statut,
                        ]
                    );

                    \App\Models\Attendance\VersionPointage::updateOrCreate(
                        ['pointage_id' => $pointage->id, 'numero_version' => 1],
                        [
                            'presence' => 'PRESENT',
                            'jours_presents' => 30,
                            'jours_absents' => 0,
                            'observation' => $legacyPointage->commentaire,
                            'saisi_le' => clone new \DateTime($legacyPointage->created_at ?? 'now'),
                        ]
                    );
                } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
                    // Ignorer les doublons de pointages pour le même stage sur la même période
                }
            }
            $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
    }

    private function migratePaiements()
    {
        $this->info("Migration des paiements (paiement_models)...");
        $query = DB::connection('legacy')->table('paiement_models');
        $total = $query->count();

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $periodesMap = DB::table('periodes')->pluck('id', 'code')->toArray();
        $source_financement = DB::table('sources_financement')->first();
        $source_financement_id = $source_financement ? $source_financement->id : DB::table('sources_financement')->insertGetId(['code' => 'DEF', 'libelle' => 'Défaut', 'actif' => true, 'created_at' => now(), 'updated_at' => now()]);

        $query->orderBy('id')->chunk(5000, function ($paiements) use (&$bar, &$periodesMap, $source_financement_id) {
            $stagiaireIds = $paiements->pluck('stagiaire_id')->filter()->unique()->toArray();
            $stagesMap = Stage::whereIn('ancien_id', $stagiaireIds)->pluck('id', 'ancien_id')->toArray();

            foreach ($paiements as $legacyPaiement) {
                // Créer le droit de paiement (la base du paiement dans la nouvelle architecture)
                $stage_id = $stagesMap[$legacyPaiement->stagiaire_id] ?? 1;

                // Créer dynamiquement une période basée sur la date de création du paiement
                $date = new \DateTime($legacyPaiement->created_at ?? 'now');
                $codePeriode = $date->format('Y-m');

                if (!isset($periodesMap[$codePeriode])) {
                    $periodesMap[$codePeriode] = DB::table('periodes')->insertGetId([
                        'code' => $codePeriode,
                        'date_debut' => $date->format('Y-m-01'),
                        'date_fin' => $date->format('Y-m-t'),
                        'ouverte_pointage' => false,
                        'ouverte_paiement' => false,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
                $periodeId = $periodesMap[$codePeriode];

            try {
                $droit = \App\Models\Payment\DroitPaiement::updateOrCreate(
                    ['ancien_id' => $legacyPaiement->id],
                    [
                        'stage_id' => $stage_id,
                        'periode_id' => $periodeId,
                        'source_financement_id' => $source_financement_id,
                        'nature' => 'PRESENCE',
                        'montant' => $legacyPaiement->montant,
                        'statut' => 'OUVERT',
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
            } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
                // Ignorer les doublons de droits de paiement
            }
            $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
    }

    private function migrateEvenements()
    {
        $this->info("Migration de l'historique (contrat_etape)...");
        $query = DB::connection('legacy')->table('contrat_etape');
        $total = $query->count();

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $query->orderBy('id')->chunk(5000, function ($historique) use (&$bar) {
            $contratIds = $historique->pluck('contrat_id')->filter()->unique()->toArray();
            $stagesMap = Stage::whereIn('ancien_id', $contratIds)->pluck('id', 'ancien_id')->toArray();
            $instancesMap = InstanceParcours::whereIn('stage_id', array_values($stagesMap))->get()->keyBy('stage_id');

            foreach ($historique as $legacyEvent) {
                $stage_id = $stagesMap[$legacyEvent->contrat_id] ?? null;
                if ($stage_id) {
                    $instance = $instancesMap[$stage_id] ?? null;
                    if ($instance) {
                        $corbeilleCible = $this->mapper->mapStatutStageToCorbeille($legacyEvent->etape_id ?? 1)->value;

                        \App\Models\Workflow\EvenementParcours::updateOrCreate(
                            [
                                'instance_parcours_id' => $instance->id,
                                'cle_idempotence' => 'mig_' . $legacyEvent->id . '_' . $instance->id,
                            ],
                            [
                                'etape_cible_id' => $instance->etape_courante_id, // we might not know the exact target step, default to current
                                'type' => 'MIGRATION_STATUT',
                                'donnees' => json_encode([
                                    'commentaire' => $legacyEvent->commentaire,
                                    'description' => "Passage à l'étape legacy ID : " . $legacyEvent->etape_id,
                                    'corbeille_cible' => $corbeilleCible
                                ]),
                                'auteur_id' => 1, // default user
                                'survenu_le' => $legacyEvent->created_at ?? now(),
                            ]
                        );
                    }
                }
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
    }
}
