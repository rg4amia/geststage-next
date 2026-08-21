<?php

namespace App\Console\Commands;

use App\Domain\Workflow\Services\DesseDoublonService;
use App\Enums\CorbeilleEnum;
use App\Enums\DoublonTypeEnum;
use App\Models\Attendance\Pointage;
use App\Models\Attendance\VersionPointage;
use App\Models\Beneficiary\Beneficiaire;
use App\Models\Company\Entreprise;
use App\Models\Company\OffreEmploi;
use App\Models\Contract\Contrat;
use App\Models\Internship\Stage;
use App\Models\Payment\BordereauPaiement;
use App\Models\Payment\DossierGroupe;
use App\Models\Payment\DossierPaiement;
use App\Models\Payment\DroitPaiement;
use App\Models\Payment\LigneDossierPaiement;
use App\Models\Payment\OrdrePaiement;
use App\Models\Payment\Paiement;
use App\Models\Reference\Agence;
use App\Models\Reference\Commune;
use App\Models\Reference\Diplome;
use App\Models\Reference\Handicap;
use App\Models\Reference\LienParente;
use App\Models\Reference\NiveauEtude;
use App\Models\Reference\OrigineStagiaire;
use App\Models\Reference\SourceFinancement;
use App\Models\Reference\TypeEnseignement;
use App\Models\Reference\TypeHandicap;
use App\Models\Reference\TypePaiement;
use App\Models\Reference\TypeStage;
use App\Models\Reference\TypeStructure;
use App\Models\User;
use App\Models\Workflow\DefinitionParcours;
use App\Models\Workflow\DesseDoublonDecision;
use App\Models\Workflow\EtapeParcours;
use App\Models\Workflow\EvenementParcours;
use App\Models\Workflow\InstanceParcours;
use App\Models\Workflow\TacheParcours;
use App\Services\Migration\LegacyMapperService;
use App\Services\Migration\LegacyMigrationRecorder;
use Carbon\Carbon;
use Database\Seeders\ContratsPaeColumnMappingSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Throwable;

class MigrateLegacyDataCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:legacy-data
        {--step=all : Phase à exécuter (references, users, entreprises, offres, beneficiaires, stages, pointages, paiements, dossiers_paiement, dossiers_groupes, operations, bordereaux, evenements, desse_doublons, all)}
        {--dry-run : Exécute toutes les transformations puis annule les écritures PostgreSQL}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migre les données de l\'ancienne base (legacy) vers la nouvelle base PostgreSQL.';

    private const SOURCE_VERSION = 'gestage-mysql-v2';

    private int $executionId;

    private int $sourceContractColumnCount = 0;

    /** @var array<string, mixed> */
    private array $migrationCounters = [];

    public function __construct(
        private LegacyMapperService $mapper,
        private DesseDoublonService $doublonService,
        private LegacyMigrationRecorder $recorder,
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $step = (string) $this->option('step');
        $dryRun = (bool) $this->option('dry-run');
        $allowedSteps = [
            'all', 'references', 'agences', 'users', 'entreprises', 'offres', 'beneficiaires',
            'stages', 'pointages', 'paiements', 'dossiers_paiement', 'dossiers_groupes',
            'operations', 'bordereaux', 'evenements', 'desse_doublons',
        ];
        if (! in_array($step, $allowedSteps, true)) {
            $this->error("Phase inconnue : {$step}.");

            return self::INVALID;
        }

        $this->migrationCounters = [];
        $this->sourceContractColumnCount = 0;
        $this->info("Début de la migration des données (Étape : $step)...");

        try {
            DB::connection('legacy')->getPdo();
        } catch (\Exception $e) {
            $this->error("Impossible de se connecter à la base 'legacy'. Vérifiez votre config/database.php et .env");
            $this->error($e->getMessage());

            return 1;
        }

        if ($dryRun) {
            DB::beginTransaction();
            $this->warn('Mode dry-run : toutes les écritures PostgreSQL seront annulées.');
        }

        try {
            if (in_array($step, ['all', 'beneficiaires', 'stages'], true)) {
                $this->call('db:seed', [
                    '--class' => ContratsPaeColumnMappingSeeder::class,
                    '--force' => true,
                ]);
                $schema = $this->recorder->validateContratsPaeSchema();
                $this->sourceContractColumnCount = $schema['source'];
                $this->migrationCounters['schema_contrats_pae'] = $schema;
            }

            $this->executionId = $this->recorder->start(self::SOURCE_VERSION.'-'.$step);

            if ($step === 'all' || $step === 'references' || $step === 'agences') {
                // legacy:migrer-referentiels est la source unique de vérité pour les référentiels
                // (régions, communes, agences, conseillers, types_stage, sources_financement, etc.) :
                // elle génère des codes lisibles à partir des libellés legacy, contrairement à
                // l'ancien code de cette commande qui fabriquait des codes ad-hoc (TS-1, SF-2, ...)
                // incompatibles et qui écrasait ces mêmes lignes (même clé ancien_id) à chaque run.
                $this->call('legacy:migrer-referentiels');
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

            if ($step === 'all' || $step === 'paiements' || $step === 'dossiers_paiement') {
                $this->backfillLegacyDossiersPaiement();
            }

            if ($step === 'all' || $step === 'dossiers_groupes') {
                $this->migrateDossiersGroupes();
            }

            if ($step === 'all' || $step === 'operations') {
                $this->migrateOperations();
            }

            if ($step === 'all' || $step === 'bordereaux') {
                $this->migrateBordereaux();
            }

            if ($step === 'all' || $step === 'evenements') {
                $this->migrateEvenements();
            }

            if ($step === 'all' || $step === 'desse_doublons') {
                $this->migrateDesseDoublonDecisions();
            }

            $this->migrationCounters += $this->collectMigrationCounters();
            $this->recorder->complete($this->executionId, $this->migrationCounters);
            $this->line('Compteurs : '.json_encode($this->migrationCounters, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

            if ($dryRun) {
                DB::rollBack();
                $this->warn('Dry-run terminé : aucune écriture PostgreSQL conservée.');
            }
        } catch (Throwable $e) {
            if ($dryRun && DB::transactionLevel() > 0) {
                DB::rollBack();
            } elseif (isset($this->executionId)) {
                $this->recorder->fail($this->executionId, $this->migrationCounters, $e->getMessage());
            }

            $this->error('Migration interrompue : '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Migration terminée !');

        return self::SUCCESS;
    }

    private function migrateUsers(): void
    {
        $this->info('Migration des utilisateurs...');
        $users = DB::connection('legacy')->table('users')->get();

        $bar = $this->output->createProgressBar(count($users));
        $bar->start();

        foreach ($users as $legacyUser) {
            $email = $this->mapper->sanitizeEmail($legacyUser->email, $legacyUser->nom ?? 'User', $legacyUser->pseudo ?? '', $legacyUser->id);

            $user = User::updateOrCreate(
                ['email' => $email],
                [
                    'nom' => $legacyUser->nom ?? 'Inconnu',
                    'prenoms' => $legacyUser->pseudo ?? '',
                    'password' => $legacyUser->password, // On garde l'ancien hash
                    // Champ reporté tel quel : le modèle User n'a pas (encore) le trait SoftDeletes,
                    // donc ceci ne bloque pas la connexion, ça garde juste l'info pour plus tard.
                    'deleted_at' => $this->mapper->normalizeLegacyDate($legacyUser->deleted_at ?? null),
                ]
            );

            // Assigner le rôle Spatie
            $roleName = $this->mapper->mapTypeUserToRole($legacyUser->type_user_id);
            if ($roleName !== null && Role::where('name', $roleName)->exists() && ! $user->hasRole($roleName)) {
                $user->syncRoles([$roleName]);
            }

            $this->recorder->correspondence(
                $this->executionId,
                'users',
                $legacyUser->id,
                'users',
                $user->id,
                (array) $legacyUser,
            );

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
    }

    private function migrateEntreprises(): void
    {
        $this->info('Migration des entreprises...');
        $entreprises = DB::connection('legacy')->table('entreprises')->get();

        // Le legacy ne porte pas type_structure_id sur "entreprises" mais sur chaque
        // contrats_pae (une entreprise peut donc avoir plusieurs valeurs différentes selon
        // les stages) : on retient la valeur la plus fréquente par entreprise comme meilleure
        // approximation d'un attribut réellement propre à l'entreprise.
        $typeStructureParEntreprise = DB::connection('legacy')->table('contrats_pae')
            ->select('id_entreprise', 'type_structure_id', DB::raw('COUNT(*) as occurrences'))
            ->whereNotNull('type_structure_id')
            ->groupBy('id_entreprise', 'type_structure_id')
            ->orderByDesc('occurrences')
            ->get()
            ->groupBy('id_entreprise')
            ->map(fn ($rows) => $rows->first()->type_structure_id);
        $typesStructureMap = TypeStructure::pluck('id', 'ancien_id')->toArray();

        // Quelques entreprises legacy partagent le même compte_contri/rccm : soit de vraies
        // saisies en double, soit (le plus souvent) des valeurs "bouche-trou" ("NEANT", "XXX",
        // "RAS", "0", ...) recopiées par dizaines d'agents faute de donnée réelle — ce ne sont
        // pas de vrais identifiants et ne doivent pas être traitées comme tels.
        // L'ancienne logique vérifiait aussi l'unicité en re-questionnant la table à chaque
        // ligne : selon l'ordre (non garanti) de traitement des lignes dans une même exécution,
        // deux lignes pouvaient temporairement croire détenir la valeur "canonique" et entrer en
        // conflit. On fixe maintenant une règle déterministe indépendante de l'ordre : seule la
        // ligne legacy au plus petit id garde la valeur telle quelle, les autres sont suffixées.
        $estPlaceholder = function (?string $valeur): bool {
            if ($valeur === null) {
                return true;
            }
            $v = mb_strtoupper(trim(Str::ascii($valeur)));

            return $v === ''
                || preg_match('/^X+$/', $v) === 1
                || preg_match('/^0+$/', $v) === 1
                || in_array($v, ['NEANT', 'RAS', 'ND', 'NA', 'N/A', 'NP', 'AUCUN', 'NON', 'NONE', '-'], true);
        };

        $valeursValides = function (string $colonne) use ($estPlaceholder) {
            return DB::connection('legacy')->table('entreprises')
                ->select($colonne, 'id')
                ->whereNotNull($colonne)->where($colonne, '!=', '')
                ->get()
                ->reject(fn ($r) => $estPlaceholder($r->{$colonne}));
        };
        $premierIdParContribuable = $valeursValides('compte_contri')
            ->groupBy(fn ($r) => trim($r->compte_contri))->map(fn ($rows) => $rows->min('id'));
        $premierIdParRccm = $valeursValides('rccm')
            ->groupBy(fn ($r) => trim($r->rccm))->map(fn ($rows) => $rows->min('id'));

        $bar = $this->output->createProgressBar(count($entreprises));
        $bar->start();

        foreach ($entreprises as $legacyEntreprise) {
            $agence_id = Agence::where('ancien_id', $legacyEntreprise->agence_id)->value('id');
            $legacyTypeStructureId = $typeStructureParEntreprise[$legacyEntreprise->id] ?? null;
            $type_structure_id = $legacyTypeStructureId ? ($typesStructureMap[$legacyTypeStructureId] ?? null) : null;

            $numContribuable = $legacyEntreprise->compte_contri ? trim($legacyEntreprise->compte_contri) : null;
            if ($estPlaceholder($numContribuable)) {
                $numContribuable = null;
            } elseif (($premierIdParContribuable[$numContribuable] ?? $legacyEntreprise->id) !== $legacyEntreprise->id) {
                $numContribuable = $numContribuable.'_'.$legacyEntreprise->id;
            }

            $registreCommerce = $legacyEntreprise->rccm ? trim($legacyEntreprise->rccm) : null;
            if ($estPlaceholder($registreCommerce)) {
                $registreCommerce = null;
            } elseif (($premierIdParRccm[$registreCommerce] ?? $legacyEntreprise->id) !== $legacyEntreprise->id) {
                $registreCommerce = $registreCommerce.'_'.$legacyEntreprise->id;
            }

            if (! $agence_id) {
                $this->recorder->anomaly(
                    $this->executionId,
                    'ENTREPRISE_SANS_AGENCE',
                    'entreprises',
                    $legacyEntreprise->id,
                    "Entreprise legacy sans agence cible (agence_id={$legacyEntreprise->agence_id}).",
                    (array) $legacyEntreprise,
                );
                $bar->advance();

                continue;
            }

            $entreprise = Entreprise::withTrashed()->updateOrCreate(
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
                    'type_structure_id' => $type_structure_id,
                    // On reporte la suppression logique de l'entreprise legacy pour ne pas
                    // faire réapparaître dans les listes des entreprises que legacy cachait déjà.
                    'deleted_at' => $this->mapper->normalizeLegacyDate($legacyEntreprise->deleted_at ?? null),
                ]
            );
            $this->recorder->correspondence(
                $this->executionId,
                'entreprises',
                $legacyEntreprise->id,
                'entreprises',
                $entreprise->id,
                (array) $legacyEntreprise,
            );
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
    }

    private function migrateOffres(): void
    {
        $this->info("Migration des offres d'emploi...");
        $offres = DB::connection('legacy')->table('offre')->get();

        $bar = $this->output->createProgressBar(count($offres));
        $bar->start();

        foreach ($offres as $legacyOffre) {
            $entreprise_id = Entreprise::where('ancien_id', $legacyOffre->entreprise_id)->value('id');
            $agence_id = Agence::where('ancien_id', $legacyOffre->agence_id)->value('id');
            $type_stage_id = TypeStage::where('ancien_id', $legacyOffre->type_stage_id)->value('id');
            $source_financement_id = SourceFinancement::where('ancien_id', $legacyOffre->source_financement_id)->value('id');

            if ($entreprise_id && $agence_id && $type_stage_id && $source_financement_id) {
                $publiee_le = $legacyOffre->date_de_publication;
                if ($publiee_le && (str_starts_with($publiee_le, '-') || str_starts_with($publiee_le, '0000'))) {
                    $publiee_le = null;
                }

                $offre = OffreEmploi::withTrashed()->updateOrCreate(
                    ['ancien_id' => $legacyOffre->id_offre],
                    [
                        'entreprise_id' => $entreprise_id,
                        'agence_id' => $agence_id,
                        'type_stage_id' => $type_stage_id,
                        'source_financement_id' => $source_financement_id,
                        'numero' => 'OFR-'.str_pad($legacyOffre->id_offre, 5, '0', STR_PAD_LEFT),
                        'intitule' => $legacyOffre->intitule_offre ?? 'Offre non spécifiée',
                        'nombre_places' => max(1, (int) ($legacyOffre->nombre_de_place ?? 1)),
                        'publiee_le' => $publiee_le,
                        'deleted_at' => $this->mapper->normalizeLegacyDate($legacyOffre->deleted_at ?? null),
                    ]
                );
                $this->recorder->correspondence(
                    $this->executionId,
                    'offre',
                    $legacyOffre->id_offre,
                    'offres_emploi',
                    $offre->id,
                    (array) $legacyOffre,
                );
            } else {
                $this->recorder->anomaly(
                    $this->executionId,
                    'OFFRE_RELATION_INTROUVABLE',
                    'offre',
                    $legacyOffre->id_offre,
                    'Offre non migrée : entreprise, agence, type de stage ou financement cible introuvable.',
                    (array) $legacyOffre,
                );
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
    }

    private function migrateBeneficiaires(): void
    {
        $this->info('Migration des bénéficiaires (extraits de contrats_pae)...');
        // NB: la table 'beneficiaire_stages' est un export ponctuel figé (mai 2024, table Power BI)
        // dont les numero_aej ne correspondent quasiment jamais à ceux de contrats_pae (372 / 68933).
        // Les données bénéficiaire sont en réalité dénormalisées directement dans contrats_pae,
        // qui est la table opérationnelle réelle et à jour.
        // NB2: on ne reporte pas contrats_pae.deleted_at sur le bénéficiaire : plusieurs lignes
        // contrats_pae (donc plusieurs stages) peuvent partager le même numero_aej, et supprimer
        // le dossier d'un stage ne signifie pas que le bénéficiaire lui-même doit disparaître.
        // C'est stages.deleted_at / contrats.deleted_at qui portent l'état de suppression.
        $query = DB::connection('legacy')->table('contrats_pae');
        $total = $query->count();

        // Mappings chargés une fois (legacy ancien_id → nouvel id), maintenant que les
        // référentiels réels sont peuplés par legacy:migrer-referentiels.
        $typesPaiementMap = TypePaiement::pluck('id', 'ancien_id')->toArray();
        $handicapsMap = Handicap::pluck('id', 'ancien_id')->toArray();
        $typesHandicapMap = TypeHandicap::pluck('id', 'ancien_id')->toArray();
        $liensParenteMap = LienParente::pluck('id', 'ancien_id')->toArray();
        $typesEnseignementMap = TypeEnseignement::pluck('id', 'ancien_id')->toArray();
        $communesMap = Commune::pluck('id', 'ancien_id')->toArray();
        $niveauxParLibelle = NiveauEtude::pluck('id', 'nom')
            ->mapWithKeys(fn ($id, $nom) => [mb_strtoupper(trim((string) $nom)) => $id])
            ->toArray();
        // Le legacy ne relie pas contrats_pae.diplome (texte libre) à la table diplome par FK :
        // on rapproche par libellé normalisé (trim + majuscules), en meilleur effort.
        $diplomesParLibelle = Diplome::pluck('id', 'nom')
            ->mapWithKeys(fn ($id, $nom) => [mb_strtoupper(trim((string) $nom)) => $id])
            ->toArray();

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $query->orderBy('id')->chunk(1000, function ($contrats) use (
            &$bar, $typesPaiementMap, $handicapsMap, $typesHandicapMap,
            $liensParenteMap, $typesEnseignementMap, $communesMap, $niveauxParLibelle, $diplomesParLibelle
        ): void {
            foreach ($contrats as $legacyContrat) {
                $this->recorder->preserveContrat(
                    $this->executionId,
                    $legacyContrat,
                    null,
                    null,
                    null,
                    $this->sourceContractColumnCount,
                );

                if (empty($legacyContrat->numero_aej)) {
                    $this->recorder->anomaly(
                        $this->executionId,
                        'CONTRAT_SANS_NUMERO_AEJ',
                        'contrats_pae',
                        $legacyContrat->id,
                        'Bénéficiaire non normalisable : numero_aej absent.',
                        (array) $legacyContrat,
                    );
                    $bar->advance();

                    continue; // Skip if no numero_aej as it's required and unique
                }

                $niveau_etude_id = $niveauxParLibelle[mb_strtoupper(trim((string) $legacyContrat->niveau_etude))] ?? null;
                if ($niveau_etude_id === null) {
                    $this->recorder->anomaly(
                        $this->executionId,
                        'NIVEAU_ETUDE_NON_MAPPE',
                        'contrats_pae',
                        $legacyContrat->id,
                        "Niveau d'étude sans correspondance cible : {$legacyContrat->niveau_etude}.",
                        ['niveau_etude' => $legacyContrat->niveau_etude],
                        'NON_BLOQUANTE',
                    );
                }

                $diplome_id = null;
                if (! empty($legacyContrat->diplome)) {
                    $diplome_id = $diplomesParLibelle[mb_strtoupper(trim($legacyContrat->diplome))] ?? null;
                }

                $date_naissance = $legacyContrat->date_de_naissance;
                if ($date_naissance && (str_starts_with($date_naissance, '-') || str_starts_with($date_naissance, '0000'))) {
                    $date_naissance = null;
                }

                $beneficiaire = Beneficiaire::updateOrCreate(
                    ['numero_aej' => $legacyContrat->numero_aej],
                    [
                        'ancien_id' => $legacyContrat->id,
                        'nom' => $legacyContrat->nom_stagiaire ?? 'Inconnu',
                        'prenoms' => $legacyContrat->prenoms_stagiaire ?? '',
                        'date_naissance' => $date_naissance,
                        'lieu_naissance' => $legacyContrat->lieu_de_naissance,
                        'sexe' => $legacyContrat->sexe,
                        'telephone_principal' => $legacyContrat->contact1,
                        'telephone_secondaire' => $legacyContrat->contact2,
                        'nature_piece_identite' => $legacyContrat->nature_piece,
                        'numero_piece_identite' => $legacyContrat->num_piece,
                        'numero_cmu' => $legacyContrat->numero_cmu ?? null,
                        'commune_residence_id' => isset($legacyContrat->id_commune_de_residence) ? ($communesMap[$legacyContrat->id_commune_de_residence] ?? null) : null,
                        'personne_urgence' => $legacyContrat->personne_urgence ?? null,
                        'lien_parente_id' => isset($legacyContrat->lienparente_id) ? ($liensParenteMap[$legacyContrat->lienparente_id] ?? null) : null,
                        'contact_urgence_1' => $legacyContrat->prsurgent_tel1 ?? null,
                        'contact_urgence_2' => $legacyContrat->prsurgent_tel2 ?? null,
                        'niveau_etude_id' => $niveau_etude_id,
                        'diplome_id' => $diplome_id,
                        'autre_diplome' => $legacyContrat->autre_diplome ?? null,
                        'specialite' => $legacyContrat->specialite ?? null,
                        'annee_diplome' => $legacyContrat->annee_diplome ?: null,
                        'etablissement_frequente' => $legacyContrat->etablissement_frequente ?? null,
                        'type_enseignement_id' => isset($legacyContrat->typeenseignement_id) ? ($typesEnseignementMap[$legacyContrat->typeenseignement_id] ?? null) : null,
                        'handicap_id' => isset($legacyContrat->handicap_id) ? ($handicapsMap[$legacyContrat->handicap_id] ?? null) : null,
                        'type_handicap_id' => isset($legacyContrat->typehandicap_id) ? ($typesHandicapMap[$legacyContrat->typehandicap_id] ?? null) : null,
                        'autre_handicap' => ! empty($legacyContrat->handicap) && strtolower($legacyContrat->handicap) !== 'non'
                            ? ($legacyContrat->type_handicap ?? $legacyContrat->handicap)
                            : null,
                        'numero_tresor_money' => $legacyContrat->numero_yup ?? null,
                        'numero_wave' => $legacyContrat->numero_wave ?? null,
                        'type_paiement_id' => isset($legacyContrat->type_paiement_id) ? ($typesPaiementMap[$legacyContrat->type_paiement_id] ?? null) : null,
                    ]
                );
                $this->recorder->preserveContrat(
                    $this->executionId,
                    $legacyContrat,
                    $beneficiaire->id,
                    null,
                    null,
                    $this->sourceContractColumnCount,
                );
                $this->recorder->correspondence(
                    $this->executionId,
                    'contrats_pae',
                    $legacyContrat->id,
                    'beneficiaires',
                    $beneficiaire->id,
                    (array) $legacyContrat,
                );
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
    }

    private function migrateStages(): void
    {
        $this->info('Migration complète des contrats/stages (contrats_pae)...');
        $query = DB::connection('legacy')->table('contrats_pae');
        $total = $query->count();

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        // Load mappings once to save memory and avoid querying per row
        $agencesMap = Agence::pluck('id', 'ancien_id')->toArray();
        $typesStageMap = TypeStage::pluck('id', 'ancien_id')->toArray();
        $entreprisesMap = Entreprise::pluck('id', 'ancien_id')->toArray();
        // BUG corrigé : contrats_pae.source_financement contient l'ancien id numérique de
        // type_financements (ex: 1, 3, 4, 5), pas le code généré ("PA_PS_GOUV", ...). L'ancien
        // pluck('id', 'code') ne matchait donc quasiment jamais et retombait sur le défaut.
        $sourcesFinancementMap = SourceFinancement::pluck('id', 'ancien_id')->toArray();
        $origineStagiaireMap = OrigineStagiaire::pluck('id', 'ancien_id')->toArray();
        // situation_stage / statut_stage restent des colonnes texte dénormalisées sur stages
        // (comme en legacy), mais on y stocke désormais le vrai code du référentiel migré par
        // legacy:migrer-referentiels au lieu d'un code fabriqué ("SS-001") qui ne correspondait
        // à rien dans la table situations_stage utilisée par le filtre de "Mes Stagiaires".
        $situationsStageMap = DB::table('situations_stage')->pluck('code', 'ancien_id')->toArray();
        $statutsStageMap = DB::table('statuts_stage')->pluck('code', 'ancien_id')->toArray();

        $query->orderBy('id')->chunk(1000, function ($contrats) use (
            &$bar, $agencesMap, $typesStageMap, $entreprisesMap, $sourcesFinancementMap,
            $origineStagiaireMap, $situationsStageMap, $statutsStageMap
        ): void {
            $aejNums = $contrats->pluck('numero_aej')->filter()->unique()->toArray();
            $beneficiairesMap = Beneficiaire::whereIn('numero_aej', $aejNums)->pluck('id', 'numero_aej')->toArray();

            foreach ($contrats as $legacyContrat) {
                $this->recorder->preserveContrat(
                    $this->executionId,
                    $legacyContrat,
                    null,
                    null,
                    null,
                    $this->sourceContractColumnCount,
                );

                $beneficiaire_id = $beneficiairesMap[$legacyContrat->numero_aej] ?? null;
                $entreprise_id = $entreprisesMap[$legacyContrat->id_entreprise] ?? null;
                $agence_id = $agencesMap[$legacyContrat->id_agence] ?? null;
                $type_stage_id = $typesStageMap[$legacyContrat->id_type_stage] ?? null;
                $source_financement_id = $sourcesFinancementMap[$legacyContrat->source_financement] ?? null;
                $origine_stagiaire_id = isset($legacyContrat->originestagiaire_id) ? ($origineStagiaireMap[$legacyContrat->originestagiaire_id] ?? null) : null;
                $situation_stage = isset($legacyContrat->id_situation_stage) ? ($situationsStageMap[$legacyContrat->id_situation_stage] ?? null) : null;
                $statut_stage = isset($legacyContrat->id_statut_stage) ? ($statutsStageMap[$legacyContrat->id_statut_stage] ?? null) : null;

                if (! $beneficiaire_id || ! $entreprise_id || ! $agence_id || ! $type_stage_id || ! $source_financement_id) {
                    $this->recorder->anomaly(
                        $this->executionId,
                        'CONTRAT_RELATION_INTROUVABLE',
                        'contrats_pae',
                        $legacyContrat->id,
                        'Stage non normalisé : bénéficiaire, entreprise, agence, type de stage ou financement cible introuvable.',
                        [
                            'numero_aej' => $legacyContrat->numero_aej,
                            'id_entreprise' => $legacyContrat->id_entreprise,
                            'id_agence' => $legacyContrat->id_agence,
                            'id_type_stage' => $legacyContrat->id_type_stage,
                            'source_financement' => $legacyContrat->source_financement,
                        ],
                    );
                    $bar->advance();

                    continue;
                }

                // 1. Création du Stage (ancien contrats_pae)
                $date_entree = $this->mapper->normalizeLegacyDate($legacyContrat->date_entree ?? null);
                $date_debut = $this->mapper->normalizeLegacyDate($legacyContrat->date_debut ?? null);
                $date_fin_prevue = $this->mapper->normalizeLegacyDate($legacyContrat->date_fin ?? null);
                if ($date_debut === null || $date_fin_prevue === null || $date_fin_prevue->lt($date_debut)) {
                    $this->recorder->anomaly(
                        $this->executionId,
                        'CONTRAT_DATES_A_RECONCILIER',
                        'contrats_pae',
                        $legacyContrat->id,
                        'Stage non normalisé : dates de début/fin absentes, invalides ou incohérentes.',
                        ['date_debut' => $legacyContrat->date_debut, 'date_fin' => $legacyContrat->date_fin],
                    );
                    $bar->advance();

                    continue;
                }

                $primeMensuelle = max(0, (float) ($legacyContrat->montant_du ?? 0));
                if ($legacyContrat->montant_du === null || $legacyContrat->montant_du === '') {
                    $this->recorder->anomaly(
                        $this->executionId,
                        'CONTRAT_PRIME_ABSENTE',
                        'contrats_pae',
                        $legacyContrat->id,
                        'Prime mensuelle absente ; valeur cible conservée à zéro sans estimation.',
                        ['montant_du' => $legacyContrat->montant_du],
                        'NON_BLOQUANTE',
                    );
                }

                // contrats_pae est soft-deletable côté legacy ; on reporte cet état sur le stage
                // et le contrat pour que "Mes Stagiaires" affiche le même volume qu'en legacy
                // (sinon les dossiers supprimés logiquement en legacy réapparaissent ici).
                $deletedAt = $this->mapper->normalizeLegacyDate($legacyContrat->deleted_at ?? null);

                $stage = Stage::withTrashed()->updateOrCreate(
                    ['ancien_id' => $legacyContrat->id],
                    [
                        'beneficiaire_id' => $beneficiaire_id,
                        'entreprise_id' => $entreprise_id,
                        'agence_id' => $agence_id,
                        'type_stage_id' => $type_stage_id,
                        'source_financement_id' => $source_financement_id,
                        'conseiller_id' => null, // conseiller mapping needs conseillers table populated
                        'origine_stagiaire_id' => $origine_stagiaire_id,
                        'date_entree_portefeuille' => $date_entree,

                        'service_affectation' => $legacyContrat->service_affectation ?? null,
                        'intitule_poste' => $legacyContrat->intitule_poste_stage ?? 'Poste non défini',

                        'localite_stage' => $legacyContrat->lieu_de_stage ?? null,

                        'nom_encadreur' => $legacyContrat->nom_encadreur ?? null,

                        'date_debut' => $date_debut,
                        'date_fin_prevue' => $date_fin_prevue,
                        'observations' => $legacyContrat->observation ?? null,
                        'situation_stage' => $situation_stage,
                        'statut_stage' => $statut_stage,
                        'deleted_at' => $deletedAt,
                    ]
                );

                // 2. Gérer le Contrat Financier lié
                $contrat = Contrat::withTrashed()->updateOrCreate(
                    ['ancien_id' => $legacyContrat->id],
                    [
                        'stage_id' => $stage->id,
                        'numero' => 'CT-'.str_pad($legacyContrat->id, 5, '0', STR_PAD_LEFT),
                        'date_debut' => $date_debut,
                        'date_fin' => $date_fin_prevue,
                        'prime_mensuelle' => $primeMensuelle,
                        'statut' => 'SIGNE', // Les anciens contrats étaient signés
                        'deleted_at' => $deletedAt,
                    ]
                );

                // 3. Gérer le Workflow via contrat_etape / etape_traitement
                $statutLegacy = (int) ($legacyContrat->etapetraitement_id ?? $legacyContrat->id_statut_stage ?? 1);

                $corbeilleEnum = $this->mapper->mapChefAgenceCorbeille($legacyContrat);
                if ($corbeilleEnum === CorbeilleEnum::CIP_MES_STAGIAIRES) {
                    $corbeilleEnum = $this->mapper->mapStatutStageToCorbeille($statutLegacy);
                }

                // Stagiaire déjà validé de bout en bout (payé ou définitivement rejeté après
                // paiement) : on clôt l'instance de workflow au lieu de la laisser trainer
                // dans une corbeille active (cf. LegacyMapperService::estStatutStageTermine).
                $termineeLe = $this->mapper->estStatutStageTermine($statutLegacy)
                    ? ($this->mapper->normalizeLegacyDate($legacyContrat->updated_at ?? null) ?? now())
                    : null;

                $definition = DefinitionParcours::firstOrCreate(
                    ['code' => 'STAGE_LEGACY', 'version' => 1],
                    ['nom' => 'Parcours Legacy', 'active' => true]
                );

                $etapeCode = strtoupper($corbeilleEnum->value);
                $etapeNom = str_replace('_', ' ', $etapeCode);

                $etape = EtapeParcours::firstOrCreate(
                    ['definition_parcours_id' => $definition->id, 'code' => $etapeCode],
                    ['nom' => $etapeNom, 'initiale' => false, 'finale' => false]
                );

                $instance = InstanceParcours::updateOrCreate(
                    ['stage_id' => $stage->id],
                    [
                        'definition_parcours_id' => $definition->id,
                        'etape_courante_id' => $etape->id,
                        'corbeille_actuelle' => $corbeilleEnum->value,
                        'terminee_le' => $termineeLe,
                    ]
                );

                $this->syncOpenTask($instance, $etape, $corbeilleEnum, $agence_id, $termineeLe !== null);
                $this->recorder->preserveContrat(
                    $this->executionId,
                    $legacyContrat,
                    $beneficiaire_id,
                    $stage->id,
                    $contrat->id,
                    $this->sourceContractColumnCount,
                );
                $this->recorder->correspondence($this->executionId, 'contrats_pae', $legacyContrat->id, 'stages', $stage->id, (array) $legacyContrat);
                $this->recorder->correspondence($this->executionId, 'contrats_pae', $legacyContrat->id, 'contrats', $contrat->id, (array) $legacyContrat);

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
    }

    private function migratePointages(): void
    {
        $this->info('Migration des pointages (pointage_models)...');
        $query = DB::connection('legacy')->table('pointage_models');
        $total = $query->count();

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $periodesMap = DB::table('periodes')->pluck('id', 'code')->toArray();

        $query->orderBy('id')->chunk(5000, function ($pointages) use (&$bar, &$periodesMap) {
            $stagiaireIds = $pointages->pluck('stagiaire_id')->filter()->unique()->toArray();
            $stagesMap = Stage::whereIn('ancien_id', $stagiaireIds)->pluck('id', 'ancien_id')->toArray();
            $datesDebutParStage = Stage::whereIn('ancien_id', $stagiaireIds)->pluck('date_debut', 'ancien_id')->toArray();
            $agencesParStage = Stage::whereIn('ancien_id', $stagiaireIds)->pluck('agence_id', 'ancien_id')->toArray();

            foreach ($pointages as $legacyPointage) {
                $stage_id = $stagesMap[$legacyPointage->stagiaire_id] ?? null;

                if (! $stage_id) {
                    $this->recorder->anomaly(
                        $this->executionId,
                        'POINTAGE_SANS_STAGE',
                        'pointage_models',
                        $legacyPointage->id,
                        "Pointage non migré : stage legacy {$legacyPointage->stagiaire_id} introuvable.",
                        (array) $legacyPointage,
                    );
                    $bar->advance();

                    continue;
                }

                // Mapper le statut du pointage (conservé tel quel)
                $statut = 'SOUMIS';
                if ($legacyPointage->status_dmg == 2) {
                    $statut = 'AJOURNE_DMG';
                }
                if ($legacyPointage->status_ca == 2) {
                    $statut = 'AJOURNE_CA';
                }
                if ($legacyPointage->status_dmg == 1 && $legacyPointage->status_ca == 1) {
                    $statut = 'VALIDE';
                }

                // `mois` est la période métier. `created_at` peut être postérieur
                // au mois pointé et ne doit pas piloter ADD/ADP.
                $date = $this->mapper->resolveLegacyPeriodDate($legacyPointage);

                if ($date === null) {
                    $this->recorder->anomaly(
                        $this->executionId,
                        'POINTAGE_SANS_PERIODE',
                        'pointage_models',
                        $legacyPointage->id,
                        'Pointage non migré : période métier indéterminable.',
                        (array) $legacyPointage,
                    );
                    $bar->advance();

                    continue;
                }

                $codePeriode = $date->format('Y-m');
                $naturePointage = $this->mapper->naturePaiementPourPeriode(
                    $datesDebutParStage[$legacyPointage->stagiaire_id] ?? null,
                    $codePeriode
                );
                $corbeilleEnum = $this->mapper->mapPointageToCorbeille(
                    isset($legacyPointage->etape_id) ? (int) $legacyPointage->etape_id : null,
                    $statut,
                    $naturePointage
                )->value;

                if (! isset($periodesMap[$codePeriode])) {
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
                $deletedAt = $this->mapper->normalizeLegacyDate($legacyPointage->deleted_at ?? null);

                try {
                    $versionExistante = VersionPointage::where('ancien_id', $legacyPointage->id)->first();

                    if ($versionExistante) {
                        $pointage = Pointage::withTrashed()->findOrFail($versionExistante->pointage_id);
                        $conflit = Pointage::query()
                            ->where('stage_id', $stage_id)
                            ->where('periode_id', $periodeId)
                            ->where('nature', $naturePointage)
                            ->whereKeyNot($pointage->id)
                            ->whereNull('deleted_at')
                            ->exists();

                        if ($conflit) {
                            $this->warn("Pointage legacy #{$legacyPointage->id} non reclassé : la cible {$codePeriode}/{$naturePointage} existe déjà.");
                            $bar->advance();

                            continue;
                        }

                        $pointage->update([
                            'stage_id' => $stage_id,
                            'periode_id' => $periodeId,
                            'nature' => $naturePointage,
                            'statut' => $statut,
                            'deleted_at' => $deletedAt,
                        ]);
                        $versionExistante->update([
                            'observation' => $legacyPointage->commentaire,
                            'saisi_le' => $date,
                        ]);
                    } else {
                        // Idempotence par (stage_id, periode_id, nature)
                        $pointage = Pointage::withTrashed()
                            ->where('stage_id', $stage_id)
                            ->where('periode_id', $periodeId)
                            ->where('nature', $naturePointage)
                            ->whereNull('deleted_at')
                            ->first();

                        if ($pointage) {
                            $numeroVersion = (VersionPointage::where('pointage_id', $pointage->id)->max('numero_version') ?? 0) + 1;
                            $pointage->update([
                                'statut' => $statut,
                                'version_courante' => $numeroVersion,
                                'deleted_at' => $deletedAt,
                            ]);
                        } else {
                            $pointage = Pointage::create([
                                'ancien_id' => $legacyPointage->id,
                                'stage_id' => $stage_id,
                                'periode_id' => $periodeId,
                                'nature' => $naturePointage,
                                'statut' => $statut,
                                'version_courante' => 1,
                                'deleted_at' => $deletedAt,
                            ]);
                            $numeroVersion = 1;
                        }

                        VersionPointage::create([
                            'ancien_id' => $legacyPointage->id,
                            'pointage_id' => $pointage->id,
                            'numero_version' => $numeroVersion,
                            'presence' => 'PRESENT',
                            'jours_presents' => 30,
                            'jours_absents' => 0,
                            'observation' => $legacyPointage->commentaire,
                            'saisi_le' => $date,
                        ]);
                    }

                    // CREATE PARCOURS FOR POINTAGE
                    $definition = DefinitionParcours::firstOrCreate(
                        ['code' => 'POINTAGE_LEGACY', 'version' => 1],
                        ['nom' => 'Parcours Pointage Legacy', 'active' => true]
                    );

                    $etapeCode = strtoupper($corbeilleEnum);
                    $etapeNom = str_replace('_', ' ', $etapeCode);

                    $etape = EtapeParcours::firstOrCreate(
                        ['definition_parcours_id' => $definition->id, 'code' => $etapeCode],
                        ['nom' => $etapeNom, 'initiale' => false, 'finale' => false]
                    );

                    // L'instance de workflow reflète toujours le dernier état connu du pointage,
                    // donc de sa dernière version (resoumission) traitée.
                    $instance = InstanceParcours::updateOrCreate(
                        ['pointage_id' => $pointage->id],
                        [
                            'definition_parcours_id' => $definition->id,
                            'etape_courante_id' => $etape->id,
                            'corbeille_actuelle' => $corbeilleEnum,
                        ]
                    );
                    $this->syncOpenTask(
                        $instance,
                        $etape,
                        CorbeilleEnum::from($corbeilleEnum),
                        $agencesParStage[$legacyPointage->stagiaire_id] ?? null,
                    );
                    $this->recorder->correspondence(
                        $this->executionId,
                        'pointage_models',
                        $legacyPointage->id,
                        'pointages',
                        $pointage->id,
                        (array) $legacyPointage,
                    );
                } catch (Throwable $e) {
                    $this->warn("Pointage legacy #{$legacyPointage->id} ignoré : {$e->getMessage()}");
                    $this->recorder->anomaly(
                        $this->executionId,
                        'POINTAGE_ECHEC_NORMALISATION',
                        'pointage_models',
                        $legacyPointage->id,
                        $e->getMessage(),
                        (array) $legacyPointage,
                    );
                }

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
    }

    private function migratePaiements(): void
    {
        $this->info('Migration des paiements (paiement_models)...');
        $query = DB::connection('legacy')->table('paiement_models');
        $total = $query->count();

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $periodesMap = DB::table('periodes')->pluck('id', 'code')->toArray();

        $query->orderBy('id')->chunk(5000, function ($paiements) use (&$bar, &$periodesMap): void {
            $stagiaireIds = $paiements->pluck('stagiaire_id')->filter()->unique()->toArray();
            $stagesMap = Stage::whereIn('ancien_id', $stagiaireIds)->pluck('id', 'ancien_id')->toArray();
            $sourceFinancementParStage = Stage::whereIn('ancien_id', $stagiaireIds)->pluck('source_financement_id', 'ancien_id')->toArray();
            $datesDebutParStage = Stage::whereIn('ancien_id', $stagiaireIds)->pluck('date_debut', 'ancien_id')->toArray();

            foreach ($paiements as $legacyPaiement) {
                $stage_id = $stagesMap[$legacyPaiement->stagiaire_id] ?? null;

                if (! $stage_id) {
                    $this->recorder->anomaly(
                        $this->executionId,
                        'PAIEMENT_SANS_STAGE',
                        'paiement_models',
                        $legacyPaiement->id,
                        "Paiement non migré : stage legacy {$legacyPaiement->stagiaire_id} introuvable.",
                        (array) $legacyPaiement,
                    );
                    $bar->advance();

                    continue;
                }

                $source_financement_id = $sourceFinancementParStage[$legacyPaiement->stagiaire_id] ?? null;
                if ($source_financement_id === null) {
                    $this->recorder->anomaly(
                        $this->executionId,
                        'PAIEMENT_SANS_FINANCEMENT',
                        'paiement_models',
                        $legacyPaiement->id,
                        'Paiement non migré : le stage cible ne possède aucun financement.',
                        (array) $legacyPaiement,
                    );
                    $bar->advance();

                    continue;
                }

                $date = $this->mapper->resolveLegacyPeriodDate($legacyPaiement);

                if ($date === null) {
                    $this->recorder->anomaly(
                        $this->executionId,
                        'PAIEMENT_SANS_PERIODE',
                        'paiement_models',
                        $legacyPaiement->id,
                        'Paiement non migré : période métier indéterminable.',
                        (array) $legacyPaiement,
                    );
                    $bar->advance();

                    continue;
                }

                $codePeriode = $date->format('Y-m');
                $nature = $this->mapper->naturePaiementPourPeriode(
                    $datesDebutParStage[$legacyPaiement->stagiaire_id] ?? null,
                    $codePeriode
                );

                if (! isset($periodesMap[$codePeriode])) {
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
                    DB::transaction(function () use ($legacyPaiement, $stage_id, $periodeId, $source_financement_id, $nature, $date): void {
                        $droit = DroitPaiement::where('ancien_id', $legacyPaiement->id)->first();

                        // On ne regroupe que par (stage_id, periode_id, nature).
                        // La ligne actuelle remplace toujours le droit actif précédent.
                        $actif = DroitPaiement::where('stage_id', $stage_id)
                            ->where('periode_id', $periodeId)
                            ->where('nature', $nature)
                            ->when($droit, fn ($q) => $q->whereKeyNot($droit->id))
                            ->whereNull('annule_le')
                            ->first();

                        if ($actif) {
                            $actif->update([
                                'annule_le' => $date,
                                'motif_annulation' => "Remplacé par le paiement legacy #{$legacyPaiement->id}",
                            ]);
                        }

                        $droit ??= new DroitPaiement(['ancien_id' => $legacyPaiement->id]);
                        $droit->fill([
                            'stage_id' => $stage_id,
                            'periode_id' => $periodeId,
                            'source_financement_id' => $source_financement_id,
                            'nature' => $nature,
                            'montant' => $legacyPaiement->montant,
                            'annule_le' => null,
                            'motif_annulation' => null,
                        ]);
                        if (! $droit->exists) {
                            $droit->statut = 'OUVERT';
                        }
                        $droit->save();

                        $paiement = Paiement::firstOrNew(['ancien_id' => $legacyPaiement->id]);
                        $legacyStatus = $this->mapLegacyPaymentStatus($legacyPaiement);
                        $paiement->fill([
                            'droit_paiement_id' => $droit->id,
                            'montant' => $legacyPaiement->montant,
                        ]);
                        if (! $paiement->exists) {
                            $paiement->statut = $legacyStatus;
                        } elseif (
                            $legacyStatus === 'VALIDE_AC'
                            || $legacyStatus === 'REJETE_AC'
                            || ($legacyStatus === 'EN_OP' && in_array($paiement->statut, ['A_TRAITER', 'EN_DOSSIER'], true))
                            || ($legacyStatus === 'EN_DOSSIER' && $paiement->statut === 'A_TRAITER')
                        ) {
                            $paiement->statut = $legacyStatus;
                        }
                        if ($legacyStatus === 'VALIDE_AC') {
                            $payeLe = $this->mapper->normalizeLegacyDate(
                                $legacyPaiement->date_confirm_pay
                                    ?? $legacyPaiement->date_vise_ac
                                    ?? $legacyPaiement->updated_at
                                    ?? null,
                            );
                            $paiement->paye_le = $payeLe?->toImmutable();
                        }
                        $paiement->save();

                        $this->recorder->correspondence(
                            $this->executionId,
                            'paiement_models',
                            $legacyPaiement->id,
                            'droits_paiement',
                            $droit->id,
                            (array) $legacyPaiement,
                        );
                        $this->recorder->correspondence(
                            $this->executionId,
                            'paiement_models',
                            $legacyPaiement->id,
                            'paiements',
                            $paiement->id,
                            (array) $legacyPaiement,
                        );
                    });
                } catch (Throwable $e) {
                    $this->warn("Paiement legacy #{$legacyPaiement->id} ignoré : {$e->getMessage()}");
                    $this->recorder->anomaly(
                        $this->executionId,
                        'PAIEMENT_ECHEC_NORMALISATION',
                        'paiement_models',
                        $legacyPaiement->id,
                        $e->getMessage(),
                        (array) $legacyPaiement,
                    );
                }
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
    }

    private function backfillLegacyDossiersPaiement(): void
    {
        $this->info('Backfill des dossiers de paiement legacy (ouverts ET en cours de chaîne)...');

        // On récupère TOUS les dossiers non supprimés (pas seulement les ouverts) :
        // cela couvre les dossiers qui sont déjà dans la chaîne CB/AC/OP et qui
        // doivent être représentés dans dossiers_paiement pour la cohérence du workflow.
        $query = DB::connection('legacy')->table('dossiers')
            ->whereNull('deleted_at');

        $total = $query->count();
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $periodesMap = DB::table('periodes')->pluck('id', 'code')->toArray();
        $agencesMap = Agence::pluck('id', 'ancien_id')->toArray();
        $sourcesMap = SourceFinancement::pluck('id', 'ancien_id')->toArray();
        $now = now();

        $query->orderBy('id')->chunk(500, function ($legacyDossiers) use (&$bar, &$periodesMap, $agencesMap, $sourcesMap, $now): void {
            $legacyDossierIds = $legacyDossiers->pluck('id')->all();

            $legacyPaiementsByDossier = DB::connection('legacy')->table('paiement_models')
                ->select('id', 'dossier_id', 'montant', 'created_at')
                ->whereIn('dossier_id', $legacyDossierIds)
                ->whereNull('deleted_at')
                ->orderBy('id')
                ->get()
                ->groupBy('dossier_id');

            $legacyPaiementIds = $legacyPaiementsByDossier->flatten(1)->pluck('id')->unique()->values()->all();
            $paiementsParAncienId = Paiement::query()
                ->whereIn('ancien_id', $legacyPaiementIds)
                ->get()
                ->keyBy('ancien_id');

            $activeLinesParPaiementId = LigneDossierPaiement::query()
                ->when(
                    $paiementsParAncienId->isNotEmpty(),
                    fn ($q) => $q->whereIn('paiement_id', $paiementsParAncienId->pluck('id')->all()),
                    fn ($q) => $q->whereRaw('1 = 0')
                )
                ->whereNull('retire_le')
                ->get()
                ->keyBy('paiement_id');

            foreach ($legacyDossiers as $legacyDossier) {
                $agenceId = $agencesMap[$legacyDossier->agence_id] ?? null;
                $sourceFinancementId = $sourcesMap[$legacyDossier->type_financement_id] ?? null;

                if (! $agenceId || ! $sourceFinancementId) {
                    $this->recorder->anomaly(
                        $this->executionId,
                        'DOSSIER_RELATION_INTROUVABLE',
                        'dossiers',
                        $legacyDossier->id,
                        'Dossier non migré : agence ou source de financement cible introuvable.',
                        (array) $legacyDossier,
                    );
                    $bar->advance();

                    continue;
                }

                $periodeCode = (string) $legacyDossier->mois;
                if (! isset($periodesMap[$periodeCode])) {
                    $date = $this->mapper->normalizeLegacyDate($periodeCode.'-01') ?? $now;
                    $periodesMap[$periodeCode] = DB::table('periodes')->insertGetId([
                        'code' => $periodeCode,
                        'date_debut' => $date->copy()->startOfMonth()->toDateString(),
                        'date_fin' => $date->copy()->endOfMonth()->toDateString(),
                        'ouverte_pointage' => false,
                        'ouverte_paiement' => false,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                $dossier = DossierPaiement::query()->firstOrNew(['ancien_id' => $legacyDossier->id]);
                if (! $dossier->exists) {
                    $dossier->uuid_public = (string) Str::uuid();
                }

                $createdAt = $this->mapper->normalizeLegacyDate($legacyDossier->created_at ?? null) ?? $now;
                $updatedAt = $this->mapper->normalizeLegacyDate($legacyDossier->updated_at ?? null) ?? $createdAt;

                // Mapper le statut du dossier legacy vers le nouveau statut
                $statutDossier = match (true) {
                    $legacyDossier->operation_id !== null => 'EN_OP',
                    $legacyDossier->multi_dossier_id !== null => 'TRANSMIS_CB',
                    ! empty($legacyDossier->status_cb) && $legacyDossier->status_cb === 'approved' => 'VALIDE_CB',
                    ! empty($legacyDossier->status_cb) && $legacyDossier->status_cb === 'rejected' => 'REJETE_CB',
                    ! empty($legacyDossier->status_cb) && $legacyDossier->status_cb === 'differ' => 'DIFFERE_CB',
                    ! empty($legacyDossier->group_by_dmg) && $legacyDossier->group_by_dmg == 1 => 'TRANSMIS_CB',
                    default => 'BROUILLON',
                };

                $dossier->fill([
                    'periode_id' => $periodesMap[$periodeCode],
                    'agence_id' => $agenceId,
                    'source_financement_id' => $sourceFinancementId,
                    'numero' => $legacyDossier->identifiant ?: 'DOS-LEGACY-'.$legacyDossier->id,
                    'nature' => Str::startsWith((string) $legacyDossier->identifiant, 'DM') ? 'DM' : 'PS',
                    'statut' => $statutDossier,
                    'montant_total' => 0,
                    'created_at' => $dossier->exists ? $dossier->created_at : $createdAt,
                    'updated_at' => $updatedAt,
                ]);
                $dossier->save();

                $montantTotal = 0;
                foreach ($legacyPaiementsByDossier->get($legacyDossier->id, collect()) as $legacyPaiement) {
                    $paiement = $paiementsParAncienId->get($legacyPaiement->id);
                    if (! $paiement) {
                        continue;
                    }

                    $activeLine = $activeLinesParPaiementId->get($paiement->id);
                    if ($activeLine && (int) $activeLine->dossier_paiement_id !== (int) $dossier->id) {
                        continue;
                    }

                    $ligne = LigneDossierPaiement::query()->firstOrNew([
                        'dossier_paiement_id' => $dossier->id,
                        'paiement_id' => $paiement->id,
                    ]);
                    $ligne->fill([
                        'montant' => $paiement->montant,
                        'ajoute_le' => $this->mapper->normalizeLegacyDate($legacyPaiement->created_at ?? null) ?? $createdAt,
                        'retire_le' => null,
                        'motif_retrait' => null,
                    ]);
                    $ligne->save();

                    $activeLinesParPaiementId->put($paiement->id, $ligne);

                    if ($paiement->statut === 'A_TRAITER') {
                        $paiement->update(['statut' => 'EN_DOSSIER']);
                    }

                    $montantTotal += (float) $paiement->montant;
                }

                $dossier->update(['montant_total' => $montantTotal]);
                $this->recorder->correspondence(
                    $this->executionId,
                    'dossiers',
                    $legacyDossier->id,
                    'dossiers_paiement',
                    $dossier->id,
                    (array) $legacyDossier,
                );
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
    }

    private function migrateDossiersGroupes(): void
    {
        $this->info('Migration des dossiers groupés (multi_dossiers)...');

        $query = DB::connection('legacy')->table('multi_dossiers');
        $sourcesMap = SourceFinancement::pluck('id', 'ancien_id')->toArray();
        $periodesMap = DB::table('periodes')->pluck('id', 'code')->toArray();
        $bar = $this->output->createProgressBar($query->count());
        $bar->start();

        $query->orderBy('id')->chunk(500, function ($groupes) use (&$periodesMap, $sourcesMap, &$bar): void {
            $legacyIds = $groupes->pluck('id')->all();
            $legacyDossiers = DB::connection('legacy')->table('dossiers')
                ->whereIn('multi_dossier_id', $legacyIds)
                ->get()
                ->groupBy('multi_dossier_id');

            foreach ($groupes as $legacyGroupe) {
                $date = $this->mapper->resolveLegacyPeriodDate($legacyGroupe);
                if ($date === null) {
                    $this->recorder->anomaly($this->executionId, 'GROUPE_SANS_PERIODE', 'multi_dossiers', $legacyGroupe->id, 'Dossier groupé sans période exploitable.', (array) $legacyGroupe);
                    $bar->advance();

                    continue;
                }

                $periodeId = $this->ensurePeriod($date, $periodesMap);
                $dossiersSource = $legacyDossiers->get($legacyGroupe->id, collect());
                $financements = $dossiersSource->pluck('type_financement_id')->filter()->unique()->values();
                $sourceFinancementId = $financements->count() === 1
                    ? ($sourcesMap[$financements->first()] ?? null)
                    : null;
                $numero = (string) ($legacyGroupe->name ?: 'MULTI-LEGACY-'.$legacyGroupe->id);
                $nature = match (true) {
                    Str::startsWith($numero, 'DM') => 'DM',
                    Str::startsWith($numero, 'PS') => 'PS',
                    default => null,
                };

                if ($financements->count() > 1) {
                    $this->recorder->anomaly(
                        $this->executionId,
                        'GROUPE_MULTI_FINANCEMENTS',
                        'multi_dossiers',
                        $legacyGroupe->id,
                        'Plusieurs sources de financement sont présentes dans le même groupe.',
                        ['sources_legacy' => $financements->all()],
                        'NON_BLOQUANTE',
                    );
                }
                if ($sourceFinancementId === null) {
                    $this->recorder->anomaly(
                        $this->executionId,
                        'GROUPE_FINANCEMENT_INTROUVABLE',
                        'multi_dossiers',
                        $legacyGroupe->id,
                        'Financement cible du dossier groupé indéterminable.',
                        ['sources_legacy' => $financements->all()],
                    );
                }
                if ($nature === null) {
                    $this->recorder->anomaly(
                        $this->executionId,
                        'GROUPE_NATURE_INCONNUE',
                        'multi_dossiers',
                        $legacyGroupe->id,
                        "Nature DM/PS indéterminable depuis le numéro {$numero}.",
                        (array) $legacyGroupe,
                    );
                }

                $groupe = DossierGroupe::firstOrNew(['ancien_id' => $legacyGroupe->id]);
                if (! $groupe->exists) {
                    $groupe->uuid_public = (string) Str::uuid();
                }
                $groupe->fill([
                    'periode_id' => $periodeId,
                    'source_financement_id' => $sourceFinancementId,
                    'numero' => Str::limit($numero, 240, ''),
                    'nature' => $nature,
                    'statut' => $this->mapLegacyGroupStatus($legacyGroupe),
                    'observation' => $legacyGroupe->observation,
                    'attestation_path' => $legacyGroupe->attestation_path,
                    'etat_financier_path' => $legacyGroupe->etat_financier_path,
                ]);
                $groupe->save();

                $dossierIds = DossierPaiement::whereIn('ancien_id', $dossiersSource->pluck('id')->all())->pluck('id');
                foreach ($dossierIds as $dossierId) {
                    DB::table('lignes_dossiers_groupes')->updateOrInsert(
                        ['dossier_groupe_id' => $groupe->id, 'dossier_paiement_id' => $dossierId],
                        ['ajoute_le' => $date, 'retire_le' => null, 'motif_retrait' => null, 'created_at' => now(), 'updated_at' => now()],
                    );
                }

                $montantTotal = DossierPaiement::whereIn('id', $dossierIds)->sum('montant_total');
                $groupe->update(['montant_total' => $montantTotal]);
                $this->recorder->correspondence($this->executionId, 'multi_dossiers', $legacyGroupe->id, 'dossiers_groupes', $groupe->id, (array) $legacyGroupe);
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
    }

    private function migrateOperations(): void
    {
        $this->info('Migration des ordres de paiement (operations)...');

        $query = DB::connection('legacy')->table('operations');
        $sourcesMap = SourceFinancement::pluck('id', 'ancien_id')->toArray();
        $periodesMap = DB::table('periodes')->pluck('id', 'code')->toArray();
        $bar = $this->output->createProgressBar($query->count());
        $bar->start();

        $query->orderBy('id')->chunk(500, function ($operations) use (&$periodesMap, $sourcesMap, &$bar): void {
            foreach ($operations as $legacyOperation) {
                $date = $this->mapper->resolveLegacyPeriodDate($legacyOperation);
                if ($date === null) {
                    $this->recorder->anomaly($this->executionId, 'OP_SANS_PERIODE', 'operations', $legacyOperation->id, 'Ordre de paiement sans période exploitable.', (array) $legacyOperation);
                    $bar->advance();

                    continue;
                }

                $legacyDossierIds = DB::connection('legacy')->table('dossiers')
                    ->where('operation_id', $legacyOperation->id)
                    ->pluck('id');
                $dossiersCibles = DossierPaiement::whereIn('ancien_id', $legacyDossierIds)->get();
                $financementsDossiers = $dossiersCibles->pluck('source_financement_id')->filter()->unique()->values();
                $sourceFinancementId = $sourcesMap[$legacyOperation->type_financement_id] ?? null;
                if ($sourceFinancementId === null && $financementsDossiers->count() === 1) {
                    $sourceFinancementId = (int) $financementsDossiers->first();
                }
                if ($sourceFinancementId === null || $financementsDossiers->count() > 1) {
                    $this->recorder->anomaly(
                        $this->executionId,
                        'OP_FINANCEMENT_A_RECONCILIER',
                        'operations',
                        $legacyOperation->id,
                        "Financement de l'opération absent ou contradictoire avec ses dossiers.",
                        [
                            'type_financement_id_legacy' => $legacyOperation->type_financement_id,
                            'financements_dossiers_cibles' => $financementsDossiers->all(),
                        ],
                    );
                }

                $ordre = OrdrePaiement::firstOrNew(['ancien_id' => $legacyOperation->id]);
                if (! $ordre->exists) {
                    $ordre->uuid_public = (string) Str::uuid();
                }
                $ordre->forceFill([
                    'ancien_id' => $legacyOperation->id,
                    'numero' => Str::limit((string) ($legacyOperation->numero_operation ?: 'OP-LEGACY-'.$legacyOperation->id), 50, ''),
                    'periode_id' => $this->ensurePeriod($date, $periodesMap),
                    'source_financement_id' => $sourceFinancementId,
                    'montant_total' => (float) ($legacyOperation->montant_op ?? $legacyOperation->montant ?? 0),
                    'statut' => $this->mapLegacyOperationStatus($legacyOperation),
                ])->save();

                $dossierStatus = match ($ordre->statut) {
                    'VISE_AC' => 'VISE_AC',
                    'REJETE_AC', 'REJETE_CB', 'DIFFERE_AC' => 'AJOURNE_DMG',
                    'ANNULE', 'A_RECONCILIER' => 'A_RECONCILIER',
                    default => 'EN_OP',
                };
                DossierPaiement::whereIn('ancien_id', $legacyDossierIds)->update([
                    'ordre_paiement_id' => $ordre->id,
                    'statut' => $dossierStatus,
                ]);

                $montantDossiers = DossierPaiement::where('ordre_paiement_id', $ordre->id)->sum('montant_total');
                $montantLegacy = (float) ($legacyOperation->montant_op ?? $legacyOperation->montant ?? 0);
                if ($montantLegacy > 0 && abs((float) $montantDossiers - $montantLegacy) > 0.01) {
                    $this->recorder->anomaly(
                        $this->executionId,
                        'OP_ECART_MONTANT',
                        'operations',
                        $legacyOperation->id,
                        "Écart entre le montant legacy de l'opération et la somme des dossiers cibles.",
                        ['montant_legacy' => $montantLegacy, 'montant_dossiers_cibles' => (float) $montantDossiers],
                    );
                }
                if ((float) $montantDossiers > 0) {
                    $ordre->update(['montant_total' => $montantDossiers]);
                }

                $this->recorder->correspondence($this->executionId, 'operations', $legacyOperation->id, 'ordre_paiements', $ordre->id, (array) $legacyOperation);
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
    }

    private function migrateBordereaux(): void
    {
        $this->info('Migration des bordereaux (borderaus)...');

        $query = DB::connection('legacy')->table('borderaus');
        $sourcesMap = SourceFinancement::pluck('id', 'ancien_id')->toArray();
        $periodesMap = DB::table('periodes')->pluck('id', 'code')->toArray();
        $bar = $this->output->createProgressBar($query->count());
        $bar->start();

        $query->orderBy('id')->chunk(500, function ($bordereaux) use (&$periodesMap, $sourcesMap, &$bar): void {
            foreach ($bordereaux as $legacyBordereau) {
                $date = $this->mapper->resolveLegacyPeriodDate($legacyBordereau);
                if ($date === null) {
                    $this->recorder->anomaly($this->executionId, 'BORDEREAU_SANS_PERIODE', 'borderaus', $legacyBordereau->id, 'Bordereau sans période exploitable.', (array) $legacyBordereau);
                    $bar->advance();

                    continue;
                }

                $legacyOperationIds = DB::connection('legacy')->table('operations')
                    ->where('borderau_id', $legacyBordereau->id)
                    ->pluck('id');
                $ordresCibles = OrdrePaiement::whereIn('ancien_id', $legacyOperationIds)->get();
                $financementsOrdres = $ordresCibles->pluck('source_financement_id')->filter()->unique()->values();
                $sourceFinancementId = $sourcesMap[$legacyBordereau->type_financement_id] ?? null;
                if ($sourceFinancementId === null && $financementsOrdres->count() === 1) {
                    $sourceFinancementId = (int) $financementsOrdres->first();
                }
                if ($sourceFinancementId === null || $financementsOrdres->count() > 1) {
                    $this->recorder->anomaly(
                        $this->executionId,
                        'BORDEREAU_FINANCEMENT_A_RECONCILIER',
                        'borderaus',
                        $legacyBordereau->id,
                        'Financement du bordereau absent ou contradictoire avec ses opérations.',
                        [
                            'type_financement_id_legacy' => $legacyBordereau->type_financement_id,
                            'financements_ordres_cibles' => $financementsOrdres->all(),
                        ],
                    );
                }

                $bordereau = BordereauPaiement::firstOrNew(['ancien_id' => $legacyBordereau->id]);
                if (! $bordereau->exists) {
                    $bordereau->uuid_public = (string) Str::uuid();
                }
                $bordereau->forceFill([
                    'ancien_id' => $legacyBordereau->id,
                    'numero' => Str::limit((string) ($legacyBordereau->numero_borderau ?: $legacyBordereau->numero_bordereau ?: 'BRD-LEGACY-'.$legacyBordereau->id), 50, ''),
                    'periode_id' => $this->ensurePeriod($date, $periodesMap),
                    'source_financement_id' => $sourceFinancementId,
                    'montant_total' => (float) ($legacyBordereau->montant_total ?? 0),
                    'statut' => $this->mapLegacyBordereauStatus($legacyBordereau),
                ])->save();

                OrdrePaiement::whereIn('ancien_id', $legacyOperationIds)->update(['bordereau_paiement_id' => $bordereau->id]);

                $montantOrdres = OrdrePaiement::where('bordereau_paiement_id', $bordereau->id)->sum('montant_total');
                $montantLegacy = (float) ($legacyBordereau->montant_total ?? 0);
                if ($montantLegacy > 0 && abs((float) $montantOrdres - $montantLegacy) > 0.01) {
                    $this->recorder->anomaly(
                        $this->executionId,
                        'BORDEREAU_ECART_MONTANT',
                        'borderaus',
                        $legacyBordereau->id,
                        'Écart entre le montant legacy du bordereau et la somme des opérations cibles.',
                        ['montant_legacy' => $montantLegacy, 'montant_ordres_cibles' => (float) $montantOrdres],
                    );
                }
                if ((float) $montantOrdres > 0) {
                    $bordereau->update(['montant_total' => $montantOrdres]);
                }

                $this->recorder->correspondence($this->executionId, 'borderaus', $legacyBordereau->id, 'bordereau_paiements', $bordereau->id, (array) $legacyBordereau);
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
    }

    /** @param array<string, int> $periodesMap */
    private function ensurePeriod(Carbon $date, array &$periodesMap): int
    {
        $code = $date->format('Y-m');

        if (! isset($periodesMap[$code])) {
            $periodesMap[$code] = DB::table('periodes')->insertGetId([
                'code' => $code,
                'date_debut' => $date->copy()->startOfMonth()->toDateString(),
                'date_fin' => $date->copy()->endOfMonth()->toDateString(),
                'ouverte_pointage' => false,
                'ouverte_paiement' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return (int) $periodesMap[$code];
    }

    private function mapLegacyGroupStatus(object $legacyGroupe): string
    {
        if ($this->mapper->normalizeLegacyDate($legacyGroupe->deleted_at ?? null) !== null) {
            return 'ANNULE';
        }

        $status = mb_strtolower(trim((string) ($legacyGroupe->status_cb ?? '')));

        $mapped = match ($status) {
            'approved', 'validated', '1' => 'VALIDE_CB',
            'rejected', '2' => 'REJETE_CB',
            'differ', 'differed', 'differe', '3' => 'DIFFERE_CB',
            'pending', '0' => 'TRANSMIS_CB',
            '' => ! empty($legacyGroupe->group_by_dmg) ? 'TRANSMIS_CB' : 'BROUILLON',
            default => null,
        };

        if ($mapped !== null) {
            return $mapped;
        }

        $this->recorder->anomaly(
            $this->executionId,
            'GROUPE_STATUT_INCONNU',
            'multi_dossiers',
            (int) data_get($legacyGroupe, 'id'),
            "Statut CB du dossier groupé non reconnu : {$status}.",
            (array) $legacyGroupe,
        );

        return 'A_RECONCILIER';
    }

    private function mapLegacyPaymentStatus(object $legacyPaiement): string
    {
        $statusAc = mb_strtolower(trim((string) ($legacyPaiement->status_ac ?? '')));

        return match (true) {
            $statusAc === 'validated' => 'VALIDE_AC',
            in_array($statusAc, ['rejected', 'rejected-by-ac'], true) => 'REJETE_AC',
            $statusAc === 'processed' => 'EN_OP',
            ! empty($legacyPaiement->dossier_id)
                && (int) ($legacyPaiement->status_dmg ?? 0) === 1 => 'EN_DOSSIER',
            default => 'A_TRAITER',
        };
    }

    private function mapLegacyOperationStatus(object $legacyOperation): string
    {
        $status = mb_strtolower(trim((string) ($legacyOperation->status_operation ?? '')));

        if (
            $this->mapper->normalizeLegacyDate($legacyOperation->deleted_at ?? null) !== null
            || $status === 'destroyed'
        ) {
            return 'ANNULE';
        }

        $mapped = match ($status) {
            'validated' => 'VISE_AC',
            'processed' => 'EN_BORDEREAU',
            'rejected', 'rejected-by-ac' => 'REJETE_AC',
            'rejected-by-cb' => 'REJETE_CB',
            'ajourne', 'differ', 'differed' => 'DIFFERE_AC',
            'pending', '' => 'BROUILLON',
            default => null,
        };

        if ($status === 'processed' && empty($legacyOperation->borderau_id)) {
            $this->recorder->anomaly(
                $this->executionId,
                'OP_TRAITEE_SANS_BORDEREAU',
                'operations',
                (int) data_get($legacyOperation, 'id'),
                'Opération marquée processed sans bordereau associé.',
                (array) $legacyOperation,
            );
        }

        if ($mapped !== null) {
            return $mapped;
        }

        $this->recorder->anomaly(
            $this->executionId,
            'OP_STATUT_INCONNU',
            'operations',
            (int) data_get($legacyOperation, 'id'),
            "Statut de l'opération non reconnu : {$status}.",
            (array) $legacyOperation,
        );

        return 'A_RECONCILIER';
    }

    private function mapLegacyBordereauStatus(object $legacyBordereau): string
    {
        $status = mb_strtolower(trim((string) ($legacyBordereau->status_borderau ?? '')));

        if (
            $this->mapper->normalizeLegacyDate($legacyBordereau->deleted_at ?? null) !== null
            || $status === 'destroyed'
        ) {
            return 'ANNULE';
        }

        $mapped = match ($status) {
            'pending', 'processed' => 'TRANSMIS_AC',
            'validated' => 'VISE_AC',
            'rejected', 'rejected-by-ac' => 'REJETE_AC',
            'rejected-by-cb' => 'REJETE_CB',
            '' => 'BROUILLON',
            default => null,
        };

        if ($mapped !== null) {
            return $mapped;
        }

        $this->recorder->anomaly(
            $this->executionId,
            'BORDEREAU_STATUT_INCONNU',
            'borderaus',
            (int) data_get($legacyBordereau, 'id'),
            "Statut du bordereau non reconnu : {$status}.",
            (array) $legacyBordereau,
        );

        return 'A_RECONCILIER';
    }

    private function syncOpenTask(
        InstanceParcours $instance,
        EtapeParcours $etape,
        CorbeilleEnum $corbeille,
        ?int $agenceId,
        bool $terminee = false,
    ): void {
        $activeTasks = TacheParcours::query()
            ->where('instance_parcours_id', $instance->id)
            ->whereIn('statut', ['OUVERTE', 'REVENDIQUEE'])
            ->orderBy('id')
            ->get();

        if ($terminee) {
            foreach ($activeTasks as $task) {
                $task->update(['statut' => 'TERMINEE', 'fermee_le' => now()]);
            }

            return;
        }

        $code = $corbeille->value;
        $roleName = match (true) {
            Str::startsWith($code, 'cip_'), $code === CorbeilleEnum::EN_STAGE->value => 'cip',
            Str::startsWith($code, 'ca_') => 'chef_agence',
            Str::startsWith($code, 'dmg_') => 'dmg',
            Str::startsWith($code, 'desse_') => 'desse',
            Str::startsWith($code, 'cb_') => 'cb',
            Str::startsWith($code, 'ac_') => 'agent_comptable',
            Str::startsWith($code, 'daicg_') => 'daicg',
            default => null,
        };
        $role = $roleName === null
            ? null
            : Role::query()->where('name', $roleName)->where('guard_name', 'web')->first();

        if ($role === null) {
            foreach ($activeTasks as $task) {
                $task->update(['statut' => 'ANNULEE', 'fermee_le' => now()]);
            }

            $sourceId = $instance->stage_id
                ? (Stage::query()->whereKey($instance->stage_id)->value('ancien_id') ?? $instance->id)
                : $instance->id;
            $this->recorder->anomaly(
                $this->executionId,
                'TACHE_ROLE_INTROUVABLE',
                $instance->stage_id ? 'contrats_pae' : 'instances_parcours',
                $sourceId,
                "Aucun rôle cible disponible pour la corbeille {$code} (rôle attendu : ".($roleName ?? 'indéterminé').').',
                ['instance_parcours_id' => $instance->id, 'corbeille' => $code],
            );

            return;
        }

        $currentTask = $activeTasks->first(fn (TacheParcours $task): bool => $task->code_corbeille === $code
            && (int) $task->etape_parcours_id === (int) $etape->id
            && (int) $task->role_responsable_id === (int) $role->id
            && ($task->agence_id === null ? $agenceId === null : (int) $task->agence_id === $agenceId)
        );

        foreach ($activeTasks as $task) {
            if ($currentTask !== null && $task->is($currentTask)) {
                continue;
            }

            $task->update(['statut' => 'ANNULEE', 'fermee_le' => now()]);
        }

        if ($currentTask !== null) {
            return;
        }

        TacheParcours::create([
            'instance_parcours_id' => $instance->id,
            'etape_parcours_id' => $etape->id,
            'role_responsable_id' => $role->id,
            'agence_id' => $agenceId,
            'code_corbeille' => $code,
            'statut' => 'OUVERTE',
            'priorite' => 0,
            'ouverte_le' => now(),
        ]);
    }

    /** @return array<string, mixed> */
    private function collectMigrationCounters(): array
    {
        $correspondances = DB::table('correspondances_ancien_systeme')
            ->where('execution_migration_id', $this->executionId);
        $parTableCible = (clone $correspondances)
            ->select('table_cible', DB::raw('COUNT(*) AS total'))
            ->groupBy('table_cible')
            ->pluck('total', 'table_cible')
            ->map(fn ($total): int => (int) $total)
            ->all();
        $anomalies = DB::table('anomalies_migration')
            ->where('execution_migration_id', $this->executionId)
            ->where('statut', 'A_RECONCILIER');
        $anomaliesParCode = (clone $anomalies)
            ->select('code', DB::raw('COUNT(*) AS total'))
            ->groupBy('code')
            ->pluck('total', 'code')
            ->map(fn ($total): int => (int) $total)
            ->all();

        return [
            'correspondances' => (clone $correspondances)->count(),
            'correspondances_par_table_cible' => $parTableCible,
            'contrats_pae_preserves' => DB::table('conservations_contrats_pae')
                ->where('execution_migration_id', $this->executionId)
                ->count(),
            'anomalies_a_reconcilier' => (clone $anomalies)->count(),
            'anomalies_par_code' => $anomaliesParCode,
        ];
    }

    private function migrateEvenements(): void
    {
        $this->info("Migration de l'historique (contrat_etape)...");

        $query = DB::connection('legacy')->table('contrat_etape');
        $total = $query->count();
        $fallbackAuthorId = User::query()->orderBy('id')->value('id');

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $query->orderBy('id')->chunk(5000, function ($historique) use (&$bar, $fallbackAuthorId): void {
            // MAP STAGES
            $contratIds = $historique->pluck('contrat_id')->filter()->unique()->toArray();
            $stagesMap = Stage::whereIn('ancien_id', $contratIds)->pluck('id', 'ancien_id')->toArray();
            $instancesStageMap = InstanceParcours::whereIn('stage_id', array_values($stagesMap))->get()->keyBy('stage_id');

            // MAP POINTAGES
            // Un pointage_models legacy n'a pas forcément créé son propre Pointage : s'il
            // s'agissait d'une resoumission pour un stage/période déjà migré, il a été rattaché
            // comme nouvelle version d'un pointage existant (cf. migratePointages()). On
            // résout donc d'abord via Pointage.ancien_id, puis via VersionPointage.ancien_id.
            $legacyPointageIds = $historique->pluck('pointage_id')->filter()->unique()->toArray();
            $pointagesMap = Pointage::whereIn('ancien_id', $legacyPointageIds)->pluck('id', 'ancien_id')->toArray();
            $manquants = array_values(array_diff($legacyPointageIds, array_keys($pointagesMap)));
            if (! empty($manquants)) {
                $pointagesMap += VersionPointage::whereIn('ancien_id', $manquants)->pluck('pointage_id', 'ancien_id')->toArray();
            }
            $instancesPointageMap = InstanceParcours::whereIn('pointage_id', array_values($pointagesMap))->get()->keyBy('pointage_id');

            $legacyUserIds = $historique->pluck('user_id')->filter()->unique()->map(fn ($id): string => (string) $id)->all();
            $auteursMap = DB::table('correspondances_ancien_systeme')
                ->where('table_source', 'users')
                ->where('table_cible', 'users')
                ->whereIn('id_source', $legacyUserIds)
                ->pluck('id_cible', 'id_source')
                ->map(fn ($id): int => (int) $id)
                ->all();

            foreach ($historique as $legacyEvent) {
                $instance = null;

                if ($legacyEvent->pointage_id) {
                    $pointage_id = $pointagesMap[$legacyEvent->pointage_id] ?? null;
                    if ($pointage_id) {
                        $instance = $instancesPointageMap[$pointage_id] ?? null;
                    }
                }

                if ($instance === null) {
                    $stage_id = $stagesMap[$legacyEvent->contrat_id] ?? null;
                    if ($stage_id) {
                        $instance = $instancesStageMap[$stage_id] ?? null;
                    }
                }

                if ($instance === null) {
                    $this->recorder->anomaly(
                        $this->executionId,
                        'EVENEMENT_SANS_INSTANCE',
                        'contrat_etape',
                        $legacyEvent->id,
                        'Historique sans instance de parcours cible.',
                        (array) $legacyEvent,
                    );
                    $bar->advance();

                    continue;
                }

                $auteurId = $legacyEvent->user_id !== null
                    ? ($auteursMap[(string) $legacyEvent->user_id] ?? null)
                    : null;
                if ($auteurId === null) {
                    $auteurId = $fallbackAuthorId;
                    $this->recorder->anomaly(
                        $this->executionId,
                        'EVENEMENT_AUTEUR_A_RECONCILIER',
                        'contrat_etape',
                        $legacyEvent->id,
                        'Auteur legacy absent ou sans correspondance ; auteur cible de repli utilisé.',
                        ['user_id_legacy' => $legacyEvent->user_id, 'auteur_id_cible' => $auteurId],
                        'NON_BLOQUANTE',
                    );
                }

                if ($auteurId === null) {
                    $this->recorder->anomaly(
                        $this->executionId,
                        'EVENEMENT_SANS_AUTEUR_CIBLE',
                        'contrat_etape',
                        $legacyEvent->id,
                        'Historique non migré : aucun utilisateur cible ne peut porter auteur_id.',
                        (array) $legacyEvent,
                    );
                    $bar->advance();

                    continue;
                }

                $corbeilleCible = $this->mapper->mapStatutStageToCorbeille((int) ($legacyEvent->etape_id ?? 1))->value;
                $etapeCode = strtoupper($corbeilleCible);
                $etapeCible = EtapeParcours::firstOrCreate(
                    ['definition_parcours_id' => $instance->definition_parcours_id, 'code' => $etapeCode],
                    ['nom' => str_replace('_', ' ', $etapeCode), 'initiale' => false, 'finale' => false]
                );

                $event = EvenementParcours::firstOrCreate(
                    ['cle_idempotence' => 'mig_'.$legacyEvent->id.'_'.$instance->id],
                    [
                        'instance_parcours_id' => $instance->id,
                        'etape_cible_id' => $etapeCible->id,
                        'type' => 'MIGRATION_STATUT',
                        'donnees' => [
                            'commentaire' => $legacyEvent->commentaire,
                            'description' => "Passage à l'étape legacy ID : ".$legacyEvent->etape_id,
                            'corbeille_cible' => $corbeilleCible,
                            'contrat_etape_ancien_id' => $legacyEvent->id,
                            'paiement_ancien_id' => $legacyEvent->paiement_id,
                            'pointage_ancien_id' => $legacyEvent->pointage_id,
                        ],
                        'auteur_id' => $auteurId,
                        'survenu_le' => $this->mapper->normalizeLegacyDate($legacyEvent->created_at ?? null) ?? now(),
                    ]
                );
                $this->recorder->correspondence(
                    $this->executionId,
                    'contrat_etape',
                    $legacyEvent->id,
                    'evenements_parcours',
                    $event->id,
                    (array) $legacyEvent,
                );
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
    }

    /**
     * Reconstitue l'historique de l'onglet "Doublons Traités" à partir des dossiers
     * déjà décidés côté legacy (statuts 5/6 -> corbeille DESSE_DOUBLONS_TRAITES, cf.
     * LegacyMapperService::mapStatutStageToCorbeille). Sans cette étape, ces dossiers
     * migrent bien vers cette corbeille mais n'ont aucune ligne dans
     * desse_doublon_decisions (table qui n'existe que depuis ce projet) : l'onglet
     * reste vide alors que legacy affiche un historique réel. Le champ type_doublon
     * n'existe pas côté legacy : on le déduit en comparant les clés de regroupement
     * (mêmes expressions que DesseDoublonService) du dossier aux clés actuellement en
     * doublon en base ; si aucun des 6 critères ne matche plus, on garde quand même
     * une trace sous "compte_paiement" (le champ dédié le plus proche côté legacy)
     * plutôt que de perdre la décision historique.
     */
    private function migrateDesseDoublonDecisions(): void
    {
        $this->info('Reconstitution de l\'historique des décisions de doublons DESSE...');

        $types = array_filter(DoublonTypeEnum::cases(), fn (DoublonTypeEnum $t) => $t !== DoublonTypeEnum::AEJ);
        $duplicateKeysByType = [];
        foreach ($types as $type) {
            $duplicateKeysByType[$type->value] = $this->doublonService->computeDuplicateKeys($type);
        }

        $instances = InstanceParcours::query()
            ->where('corbeille_actuelle', CorbeilleEnum::DESSE_DOUBLONS_TRAITES->value)
            ->with('stage.beneficiaire')
            ->get();

        $ancienIds = $instances->pluck('stage.ancien_id')->filter()->all();
        $legacyRows = DB::connection('legacy')->table('contrats_pae')->whereIn('id', $ancienIds)->get()->keyBy('id');
        $legacyUsers = DB::connection('legacy')->table('users')->get()->keyBy('id');
        $userIdCache = [];

        $bar = $this->output->createProgressBar($instances->count());
        $bar->start();

        foreach ($instances as $instance) {
            $stage = $instance->stage_id ? Stage::query()->find($instance->stage_id) : null;
            $legacyRow = $stage ? $legacyRows->get($stage->ancien_id) : null;

            if (! $stage || ! $legacyRow) {
                $bar->advance();

                continue;
            }

            $decision = ((int) $legacyRow->etat_desse) === 1 ? 'avere' : 'non_avere';
            $motif = trim((string) $legacyRow->motif_desse) !== ''
                ? $legacyRow->motif_desse
                : "Décision historique migrée depuis l'ancienne application (motif non renseigné).";
            $decideLe = $this->mapper->normalizeLegacyDate($legacyRow->date_desse) ?? $instance->updated_at ?? now();

            $decideParId = null;
            if (! empty($legacyRow->id_user_desse)) {
                if (! array_key_exists($legacyRow->id_user_desse, $userIdCache)) {
                    $legacyUser = $legacyUsers->get($legacyRow->id_user_desse);
                    $userIdCache[$legacyRow->id_user_desse] = $legacyUser
                        ? User::where('email', $this->mapper->sanitizeEmail($legacyUser->email, $legacyUser->nom ?? 'User', $legacyUser->pseudo ?? '', $legacyUser->id))->value('id')
                        : null;
                }
                $decideParId = $userIdCache[$legacyRow->id_user_desse];
            }

            $matches = $this->doublonService->matchingTypesForStage($stage, $duplicateKeysByType);
            if ($matches === []) {
                $matches = [DoublonTypeEnum::COMPTE_PAIEMENT->value => '-'];
            }

            foreach ($matches as $typeValue => $cle) {
                DesseDoublonDecision::updateOrCreate(
                    ['instance_parcours_id' => $instance->id, 'type_doublon' => $typeValue],
                    [
                        'cle_doublon' => $cle,
                        'decision' => $decision,
                        'motif' => $motif,
                        'decide_par_id' => $decideParId,
                        'decide_le' => $decideLe,
                    ]
                );
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
    }
}
