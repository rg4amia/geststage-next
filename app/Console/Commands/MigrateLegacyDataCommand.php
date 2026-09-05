<?php

namespace App\Console\Commands;

use App\Domain\Audit\Support\AuditContext;
use App\Domain\Payment\Services\AgentComptableService;
use App\Domain\Validation\Services\ValidationChefAgenceService;
use App\Domain\Workflow\Services\DesseDoublonService;
use App\Enums\CorbeilleEnum;
use App\Enums\DoublonTypeEnum;
use App\Enums\VisaDesseEnum;
use App\Models\Attendance\Pointage;
use App\Models\Attendance\VersionPointage;
use App\Models\Beneficiary\Beneficiaire;
use App\Models\Company\Entreprise;
use App\Models\Company\OffreEmploi;
use App\Models\Contract\AvenantContrat;
use App\Models\Contract\Contrat;
use App\Models\Internship\Stage;
use App\Models\Payment\BordereauPaiement;
use App\Models\Payment\DecisionPaiement;
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
use App\Models\Reference\SituationStage;
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
use App\Models\Workflow\InstanceParcours;
use App\Models\Workflow\TacheParcours;
use App\Services\Migration\LegacyMapperService;
use App\Services\Migration\LegacyMigrationRecorder;
use Carbon\Carbon;
use Closure;
use Database\Seeders\ContratsPaeColumnMappingSeeder;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
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
        {--step=all : Phase à exécuter (references, users, entreprises, offres, beneficiaires, stages, pointages, paiements, dossiers_paiement, dossiers_groupes, operations, bordereaux, evenements, desse_doublons, backfill_adp_nature, backfill_corbeilles_ca, backfill_retour_chefagence, backfill_paiements_dmg, backfill_avenants_renouvellement, fix_statut_paiements_legacy, fix_pointage_revisions, backfill_corbeilles_dmg, fix_etat_chef_agence_100, fix_legacy_ca_validation, update_missing_data, remaining, all)}
        {--dry-run : Exécute toutes les transformations puis annule les écritures PostgreSQL}
        {--chunk=1000 : Nombre maximal de lignes chargées et validées par transaction (1 à 5000)}
        {--with-model-audits : Conserve les journaux Eloquent ligne par ligne (beaucoup plus lent)}
        {--resume : Reprend chaque phase au dernier chunk validé (implicite avec --step=remaining)}
        {--cohorte= : Filtrer par mois de démarrage (ex: 2026-08) — applicable aux étapes backfill}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migre les données de l\'ancienne base (legacy) vers la nouvelle base PostgreSQL, incluant les backfills et corrections.';

    private const SOURCE_VERSION = 'gestage-mysql-v2';

    /** Financement PEJEDEC : payé par un circuit distinct de la file DMG classique. */
    private const FINANCEMENT_PEJEDEC = 5;

    /** Origines de stagiaires n'ouvrant aucun droit à paiement (contrats_pae.originestagiaire_id). */
    private const ORIGINES_SANS_DROIT_PAIEMENT = [3, 4, 19];

    /** Étape de traitement que PaiementDmgService::attentePaiementValidation() écarte. */
    private const ETAPE_TRAITEMENT_EXCLUE_DMG = 5;

    private int $executionId;

    private int $sourceContractColumnCount = 0;

    /** @var array<string, mixed> */
    private array $migrationCounters = [];

    private int $chunkSize = 1000;

    private bool $resume = false;

    private ?string $currentPhase = null;

    /** @var array<string, int> */
    private array $phaseChunkCounts = [];

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
        $chunkSize = filter_var($this->option('chunk'), FILTER_VALIDATE_INT);
        $allowedSteps = [
            'all', 'references', 'agences', 'users', 'entreprises', 'offres', 'beneficiaires',
            'stages', 'pointages', 'paiements', 'dossiers_paiement', 'dossiers_groupes',
            'operations', 'bordereaux', 'evenements', 'desse_doublons',
            'backfill_adp_nature', 'backfill_corbeilles_ca', 'backfill_retour_chefagence', 'backfill_paiements_dmg', 'backfill_presence_payments',
            'backfill_situation_pointage', 'backfill_droits_pointage', 'backfill_corbeilles_dmg',
            'fix_statut_paiements_legacy', 'fix_pointage_revisions', 'backfill_avenants_renouvellement',
            'backfill_visa_desse', 'backfill_stagiaires_differes_ac',
            'fix_etat_chef_agence_100', 'fix_legacy_ca_validation', 'update_missing_data',
            'remaining',
        ];
        if (! in_array($step, $allowedSteps, true)) {
            $this->error("Phase inconnue : {$step}.");

            return self::INVALID;
        }
        if ($chunkSize === false || $chunkSize < 1 || $chunkSize > 5000) {
            $this->error('La taille de chunk doit être comprise entre 1 et 5000.');

            return self::INVALID;
        }

        $this->migrationCounters = [];
        $this->phaseChunkCounts = [];
        $this->chunkSize = $chunkSize;
        $this->resume = (bool) $this->option('resume') || $step === 'remaining';
        $this->sourceContractColumnCount = 0;
        $this->info("Début de la migration des données (Étape : {$step}, chunk : {$this->chunkSize})...");

        try {
            DB::connection('legacy')->getPdo();
            DB::connection('legacy')->disableQueryLog();
            DB::connection()->disableQueryLog();
        } catch (\Exception $e) {
            $this->error("Impossible de se connecter à la base 'legacy'. Vérifiez votre config/database.php et .env");
            $this->error($e->getMessage());

            return 1;
        }

        if (! $this->acquireMigrationLock()) {
            $this->error('Une autre migration legacy est déjà en cours. Aucune nouvelle exécution n’a été démarrée.');

            return self::FAILURE;
        }

        try {
            if (! $dryRun) {
                $staleCount = $this->recorder->failStaleExecutions(self::SOURCE_VERSION);
                if ($staleCount > 0) {
                    $this->warn("{$staleCount} exécution(s) interrompue(s) précédemment ont été classées en échec.");
                }
            } else {
                DB::beginTransaction();
                $this->warn('Mode dry-run : toutes les écritures PostgreSQL seront annulées.');
            }

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
                $this->runPhase('references', fn () => $this->call('legacy:migrer-referentiels'));
            }

            if ($step === 'all' || $step === 'users') {
                $this->runPhase('users', fn () => $this->migrateUsers());
            }

            if ($step === 'all' || $step === 'entreprises') {
                $this->runPhase('entreprises', fn () => $this->migrateEntreprises());
            }

            if ($step === 'all' || $step === 'offres') {
                $this->runPhase('offres', fn () => $this->migrateOffres());
            }

            if ($step === 'all' || $step === 'beneficiaires') {
                $this->runPhase('beneficiaires', fn () => $this->migrateBeneficiaires());
            }

            if ($step === 'all' || $step === 'stages') {
                $this->runPhase('stages', fn () => $this->migrateStages());
            }

            if ($step === 'all' || $step === 'pointages') {
                $this->runPhase('pointages', fn () => $this->migratePointages());
            }

            if ($step === 'all' || $step === 'paiements') {
                $this->runPhase('paiements', fn () => $this->migratePaiements());
            }

            if ($step === 'all' || $step === 'remaining' || $step === 'paiements' || $step === 'dossiers_paiement') {
                $this->runPhase('dossiers_paiement', fn () => $this->backfillLegacyDossiersPaiement());
            }

            if ($step === 'all' || $step === 'remaining' || $step === 'dossiers_groupes') {
                $this->runPhase('dossiers_groupes', fn () => $this->migrateDossiersGroupes());
            }

            if ($step === 'all' || $step === 'remaining' || $step === 'operations') {
                $this->runPhase('operations', fn () => $this->migrateOperations());
            }

            if ($step === 'all' || $step === 'remaining' || $step === 'bordereaux') {
                $this->runPhase('bordereaux', fn () => $this->migrateBordereaux());
            }

            if ($step === 'all' || $step === 'remaining' || $step === 'evenements') {
                $this->runPhase('evenements', fn () => $this->migrateEvenements());
            }

            if ($step === 'all' || $step === 'remaining' || $step === 'desse_doublons') {
                $this->runPhase('desse_doublons', fn () => $this->migrateDesseDoublonDecisions());
            }

            if ($step === 'all' || $step === 'backfill_adp_nature') {
                $this->runPhase('backfill_adp_nature', fn () => $this->backfillAddAdpNature($dryRun));
            }

            if ($step === 'all' || $step === 'backfill_corbeilles_ca') {
                $this->runPhase('backfill_corbeilles_ca', fn () => $this->backfillChefAgenceCorbeilles($dryRun));
            }

            if ($step === 'all' || $step === 'backfill_retour_chefagence') {
                $this->runPhase('backfill_retour_chefagence', fn () => $this->backfillRetourChefAgence($dryRun));
            }

            if ($step === 'all' || $step === 'backfill_paiements_dmg' || $step === 'backfill_presence_payments') {
                $this->runPhase('backfill_paiements_dmg', fn () => $this->backfillPaiementsDmg());
            }

            if ($step === 'all' || $step === 'backfill_avenants_renouvellement') {
                $this->runPhase('backfill_avenants_renouvellement', fn () => $this->backfillAvenantsRenouvellement($dryRun));
            }

            if ($step === 'all' || $step === 'backfill_stagiaires_differes_ac') {
                $this->runPhase('backfill_stagiaires_differes_ac', fn () => $this->backfillStagiairesDifferesAc($dryRun));
            }

            if ($step === 'all' || $step === 'backfill_visa_desse') {
                $this->runPhase('backfill_visa_desse', fn () => $this->backfillVisaDesse($dryRun));
            }

            if ($step === 'all' || $step === 'fix_statut_paiements_legacy') {
                $this->runPhase('fix_statut_paiements_legacy', fn () => $this->fixStatutPaiementsLegacy($dryRun));
            }

            if ($step === 'all' || $step === 'backfill_situation_pointage') {
                $this->runPhase('backfill_situation_pointage', fn () => $this->backfillSituationPointage($dryRun));
            }

            if ($step === 'all' || $step === 'backfill_droits_pointage') {
                $this->runPhase('backfill_droits_pointage', fn () => $this->backfillDroitsPointage($dryRun));
            }

            if ($step === 'all' || $step === 'fix_pointage_revisions') {
                $this->runPhase('fix_pointage_revisions', fn () => $this->fixPointageRevisions($dryRun));
            }

            if ($step === 'all' || $step === 'backfill_corbeilles_dmg') {
                $this->runPhase('backfill_corbeilles_dmg', fn () => $this->backfillCorbeillesDmg($dryRun));
            }

            if ($step === 'all' || $step === 'fix_etat_chef_agence_100') {
                $this->runPhase('fix_etat_chef_agence_100', fn () => $this->fixEtatChefAgence100());
            }

            if ($step === 'all' || $step === 'fix_legacy_ca_validation') {
                $this->runPhase('fix_legacy_ca_validation', fn () => $this->fixLegacyChefAgenceValidation());
            }

            if ($step === 'all' || $step === 'update_missing_data') {
                $this->runPhase('update_missing_data', fn () => $this->updateLegacyMissingData());
            }

            $this->migrationCounters += $this->collectMigrationCounters();
            $this->migrationCounters['taille_chunk'] = $this->chunkSize;
            $this->recorder->flush();
            $this->recorder->complete($this->executionId, $this->migrationCounters);
            $this->line('Compteurs : '.json_encode($this->migrationCounters, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

            if ($dryRun) {
                DB::rollBack();
                $this->warn('Dry-run terminé : aucune écriture PostgreSQL conservée.');
            }
        } catch (Throwable $e) {
            if ($this->currentPhase !== null) {
                $this->migrationCounters['phase_en_echec'] = $this->currentPhase;
            }
            $errorMessage = $this->safeErrorMessage($e);
            if ($dryRun && DB::transactionLevel() > 0) {
                DB::rollBack();
            } elseif (isset($this->executionId)) {
                try {
                    $this->recorder->flush();
                    $this->recorder->fail($this->executionId, $this->migrationCounters, $errorMessage);
                } catch (Throwable) {
                    $this->warn('Le statut d’échec n’a pas pu être enregistré; aucune donnée SQL sensible n’est affichée.');
                }
            }

            $this->error('Migration interrompue : '.$errorMessage);

            return self::FAILURE;
        } finally {
            $this->releaseMigrationLock();
        }

        $this->info('Migration terminée !');

        return self::SUCCESS;
    }

    private function safeErrorMessage(Throwable $exception): string
    {
        if ($exception instanceof QueryException) {
            return sprintf(
                'Erreur SQL dans la phase %s (code %s). La requête et ses données ne sont pas affichées.',
                $this->currentPhase ?? 'inconnue',
                (string) $exception->getCode(),
            );
        }

        return $exception->getMessage();
    }

    private function acquireMigrationLock(): bool
    {
        if (DB::getDriverName() !== 'pgsql') {
            return true;
        }

        $result = DB::selectOne('SELECT pg_try_advisory_lock(hashtext(?)) AS acquired', [self::SOURCE_VERSION]);

        return (bool) data_get($result, 'acquired', false);
    }

    private function releaseMigrationLock(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::selectOne('SELECT pg_advisory_unlock(hashtext(?))', [self::SOURCE_VERSION]);
        }
    }

    private function runPhase(string $name, Closure $callback): void
    {
        $this->currentPhase = $name;
        $startedAt = hrtime(true);
        $memoryBefore = memory_get_usage(true);

        try {
            if ((bool) $this->option('with-model-audits')) {
                $callback();
            } else {
                AuditContext::withoutAuditing($callback);
            }
            $this->recorder->flush();
        } catch (Throwable $e) {
            $this->migrationCounters['phases'][$name] = [
                'statut' => 'ECHEC',
                'duree_secondes' => round((hrtime(true) - $startedAt) / 1_000_000_000, 3),
                'chunks' => $this->phaseChunkCounts[$name] ?? 0,
                'memoire_fin_mo' => round(memory_get_usage(true) / 1048576, 1),
            ];

            throw $e;
        }

        $duration = round((hrtime(true) - $startedAt) / 1_000_000_000, 3);
        $memoryAfter = memory_get_usage(true);
        $this->migrationCounters['phases'][$name] = [
            'statut' => 'TERMINEE',
            'duree_secondes' => $duration,
            'chunks' => $this->phaseChunkCounts[$name] ?? 0,
            'memoire_avant_mo' => round($memoryBefore / 1048576, 1),
            'memoire_fin_mo' => round($memoryAfter / 1048576, 1),
            'memoire_pic_mo' => round(memory_get_peak_usage(true) / 1048576, 1),
        ];
        $this->line(sprintf(
            '<info>Phase %s terminée</info> — %.3f s, %d chunk(s), mémoire %.1f Mo (pic %.1f Mo)',
            $name,
            $duration,
            $this->phaseChunkCounts[$name] ?? 0,
            $memoryAfter / 1048576,
            memory_get_peak_usage(true) / 1048576,
        ));
        $this->currentPhase = null;
    }

    /**
     * Parcourt une source par clé croissante et rend chaque chunk atomique côté cible.
     * La pagination par clé évite le coût cumulatif d'OFFSET sur les tables volumineuses.
     *
     * @param  QueryBuilder|EloquentBuilder<*>  $query
     */
    private function processInChunks(
        QueryBuilder|EloquentBuilder $query,
        int $preferredSize,
        Closure $callback,
        string $column = 'id',
        ?string $alias = null,
        ?string $checkpointKey = null,
    ): void {
        $size = min($preferredSize, $this->chunkSize);
        $phase = $this->currentPhase ?? 'inconnue';
        $checkpointKey ??= $phase;
        $this->phaseChunkCounts[$phase] ??= 0;

        $checkpoint = DB::table('progressions_migration_legacy')
            ->where('phase', $checkpointKey)
            ->where('version_source', self::SOURCE_VERSION)
            ->first();

        if ($this->resume && $checkpoint !== null && $checkpoint->statut === 'TERMINEE') {
            $this->line("Phase {$checkpointKey} déjà terminée : checkpoint conservé.");

            return;
        }

        $lastSourceId = $this->resume && $checkpoint !== null
            ? (int) $checkpoint->dernier_id_source
            : 0;
        DB::table('progressions_migration_legacy')->updateOrInsert(
            ['phase' => $checkpointKey],
            [
                'version_source' => self::SOURCE_VERSION,
                'dernier_id_source' => $lastSourceId,
                'statut' => 'EN_COURS',
                'execution_migration_id' => $this->executionId,
                'terminee_le' => null,
                'created_at' => $checkpoint !== null ? $checkpoint->created_at : now(),
                'updated_at' => now(),
            ],
        );

        if ($lastSourceId > 0) {
            $query->where($column, '>', $lastSourceId);
            $this->line("Reprise {$checkpointKey} après l’ID {$lastSourceId}.");
        }

        try {
            $query->chunkById($size, function ($rows) use ($callback, $phase, $alias, $column, $checkpointKey): void {
                $lastRow = $rows->last();
                $lastIdAttribute = $alias ?? (
                    str_contains($column, '.')
                        ? substr($column, strrpos($column, '.') + 1)
                        : $column
                );
                $lastId = (int) data_get($lastRow, $lastIdAttribute);

                if ($lastId < 1) {
                    throw new \RuntimeException("Impossible de déterminer le dernier ID du chunk {$checkpointKey}.");
                }

                try {
                    DB::transaction(function () use ($callback, $rows, $checkpointKey, $lastId): void {
                        $callback($rows);
                        $this->recorder->flush();
                        DB::table('progressions_migration_legacy')->where('phase', $checkpointKey)->update([
                            'dernier_id_source' => $lastId,
                            'statut' => 'EN_COURS',
                            'execution_migration_id' => $this->executionId,
                            'updated_at' => now(),
                        ]);
                    });
                } catch (Throwable $e) {
                    $this->recorder->discardPending();

                    throw $e;
                }

                $this->phaseChunkCounts[$phase]++;
                gc_collect_cycles();
            }, $column, $alias);
        } catch (Throwable $e) {
            DB::table('progressions_migration_legacy')->where('phase', $checkpointKey)->update([
                'statut' => 'ECHEC',
                'execution_migration_id' => $this->executionId,
                'updated_at' => now(),
            ]);

            throw $e;
        }

        DB::table('progressions_migration_legacy')->where('phase', $checkpointKey)->update([
            'statut' => 'TERMINEE',
            'execution_migration_id' => $this->executionId,
            'terminee_le' => now(),
            'updated_at' => now(),
        ]);
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

        $agencesMap = Agence::whereNotNull('ancien_id')->pluck('id', 'ancien_id')->toArray();
        $legacyIds = collect($entreprises)->pluck('id')->toArray();
        $entreprisesExistantes = Entreprise::withTrashed()->whereIn('ancien_id', $legacyIds)->get()->keyBy('ancien_id');

        foreach ($entreprises as $legacyEntreprise) {
            $agence_id = $agencesMap[$legacyEntreprise->agence_id] ?? null;
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

            $entreprise = $entreprisesExistantes[$legacyEntreprise->id] ?? new Entreprise(['ancien_id' => $legacyEntreprise->id]);
            $entreprise->fill([
                'raison_sociale' => $legacyEntreprise->libelle_entreprise ?? 'Inconnu',
                'sigle' => $legacyEntreprise->sigle,
                'numero_contribuable' => $numContribuable,
                'registre_commerce' => $registreCommerce,
                'telephone' => $legacyEntreprise->contact,
                'email' => $legacyEntreprise->mail,
                'adresse' => $legacyEntreprise->adresse,
                'agence_id' => $agence_id,
                'type_structure_id' => $type_structure_id,
                'deleted_at' => $this->mapper->normalizeLegacyDate($legacyEntreprise->deleted_at ?? null),
            ]);
            $entreprise->save();
            $entreprisesExistantes[$legacyEntreprise->id] = $entreprise;
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

        $entreprisesMap = Entreprise::whereNotNull('ancien_id')->pluck('id', 'ancien_id')->toArray();
        $agencesMap = Agence::whereNotNull('ancien_id')->pluck('id', 'ancien_id')->toArray();
        $typesStageMap = TypeStage::whereNotNull('ancien_id')->pluck('id', 'ancien_id')->toArray();
        $sourcesFinancementMap = SourceFinancement::whereNotNull('ancien_id')->pluck('id', 'ancien_id')->toArray();

        $legacyIds = $offres->pluck('id_offre')->toArray();
        $offresExistantes = OffreEmploi::withTrashed()->whereIn('ancien_id', $legacyIds)->get()->keyBy('ancien_id');

        foreach ($offres as $legacyOffre) {
            $entreprise_id = $entreprisesMap[$legacyOffre->entreprise_id] ?? null;
            $agence_id = $agencesMap[$legacyOffre->agence_id] ?? null;
            $type_stage_id = $typesStageMap[$legacyOffre->type_stage_id] ?? null;
            $source_financement_id = $sourcesFinancementMap[$legacyOffre->source_financement_id] ?? null;

            if ($entreprise_id && $agence_id && $type_stage_id && $source_financement_id) {
                $publiee_le = $legacyOffre->date_de_publication;
                if ($publiee_le && (str_starts_with($publiee_le, '-') || str_starts_with($publiee_le, '0000'))) {
                    $publiee_le = null;
                }

                $offre = $offresExistantes[$legacyOffre->id_offre] ?? new OffreEmploi(['ancien_id' => $legacyOffre->id_offre]);
                $offre->fill([
                    'entreprise_id' => $entreprise_id,
                    'agence_id' => $agence_id,
                    'type_stage_id' => $type_stage_id,
                    'source_financement_id' => $source_financement_id,
                    'numero' => 'OFR-'.str_pad($legacyOffre->id_offre, 5, '0', STR_PAD_LEFT),
                    'intitule' => $legacyOffre->intitule_offre ?? 'Offre non spécifiée',
                    'nombre_places' => max(1, (int) ($legacyOffre->nombre_de_place ?? 1)),
                    'publiee_le' => $publiee_le,
                    'deleted_at' => $this->mapper->normalizeLegacyDate($legacyOffre->deleted_at ?? null),
                ]);
                $offre->save();
                $offresExistantes[$legacyOffre->id_offre] = $offre;
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

        $this->processInChunks($query, 500, function ($contrats) use (
            &$bar, $typesPaiementMap, $handicapsMap, $typesHandicapMap,
            $liensParenteMap, $typesEnseignementMap, $communesMap, $niveauxParLibelle, $diplomesParLibelle
        ): void {
            $chunkNumeroAej = $contrats->pluck('numero_aej')->filter()->unique()->toArray();
            $existantsMap = Beneficiaire::whereIn('numero_aej', $chunkNumeroAej)->get()->keyBy('numero_aej');

            foreach ($contrats as $legacyContrat) {
                if (empty($legacyContrat->numero_aej)) {
                    $this->recorder->anomaly(
                        $this->executionId,
                        'CONTRAT_SANS_NUMERO_AEJ',
                        'contrats_pae',
                        $legacyContrat->id,
                        'Bénéficiaire non normalisable : numero_aej absent.',
                        (array) $legacyContrat,
                    );
                    $this->recorder->preserveContrat(
                        $this->executionId,
                        $legacyContrat,
                        null,
                        null,
                        null,
                        $this->sourceContractColumnCount,
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

                $beneficiaire = $existantsMap[$legacyContrat->numero_aej] ?? new Beneficiaire(['numero_aej' => $legacyContrat->numero_aej]);
                $beneficiaire->fill([
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
                ]);
                $beneficiaire->save();
                $existantsMap[$legacyContrat->numero_aej] = $beneficiaire;
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

            // Vider le buffer à chaque chunk pour éviter un OOM sur de gros volumes.
            $this->recorder->flush();
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

        $this->processInChunks($query, 500, function ($contrats) use (
            &$bar, $agencesMap, $typesStageMap, $entreprisesMap, $sourcesFinancementMap,
            $origineStagiaireMap, $situationsStageMap, $statutsStageMap
        ): void {
            $aejNums = $contrats->pluck('numero_aej')->filter()->unique()->toArray();
            $beneficiairesMap = Beneficiaire::whereIn('numero_aej', $aejNums)->pluck('id', 'numero_aej')->toArray();

            $legacyIds = $contrats->pluck('id')->toArray();
            $stagesExistants = Stage::withTrashed()->whereIn('ancien_id', $legacyIds)->get()->keyBy('ancien_id');
            $contratsExistants = Contrat::withTrashed()->whereIn('ancien_id', $legacyIds)->get()->keyBy('ancien_id');
            $stageIds = $stagesExistants->pluck('id')->filter()->toArray();
            $instancesExistantes = empty($stageIds) ? collect() : InstanceParcours::whereIn('stage_id', $stageIds)->get()->keyBy('stage_id');
            $instanceIds = $instancesExistantes->pluck('id')->filter()->toArray();
            $tachesExistantes = empty($instanceIds) ? collect() : TacheParcours::whereIn('instance_parcours_id', $instanceIds)->whereIn('statut', ['OUVERTE', 'REVENDIQUEE'])->get()->groupBy('instance_parcours_id');

            $definition = DefinitionParcours::firstOrCreate(
                ['code' => 'STAGE_LEGACY', 'version' => 1],
                ['nom' => 'Parcours Legacy', 'active' => true]
            );
            $etapesMap = [];

            foreach ($contrats as $legacyContrat) {
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
                    $this->recorder->preserveContrat(
                        $this->executionId,
                        $legacyContrat,
                        null,
                        null,
                        null,
                        $this->sourceContractColumnCount,
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
                    $this->recorder->preserveContrat(
                        $this->executionId,
                        $legacyContrat,
                        null,
                        null,
                        null,
                        $this->sourceContractColumnCount,
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

                $stage = $stagesExistants[$legacyContrat->id] ?? new Stage(['ancien_id' => $legacyContrat->id]);
                $stage->fill([
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
                ]);
                $stage->save();
                $stagesExistants[$legacyContrat->id] = $stage;

                // 2. Gérer le Contrat Financier lié
                $contrat = $contratsExistants[$legacyContrat->id] ?? new Contrat(['ancien_id' => $legacyContrat->id]);
                $contrat->fill([
                    'stage_id' => $stage->id,
                    'numero' => 'CT-'.str_pad($legacyContrat->id, 5, '0', STR_PAD_LEFT),
                    'date_debut' => $date_debut,
                    'date_fin' => $date_fin_prevue,
                    'prime_mensuelle' => $primeMensuelle,
                    'statut' => 'SIGNE', // Les anciens contrats étaient signés
                    'deleted_at' => $deletedAt,
                ]);
                $contrat->save();
                $contratsExistants[$legacyContrat->id] = $contrat;

                // 3. Gérer le Workflow via contrat_etape / etape_traitement
                $statutLegacy = (int) ($legacyContrat->etapetraitement_id ?? $legacyContrat->id_statut_stage ?? 1);

                // mapChefAgenceCorbeille() retombe déjà sur mapStatutStageToCorbeille() quand le
                // contexte Chef d'Agence ne tranche pas : re-dériver ici écraserait les dossiers
                // qu'il renvoie volontairement au CIP (étape 2 sans décision du CA notamment).
                $corbeilleEnum = $this->mapper->mapChefAgenceCorbeille($legacyContrat);

                // Stagiaire déjà validé de bout en bout (payé ou définitivement rejeté après
                // paiement) : on clôt l'instance de workflow au lieu de la laisser trainer
                // dans une corbeille active (cf. LegacyMapperService::estStatutStageTermine).
                $termineeLe = $this->mapper->estStatutStageTermine($statutLegacy)
                    ? ($this->mapper->normalizeLegacyDate($legacyContrat->updated_at ?? null) ?? now())
                    : null;

                $etapeCode = strtoupper($corbeilleEnum->value);
                if (! isset($etapesMap[$etapeCode])) {
                    $etapeNom = str_replace('_', ' ', $etapeCode);
                    $etapesMap[$etapeCode] = EtapeParcours::firstOrCreate(
                        ['definition_parcours_id' => $definition->id, 'code' => $etapeCode],
                        ['nom' => $etapeNom, 'initiale' => false, 'finale' => false]
                    );
                }
                $etape = $etapesMap[$etapeCode];

                $instance = $instancesExistantes[$stage->id] ?? new InstanceParcours(['stage_id' => $stage->id]);
                $instance->fill([
                    'definition_parcours_id' => $definition->id,
                    'etape_courante_id' => $etape->id,
                    'corbeille_actuelle' => $corbeilleEnum->value,
                    'terminee_le' => $termineeLe,
                ]);
                $instance->save();
                $instancesExistantes[$stage->id] = $instance;

                $preloadedTasks = $tachesExistantes[$instance->id] ?? collect();
                $tachesExistantes[$instance->id] = $preloadedTasks;
                $this->syncOpenTask($instance, $etape, $corbeilleEnum, $agence_id, $termineeLe !== null, $preloadedTasks);
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

            // Vider le buffer à chaque chunk pour éviter un OOM sur de gros volumes.
            $this->recorder->flush();
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
        // Situation du stage au moment du pointage (réactivation, fin de stage...) : distincte de
        // la situation courante du stage, elle conditionne l'entrée en file DMG côté legacy.
        $situationsStageMap = DB::table('situations_stage')->pluck('id', 'ancien_id')->toArray();
        $definition = DefinitionParcours::firstOrCreate(
            ['code' => 'POINTAGE_LEGACY', 'version' => 1],
            ['nom' => 'Parcours Pointage Legacy', 'active' => true]
        );
        $etapesMap = [];

        $this->processInChunks($query, 1000, function ($pointages) use (&$bar, &$periodesMap, $definition, &$etapesMap, $situationsStageMap) {
            $stagiaireIds = $pointages->pluck('stagiaire_id')->filter()->unique()->toArray();
            $legacyIds = $pointages->pluck('id')->toArray();

            $stagesMap = Stage::whereIn('ancien_id', $stagiaireIds)->pluck('id', 'ancien_id')->toArray();
            $datesDebutParStage = Stage::whereIn('ancien_id', $stagiaireIds)->pluck('date_debut', 'ancien_id')->toArray();
            $agencesParStage = Stage::whereIn('ancien_id', $stagiaireIds)->pluck('agence_id', 'ancien_id')->toArray();
            $etatsChefAgence = DB::connection('legacy')->table('contrats_pae')->whereIn('id', $stagiaireIds)->pluck('etat_chef_agence', 'id')->toArray();
            $paiementsRenvoyesAuDmg = $this->paiementsRenvoyesAuDmg($stagiaireIds);

            // L'agent de saisie du pointage legacy (`pointage_models.user_id`, souvent le CIP)
            // est conservé sur chaque version pour alimenter la colonne « Agent Saisie ».
            $legacyUserIds = $pointages->pluck('user_id')->filter()->unique()->toArray();
            $saisisParMap = DB::table('correspondances_ancien_systeme')
                ->where('table_source', 'users')
                ->where('table_cible', 'users')
                ->whereIn('id_source', $legacyUserIds)
                ->pluck('id_cible', 'id_source')
                ->map(fn ($id): int => (int) $id)
                ->all();

            $versionsMap = VersionPointage::whereIn('ancien_id', $legacyIds)->get()->keyBy('ancien_id');
            $stageIdsFilter = array_filter(array_values($stagesMap));

            $pointagesExistant = ! empty($stageIdsFilter) ? Pointage::withTrashed()->whereIn('stage_id', $stageIdsFilter)->get() : collect();
            $pointagesMap = [];
            foreach ($pointagesExistant as $p) {
                if (is_null($p->deleted_at)) {
                    $pointagesMap["{$p->stage_id}_{$p->periode_id}_{$p->nature}"] = $p;
                }
                $pointagesMap["id_{$p->id}"] = $p;
            }

            $pointageIds = $pointagesExistant->pluck('id')->toArray();
            $maxVersions = ! empty($pointageIds) ? VersionPointage::whereIn('pointage_id', $pointageIds)->select('pointage_id', DB::raw('MAX(numero_version) as max_version'))->groupBy('pointage_id')->pluck('max_version', 'pointage_id')->toArray() : [];

            $instancesExistantes = ! empty($pointageIds) ? InstanceParcours::whereIn('pointage_id', $pointageIds)->get()->keyBy('pointage_id') : collect();
            $instanceIds = $instancesExistantes->pluck('id')->filter()->toArray();
            $tachesExistantes = empty($instanceIds) ? collect() : TacheParcours::whereIn('instance_parcours_id', $instanceIds)->whereIn('statut', ['OUVERTE', 'REVENDIQUEE'])->get()->groupBy('instance_parcours_id');

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

                $statut = $this->mapper->mapStatutPointage($legacyPointage);

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
                $situationStageId = isset($legacyPointage->situationstage_id)
                    ? ($situationsStageMap[(int) $legacyPointage->situationstage_id] ?? null)
                    : null;
                $corbeilleEnum = $this->mapper->mapPointageToCorbeille(
                    isset($legacyPointage->etape_id) ? (int) $legacyPointage->etape_id : null,
                    $statut,
                    $naturePointage,
                    isset($etatsChefAgence[$legacyPointage->stagiaire_id]) ? (int) $etatsChefAgence[$legacyPointage->stagiaire_id] : null,
                    isset($paiementsRenvoyesAuDmg[$legacyPointage->stagiaire_id.'|'.$codePeriode])
                )->value;

                // PointageChefAgenceService::getPointage() (legacy) exclut du crible du CA les
                // pointages dont `situationstage_id` vaut ABANDON(2)/SUSPENSION(3)/DESISTEMENT
                // SANS PAIEMENT(6) : le stagiaire est sorti du dispositif, le CA ne les voit
                // jamais. On garde la corbeille pour la traçabilité mais on ne crée pas de tâche
                // ouverte, pour ne pas faire apparaître un dossier fantôme chez le CA.
                $stagiaireSortiHorsCorbeilleCa = $corbeilleEnum === CorbeilleEnum::CA_VALIDATION_POINTAGES->value
                    && in_array((int) ($legacyPointage->situationstage_id ?? 0), [2, 3, 6], true);

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
                    $versionExistante = $versionsMap[$legacyPointage->id] ?? null;

                    if ($versionExistante) {
                        $pointage = $pointagesMap["id_{$versionExistante->pointage_id}"] ?? Pointage::withTrashed()->findOrFail($versionExistante->pointage_id);
                        $conflit = false;
                        $cleConflit = "{$stage_id}_{$periodeId}_{$naturePointage}";
                        if (isset($pointagesMap[$cleConflit]) && $pointagesMap[$cleConflit]->id !== $pointage->id) {
                            $conflit = true;
                        }

                        if ($conflit) {
                            $this->warn("Pointage legacy #{$legacyPointage->id} non reclassé : la cible {$codePeriode}/{$naturePointage} existe déjà.");
                            $bar->advance();

                            continue;
                        }

                        $pointage->update([
                            'stage_id' => $stage_id,
                            'periode_id' => $periodeId,
                            'nature' => $naturePointage,
                            'situation_stage_id' => $situationStageId,
                            'statut' => $statut,
                            'deleted_at' => $deletedAt,
                        ]);
                        $pointagesMap["{$stage_id}_{$periodeId}_{$naturePointage}"] = $pointage;

                        $versionExistante->update([
                            'saisi_par_id' => $saisisParMap[$legacyPointage->user_id] ?? null,
                            'observation' => $legacyPointage->commentaire,
                            'saisi_le' => $date,
                        ]);
                    } else {
                        // Idempotence par (stage_id, periode_id, nature)
                        $pointage = $pointagesMap["{$stage_id}_{$periodeId}_{$naturePointage}"] ?? null;

                        if ($pointage) {
                            $numeroVersion = ($maxVersions[$pointage->id] ?? 0) + 1;
                            $maxVersions[$pointage->id] = $numeroVersion;
                            $pointage->update([
                                // Le legacy crée une nouvelle ligne (nouvel id) à chaque
                                // resoumission au lieu de mettre à jour en place ; comme les lignes
                                // sont parcourues par id croissant, la dernière rencontrée pour ce
                                // triplet (stage, période, nature) est la révision la plus récente.
                                // Sans cette réaffectation, `ancien_id` reste bloqué sur la toute
                                // première révision vue (parfois déjà soft-deleted côté legacy).
                                'ancien_id' => $legacyPointage->id,
                                'statut' => $statut,
                                'version_courante' => $numeroVersion,
                                'situation_stage_id' => $situationStageId,
                                'deleted_at' => $deletedAt,
                            ]);
                        } else {
                            $pointage = Pointage::create([
                                'ancien_id' => $legacyPointage->id,
                                'stage_id' => $stage_id,
                                'periode_id' => $periodeId,
                                'nature' => $naturePointage,
                                'situation_stage_id' => $situationStageId,
                                'statut' => $statut,
                                'version_courante' => 1,
                                'deleted_at' => $deletedAt,
                            ]);
                            $pointagesMap["{$stage_id}_{$periodeId}_{$naturePointage}"] = $pointage;
                            $pointagesMap["id_{$pointage->id}"] = $pointage;
                            $numeroVersion = 1;
                            $maxVersions[$pointage->id] = 1;
                        }

                        VersionPointage::create([
                            'ancien_id' => $legacyPointage->id,
                            'saisi_par_id' => $saisisParMap[$legacyPointage->user_id] ?? null,
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
                    $etapeCode = strtoupper($corbeilleEnum);
                    if (! isset($etapesMap[$etapeCode])) {
                        $etapeNom = str_replace('_', ' ', $etapeCode);
                        $etapesMap[$etapeCode] = EtapeParcours::firstOrCreate(
                            ['definition_parcours_id' => $definition->id, 'code' => $etapeCode],
                            ['nom' => $etapeNom, 'initiale' => false, 'finale' => false]
                        );
                    }
                    $etape = $etapesMap[$etapeCode];

                    // L'instance de workflow reflète toujours le dernier état connu du pointage,
                    // donc de sa dernière version (resoumission) traitée.
                    $instance = $instancesExistantes[$pointage->id] ?? new InstanceParcours(['pointage_id' => $pointage->id]);
                    $instance->fill([
                        'definition_parcours_id' => $definition->id,
                        'etape_courante_id' => $etape->id,
                        'corbeille_actuelle' => $corbeilleEnum,
                    ]);
                    $instance->save();
                    $instancesExistantes[$pointage->id] = $instance;

                    $preloadedTasks = $tachesExistantes[$instance->id] ?? collect();
                    $tachesExistantes[$instance->id] = $preloadedTasks;
                    $this->syncOpenTask(
                        $instance,
                        $etape,
                        CorbeilleEnum::from($corbeilleEnum),
                        $agencesParStage[$legacyPointage->stagiaire_id] ?? null,
                        $stagiaireSortiHorsCorbeilleCa,
                        $preloadedTasks
                    );

                    if ($stagiaireSortiHorsCorbeilleCa) {
                        $this->recorder->anomaly(
                            $this->executionId,
                            'POINTAGE_STAGIAIRE_SORTI_HORS_CORBEILLE_CA',
                            'pointage_models',
                            $legacyPointage->id,
                            'Pointage exclu de la corbeille de validation CA : stagiaire abandon/suspension/désistement (situationstage_id='.$legacyPointage->situationstage_id.').',
                            (array) $legacyPointage,
                            'NON_BLOQUANTE',
                        );
                    }

                    $this->recorder->correspondence(
                        $this->executionId,
                        'pointage_models',
                        $legacyPointage->id,
                        'pointages',
                        $pointage->id,
                        (array) $legacyPointage,
                    );
                } catch (Throwable $e) {
                    if ($e instanceof QueryException) {
                        throw $e;
                    }

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

        $this->processInChunks($query, 1000, function ($paiements) use (&$bar, &$periodesMap): void {
            $stagiaireIds = $paiements->pluck('stagiaire_id')->filter()->unique()->toArray();
            $legacyUserIds = $paiements->pluck('user_id')->filter()->unique()->toArray();
            $legacyPointageIds = $paiements->pluck('pointage_id')->filter()->unique()->toArray();
            $legacyIds = $paiements->pluck('id')->toArray();

            // withTrashed() : un paiement déjà validé/rejeté par l'AC dans le legacy reste dans
            // son historique même si le stage a été soft-supprimé depuis (ex. contrat clôturé
            // puis retiré du portefeuille). Le legacy ne filtre jamais l'écran AC sur
            // `contrats_pae.deleted_at`, seulement sur celui du paiement et du dossier — sans
            // withTrashed() le scope global de Stage (SoftDeletes) les faisait disparaître en
            // PAIEMENT_SANS_STAGE alors que le stage existe bel et bien.
            $stagesMap = Stage::withTrashed()->whereIn('ancien_id', $stagiaireIds)->pluck('id', 'ancien_id')->toArray();
            $sourceFinancementParStage = Stage::withTrashed()->whereIn('ancien_id', $stagiaireIds)->pluck('source_financement_id', 'ancien_id')->toArray();
            $datesDebutParStage = Stage::withTrashed()->whereIn('ancien_id', $stagiaireIds)->pluck('date_debut', 'ancien_id')->toArray();
            $usersMap = DB::table('correspondances_ancien_systeme')
                ->where('table_source', 'users')
                ->where('table_cible', 'users')
                ->whereIn('id_source', $legacyUserIds)
                ->pluck('id_cible', 'id_source')
                ->map(fn ($id): int => (int) $id)
                ->all();
            $fallbackAuthorId = User::query()->orderBy('id')->value('id');
            $pointagesMap = Pointage::whereIn('ancien_id', $legacyPointageIds)->pluck('id', 'ancien_id')->toArray();
            $pointagesManquants = array_values(array_diff($legacyPointageIds, array_keys($pointagesMap)));
            if ($pointagesManquants !== []) {
                $pointagesMap += VersionPointage::whereIn('ancien_id', $pointagesManquants)
                    ->pluck('pointage_id', 'ancien_id')
                    ->toArray();
            }

            $droitsParAncienId = DroitPaiement::whereIn('ancien_id', $legacyIds)->get()->keyBy('ancien_id');
            $paiementsParAncienId = Paiement::whereIn('ancien_id', $legacyIds)->get()->keyBy('ancien_id');

            $stageIdsFilter = array_filter(array_values($stagesMap));
            $droitsActifs = ! empty($stageIdsFilter) ? DroitPaiement::whereIn('stage_id', $stageIdsFilter)->whereNull('annule_le')->get() : collect();
            $droitsActifsMap = [];
            foreach ($droitsActifs as $d) {
                $droitsActifsMap["{$d->stage_id}_{$d->periode_id}_{$d->nature}"] = $d;
            }

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
                    // Chaque écriture est idempotente. Une transaction PostgreSQL par paiement
                    // ajoutait deux allers-retours BEGIN/COMMIT sur près de 200 000 lignes.
                    (function () use ($legacyPaiement, $stage_id, $periodeId, $source_financement_id, $nature, $date, $usersMap, $fallbackAuthorId, $pointagesMap, &$droitsParAncienId, &$droitsActifsMap, &$paiementsParAncienId): void {
                        $droit = $droitsParAncienId[$legacyPaiement->id] ?? null;

                        // On ne regroupe que par (stage_id, periode_id, nature).
                        // La ligne actuelle remplace toujours le droit actif précédent.
                        $actif = $droitsActifsMap["{$stage_id}_{$periodeId}_{$nature}"] ?? null;
                        if ($actif && $droit && $actif->id === $droit->id) {
                            $actif = null;
                        }

                        if ($actif) {
                            $actif->update([
                                'annule_le' => $date,
                                'motif_annulation' => "Remplacé par le paiement legacy #{$legacyPaiement->id}",
                            ]);
                            unset($droitsActifsMap["{$stage_id}_{$periodeId}_{$nature}"]);
                        }

                        if (! $droit) {
                            $droit = new DroitPaiement(['ancien_id' => $legacyPaiement->id]);
                        }

                        $droit->fill([
                            'stage_id' => $stage_id,
                            'pointage_id' => $pointagesMap[$legacyPaiement->pointage_id ?? null] ?? null,
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
                        $droitsActifsMap["{$stage_id}_{$periodeId}_{$nature}"] = $droit;

                        $paiement = $paiementsParAncienId[$legacyPaiement->id] ?? new Paiement(['ancien_id' => $legacyPaiement->id]);
                        $legacyStatus = $this->mapLegacyPaymentStatus($legacyPaiement);
                        $paiement->fill([
                            'droit_paiement_id' => $droit->id,
                            'montant' => $legacyPaiement->montant,
                        ]);
                        // Un stagiaire différé par l'AC (backfill_stagiaires_differes_ac) reste
                        // en `A_TRAITER` avec la corbeille `dmg_op_differe_ac` alors que le
                        // legacy garde `status_ac = 'processed'` sur la ligne d'origine : sans
                        // ce garde-fou, chaque re-run de cette étape idempotente écrasait la
                        // décision de l'AC en la repromouvant à `EN_OP`.
                        $differeParAc = $paiement->exists
                            && $paiement->statut === 'A_TRAITER'
                            && $paiement->corbeille_actuelle === CorbeilleEnum::DMG_OP_DIFFERE_AC->value;
                        if (! $paiement->exists) {
                            $paiement->statut = $legacyStatus;
                        } elseif ($differeParAc) {
                            // Décision de l'AC préservée telle quelle.
                        } elseif (
                            ($legacyStatus === 'AJOURNE_DMG' && in_array($paiement->statut, ['A_TRAITER', 'EN_DOSSIER', 'AJOURNE_DMG'], true))
                            || in_array($legacyStatus, ['VALIDE_AC', 'PAYE', 'NON_PAYE'], true)
                            || $legacyStatus === 'REJETE_AC'
                            || ($legacyStatus === 'EN_OP' && in_array($paiement->statut, ['A_TRAITER', 'EN_DOSSIER'], true))
                            || ($legacyStatus === 'EN_DOSSIER' && $paiement->statut === 'A_TRAITER')
                        ) {
                            $paiement->statut = $legacyStatus;
                        }
                        if ($legacyStatus === 'PAYE') {
                            $payeLe = $this->mapper->normalizeLegacyDate(
                                $legacyPaiement->date_confirm_pay
                                    ?? $legacyPaiement->updated_at
                                    ?? null,
                            );
                            $paiement->paye_le = $payeLe?->toImmutable();
                        } elseif (in_array($legacyStatus, ['VALIDE_AC', 'NON_PAYE'], true)) {
                            $paiement->paye_le = null;
                        }
                        $paiement->save();

                        $auteurId = $usersMap[$legacyPaiement->user_id ?? null] ?? $fallbackAuthorId;
                        if ($legacyStatus === 'AJOURNE_DMG' && $auteurId) {
                            DecisionPaiement::updateOrCreate(
                                [
                                    'paiement_id' => $paiement->id,
                                    'decision' => 'AJOURNE_DMG',
                                ],
                                [
                                    'auteur_id' => $auteurId,
                                    'statut_avant' => 'A_TRAITER',
                                    'statut_apres' => 'AJOURNE_DMG',
                                    'motif' => $legacyPaiement->observation ?? null,
                                    'decide_le' => $this->mapper->normalizeLegacyDate($legacyPaiement->created_at ?? null) ?? $date,
                                ],
                            );
                        }

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
                    })();
                } catch (Throwable $e) {
                    if ($e instanceof QueryException) {
                        throw $e;
                    }

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
        $duplicateNumeroOwners = DB::connection('legacy')->table('dossiers')
            ->select('identifiant', DB::raw('MIN(id) AS owner_id'))
            ->whereNull('deleted_at')
            ->whereNotNull('identifiant')
            ->where('identifiant', '<>', '')
            ->groupBy('identifiant')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('owner_id', 'identifiant')
            ->map(fn ($id): int => (int) $id)
            ->all();
        $targetNumeroOwners = DossierPaiement::query()
            ->whereNotNull('ancien_id')
            ->pluck('ancien_id', 'numero')
            ->map(fn ($id): int => (int) $id)
            ->all();
        $now = now();

        $this->processInChunks($query, 500, function ($legacyDossiers) use (
            &$bar,
            &$periodesMap,
            &$targetNumeroOwners,
            $agencesMap,
            $sourcesMap,
            $duplicateNumeroOwners,
            $now,
        ): void {
            $legacyDossierIds = $legacyDossiers->pluck('id')->all();

            $legacyPaiementsByDossier = DB::connection('legacy')->table('paiement_models')
                ->select('id', 'dossier_id', 'stagiaire_id', 'montant', 'created_at')
                ->whereIn('dossier_id', $legacyDossierIds)
                ->whereNull('deleted_at')
                ->orderBy('id')
                ->get()
                ->groupBy('dossier_id');
            $legacyStagiaireIds = $legacyPaiementsByDossier->flatten(1)
                ->pluck('stagiaire_id')
                ->filter()
                ->unique()
                ->values()
                ->all();
            // withTrashed() pour la même raison que migratePaiements() : le dossier de paiement
            // d'un stage depuis soft-supprimé doit conserver son agence d'origine.
            $agencesParStagiaire = Stage::withTrashed()
                ->whereIn('ancien_id', $legacyStagiaireIds)
                ->pluck('agence_id', 'ancien_id');

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
                $agenceId = $legacyDossier->agence_id !== null
                    ? ($agencesMap[$legacyDossier->agence_id] ?? null)
                    : null;
                $sourceFinancementId = $sourcesMap[$legacyDossier->type_financement_id] ?? null;

                if ($legacyDossier->agence_id === null) {
                    $agencesDossier = $legacyPaiementsByDossier
                        ->get($legacyDossier->id, collect())
                        ->pluck('stagiaire_id')
                        ->map(fn ($stagiaireId) => $agencesParStagiaire->get($stagiaireId))
                        ->filter()
                        ->unique()
                        ->values();

                    if ($agencesDossier->count() === 1) {
                        $agenceId = (int) $agencesDossier->first();
                    } else {
                        $this->recorder->anomaly(
                            $this->executionId,
                            'DOSSIER_AGENCE_A_RECONCILIER',
                            'dossiers',
                            $legacyDossier->id,
                            $agencesDossier->isEmpty()
                                ? 'Dossier legacy sans agence et sans stagiaire permettant de la dériver.'
                                : 'Dossier legacy regroupant plusieurs agences ; agence cible laissée vide.',
                            [
                                'agence_id_legacy' => null,
                                'agences_cibles_detectees' => $agencesDossier->all(),
                            ],
                        );
                    }
                }

                if (($legacyDossier->agence_id !== null && ! $agenceId) || ! $sourceFinancementId) {
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
                $baseNumero = trim((string) ($legacyDossier->identifiant ?: 'DOS-LEGACY-'.$legacyDossier->id));
                $sourceId = (int) $legacyDossier->id;
                $canonicalSourceId = $duplicateNumeroOwners[$baseNumero] ?? $sourceId;
                $existingTargetOwner = $targetNumeroOwners[$baseNumero] ?? null;
                $requiresSuffix = $canonicalSourceId !== $sourceId
                    || ($existingTargetOwner !== null && $existingTargetOwner !== $sourceId);
                $targetNumero = $requiresSuffix
                    ? mb_substr($baseNumero, 0, 230).'-LEGACY-'.$sourceId
                    : $baseNumero;

                if (isset($duplicateNumeroOwners[$baseNumero])) {
                    $this->recorder->anomaly(
                        $this->executionId,
                        'DOSSIER_NUMERO_DUPLIQUE',
                        'dossiers',
                        $legacyDossier->id,
                        "Numéro legacy partagé par plusieurs dossiers : {$baseNumero}.",
                        [
                            'numero_legacy' => $baseNumero,
                            'source_canonique_id' => $canonicalSourceId,
                            'numero_cible' => $targetNumero,
                        ],
                        'NON_BLOQUANTE',
                    );
                }

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
                    'numero' => $targetNumero,
                    'nature' => Str::startsWith((string) $legacyDossier->identifiant, 'DM') ? 'DM' : 'PS',
                    'statut' => $statutDossier,
                    'montant_total' => 0,
                    'created_at' => $dossier->exists ? $dossier->created_at : $createdAt,
                    'updated_at' => $updatedAt,
                ]);
                $dossier->save();
                $targetNumeroOwners[$targetNumero] = $sourceId;

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

        $this->processInChunks($query, 500, function ($groupes) use (&$periodesMap, $sourcesMap, &$bar): void {
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
        $duplicateNumeroOwners = DB::connection('legacy')->table('operations')
            ->select('id', 'numero_operation')
            ->get()
            ->groupBy(fn ($row): string => trim((string) $row->numero_operation))
            ->filter(fn ($rows, $numero): bool => $numero !== '' && $rows->count() > 1)
            ->map(fn ($rows): int => (int) $rows->min('id'))
            ->all();
        $targetNumeroOwners = OrdrePaiement::query()
            ->whereNotNull('ancien_id')
            ->pluck('ancien_id', 'numero')
            ->map(fn ($id): int => (int) $id)
            ->all();
        $bar = $this->output->createProgressBar($query->count());
        $bar->start();

        $this->processInChunks($query, 500, function ($operations) use (
            &$periodesMap,
            &$targetNumeroOwners,
            $sourcesMap,
            $duplicateNumeroOwners,
            &$bar,
        ): void {
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

                $baseNumero = trim((string) ($legacyOperation->numero_operation ?: 'OP-LEGACY-'.$legacyOperation->id));
                $resolvedNumber = $this->resolveUniqueLegacyNumber(
                    $baseNumero,
                    (int) $legacyOperation->id,
                    $duplicateNumeroOwners,
                    $targetNumeroOwners,
                );
                if (isset($duplicateNumeroOwners[$baseNumero])) {
                    $this->recorder->anomaly(
                        $this->executionId,
                        'OP_NUMERO_DUPLIQUE',
                        'operations',
                        $legacyOperation->id,
                        "Numéro legacy partagé par plusieurs opérations : {$baseNumero}.",
                        [
                            'numero_legacy' => $baseNumero,
                            'source_canonique_id' => $resolvedNumber['canonical_source_id'],
                            'numero_cible' => $resolvedNumber['numero'],
                        ],
                        'NON_BLOQUANTE',
                    );
                }

                $ordre = OrdrePaiement::firstOrNew(['ancien_id' => $legacyOperation->id]);
                if (! $ordre->exists) {
                    $ordre->uuid_public = (string) Str::uuid();
                }
                $ordre->forceFill([
                    'ancien_id' => $legacyOperation->id,
                    'numero' => $resolvedNumber['numero'],
                    'periode_id' => $this->ensurePeriod($date, $periodesMap),
                    'source_financement_id' => $sourceFinancementId,
                    'montant_total' => (float) ($legacyOperation->montant_op ?? $legacyOperation->montant ?? 0),
                    'statut' => $this->mapLegacyOperationStatus($legacyOperation),
                ])->save();
                $targetNumeroOwners[$resolvedNumber['numero']] = (int) $legacyOperation->id;

                $dossierStatus = match ($ordre->statut) {
                    'VISE_AC' => 'VISE_AC',
                    'REJETE_AC', 'REJETE_CB', 'DIFFERE_AC' => 'AJOURNE_DMG',
                    'ANNULE', 'A_RECONCILIER' => 'A_RECONCILIER',
                    default => 'EN_OP',
                };
                $dossierIds = $dossiersCibles->modelKeys();
                DossierPaiement::whereIn('id', $dossierIds)->update([
                    'ordre_paiement_id' => $ordre->id,
                    'statut' => $dossierStatus,
                ]);

                $motifOperation = trim((string) ($legacyOperation->motif_status ?? ''));
                if ($motifOperation !== '' && in_array($ordre->statut, ['REJETE_AC', 'REJETE_CB', 'DIFFERE_AC'], true)) {
                    DB::table('lignes_dossiers_paiement')
                        ->whereIn('dossier_paiement_id', $dossierIds)
                        ->whereNull('retire_le')
                        ->whereNull('motif_retrait')
                        ->update(['motif_retrait' => $motifOperation]);
                }

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
        $duplicateNumeroOwners = DB::connection('legacy')->table('borderaus')
            ->select('id', 'numero_borderau', 'numero_bordereau')
            ->get()
            ->groupBy(fn ($row): string => trim((string) ($row->numero_borderau ?: $row->numero_bordereau)))
            ->filter(fn ($rows, $numero): bool => $numero !== '' && $rows->count() > 1)
            ->map(fn ($rows): int => (int) $rows->min('id'))
            ->all();
        $targetNumeroOwners = BordereauPaiement::query()
            ->whereNotNull('ancien_id')
            ->pluck('ancien_id', 'numero')
            ->map(fn ($id): int => (int) $id)
            ->all();
        $bar = $this->output->createProgressBar($query->count());
        $bar->start();

        $this->processInChunks($query, 500, function ($bordereaux) use (
            &$periodesMap,
            &$targetNumeroOwners,
            $sourcesMap,
            $duplicateNumeroOwners,
            &$bar,
        ): void {
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

                $baseNumero = trim((string) (
                    $legacyBordereau->numero_borderau
                    ?: $legacyBordereau->numero_bordereau
                    ?: 'BRD-LEGACY-'.$legacyBordereau->id
                ));
                $resolvedNumber = $this->resolveUniqueLegacyNumber(
                    $baseNumero,
                    (int) $legacyBordereau->id,
                    $duplicateNumeroOwners,
                    $targetNumeroOwners,
                );
                if (isset($duplicateNumeroOwners[$baseNumero])) {
                    $this->recorder->anomaly(
                        $this->executionId,
                        'BORDEREAU_NUMERO_DUPLIQUE',
                        'borderaus',
                        $legacyBordereau->id,
                        "Numéro legacy partagé par plusieurs bordereaux : {$baseNumero}.",
                        [
                            'numero_legacy' => $baseNumero,
                            'source_canonique_id' => $resolvedNumber['canonical_source_id'],
                            'numero_cible' => $resolvedNumber['numero'],
                        ],
                        'NON_BLOQUANTE',
                    );
                }

                $bordereau = BordereauPaiement::firstOrNew(['ancien_id' => $legacyBordereau->id]);
                if (! $bordereau->exists) {
                    $bordereau->uuid_public = (string) Str::uuid();
                }
                $bordereau->forceFill([
                    'ancien_id' => $legacyBordereau->id,
                    'numero' => $resolvedNumber['numero'],
                    'periode_id' => $this->ensurePeriod($date, $periodesMap),
                    'source_financement_id' => $sourceFinancementId,
                    'montant_total' => (float) ($legacyBordereau->montant_total ?? 0),
                    'statut' => $this->mapLegacyBordereauStatus($legacyBordereau),
                ])->save();
                $targetNumeroOwners[$resolvedNumber['numero']] = (int) $legacyBordereau->id;

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

    /**
     * @param  array<string, int>  $duplicateOwners
     * @param  array<string, int>  $targetOwners
     * @return array{numero: string, canonical_source_id: int, conflict: bool}
     */
    private function resolveUniqueLegacyNumber(
        string $baseNumber,
        int $sourceId,
        array $duplicateOwners,
        array $targetOwners,
        int $maxLength = 50,
    ): array {
        $canonicalSourceId = $duplicateOwners[$baseNumber] ?? $sourceId;
        $targetOwner = $targetOwners[$baseNumber] ?? null;
        $conflict = $canonicalSourceId !== $sourceId
            || ($targetOwner !== null && $targetOwner !== $sourceId);

        if (! $conflict) {
            return [
                'numero' => mb_substr($baseNumber, 0, $maxLength),
                'canonical_source_id' => $canonicalSourceId,
                'conflict' => false,
            ];
        }

        $suffix = '-LEGACY-'.$sourceId;

        return [
            'numero' => mb_substr($baseNumber, 0, max(1, $maxLength - mb_strlen($suffix))).$suffix,
            'canonical_source_id' => $canonicalSourceId,
            'conflict' => true,
        ];
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

    /**
     * Statut cible d'un paiement legacy.
     *
     * Côté legacy, une ligne `paiement_models` n'existe que si la DMG a généré le paiement
     * (PaiementDmgService::validerPaiement) : sa seule présence sort le dossier de la file
     * « attente de paiement ». Seul le paiement ajourné par la DMG et pas encore engagé côté
     * CB y revient — c'est exactement la clause `orWhereHas('mespaiements', …)` de
     * PaiementDmgService::attentePaiementValidation(). Retomber sur `A_TRAITER` par défaut
     * remettait ~50 000 paiements déjà générés dans la corbeille DMG du nouveau projet.
     */
    private function mapLegacyPaymentStatus(object $legacyPaiement): string
    {
        $statusAc = mb_strtolower(trim((string) ($legacyPaiement->status_ac ?? '')));
        $statusPaiement = (int) ($legacyPaiement->status ?? 0);

        return match (true) {
            $this->estPointageAjournePourCorrectionCip($legacyPaiement) => 'AJOURNE_DMG',
            $statusAc === 'validated' && $statusPaiement === 1 => 'PAYE',
            $statusAc === 'validated' && $statusPaiement === 2 => 'NON_PAYE',
            $statusAc === 'validated' => 'VALIDE_AC',
            in_array($statusAc, ['rejected', 'rejected-by-ac'], true) => 'REJETE_AC',
            $statusAc === 'processed' => 'EN_OP',
            $this->estPaiementAjourneParDmg($legacyPaiement) => 'A_TRAITER',
            default => 'EN_DOSSIER',
        };
    }

    /**
     * Le legacy affiche dans « Pointage ajourné par la DMG » les paiements liés à un
     * pointage avec `status_dmg = 0` et `status_ar != 1`. Dans le nouveau modèle cet
     * état métier est porté par le statut explicite `AJOURNE_DMG` du paiement.
     */
    private function estPointageAjournePourCorrectionCip(object $legacyPaiement): bool
    {
        return ! empty($legacyPaiement->pointage_id)
            && (int) ($legacyPaiement->status_dmg ?? 0) === 0
            && (int) ($legacyPaiement->status_ar ?? 0) !== 1;
    }

    /**
     * Paiement ajourné par la DMG et non encore pris en charge par le Contrôle Budgétaire :
     * le legacy le renvoie dans la file « attente de paiement ».
     */
    private function estPaiementAjourneParDmg(object $legacyPaiement): bool
    {
        return (int) ($legacyPaiement->status_dmg ?? 0) === 2
            && (int) ($legacyPaiement->status_cb ?? 0) === 0
            && empty($legacyPaiement->dossier_id)
            && empty($legacyPaiement->created_by_cb)
            && empty($legacyPaiement->date_vise_cb);
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

    /** @param Collection<int, TacheParcours>|null $preloadedTasks */
    private function syncOpenTask(
        InstanceParcours $instance,
        EtapeParcours $etape,
        CorbeilleEnum $corbeille,
        ?int $agenceId,
        bool $terminee = false,
        ?Collection $preloadedTasks = null
    ): void {
        $activeTasks = $preloadedTasks ? $preloadedTasks->filter(fn ($t) => in_array($t->statut, ['OUVERTE', 'REVENDIQUEE'])) : TacheParcours::query()
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
        static $rolesCache = [];
        if (empty($rolesCache)) {
            $rolesCache = Role::where('guard_name', 'web')->pluck('id', 'name')->toArray();
        }

        $roleId = $roleName === null ? null : ($rolesCache[$roleName] ?? null);

        if ($roleId === null) {
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
                "Aucun rôle cible disponible pour la corbeille {$code} (rôle attendu : {$roleName}).",
                ['instance_parcours_id' => $instance->id, 'corbeille' => $code],
            );

            return;
        }

        $currentTask = $activeTasks->first(fn (TacheParcours $task): bool => $task->code_corbeille === $code
            && (int) $task->etape_parcours_id === (int) $etape->id
            && (int) $task->role_responsable_id === (int) $roleId
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

        $created = TacheParcours::create([
            'instance_parcours_id' => $instance->id,
            'etape_parcours_id' => $etape->id,
            'role_responsable_id' => $roleId,
            'agence_id' => $agenceId,
            'code_corbeille' => $code,
            'statut' => 'OUVERTE',
            'priorite' => 0,
            'ouverte_le' => now(),
        ]);

        if ($preloadedTasks instanceof Collection) {
            $preloadedTasks->push($created);
        }
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
        /** @var array<string, int> $etapesCache */
        $etapesCache = [];

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $this->processInChunks($query, 2000, function ($historique) use (&$bar, &$etapesCache, $fallbackAuthorId): void {
            $eventRows = [];
            $eventSourcesByKey = [];
            $batchNow = now();
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
                $etapeCacheKey = $instance->definition_parcours_id.':'.$etapeCode;
                if (! isset($etapesCache[$etapeCacheKey])) {
                    $etapesCache[$etapeCacheKey] = EtapeParcours::firstOrCreate(
                        ['definition_parcours_id' => $instance->definition_parcours_id, 'code' => $etapeCode],
                        ['nom' => str_replace('_', ' ', $etapeCode), 'initiale' => false, 'finale' => false]
                    )->id;
                }

                $idempotencyKey = 'mig_'.$legacyEvent->id.'_'.$instance->id;
                $sourceData = (array) $legacyEvent;
                $eventRows[] = [
                    'uuid_public' => (string) Str::uuid(),
                    'instance_parcours_id' => $instance->id,
                    'etape_cible_id' => $etapesCache[$etapeCacheKey],
                    'auteur_id' => $auteurId,
                    'type' => 'MIGRATION_STATUT',
                    'cle_idempotence' => $idempotencyKey,
                    'donnees' => json_encode([
                        'commentaire' => $legacyEvent->commentaire,
                        'description' => "Passage à l'étape legacy ID : ".$legacyEvent->etape_id,
                        'corbeille_cible' => $corbeilleCible,
                        'contrat_etape_ancien_id' => $legacyEvent->id,
                        'paiement_ancien_id' => $legacyEvent->paiement_id,
                        'pointage_ancien_id' => $legacyEvent->pointage_id,
                    ], JSON_THROW_ON_ERROR),
                    'survenu_le' => $this->mapper->normalizeLegacyDate($legacyEvent->created_at ?? null) ?? $batchNow,
                    'created_at' => $batchNow,
                    'updated_at' => $batchNow,
                ];
                $eventSourcesByKey[$idempotencyKey] = $sourceData;
                $bar->advance();
            }

            if ($eventRows === []) {
                return;
            }

            DB::table('evenements_parcours')->insertOrIgnore($eventRows);
            $eventIds = DB::table('evenements_parcours')
                ->whereIn('cle_idempotence', array_keys($eventSourcesByKey))
                ->pluck('id', 'cle_idempotence');
            $correspondenceRows = [];
            foreach ($eventSourcesByKey as $idempotencyKey => $sourceData) {
                $eventId = $eventIds[$idempotencyKey] ?? null;
                if ($eventId === null) {
                    continue;
                }
                $correspondenceRows[] = [
                    'execution_migration_id' => $this->executionId,
                    'table_source' => 'contrat_etape',
                    'id_source' => (string) $sourceData['id'],
                    'table_cible' => 'evenements_parcours',
                    'id_cible' => (int) $eventId,
                    'empreinte_source' => $this->recorder->fingerprint($sourceData),
                    'created_at' => $batchNow,
                    'updated_at' => $batchNow,
                ];
            }
            if ($correspondenceRows !== []) {
                DB::table('correspondances_ancien_systeme')->upsert(
                    $correspondenceRows,
                    ['table_source', 'id_source', 'table_cible'],
                    ['execution_migration_id', 'id_cible', 'empreinte_source', 'updated_at'],
                );
            }
        });

        $bar->finish();
        $this->newLine();
    }

    /**
     * Reconstitue l'historique des décisions de doublons à partir des dossiers déjà tranchés
     * côté legacy, repérés par `doubloncheck != 0` (IndexDesseController pose ce drapeau au
     * moment de la décision). Sans cette étape, ces dossiers n'ont aucune ligne dans
     * desse_doublon_decisions (table qui n'existe que depuis ce projet) : l'onglet "Doublons
     * Traités" reste vide alors que legacy affiche un historique réel, et surtout le pare-feu
     * de DesseDoublonService les rebloque alors que la DESSE les a déjà libérés — ils
     * disparaissent alors des files de paiement DMG. Le champ type_doublon
     * n'existe pas côté legacy : on le déduit en comparant les clés de regroupement
     * (mêmes expressions que DesseDoublonService) du dossier aux clés actuellement en
     * doublon en base ; si aucun des critères ne matche plus, on garde quand même
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

        // Un dossier tranché poursuit son parcours : sa corbeille n'est plus forcément
        // DESSE_DOUBLONS_TRAITES (elle suit son étape courante, paiement compris).
        $ancienIdsTranches = DB::connection('legacy')->table('contrats_pae')
            ->whereNull('deleted_at')
            ->where('doubloncheck', '!=', 0)
            ->pluck('id')
            ->all();

        $stageIdsTranches = Stage::whereIn('ancien_id', $ancienIdsTranches)->pluck('id');

        $instances = InstanceParcours::query()
            ->where(fn ($q) => $q
                ->where('corbeille_actuelle', CorbeilleEnum::DESSE_DOUBLONS_TRAITES->value)
                ->orWhereIn('stage_id', $stageIdsTranches))
            ->whereNotNull('stage_id')
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

    // ═══════════════════════════════════════════════════════════════════
    // Backfills & Corrections (anciennement commands séparées)
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Étape backfill_adp_nature : corrige la nature des droits de paiement
     * (DEMARRAGE vs PRESENCE) et les corbeilles des instances de workflow.
     * Anciennement : BackfillAddAdpNatureCommand
     */
    private function backfillAddAdpNature(bool $dryRun): void
    {
        $cohorte = $this->option('cohorte');

        $this->info('=== Backfill nature ADD/ADP et corbeilles ===');
        if ($dryRun) {
            $this->warn('MODE DRY-RUN : Aucune modification ne sera appliquée.');
        }
        if ($cohorte) {
            $this->info("Filtre cohorte : date_debut en {$cohorte}");
        }
        $this->newLine();

        // ─── Étape 1 : Corriger les natures des droits de paiement ───
        $this->info('Étape 1 : Correction des natures DEMARRAGE/PRESENCE sur les droits de paiement...');

        // Cache : stage_id → [date_debut, periode.code] pour éviter N+1 sur les relations
        $stageDateDebutCache = [];
        $periodeCodeCache = [];

        $droitsQuery = DroitPaiement::query()
            ->select('id', 'stage_id', 'periode_id', 'nature')
            ->where('nature', 'PRESENCE')
            ->whereNull('annule_le');

        if ($cohorte) {
            $year = (int) substr($cohorte, 0, 4);
            $month = (int) substr($cohorte, 5, 2);
            $droitsQuery->whereHas('stage', function ($q) use ($year, $month) {
                $q->whereYear('date_debut', $year)->whereMonth('date_debut', $month);
            });
        }

        $nbCorriges = 0;
        $totalDroits = $droitsQuery->count();
        $bar = $this->output->createProgressBar($totalDroits);
        $bar->start();

        // Chunk au lieu de get() : on ne charge que 2000 droits à la fois
        $this->processInChunks($droitsQuery, 2000, function ($droits) use (
            &$nbCorriges, $dryRun, &$stageDateDebutCache, &$periodeCodeCache, $bar
        ): void {
            // Précharger les stages et périodes de ce chunk en une seule requête chacune
            $stageIds = $droits->pluck('stage_id')->unique()->values()->toArray();
            $periodeIds = $droits->pluck('periode_id')->unique()->values()->toArray();

            $missingStageIds = array_diff($stageIds, array_keys($stageDateDebutCache));
            if (! empty($missingStageIds)) {
                $stagesData = Stage::whereIn('id', $missingStageIds)->pluck('date_debut', 'id')->toArray();
                foreach ($stagesData as $sid => $dateDebut) {
                    $stageDateDebutCache[$sid] = $dateDebut;
                }
            }

            $missingPeriodeIds = array_diff($periodeIds, array_keys($periodeCodeCache));
            if (! empty($missingPeriodeIds)) {
                $periodesData = DB::table('periodes')->whereIn('id', $missingPeriodeIds)->pluck('code', 'id')->toArray();
                foreach ($periodesData as $pid => $code) {
                    $periodeCodeCache[$pid] = $code;
                }
            }

            $toUpdate = [];
            foreach ($droits as $droit) {
                $dateDebut = $stageDateDebutCache[$droit->stage_id] ?? null;
                $codePeriode = $periodeCodeCache[$droit->periode_id] ?? null;

                $natureOrigine = $this->mapper->naturePaiementPourPeriode(
                    (string) $dateDebut,
                    (string) $codePeriode
                );

                if ($natureOrigine === 'DEMARRAGE') {
                    $toUpdate[] = $droit->id;
                }
                $bar->advance();
            }

            $nbCorriges += count($toUpdate);
            if (! $dryRun && ! empty($toUpdate)) {
                DroitPaiement::whereIn('id', $toUpdate)->update([
                    'nature' => 'DEMARRAGE',
                    'motif_annulation' => null,
                    'annule_le' => null,
                ]);
            }
        }, 'id', null, 'backfill_adp_nature.droits');

        $bar->finish();
        $this->newLine();
        $this->info("  Droits de paiement à corriger (PRESENCE → DEMARRAGE) : {$nbCorriges}");

        // ─── Étape 2 : Corriger les corbeilles des instances de workflow ───
        $this->newLine();
        $this->info('Étape 2 : Correction des corbeilles des instances de workflow...');

        $query = DB::connection('legacy')->table('contrats_pae')
            ->whereNull('deleted_at')
            ->select('id as ancien_id', 'etapetraitement_id', 'etat_chef_agence', 'date_chef_agence', 'date_debut', 'agent_id', 'avis_contrat', 'file_contrat');

        if ($cohorte) {
            $year = (int) substr($cohorte, 0, 4);
            $month = (int) substr($cohorte, 5, 2);
            $query->whereYear('date_debut', $year)->whereMonth('date_debut', $month);
        }

        $total = $query->count();
        $this->info("  Stages legacy candidats : {$total}");

        $nbCorbeilleChanges = 0;
        $defCode = 'STAGE_LEGACY';
        $definition = DefinitionParcours::firstOrCreate(
            ['code' => $defCode, 'version' => 1],
            ['nom' => 'Parcours Legacy', 'active' => true]
        );
        // Cache des étapes pour éviter firstOrCreate à chaque ligne
        $etapesCache = [];

        $this->processInChunks($query, 1000, function ($contrats) use (
            &$nbCorbeilleChanges, $dryRun, $definition, &$etapesCache
        ): void {
            $ancienIds = $contrats->pluck('ancien_id')->toArray();
            $stagesMap = Stage::withTrashed()->whereIn('ancien_id', $ancienIds)->pluck('id', 'ancien_id');
            $stageIds = $stagesMap->values()->toArray();

            $instances = empty($stageIds)
                ? collect()
                : InstanceParcours::whereIn('stage_id', $stageIds)
                    ->whereNull('terminee_le')
                    ->get()
                    ->keyBy('stage_id');

            $batchUpdates = [];
            foreach ($contrats as $legacyContrat) {
                $stageId = $stagesMap[$legacyContrat->ancien_id] ?? null;
                if (! $stageId) {
                    continue;
                }

                $instance = $instances->get($stageId);
                if (! $instance) {
                    continue;
                }

                $corbeilleEnum = $this->mapper->mapChefAgenceCorbeille($legacyContrat);

                if ($instance->corbeille_actuelle === $corbeilleEnum->value) {
                    continue;
                }

                $nbCorbeilleChanges++;

                if (! $dryRun) {
                    $etapeCode = strtoupper($corbeilleEnum->value);
                    if (! isset($etapesCache[$etapeCode])) {
                        $etapesCache[$etapeCode] = EtapeParcours::firstOrCreate(
                            ['definition_parcours_id' => $definition->id, 'code' => $etapeCode],
                            ['nom' => str_replace('_', ' ', $etapeCode), 'initiale' => false, 'finale' => false]
                        );
                    }
                    $etape = $etapesCache[$etapeCode];

                    $batchUpdates[] = [
                        'id' => $instance->id,
                        'corbeille_actuelle' => $corbeilleEnum->value,
                        'etape_courante_id' => $etape->id,
                        'updated_at' => now(),
                    ];
                }
            }

            if (! $dryRun && ! empty($batchUpdates)) {
                // Batch update via DB::table pour éviter N requêtes UPDATE
                $instanceIds = array_column($batchUpdates, 'id');
                foreach ($batchUpdates as $update) {
                    InstanceParcours::where('id', $update['id'])->update([
                        'corbeille_actuelle' => $update['corbeille_actuelle'],
                        'etape_courante_id' => $update['etape_courante_id'],
                    ]);
                }
            }
        }, 'id', 'ancien_id', 'backfill_adp_nature.corbeilles');

        $this->info("  Instances de workflow reclassées : {$nbCorbeilleChanges}");

        $this->newLine();
        $this->info('=== Résumé backfill_adp_nature ===');
        $this->info("  Droits paiement corrigés (PRESENCE → DEMARRAGE) : {$nbCorriges}");
        $this->info("  Instances workflow reclassées : {$nbCorbeilleChanges}");

        if ($dryRun) {
            $this->newLine();
            $this->warn('Aucune modification appliquée (dry-run).');
        }
    }

    /**
     * Étape backfill_corbeilles_ca : resynchronise corbeille_actuelle pour les dossiers
     * posés dans une corbeille Chef d'Agence (attente démarrage, démarrage omis, retour
     * d'ajournement) avec la règle courante de LegacyMapperService::mapChefAgenceCorbeille().
     * À rejouer après toute évolution de cette règle, sans relancer la phase `stages`.
     * Anciennement : BackfillChefAgenceCorbeillesCommand
     */
    private function backfillChefAgenceCorbeilles(bool $dryRun): void
    {
        try {
            DB::connection('legacy')->getPdo();
        } catch (Throwable $e) {
            $this->error("Impossible de se connecter à la base 'legacy' : {$e->getMessage()}");

            return;
        }

        $definition = DefinitionParcours::where('code', 'STAGE_LEGACY')->where('version', 1)->first();
        if (! $definition) {
            $this->error("Definition de parcours 'STAGE_LEGACY' introuvable : la migration initiale a-t-elle été jouée ?");

            return;
        }

        // On balaie tous les contrats vivants, pas seulement ceux en attente du CA :
        // un dossier déjà validé (etat_chef_agence=2) peut lui aussi stagner à tort
        // dans une corbeille CA. Le périmètre réel est borné côté cible par
        // $corbeillesConcernees, qui ne retient que les instances à resynchroniser.
        $query = DB::connection('legacy')->table('contrats_pae')->whereNull('deleted_at');

        $total = $query->count();
        $this->info("Contrats legacy vivants à confronter aux corbeilles CA : {$total}");

        $inspected = 0;
        $changed = 0;
        $transitions = [];
        // Cache des étapes pour éviter un SELECT/INSERT par ligne
        $etapesCache = [];

        $corbeillesConcernees = [
            CorbeilleEnum::CA_ATTENTE_VALIDATION_DEMARRAGE->value,
            CorbeilleEnum::CA_ATTENTE_VALIDATION_OMIS->value,
            CorbeilleEnum::CA_RETOUR_AJOURNEMENT->value,
        ];

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $this->processInChunks($query, 500, function ($contrats) use (
            $definition, $dryRun, &$inspected, &$changed, &$transitions, &$etapesCache, $corbeillesConcernees, $bar
        ) {
            $ancienIds = $contrats->pluck('id')->toArray();

            $stagesMap = Stage::withTrashed()->whereIn('ancien_id', $ancienIds)->pluck('id', 'ancien_id');
            $stageIds = $stagesMap->values()->toArray();

            $instancesMap = empty($stageIds)
                ? collect()
                : InstanceParcours::whereIn('stage_id', $stageIds)
                    ->whereIn('corbeille_actuelle', $corbeillesConcernees)
                    ->whereNull('terminee_le')
                    ->get()
                    ->keyBy('stage_id');

            $batchUpdates = [];
            foreach ($contrats as $legacyContrat) {
                $stageId = $stagesMap[$legacyContrat->id] ?? null;
                if (! $stageId) {
                    $bar->advance();

                    continue;
                }

                $instance = $instancesMap->get($stageId);
                if (! $instance) {
                    $bar->advance();

                    continue;
                }

                $inspected++;

                // mapChefAgenceCorbeille() retombe déjà sur mapStatutStageToCorbeille()
                // quand le contexte CA ne tranche pas : la re-dériver ici écraserait
                // les cas que le mapper renvoie volontairement au CIP.
                $nouvelleCorbeille = $this->mapper->mapChefAgenceCorbeille($legacyContrat);

                if ($nouvelleCorbeille->value === $instance->corbeille_actuelle) {
                    $bar->advance();

                    continue;
                }

                $transitionKey = "{$instance->corbeille_actuelle} => {$nouvelleCorbeille->value}";
                $transitions[$transitionKey] = ($transitions[$transitionKey] ?? 0) + 1;

                if (! $dryRun) {
                    $etapeCode = strtoupper($nouvelleCorbeille->value);
                    if (! isset($etapesCache[$etapeCode])) {
                        $etapesCache[$etapeCode] = EtapeParcours::firstOrCreate(
                            ['definition_parcours_id' => $definition->id, 'code' => $etapeCode],
                            ['nom' => str_replace('_', ' ', $etapeCode), 'initiale' => false, 'finale' => false]
                        );
                    }
                    $etape = $etapesCache[$etapeCode];

                    $batchUpdates[] = [
                        'id' => $instance->id,
                        'corbeille_actuelle' => $nouvelleCorbeille->value,
                        'etape_courante_id' => $etape->id,
                    ];
                }

                $changed++;
                $bar->advance();
            }

            // Batch update des instances de workflow
            if (! $dryRun && ! empty($batchUpdates)) {
                foreach ($batchUpdates as $update) {
                    InstanceParcours::where('id', $update['id'])->update([
                        'corbeille_actuelle' => $update['corbeille_actuelle'],
                        'etape_courante_id' => $update['etape_courante_id'],
                    ]);
                }
            }
        });

        $bar->finish();
        $this->newLine(2);

        $this->info("Dossiers actuellement dans une corbeille Chef d'Agence : {$inspected}");
        $this->info(($dryRun ? '[DRY-RUN] ' : '')."Dossiers reclassés : {$changed}");

        if (! empty($transitions)) {
            $rows = collect($transitions)->map(fn ($n, $t) => [$t, $n])->values()->toArray();
            $this->table(['Transition', 'Nombre'], $rows);
        }
    }

    /**
     * Étape backfill_retour_chefagence : corrige le placement des dossiers legacy du circuit
     * doublon « retour agence » (vue legacy « Stagiaires doublon retourné / Agence »,
     * IndexDesseController@retourChefagence) qui avaient été migrés dans des corbeilles
     * inadaptées, puis reste aligné sur le mapping corrigé (LegacyMapperService) :
     *
     *  - étape 7 (doublon avéré traité par le Chef d'Agence, en attente de la validation
     *    finale de la DESSE) → CIP_AJOURNE_DESSE. Ils étaient dans CIP_MES_STAGIAIRES,
     *    corbeille invisible pour la DESSE : l'onglet « Retour Chef d'Agence » de
     *    Desse/Stagiaires/Index (DESSE_RETOUR_AGENCE + CIP_AJOURNE_DESSE) restait vide.
     *    Ils réapparaissent dans l'onglet DESSE tout en restant visibles côté CIP
     *    (Mes Stagiaires / Suivi « Doublon DESSE ») pour finir la correction du doublon.
     *  - étape 8 (doublon déjà validé par la DESSE après retour du CA — état « clos »,
     *    jamais produit en pratique côté legacy, 0 événement dans contrat_etape) →
     *    DAICG_VALIDES_DESSE, la corbeille « Validé par la DESSE » où aboutit l'action
     *    « Renvoyer / Valider » de l'onglet : l'ancienne cible DESSE_SUIVI_PROCESSUS n'a
     *    ni lecteur UI ni transition de sortie et perdrait les dossiers.
     */
    private function backfillRetourChefAgence(bool $dryRun): void
    {
        try {
            DB::connection('legacy')->getPdo();
        } catch (Throwable $e) {
            $this->error("Impossible de se connecter à la base 'legacy' : {$e->getMessage()}");

            return;
        }

        $definition = DefinitionParcours::where('code', 'STAGE_LEGACY')->where('version', 1)->first();
        if (! $definition) {
            $this->error("Definition de parcours 'STAGE_LEGACY' introuvable : la migration initiale a-t-elle été jouée ?");

            return;
        }

        // Cible par étape legacy et corbeilles sources acceptées : on ne rejoue la
        // correction que sur les dossiers encore dans l'état où l'ancien mapping (ou une
        // migration antérieure) les avait laissés — jamais sur un dossier qui aurait déjà
        // poursuivi son circuit depuis.
        $cibles = [
            7 => [
                'corbeille' => CorbeilleEnum::CIP_AJOURNE_DESSE,
                'sources' => [CorbeilleEnum::CIP_MES_STAGIAIRES->value],
                'nom_etape' => 'CIP : Ajourné par la DESSE',
            ],
            8 => [
                'corbeille' => CorbeilleEnum::DAICG_VALIDES_DESSE,
                'sources' => [
                    CorbeilleEnum::DESSE_SUIVI_PROCESSUS->value,
                    CorbeilleEnum::CIP_MES_STAGIAIRES->value,
                ],
                'nom_etape' => 'DAICG : Validés par la DESSE',
            ],
        ];

        foreach ($cibles as $etapeLegacy => $config) {
            $cible = $config['corbeille']->value;

            $query = DB::connection('legacy')->table('contrats_pae')
                ->whereNull('deleted_at')
                ->where('etapetraitement_id', $etapeLegacy);

            $total = $query->count();
            $this->info("Contrats legacy en étape {$etapeLegacy} : {$total}");

            $moved = 0;
            $dejaPlace = 0;
            $ignores = 0;
            $etapeCache = null;

            $this->processInChunks($query, 500, function ($contrats) use (
                $definition, $dryRun, $config, $cible, &$moved, &$dejaPlace, &$ignores, &$etapeCache
            ) {
                $ancienIds = $contrats->pluck('id')->toArray();

                $stagesMap = Stage::whereIn('ancien_id', $ancienIds)->pluck('id', 'ancien_id');
                $stageIds = $stagesMap->values()->toArray();

                $instances = empty($stageIds)
                    ? collect()
                    : InstanceParcours::whereIn('stage_id', $stageIds)
                        ->where('definition_parcours_id', $definition->id)
                        ->whereNull('terminee_le')
                        ->get();

                $batchUpdates = [];
                foreach ($contrats as $legacyContrat) {
                    $stageId = $stagesMap[$legacyContrat->id] ?? null;
                    if (! $stageId) {
                        $ignores++;

                        continue;
                    }

                    $instance = $instances->firstWhere('stage_id', $stageId);
                    if (! $instance) {
                        $ignores++;

                        continue;
                    }

                    if ($instance->corbeille_actuelle === $cible) {
                        $dejaPlace++;

                        continue;
                    }

                    if (! in_array($instance->corbeille_actuelle, $config['sources'], true)) {
                        $this->line("  Dossier #{$legacyContrat->id} ignoré : déjà dans la corbeille « {$instance->corbeille_actuelle} ».");
                        $ignores++;

                        continue;
                    }

                    if (! $dryRun) {
                        if ($etapeCache === null) {
                            $etapeCache = EtapeParcours::firstOrCreate(
                                ['definition_parcours_id' => $definition->id, 'code' => strtoupper($cible)],
                                ['nom' => $config['nom_etape'], 'initiale' => false, 'finale' => false]
                            );
                        }

                        $batchUpdates[] = [
                            'id' => $instance->id,
                            'corbeille_actuelle' => $cible,
                            'etape_courante_id' => $etapeCache->id,
                        ];
                    }

                    $moved++;
                }

                if (! $dryRun && ! empty($batchUpdates)) {
                    foreach ($batchUpdates as $update) {
                        InstanceParcours::where('id', $update['id'])->update([
                            'corbeille_actuelle' => $update['corbeille_actuelle'],
                            'etape_courante_id' => $update['etape_courante_id'],
                        ]);
                    }
                }
            });

            $this->info(($dryRun ? '[DRY-RUN] ' : '')."Dossiers reclassés vers {$cible} : {$moved}");
            $this->info("Dossiers déjà dans {$cible} : {$dejaPlace}, ignorés (non migrés / engagés ailleurs) : {$ignores}");
        }
    }

    /**
     * Étape backfill_presence_payments : génère les DroitPaiement et Paiement manquants
     * pour les pointages bloqués dans dmg_attente_paiement_presence.
     * Anciennement : BackfillPresencePaymentsCommand
     */
    /**
     * Étape backfill_paiements_dmg : les droits de paiement sont migrés depuis
     * `paiement_models`, qui ne contient QUE les paiements déjà émis. Un pointage validé
     * par le CA mais pas encore payé n'y figure donc pas et arriverait sans droit dans la
     * corbeille DMG, qui se lit via `droits_paiement`/`paiements`. On matérialise le droit
     * en attente pour les deux natures — sans quoi la corbeille « démarrage » reste vide
     * face aux 422 dossiers du legacy.
     */
    private function backfillPaiementsDmg(): void
    {
        foreach ([
            CorbeilleEnum::DMG_ATTENTE_PAIEMENT_DEMARRAGE->value => 'DEMARRAGE',
            CorbeilleEnum::DMG_ATTENTE_PAIEMENT_PRESENCE->value => 'PRESENCE',
        ] as $corbeille => $nature) {
            $this->backfillPaiementsDmgPourNature($corbeille, $nature);
        }
    }

    private function backfillPaiementsDmgPourNature(string $corbeille, string $nature): void
    {
        $this->info("Recherche des pointages {$nature} sans droit de paiement...");

        $pointageIds = DB::table('pointages')
            ->join('instances_parcours', 'instances_parcours.pointage_id', '=', 'pointages.id')
            ->where('instances_parcours.corbeille_actuelle', $corbeille)
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('droits_paiement')
                    ->whereColumn('droits_paiement.pointage_id', 'pointages.id');
            })
            ->pluck('pointages.id')
            ->toArray();

        $this->info('Pointages '.$nature.' sans droit de paiement : '.count($pointageIds));
        if (count($pointageIds) === 0) {
            return;
        }

        $bar = $this->output->createProgressBar(count($pointageIds));
        $bar->start();

        $fixedCount = 0;

        // Cache : ancien_id → date_ca (préchargé par chunk, pas par ligne)
        $legacyDateCache = [];

        foreach (array_chunk($pointageIds, 500) as $chunk) {
            $pointages = Pointage::whereIn('id', $chunk)
                ->with(['stage.contrats'])
                ->get();

            // Batch-load les dates legacy pour tout le chunk en une seule requête
            $ancienIds = $pointages->pluck('ancien_id')->filter()->values()->toArray();
            if (! empty($ancienIds)) {
                $legacyDates = DB::connection('legacy')->table('pointage_models')
                    ->whereIn('id', $ancienIds)
                    ->pluck('date_ca', 'id')
                    ->toArray();
                $legacyDateCache += $legacyDates;
            }

            $stageIds = $pointages->pluck('stage_id')->unique()->toArray();
            $droitsExistants = DroitPaiement::whereIn('stage_id', $stageIds)
                ->where('nature', $nature)
                ->whereNull('annule_le')
                ->get();
            $droitsActifsMap = [];
            foreach ($droitsExistants as $d) {
                $droitsActifsMap["{$d->stage_id}_{$d->periode_id}"] = $d;
            }
            $droitIds = $droitsExistants->pluck('id')->toArray();
            $paiementsExistants = empty($droitIds) ? collect() : Paiement::whereIn('droit_paiement_id', $droitIds)->get()->keyBy('droit_paiement_id');

            foreach ($pointages as $pointage) {
                try {
                    $stage = $pointage->stage;
                    if (! $stage) {
                        $bar->advance();

                        continue;
                    }

                    // Utiliser le contrat eager-loadé au lieu de refaire une requête
                    $contratActif = $stage->contrats->first();
                    $montantPaiement = $contratActif ? $contratActif->prime_mensuelle : 45000;

                    $legacyDate = $legacyDateCache[$pointage->ancien_id] ?? null;
                    $createdAt = $legacyDate && $legacyDate !== '0000-00-00 00:00:00' ? $legacyDate : now();

                    $droitPaiement = $droitsActifsMap["{$stage->id}_{$pointage->periode_id}"] ?? null;

                    if ($droitPaiement) {
                        if (is_null($droitPaiement->pointage_id)) {
                            $droitPaiement->update(['pointage_id' => $pointage->id]);
                        }

                        $paiement = $paiementsExistants[$droitPaiement->id] ?? new Paiement(['droit_paiement_id' => $droitPaiement->id]);
                        if (! $paiement->exists) {
                            $paiement->fill([
                                'statut' => 'A_TRAITER',
                                'montant' => $montantPaiement,
                                'created_at' => $createdAt,
                                'updated_at' => $createdAt,
                            ]);
                            $paiement->save();
                            $paiementsExistants[$droitPaiement->id] = $paiement;
                        }
                    } else {
                        $droitPaiement = new DroitPaiement;
                        $droitPaiement->fill([
                            'stage_id' => $stage->id,
                            'pointage_id' => $pointage->id,
                            'periode_id' => $pointage->periode_id,
                            'source_financement_id' => $stage->source_financement_id ?? 1,
                            'nature' => $nature,
                            'montant' => $montantPaiement,
                            'statut' => 'OUVERT',
                            'created_at' => $createdAt,
                            'updated_at' => $createdAt,
                        ]);
                        $droitPaiement->save();
                        $droitsActifsMap["{$stage->id}_{$pointage->periode_id}"] = $droitPaiement;

                        $paiement = new Paiement([
                            'droit_paiement_id' => $droitPaiement->id,
                            'statut' => 'A_TRAITER',
                            'montant' => $montantPaiement,
                            'created_at' => $createdAt,
                            'updated_at' => $createdAt,
                        ]);
                        $paiement->save();
                    }

                    $fixedCount++;
                } catch (\Exception $e) {
                    $this->error("Failed to generate payment for pointage {$pointage->id}: ".$e->getMessage());
                }
                $bar->advance();
            }
        }

        $bar->finish();

        $this->info("\nDroits de paiement {$nature} générés : {$fixedCount}");
    }

    /**
     * Étape backfill_corbeilles_dmg : aligne la corbeille du stage sur celle de son pointage
     * en attente de paiement.
     *
     * Le legacy construit sa file DMG à partir du pointage (PaiementDmgService::attentePaiementValidation()
     * ne regarde jamais `etapetraitement_id`), alors que DmgService::attentePaiement() la lit sur
     * l'instance de parcours du STAGE. Un dossier validé par le CA mais resté à l'étape 2 garde donc
     * une instance stage en `en_stage` et disparaît de la corbeille DMG : 419 des 449 dossiers
     * « démarrage » attendus pour un mois donné étaient dans ce cas.
     *
     * On ne promeut que les instances en `en_stage` : cette corbeille ne porte aucune décision
     * métier concurrente, contrairement aux corbeilles CA/CB/DESSE qu'il ne faut pas écraser.
     * L'index unique `parcours_une_tache_ouverte` impose une seule corbeille ouverte par instance :
     * quand un stage a plusieurs pointages impayés, la période la plus récente l'emporte.
     */
    /**
     * Étape backfill_situation_pointage : rattache à chaque pointage déjà migré la situation du
     * stage à ce moment-là (réactivation, fin de stage...), portée par `pointage_models.situationstage_id`
     * et absente de la première migration de `pointages`.
     *
     * Sans elle, DmgService::attentePaiement() ne peut pas reproduire l'exigence legacy
     * `situationstage_id = 1` : des pointages en réactivation ou fin de stage, pourtant validés
     * par le CIP et le Chef d'Agence, apparaissaient dans la file DMG alors que l'ancien Gestage
     * ne les affiche pas ce mois-là (PaiementDmgService::attentePaiementValidation()).
     */
    private function backfillSituationPointage(bool $dryRun): void
    {
        $situationsStageMap = DB::table('situations_stage')->pluck('id', 'ancien_id')->toArray();

        $aTraiter = DB::table('pointages')->whereNotNull('ancien_id')->whereNull('situation_stage_id')->count();

        if ($dryRun) {
            $this->info("[DRY-RUN] Pointages à rattacher à leur situation de stage : {$aTraiter}");

            return;
        }

        $miseAJour = 0;

        DB::table('pointages')
            ->whereNotNull('ancien_id')
            ->whereNull('situation_stage_id')
            ->orderBy('id')
            ->select('id', 'ancien_id')
            ->chunkById(1000, function ($pointages) use ($situationsStageMap, &$miseAJour): void {
                $anciensIds = $pointages->pluck('ancien_id')->all();

                $situationsLegacy = DB::connection('legacy')->table('pointage_models')
                    ->whereIn('id', $anciensIds)
                    ->pluck('situationstage_id', 'id');

                foreach ($pointages as $pointage) {
                    $situationLegacyId = $situationsLegacy[$pointage->ancien_id] ?? null;
                    $situationStageId = $situationLegacyId !== null ? ($situationsStageMap[(int) $situationLegacyId] ?? null) : null;

                    if ($situationStageId === null) {
                        continue;
                    }

                    DB::table('pointages')->where('id', $pointage->id)->update([
                        'situation_stage_id' => $situationStageId,
                        'updated_at' => now(),
                    ]);
                    $miseAJour++;
                }
            });

        $this->info("Pointages rattachés à leur situation de stage : {$miseAJour}");
    }

    /**
     * Étape fix_pointage_revisions : recale `pointages.ancien_id` sur la révision legacy la plus
     * récente pour chaque (stagiaire, mois).
     *
     * Le legacy ne met jamais une ligne `pointage_models` à jour : chaque resoumission (ex. après
     * ajournement CA/CB) crée une nouvelle ligne (nouvel id) et soft-delete l'ancienne. Avant la
     * correction apportée à migratePointages(), une resoumission arrivant après le premier passage
     * de migration (donc dans un chunk déjà traité, ou lors d'un run antérieur au correctif)
     * laissait `ancien_id` bloqué sur la toute première révision vue — parfois déjà soft-deleted
     * côté legacy — alors que `statut`/`situation_stage_id`/`deleted_at` restaient corrects. Cette
     * étape retrouve, pour chaque pointage déjà migré, la ligne legacy de plus grand id partageant
     * son (stagiaire, mois) et réaligne `ancien_id`, la corbeille et la tâche ouverte dessus.
     *
     * Rejouable : un pointage déjà aligné sur la dernière révision n'est pas compté.
     */
    private function fixPointageRevisions(bool $dryRun): int
    {
        $definition = DefinitionParcours::firstOrCreate(
            ['code' => 'POINTAGE_LEGACY', 'version' => 1],
            ['nom' => 'Parcours Pointage Legacy', 'active' => true]
        );
        $etapesMap = [];
        $situationsStageMap = DB::table('situations_stage')->pluck('id', 'ancien_id')->toArray();

        $fixed = 0;

        Pointage::withTrashed()
            ->whereNotNull('ancien_id')
            ->with(['stage', 'periode'])
            ->orderBy('id')
            ->chunk(500, function ($pointages) use ($situationsStageMap, $definition, &$etapesMap, $dryRun, &$fixed): void {
                $stageAnciensIds = $pointages->pluck('stage.ancien_id')->filter()->unique()->values()->all();

                if ($stageAnciensIds === []) {
                    return;
                }

                $legacyParStagiaireEtMois = DB::connection('legacy')->table('pointage_models')
                    ->whereIn('stagiaire_id', $stageAnciensIds)
                    ->select(
                        'id', 'stagiaire_id', 'etape_id', 'situationstage_id', 'deleted_at',
                        'mois', 'date_pointage', 'created_at', 'status_cip', 'status_ca', 'status_dmg'
                    )
                    ->get()
                    ->map(function ($ligne) {
                        // Même résolution que migratePointages() : `mois` fait foi, sinon repli
                        // sur les dates de traitement, pour rester dans le même groupe (stagiaire,
                        // mois) que la migration initiale. `statut` n'est pas une colonne : il est
                        // dérivé des status_cip/status_ca/status_dmg, comme mapStatutPointage().
                        $ligne->periode_resolue = $this->mapper->resolveLegacyPeriodDate($ligne);
                        $ligne->statut = $this->mapper->mapStatutPointage($ligne);

                        return $ligne;
                    })
                    ->filter(fn ($ligne) => $ligne->periode_resolue !== null)
                    ->groupBy(fn ($ligne) => $ligne->stagiaire_id.'|'.$ligne->periode_resolue->format('Y-m'));

                $etatsChefAgence = DB::connection('legacy')->table('contrats_pae')
                    ->whereIn('id', $stageAnciensIds)
                    ->pluck('etat_chef_agence', 'id');
                $paiementsRenvoyesAuDmg = $this->paiementsRenvoyesAuDmg($stageAnciensIds);

                $pointageIds = $pointages->pluck('id')->all();
                $instancesExistantes = InstanceParcours::whereIn('pointage_id', $pointageIds)->get()->keyBy('pointage_id');
                $instanceIds = $instancesExistantes->pluck('id')->filter()->all();
                $tachesExistantes = $instanceIds === [] ? collect() : TacheParcours::whereIn('instance_parcours_id', $instanceIds)
                    ->whereIn('statut', ['OUVERTE', 'REVENDIQUEE'])
                    ->get()
                    ->groupBy('instance_parcours_id');

                foreach ($pointages as $pointage) {
                    $stagiaireId = $pointage->stage->ancien_id ?? null;
                    $moisCode = $pointage->periode->code ?? null;

                    if ($stagiaireId === null || $moisCode === null) {
                        continue;
                    }

                    $candidats = $legacyParStagiaireEtMois->get($stagiaireId.'|'.$moisCode);

                    if ($candidats === null || $candidats->isEmpty()) {
                        continue;
                    }

                    $dernier = $candidats->sortByDesc('id')->first();

                    if ((int) $dernier->id === (int) $pointage->ancien_id) {
                        continue;
                    }

                    // `ancien_id` est unique : si la révision la plus récente est déjà portée par
                    // un autre pointage, ce n'est pas une simple obsolescence mais un doublon de
                    // pointage pour le même (stage, période, nature) — cf. migratePointages(), qui
                    // n'indexe pas les pointages déjà soft-deleted dans sa carte d'idempotence et
                    // peut donc en recréer un second sur une exécution ultérieure. Un tel doublon
                    // se règle par une fusion dédiée, pas par cette étape ; on le signale et on
                    // passe au suivant plutôt que de laisser la contrainte SQL interrompre le lot.
                    $dejaPris = Pointage::withTrashed()
                        ->where('ancien_id', $dernier->id)
                        ->where('id', '!=', $pointage->id)
                        ->first();

                    if ($dejaPris !== null) {
                        if (! $dryRun) {
                            $this->recorder->anomaly(
                                $this->executionId,
                                'POINTAGE_DOUBLON_REVISION',
                                'pointage_models',
                                $dernier->id,
                                "Révision la plus récente déjà rattachée au pointage #{$dejaPris->id} ; pointage #{$pointage->id} (même stage/période/nature) est un doublon probable à fusionner.",
                                ['pointage_id' => $pointage->id, 'pointage_conflit_id' => $dejaPris->id, 'ancien_id_dernier' => $dernier->id],
                            );
                        }

                        continue;
                    }

                    $situationStageId = $dernier->situationstage_id !== null
                        ? ($situationsStageMap[(int) $dernier->situationstage_id] ?? null)
                        : null;
                    $deletedAt = $this->mapper->normalizeLegacyDate($dernier->deleted_at);

                    // Second cas de doublon : `pointage_unique_periode_nature` n'est unique que
                    // parmi les pointages non supprimés. Si la révision la plus récente est active
                    // côté legacy, cette mise à jour ressusciterait #{$pointage->id} — mais un autre
                    // pointage non supprimé porte peut-être déjà ce (stage, période, nature), auquel
                    // cas #{$pointage->id} lui-même est le doublon obsolète à ne pas ressusciter.
                    if ($deletedAt === null) {
                        $doublonActif = Pointage::where('stage_id', $pointage->stage_id)
                            ->where('periode_id', $pointage->periode_id)
                            ->where('nature', $pointage->nature)
                            ->where('id', '!=', $pointage->id)
                            ->whereNull('deleted_at')
                            ->first();

                        if ($doublonActif !== null) {
                            if (! $dryRun) {
                                $this->recorder->anomaly(
                                    $this->executionId,
                                    'POINTAGE_DOUBLON_REVISION',
                                    'pointage_models',
                                    $dernier->id,
                                    "Pointage #{$pointage->id} redeviendrait actif (stage/période/nature) déjà porté par le pointage actif #{$doublonActif->id} ; doublon probable à fusionner.",
                                    ['pointage_id' => $pointage->id, 'pointage_actif_id' => $doublonActif->id, 'ancien_id_dernier' => $dernier->id],
                                );
                            }

                            continue;
                        }
                    }

                    $renvoye = isset($paiementsRenvoyesAuDmg[$stagiaireId.'|'.$moisCode]);

                    $corbeilleEnum = $this->mapper->mapPointageToCorbeille(
                        $dernier->etape_id !== null ? (int) $dernier->etape_id : null,
                        $dernier->statut,
                        $pointage->nature,
                        isset($etatsChefAgence[$stagiaireId]) ? (int) $etatsChefAgence[$stagiaireId] : null,
                        $renvoye
                    )->value;

                    // Même exclusion que migratePointages() : cf. POINTAGE_STAGIAIRE_SORTI_HORS_CORBEILLE_CA.
                    $stagiaireSortiHorsCorbeilleCa = $corbeilleEnum === CorbeilleEnum::CA_VALIDATION_POINTAGES->value
                        && in_array((int) ($dernier->situationstage_id ?? 0), [2, 3, 6], true);

                    $fixed++;

                    if ($dryRun) {
                        continue;
                    }

                    $pointage->update([
                        'ancien_id' => $dernier->id,
                        'statut' => $dernier->statut,
                        'situation_stage_id' => $situationStageId,
                        'deleted_at' => $deletedAt,
                    ]);

                    $etapeCode = strtoupper($corbeilleEnum);
                    if (! isset($etapesMap[$etapeCode])) {
                        $etapesMap[$etapeCode] = EtapeParcours::firstOrCreate(
                            ['definition_parcours_id' => $definition->id, 'code' => $etapeCode],
                            ['nom' => str_replace('_', ' ', $etapeCode), 'initiale' => false, 'finale' => false]
                        );
                    }
                    $etape = $etapesMap[$etapeCode];

                    $instance = $instancesExistantes[$pointage->id] ?? new InstanceParcours(['pointage_id' => $pointage->id]);
                    $instance->fill([
                        'definition_parcours_id' => $definition->id,
                        'etape_courante_id' => $etape->id,
                        'corbeille_actuelle' => $corbeilleEnum,
                    ]);
                    $instance->save();
                    $instancesExistantes[$pointage->id] = $instance;

                    $this->syncOpenTask(
                        $instance,
                        $etape,
                        CorbeilleEnum::from($corbeilleEnum),
                        $pointage->stage->agence_id ?? null,
                        $stagiaireSortiHorsCorbeilleCa,
                        $tachesExistantes[$instance->id] ?? collect()
                    );

                    if ($stagiaireSortiHorsCorbeilleCa) {
                        $this->recorder->anomaly(
                            $this->executionId,
                            'POINTAGE_STAGIAIRE_SORTI_HORS_CORBEILLE_CA',
                            'pointage_models',
                            $dernier->id,
                            'Pointage exclu de la corbeille de validation CA : stagiaire abandon/suspension/désistement (situationstage_id='.$dernier->situationstage_id.').',
                            (array) $dernier,
                            'NON_BLOQUANTE',
                        );
                    }
                }
            });

        $this->info(($dryRun ? '[DRY-RUN] ' : '')."Pointages recalés sur leur dernière révision legacy : {$fixed}");

        return $fixed;
    }

    /**
     * Étape backfill_droits_pointage : rattache au pointage du mois les droits de paiement qui
     * n'en portent aucun.
     *
     * L'ancien Gestage raccroche le paiement au pointage (`paiement_models.stagiaire_id` + `mois`,
     * cf. PaiementDmgService::validerPaiement). Sans ce lien, la file DMG ne peut pas lire la
     * corbeille du pointage et se rabat sur celle du stage, qui ignore le détail du mois : des
     * dossiers que l'ancien Gestage affiche disparaissent alors de la file.
     *
     * Le triplet (stage, période, nature) est unique côté pointages, le rattachement est donc
     * sans ambiguïté ; l'étape ne touche que les droits encore orphelins, elle est rejouable.
     */
    private function backfillDroitsPointage(bool $dryRun): void
    {
        $aRattacher = DB::table('droits_paiement as d')
            ->join('pointages as p', function ($jointure): void {
                $jointure->on('p.stage_id', '=', 'd.stage_id')
                    ->on('p.periode_id', '=', 'd.periode_id')
                    ->on('p.nature', '=', 'd.nature');
            })
            ->whereNull('d.pointage_id')
            ->whereNull('p.deleted_at')
            ->count();

        if ($dryRun) {
            $this->info("[DRY-RUN] Droits de paiement à rattacher à leur pointage : {$aRattacher}");

            return;
        }

        DB::statement(<<<'SQL'
            UPDATE droits_paiement AS d
            SET pointage_id = p.id, updated_at = NOW()
            FROM pointages AS p
            WHERE p.stage_id = d.stage_id
              AND p.periode_id = d.periode_id
              AND p.nature = d.nature
              AND p.deleted_at IS NULL
              AND d.pointage_id IS NULL
        SQL);

        $this->info("Droits de paiement rattachés à leur pointage : {$aRattacher}");
    }

    /**
     * Paiements que l'ancien Gestage laisse dans la file DMG bien qu'ils existent : le DMG les a
     * ajournés (`status_dmg=2`) et le CB ne les a jamais visés. C'est l'exception au filtre
     * d'absence de paiement de PaiementDmgService::attentePaiementValidation() — qui exige aussi,
     * en amont, un pointage du mois recevable (`mespointagesConditions` :
     * situationstage_id=1, status_cip=1, status_ca=1, date_ca renseignée) : sans ce second
     * filtre, un paiement ajourné rattaché à un pointage non recevable (abandon, réactivation,
     * pas encore validé par le CIP/CA) se retrouverait promu à tort côté cible.
     *
     * @param  array<int, int>  $stagiaireIds  `contrats_pae.id` ; tous si vide
     * @return array<string, true> indexé par « stagiaire_id|Y-m »
     */
    private function paiementsRenvoyesAuDmg(array $stagiaireIds = []): array
    {
        $requete = DB::connection('legacy')->table('paiement_models')
            ->whereNull('deleted_at')
            ->where('status_dmg', 2)
            ->where('status_cb', 0)
            ->whereNull('dossier_id')
            ->whereNull('created_by_cb')
            ->whereNull('date_vise_cb')
            ->whereExists(function ($pointage): void {
                $pointage->selectRaw('1')
                    ->from('pointage_models')
                    ->whereColumn('pointage_models.stagiaire_id', 'paiement_models.stagiaire_id')
                    ->whereColumn('pointage_models.mois', 'paiement_models.mois')
                    ->where('pointage_models.situationstage_id', 1)
                    ->where('pointage_models.status_cip', 1)
                    ->where('pointage_models.status_ca', 1)
                    ->whereNotNull('pointage_models.date_ca')
                    ->whereNull('pointage_models.deleted_at');
            })
            ->select('stagiaire_id', 'mois');

        if ($stagiaireIds !== []) {
            $requete->whereIn('stagiaire_id', $stagiaireIds);
        }

        $cles = [];
        foreach ($requete->cursor() as $paiement) {
            $mois = $this->mapper->normalizeLegacyDate($paiement->mois);

            if ($mois === null) {
                continue;
            }

            $cles[$paiement->stagiaire_id.'|'.$mois->format('Y-m')] = true;
        }

        return $cles;
    }

    /**
     * Remet dans la file DMG les pointages dont le paiement porte la signature « ajourné DMG,
     * jamais visé CB » — l'ancien Gestage continue de les afficher au DMG. `PaiementDmgService::
     * attentePaiementValidation()` ne teste que cette signature, sans condition d'étape : elle
     * peut survenir via l'ajournement CB classique (étapes legacy 20/21,
     * `AjournementDossierStagiaireController`/`MultiDossierController`) mais aussi via un rejet AC
     * (étape 29, `TraitementAjournementStagiaireRejetByAcJob`) qui, lui, ne touche jamais l'étape
     * du pointage — le restreindre à 20/21 laissait ces dossiers hors file DMG côté cible.
     *
     * Le rattrapage est symétrique : dès qu'un paiement quitte cette signature, son pointage
     * reprend la corbeille que son étape legacy lui donnerait normalement (`mapPointageToCorbeille`
     * fait foi dans les deux sens), ce qui rend l'étape rejouable.
     *
     * @param  array<int, string>  $stagesExclus  contrats hors file DMG, à ne pas promouvoir :
     *                                            sortirCorbeillesDmgHorsPerimetre() les ressortirait
     *                                            aussitôt et l'étape oscillerait d'un passage à l'autre.
     */
    private function reclasserPointagesAjournesAvantCb(bool $dryRun, array $stagesExclus): int
    {
        $renvoyes = $this->paiementsRenvoyesAuDmg();

        $corbeillesConcernees = [
            CorbeilleEnum::DMG_ATTENTE_PAIEMENT_DEMARRAGE->value,
            CorbeilleEnum::DMG_ATTENTE_PAIEMENT_PRESENCE->value,
            CorbeilleEnum::CB_ETAT_PAIEMENT_AJOURNE->value,
        ];

        // Candidats : les pointages des contrats renvoyés au DMG (promotion, quelle que soit leur
        // étape legacy), plus tout pointage déjà dans une de ces trois corbeilles (réversion si son
        // paiement a quitté la signature depuis le dernier passage).
        $stagiairesRenvoyes = array_values(array_unique(array_map(
            static fn (string $cle): int => (int) explode('|', $cle)[0],
            array_keys($renvoyes)
        )));

        $candidats = DB::table('instances_parcours as ip')
            ->join('pointages as p', 'p.id', '=', 'ip.pointage_id')
            ->join('stages as s', 's.id', '=', 'p.stage_id')
            ->join('periodes as pe', 'pe.id', '=', 'p.periode_id')
            ->whereNull('ip.terminee_le')
            ->whereNull('p.deleted_at')
            ->whereNotNull('p.ancien_id')
            ->where(function (Builder $q) use ($stagiairesRenvoyes, $corbeillesConcernees): void {
                $q->whereIn('ip.corbeille_actuelle', $corbeillesConcernees);

                if ($stagiairesRenvoyes !== []) {
                    $q->orWhereIn('s.ancien_id', $stagiairesRenvoyes);
                }
            });

        $changed = 0;

        $definition = DefinitionParcours::firstOrCreate(
            ['code' => 'POINTAGE_LEGACY', 'version' => 1],
            ['nom' => 'Parcours Pointage Legacy', 'active' => true]
        );
        $etapesMap = [];

        $candidats
            ->select('ip.id', 'ip.corbeille_actuelle', 'p.ancien_id', 'p.statut', 's.ancien_id as stage_ancien_id', 's.agence_id', 's.date_debut', 'pe.code')
            ->orderBy('ip.id')
            ->chunk(500, function ($instances) use ($renvoyes, $stagesExclus, $corbeillesConcernees, $dryRun, $definition, &$etapesMap, &$changed): void {
                $anciensIds = $instances->pluck('ancien_id')->filter()->all();
                $etapesLegacy = $anciensIds === [] ? collect() : DB::connection('legacy')->table('pointage_models')
                    ->whereIn('id', $anciensIds)
                    ->pluck('etape_id', 'id');

                $instanceIds = $instances->pluck('id')->all();
                $instancesEloquent = InstanceParcours::whereIn('id', $instanceIds)->get()->keyBy('id');
                $tachesExistantes = TacheParcours::whereIn('instance_parcours_id', $instanceIds)
                    ->whereIn('statut', ['OUVERTE', 'REVENDIQUEE'])
                    ->get()
                    ->groupBy('instance_parcours_id');

                foreach ($instances as $instance) {
                    if (isset($stagesExclus[(int) $instance->stage_ancien_id])) {
                        continue;
                    }

                    $legacyEtapeId = isset($etapesLegacy[$instance->ancien_id])
                        ? (int) $etapesLegacy[$instance->ancien_id]
                        : null;
                    $renvoye = isset($renvoyes[$instance->stage_ancien_id.'|'.$instance->code]);
                    $nature = $this->mapper->naturePaiementPourPeriode($instance->date_debut, $instance->code);

                    $attendue = $this->mapper->mapPointageToCorbeille($legacyEtapeId, $instance->statut, $nature, null, $renvoye)->value;

                    // Cette étape n'arbitre que l'axe DMG / CB-ajourné : un résultat hors de ce
                    // périmètre (ex. étape sans lien avec 20/21/29) signifie que la corbeille
                    // actuelle relève d'une autre étape de migration, pas de celle-ci.
                    if (! in_array($attendue, $corbeillesConcernees, true)) {
                        continue;
                    }

                    if ($instance->corbeille_actuelle === $attendue) {
                        continue;
                    }

                    if (! $dryRun) {
                        $etapeCode = strtoupper($attendue);
                        if (! isset($etapesMap[$etapeCode])) {
                            $etapesMap[$etapeCode] = EtapeParcours::firstOrCreate(
                                ['definition_parcours_id' => $definition->id, 'code' => $etapeCode],
                                ['nom' => str_replace('_', ' ', $etapeCode), 'initiale' => false, 'finale' => false]
                            );
                        }
                        $etape = $etapesMap[$etapeCode];

                        $instanceEloquent = $instancesEloquent[$instance->id];
                        $instanceEloquent->fill([
                            'etape_courante_id' => $etape->id,
                            'corbeille_actuelle' => $attendue,
                        ]);
                        $instanceEloquent->save();

                        // Une simple réécriture de `corbeille_actuelle` ne suffit pas :
                        // DmgService::filtreCorbeille() donne priorité à une tâche OUVERTE, quelle
                        // que soit sa corbeille. Sans resynchronisation ici, une tâche restée
                        // ouverte dans l'ancienne corbeille masquerait ce reclassement (l'instance
                        // ne matcherait ni la tâche cible, ni le repli faute de tâche ouverte).
                        $this->syncOpenTask(
                            $instanceEloquent,
                            $etape,
                            CorbeilleEnum::from($attendue),
                            $instance->agence_id,
                            false,
                            $tachesExistantes[$instance->id] ?? collect()
                        );
                    }

                    $changed++;
                }
            });

        return $changed;
    }

    private function backfillCorbeillesDmg(bool $dryRun): void
    {
        $corbeillesDmg = [
            CorbeilleEnum::DMG_ATTENTE_PAIEMENT_DEMARRAGE->value,
            CorbeilleEnum::DMG_ATTENTE_PAIEMENT_PRESENCE->value,
        ];

        $stagesExclus = $this->contratsHorsFileDmg();

        $this->info('Contrats legacy hors file DMG classique : '.count($stagesExclus));

        $reclasses = $this->reclasserPointagesAjournesAvantCb($dryRun, $stagesExclus);
        $this->info(($dryRun ? '[DRY-RUN] ' : '')."Pointages ajournés avant visa CB reclassés : {$reclasses}");

        // On sort d'abord les dossiers hors périmètre : la promotion ci-dessous lit les
        // corbeilles des pointages, elle ne doit plus y trouver ces dossiers.
        $sorties = $this->sortirCorbeillesDmgHorsPerimetre($corbeillesDmg, $stagesExclus, $dryRun);
        $this->info(($dryRun ? '[DRY-RUN] ' : '')."Instances sorties de la file DMG (hors périmètre) : {$sorties}");

        // Corbeille DMG du pointage impayé le plus récent, par stage.
        $source = DB::table('instances_parcours as ip')
            ->join('pointages as p', 'p.id', '=', 'ip.pointage_id')
            ->join('droits_paiement as d', 'd.pointage_id', '=', 'p.id')
            ->join('paiements as pa', 'pa.droit_paiement_id', '=', 'd.id')
            ->join('periodes as pe', 'pe.id', '=', 'd.periode_id')
            ->whereNull('ip.terminee_le')
            ->whereNull('d.annule_le')
            ->where('pa.statut', 'A_TRAITER')
            ->whereIn('ip.corbeille_actuelle', $corbeillesDmg)
            ->select('p.stage_id', 'ip.corbeille_actuelle', 'pe.date_debut')
            ->orderBy('p.stage_id')
            ->orderByDesc('pe.date_debut');

        $ancienIdParStage = Stage::whereNotNull('ancien_id')->pluck('ancien_id', 'id')->all();

        $corbeilleParStage = [];
        foreach ($source->cursor() as $ligne) {
            if (isset($stagesExclus[(int) ($ancienIdParStage[$ligne->stage_id] ?? 0)])) {
                continue;
            }

            $corbeilleParStage[$ligne->stage_id] ??= $ligne->corbeille_actuelle;
        }

        $this->info('Stages avec un pointage validé en attente de paiement : '.count($corbeilleParStage));

        // L'étape legacy peut à elle seule placer le dossier en attente de paiement DMG
        // (`etapetraitement_id` 13/14) : cette corbeille-là ne dépend pas des pointages impayés.
        // Les dossiers hors périmètre ne sont pas concernés : leur exclusion prime sur l'étape.
        $dmgParEtape = [];
        foreach ($this->contratsLegacyPourRoutage() as $contrat) {
            $corbeille = $this->mapper->mapChefAgenceCorbeille($contrat)->value;

            if (in_array($corbeille, $corbeillesDmg, true) && ! isset($stagesExclus[(int) $contrat->id])) {
                $dmgParEtape[(int) $contrat->id] = $corbeille;
            }
        }

        // Corbeille attendue pour chaque instance de stage, en une seule table de vérité :
        // l'étape legacy fait foi ; le pointage impayé le plus récent ne sert qu'à rattraper les
        // dossiers qu'elle laisse en `en_stage`, sinon on repart au stage.
        // L'étape doit être rejouable dans les deux sens, sans quoi un stage promu lors d'un
        // passage précédent resterait en corbeille DMG après correction de son paiement.
        $changed = 0;
        $transitions = [];
        $modifiables = array_merge([CorbeilleEnum::EN_STAGE->value], $corbeillesDmg);

        $definition = DefinitionParcours::firstOrCreate(
            ['code' => 'POINTAGE_LEGACY', 'version' => 1],
            ['nom' => 'Parcours Pointage Legacy', 'active' => true]
        );
        $etapesMap = [];

        InstanceParcours::whereIn('corbeille_actuelle', $modifiables)
            ->whereNotNull('stage_id')
            ->whereNull('terminee_le')
            ->chunkById(500, function ($instances) use ($corbeilleParStage, $dmgParEtape, $ancienIdParStage, $dryRun, $definition, &$etapesMap, &$changed, &$transitions): void {
                $stageIds = $instances->pluck('stage_id')->filter()->all();
                $agencesParStageId = $stageIds === [] ? collect() : Stage::whereIn('id', $stageIds)->pluck('agence_id', 'id');
                $instanceIds = $instances->pluck('id')->all();
                $tachesExistantes = TacheParcours::whereIn('instance_parcours_id', $instanceIds)
                    ->whereIn('statut', ['OUVERTE', 'REVENDIQUEE'])
                    ->get()
                    ->groupBy('instance_parcours_id');

                foreach ($instances as $instance) {
                    $ancienId = (int) ($ancienIdParStage[$instance->stage_id] ?? 0);
                    $cible = $dmgParEtape[$ancienId]
                        ?? $corbeilleParStage[$instance->stage_id]
                        ?? CorbeilleEnum::EN_STAGE->value;

                    if ($cible === $instance->corbeille_actuelle) {
                        continue;
                    }

                    $cle = $instance->corbeille_actuelle.' → '.$cible;
                    $transitions[$cle] = ($transitions[$cle] ?? 0) + 1;
                    $changed++;

                    if (! $dryRun) {
                        $etapeCode = strtoupper($cible);
                        if (! isset($etapesMap[$etapeCode])) {
                            $etapesMap[$etapeCode] = EtapeParcours::firstOrCreate(
                                ['definition_parcours_id' => $definition->id, 'code' => $etapeCode],
                                ['nom' => str_replace('_', ' ', $etapeCode), 'initiale' => false, 'finale' => false]
                            );
                        }
                        $etape = $etapesMap[$etapeCode];

                        $instance->fill([
                            'etape_courante_id' => $etape->id,
                            'corbeille_actuelle' => $cible,
                        ]);
                        $instance->save();

                        // Cf. reclasserPointagesAjournesAvantCb() : une tâche restée ouverte dans
                        // l'ancienne corbeille primerait sur ce `corbeille_actuelle` réécrit.
                        $this->syncOpenTask(
                            $instance,
                            $etape,
                            CorbeilleEnum::from($cible),
                            $agencesParStageId[$instance->stage_id] ?? null,
                            false,
                            $tachesExistantes[$instance->id] ?? collect()
                        );
                    }
                }
            });

        ksort($transitions);

        foreach ($transitions as $transition => $nombre) {
            $this->line("  {$transition} : {$nombre}");
        }

        $this->info(($dryRun ? '[DRY-RUN] ' : '')."Instances de stage réalignées : {$changed}");
    }

    /**
     * Les contrats legacy actifs, avec les colonnes dont dépend `mapChefAgenceCorbeille()`.
     *
     * @return \Generator<int, object>
     */
    private function contratsLegacyPourRoutage(): \Generator
    {
        yield from DB::connection('legacy')->table('contrats_pae')
            ->whereNull('deleted_at')
            ->select('id', 'etapetraitement_id', 'id_statut_stage', 'etat_chef_agence')
            ->cursor();
    }

    /**
     * Contrats que l'ancien Gestage exclut de la file DMG classique, et corbeille de repli.
     *
     * Reprend les clauses de PaiementDmgService::attentePaiementValidation() :
     * - financement PEJEDEC : circuit de pointage et de paiement distinct
     *   (Cip\PointagePejedecController, AdmBcpe\PaiementPejedecController) ;
     * - origines 3/4/19 : n'ouvrent aucun droit à paiement (scopeSansPaiement) ;
     * - étape de traitement 5 et contrats non validés par le chef d'agence.
     *
     * @return array<int, string> corbeille de repli du pointage, indexée par `contrats_pae.id`
     *                            (l'instance du stage, elle, repart toujours en `en_stage`)
     */
    private function contratsHorsFileDmg(): array
    {
        $exclus = [];

        $base = fn () => DB::connection('legacy')->table('contrats_pae')->whereNull('deleted_at')->select('id');

        // Les autres motifs ne correspondent à aucune file : le dossier repart au stage.
        $horsDroit = $base()
            ->where(fn ($q) => $q
                ->whereIn('originestagiaire_id', self::ORIGINES_SANS_DROIT_PAIEMENT)
                ->orWhere('etapetraitement_id', self::ETAPE_TRAITEMENT_EXCLUE_DMG)
                ->orWhere('etat_chef_agence', '!=', 2)
                ->orWhere('avis_contrat', '!=', 1)
                ->orWhere('active_chef_agence', '!=', 1));

        foreach ($horsDroit->cursor() as $contrat) {
            $exclus[(int) $contrat->id] = CorbeilleEnum::EN_STAGE->value;
        }

        // PEJEDEC prime : ces pointages ont une corbeille dédiée.
        foreach ($base()->where('source_financement', self::FINANCEMENT_PEJEDEC)->cursor() as $contrat) {
            $exclus[(int) $contrat->id] = CorbeilleEnum::CIP_POINTAGE_PEJEDEC->value;
        }

        return $exclus;
    }

    /**
     * Sort de la file DMG les instances des dossiers que le legacy en exclut : leur paiement
     * relève d'un autre circuit (PEJEDEC) ou d'aucun.
     *
     * La correction porte sur les deux niveaux d'instance, car la file se lit d'abord sur celle
     * du pointage : ne traiter que le stage laissait entrer les mois antérieurs.
     *
     * @param  array<int, string>  $corbeillesDmg
     * @param  array<int, string>  $stagesExclus  corbeille de repli du pointage, par `contrats_pae.id`
     */
    private function sortirCorbeillesDmgHorsPerimetre(array $corbeillesDmg, array $stagesExclus, bool $dryRun): int
    {
        if ($stagesExclus === []) {
            return 0;
        }

        $sorties = 0;

        foreach (array_chunk($stagesExclus, 500, true) as $chunk) {
            $repliParStage = [];

            foreach (Stage::whereIn('ancien_id', array_keys($chunk))->select('id', 'ancien_id')->get() as $stage) {
                $repliParStage[$stage->id] = $chunk[(int) $stage->ancien_id];
            }

            if ($repliParStage === []) {
                continue;
            }

            $stageIds = array_keys($repliParStage);

            $instances = InstanceParcours::whereIn('corbeille_actuelle', $corbeillesDmg)
                ->whereNull('terminee_le')
                ->where(fn ($q) => $q
                    ->whereIn('stage_id', $stageIds)
                    ->orWhereIn('pointage_id', fn ($sq) => $sq
                        ->select('id')->from('pointages')->whereIn('stage_id', $stageIds)))
                ->with('pointage:id,stage_id')
                ->get();

            foreach ($instances as $instance) {
                $stageId = $instance->stage_id ?? $instance->pointage?->stage_id;
                $repli = $repliParStage[$stageId] ?? null;

                if ($repli === null) {
                    continue;
                }

                // La corbeille PEJEDEC est une file de pointage : l'instance du stage, elle,
                // n'a pas d'autre place que le stage lui-même.
                if ($instance->stage_id !== null) {
                    $repli = CorbeilleEnum::EN_STAGE->value;
                }

                $sorties++;

                if (! $dryRun) {
                    $instance->update(['corbeille_actuelle' => $repli]);
                }
            }
        }

        return $sorties;
    }

    /**
     * Étape backfill_avenants_renouvellement : reprend les renouvellements legacy.
     *
     * `contrats_pae.etatrenouvellement_id = 1` (8 220 dossiers, tous avec `date_debut_renouv`
     * et `date_fin_renouv` renseignées) n'était repris nulle part. Or ce drapeau arbitre le
     * partage des corbeilles de paiement DMG : ContratsPae::scopeAttestationDemarrage() est
     * doublé d'un `etatrenouvellement_id != 1`, et scopeAttestationPresence() ne réintègre le
     * mois de démarrage que pour les renouvellements. Sans lui, un stage renouvelé qui démarre
     * dans le mois tombe dans la mauvaise file.
     *
     * Le schéma cible modélise déjà cela avec `avenants_contrats` : on y matérialise la période
     * de renouvellement plutôt que d'ajouter une colonne technique au contrat.
     */
    private function backfillAvenantsRenouvellement(bool $dryRun): void
    {
        $query = DB::connection('legacy')->table('contrats_pae')
            ->whereNull('deleted_at')
            ->where('etatrenouvellement_id', 1)
            ->select([
                'id', 'date_debut_renouv', 'date_fin_renouv',
                // Le trio ci-dessous marque, côté legacy, un renouvellement ajourné par le
                // chef d'agence : il devient le `statut` de l'avenant.
                'etapetraitement_id', 'etat_chef_agence', 'active_chef_agence',
            ]);

        $total = $query->count();
        $this->info("Contrats legacy renouvelés à reprendre : {$total}");

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $crees = 0;
        $sansContrat = 0;
        $sansDate = 0;

        $this->processInChunks($query, 500, function ($contrats) use (&$crees, &$sansContrat, &$sansDate, $dryRun, $bar) {
            $ancienIds = collect($contrats)->pluck('id')->all();

            $stagesMap = Stage::whereIn('ancien_id', $ancienIds)->pluck('id', 'ancien_id');
            $contratsMap = Contrat::whereIn('stage_id', $stagesMap->values()->all())
                ->get()
                ->keyBy('stage_id');

            $dejaCrees = AvenantContrat::whereIn('contrat_id', $contratsMap->pluck('id')->all())
                ->pluck('contrat_id')
                ->flip();

            foreach ($contrats as $legacyContrat) {
                $bar->advance();

                $stageId = $stagesMap[$legacyContrat->id] ?? null;
                $contrat = $stageId ? ($contratsMap[$stageId] ?? null) : null;

                if (! $contrat) {
                    $sansContrat++;

                    continue;
                }

                if ($dejaCrees->has($contrat->id)) {
                    continue;
                }

                $dateEffet = $this->mapper->normalizeLegacyDate($legacyContrat->date_debut_renouv ?? null);

                if (! $dateEffet) {
                    $sansDate++;

                    continue;
                }

                $crees++;

                if ($dryRun) {
                    continue;
                }

                $ajourne = (int) ($legacyContrat->etapetraitement_id ?? 0) === 2
                    && (int) ($legacyContrat->etat_chef_agence ?? 0) === 1
                    && (int) ($legacyContrat->active_chef_agence ?? 1) === 0;

                AvenantContrat::create([
                    'contrat_id' => $contrat->id,
                    'numero' => $contrat->numero.'-R1',
                    'date_effet' => $dateEffet,
                    'nouvelle_date_fin' => $this->mapper->normalizeLegacyDate($legacyContrat->date_fin_renouv ?? null),
                    'motif' => 'Renouvellement repris du legacy',
                    'statut' => $ajourne
                        ? AvenantContrat::STATUT_AJOURNE
                        : AvenantContrat::STATUT_VALIDE,
                    'motif_ajournement' => $ajourne ? 'Ajournement repris du legacy' : null,
                ]);
            }
        });

        $bar->finish();
        $this->newLine();

        if ($sansContrat > 0) {
            $this->warn("Renouvellements sans contrat cible : {$sansContrat}");
        }

        if ($sansDate > 0) {
            $this->warn("Renouvellements sans date de début exploitable : {$sansDate}");
        }

        $this->info(($dryRun ? '[DRY-RUN] ' : '')."Avenants de renouvellement créés : {$crees}");
    }

    /**
     * Étape backfill_visa_desse : reprend `contrats_pae.etat_desse` sur `stages.visa_desse`.
     *
     * Le visa n'est demandé qu'une fois le démarrage validé par le chef d'agence
     * (`etat_chef_agence = 2`) ; sinon le dossier n'est pas encore soumis à la DESSE et
     * reste sans visa.
     */
    private function backfillVisaDesse(bool $dryRun): void
    {
        $query = DB::connection('legacy')->table('contrats_pae')
            ->whereNull('deleted_at')
            ->select(['id', 'etat_desse', 'etat_chef_agence', 'motif_desse', 'date_desse', 'id_user_desse']);

        $total = $query->count();
        $this->info("Contrats legacy à viser : {$total}");

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $misAJour = 0;
        $sansStage = 0;
        $userIdCache = [];

        $this->processInChunks($query, 1000, function ($contrats) use (&$misAJour, &$sansStage, &$userIdCache, $dryRun, $bar) {
            $stagesMap = Stage::whereIn('ancien_id', collect($contrats)->pluck('id')->all())
                ->pluck('id', 'ancien_id');

            foreach ($contrats as $legacyContrat) {
                $bar->advance();

                $stageId = $stagesMap[$legacyContrat->id] ?? null;

                if (! $stageId) {
                    $sansStage++;

                    continue;
                }

                $visa = (int) ($legacyContrat->etat_chef_agence ?? 0) === 2
                    ? VisaDesseEnum::depuisEtatLegacy(
                        $legacyContrat->etat_desse === null ? null : (int) $legacyContrat->etat_desse
                    )
                    : null;

                if (! $visa) {
                    continue;
                }

                $misAJour++;

                if ($dryRun) {
                    continue;
                }

                // Résolution paresseuse : la table `users` legacy n'est interrogée que si un
                // dossier porte réellement un décideur.
                $decideParId = null;
                if (! empty($legacyContrat->id_user_desse)) {
                    if (! array_key_exists($legacyContrat->id_user_desse, $userIdCache)) {
                        $legacyUser = DB::connection('legacy')->table('users')
                            ->where('id', $legacyContrat->id_user_desse)
                            ->first();

                        $userIdCache[$legacyContrat->id_user_desse] = $legacyUser
                            ? User::where('email', $this->mapper->sanitizeEmail($legacyUser->email, $legacyUser->nom ?? 'User', $legacyUser->pseudo ?? '', $legacyUser->id))->value('id')
                            : null;
                    }
                    $decideParId = $userIdCache[$legacyContrat->id_user_desse];
                }

                $motif = trim((string) ($legacyContrat->motif_desse ?? ''));

                DB::table('stages')->where('id', $stageId)->update([
                    'visa_desse' => $visa->value,
                    'motif_visa_desse' => $motif !== '' ? $motif : null,
                    'visa_desse_le' => $visa === VisaDesseEnum::EN_ATTENTE
                        ? null
                        : $this->mapper->normalizeLegacyDate($legacyContrat->date_desse),
                    'visa_desse_par_id' => $visa === VisaDesseEnum::EN_ATTENTE ? null : $decideParId,
                ]);
            }
        });

        $bar->finish();
        $this->newLine();

        if ($sansStage > 0) {
            $this->warn("Contrats legacy sans stage cible : {$sansStage}");
        }

        $this->info(($dryRun ? '[DRY-RUN] ' : '')."Visas DESSE repris : {$misAJour}");
    }

    /**
     * Étape backfill_stagiaires_differes_ac : reprend le `paiement_models.user_differed` du
     * legacy, seul support de l'onglet « OP différé » de l'écran AC `wait-to-generate-bordereau`.
     *
     * Le legacy laisse ces paiements en `status_ac = 'processed'` et se contente du drapeau
     * `user_differed` ; le nouveau modèle porte le même état par le couple statut `A_TRAITER`
     * + corbeille `dmg_op_differe_ac`, exactement ce qu'écrit AgentComptableService. Sans cette
     * reprise, un stagiaire différé par l'AC réapparaît dans l'onglet « en attente ».
     */
    private function backfillStagiairesDifferesAc(bool $dryRun): void
    {
        $query = DB::connection('legacy')->table('paiement_models')
            ->whereNull('deleted_at')
            ->where('status_dmg', 1)
            ->where('status_ac', 'processed')
            ->whereNotNull('user_differed')
            ->select(['id', 'motif_status', 'user_differed', 'date_vise_ac']);

        $total = $query->count();
        $this->info("Paiements legacy différés par l'AC : {$total}");

        $misAJour = 0;
        $sansPaiement = 0;
        $dejaTranches = 0;
        $userIdCache = [];

        $this->processInChunks($query, 500, function ($legacyPaiements) use (&$misAJour, &$sansPaiement, &$dejaTranches, &$userIdCache, $dryRun) {
            $paiements = Paiement::whereIn('ancien_id', collect($legacyPaiements)->pluck('id')->all())
                ->get(['id', 'ancien_id', 'statut'])
                ->keyBy('ancien_id');

            foreach ($legacyPaiements as $legacyPaiement) {
                $paiement = $paiements[$legacyPaiement->id] ?? null;

                if (! $paiement) {
                    $sansPaiement++;

                    continue;
                }

                // Une décision prise côté cible depuis la migration prime sur le drapeau legacy.
                if ($paiement->statut !== AgentComptableService::STATUT_ATTENTE_AC) {
                    $dejaTranches++;

                    continue;
                }

                $misAJour++;

                if ($dryRun) {
                    continue;
                }

                $motif = trim((string) ($legacyPaiement->motif_status ?? ''));

                DB::table('paiements')->where('id', $paiement->id)->update([
                    'statut' => 'A_TRAITER',
                    'corbeille_actuelle' => CorbeilleEnum::DMG_OP_DIFFERE_AC->value,
                ]);

                if ($motif !== '') {
                    DB::table('lignes_dossiers_paiement')
                        ->where('paiement_id', $paiement->id)
                        ->whereNull('retire_le')
                        ->update(['motif_retrait' => $motif]);
                }

                $auteurId = $this->resoudreUserLegacy($legacyPaiement->user_differed, $userIdCache);

                if ($auteurId !== null) {
                    // updateOrCreate : l'étape amont (paiements) n'a pas connaissance de cette
                    // décision et peut repromouvoir le paiement à EN_OP entre deux exécutions
                    // (garde-fou côté migratePaiements) ; sans idempotence ici, chaque re-run
                    // dupliquait la ligne de décision dans l'historique.
                    DecisionPaiement::updateOrCreate(
                        ['paiement_id' => $paiement->id, 'decision' => 'DIFFERE_STAGIAIRE_AC'],
                        [
                            'auteur_id' => $auteurId,
                            'statut_avant' => AgentComptableService::STATUT_ATTENTE_AC,
                            'statut_apres' => 'A_TRAITER',
                            'motif' => $motif !== '' ? $motif : null,
                            'decide_le' => $this->mapper->normalizeLegacyDate($legacyPaiement->date_vise_ac) ?? now(),
                        ],
                    );
                }
            }
        });

        if ($sansPaiement > 0) {
            $this->warn("Paiements legacy différés sans paiement cible : {$sansPaiement}");
        }

        if ($dejaTranches > 0) {
            $this->warn("Paiements différés déjà tranchés côté cible, laissés en l'état : {$dejaTranches}");
        }

        $this->info(($dryRun ? '[DRY-RUN] ' : '')."Stagiaires différés par l'AC repris : {$misAJour}");
    }

    /**
     * Résolution paresseuse d'un identifiant utilisateur legacy vers son homologue cible.
     *
     * @param  array<int|string, int|null>  $cache
     */
    private function resoudreUserLegacy(mixed $legacyUserId, array &$cache): ?int
    {
        if (empty($legacyUserId)) {
            return null;
        }

        if (! array_key_exists($legacyUserId, $cache)) {
            $legacyUser = DB::connection('legacy')->table('users')->where('id', $legacyUserId)->first();

            $cache[$legacyUserId] = $legacyUser
                ? User::where('email', $this->mapper->sanitizeEmail($legacyUser->email, $legacyUser->nom ?? 'User', $legacyUser->pseudo ?? '', $legacyUser->id))->value('id')
                : null;
        }

        return $cache[$legacyUserId];
    }

    /**
     * Étape fix_statut_paiements_legacy : réaligne le statut des paiements déjà migrés sur
     * mapLegacyPaymentStatus().
     *
     * La phase `paiements` n'écrase le statut que « vers l'avant » lors d'une reprise, pour ne
     * pas défaire une décision prise côté cible. Cette étape est donc nécessaire pour propager
     * la correction aux lignes déjà en base sans rejouer toute la migration.
     */
    private function fixStatutPaiementsLegacy(bool $dryRun): void
    {
        $query = DB::connection('legacy')->table('paiement_models')
            ->select(['id', 'status', 'status_ac', 'status_dmg', 'status_cb', 'dossier_id', 'created_by_cb', 'date_vise_cb', 'date_confirm_pay', 'updated_at']);

        $total = $query->count();
        $this->info("Paiements legacy à réévaluer : {$total}");

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $corriges = 0;
        $transitions = [];

        $this->processInChunks($query, 2000, function ($legacyPaiements) use (&$corriges, &$transitions, $dryRun, $bar) {
            $attendus = [];
            $legacyParId = [];
            foreach ($legacyPaiements as $legacyPaiement) {
                $attendus[(int) $legacyPaiement->id] = $this->mapLegacyPaymentStatus($legacyPaiement);
                $legacyParId[(int) $legacyPaiement->id] = $legacyPaiement;
            }

            $paiements = Paiement::whereIn('ancien_id', array_keys($attendus))->get();

            foreach ($paiements as $paiement) {
                $attendu = $attendus[(int) $paiement->ancien_id] ?? null;

                if ($attendu === null || $paiement->statut === $attendu) {
                    continue;
                }

                $cle = "{$paiement->statut} → {$attendu}";
                $transitions[$cle] = ($transitions[$cle] ?? 0) + 1;
                $corriges++;

                if (! $dryRun) {
                    $valeurs = ['statut' => $attendu];
                    if ($attendu === 'PAYE') {
                        $valeurs['paye_le'] = $this->mapper->normalizeLegacyDate(
                            $legacyParId[(int) $paiement->ancien_id]->date_confirm_pay
                                ?? $legacyParId[(int) $paiement->ancien_id]->updated_at
                                ?? null,
                        )?->toImmutable();
                    } elseif (in_array($attendu, ['VALIDE_AC', 'NON_PAYE'], true)) {
                        $valeurs['paye_le'] = null;
                    }

                    $paiement->update($valeurs);
                }
            }

            $bar->advance(count($legacyPaiements));
        });

        $bar->finish();
        $this->newLine();

        ksort($transitions);
        foreach ($transitions as $transition => $nombre) {
            $this->line("  {$transition} : {$nombre}");
        }

        $this->info(($dryRun ? '[DRY-RUN] ' : '')."Statuts de paiement corrigés : {$corriges}");
    }

    /**
     * Étape fix_etat_chef_agence_100 : corrige les pointages placés erronément dans
     * les corbeilles DMG à cause de etat_chef_agence=100.
     * Anciennement : FixEtatChefAgence100Command
     */
    private function fixEtatChefAgence100(): void
    {
        $this->info('Fetching legacy stages with etat_chef_agence = 100...');
        $legacyIds = DB::connection('legacy')->table('contrats_pae')
            ->where('etat_chef_agence', 100)
            ->pluck('id')
            ->toArray();

        $this->info('Found '.count($legacyIds).' stages in legacy.');

        $stages = Stage::whereIn('ancien_id', $legacyIds)->pluck('id')->toArray();

        $pointagesInStage = Pointage::whereIn('stage_id', $stages)->pluck('id')->toArray();

        $instances = InstanceParcours::whereIn('corbeille_actuelle', ['dmg_attente_paiement_presence', 'dmg_attente_paiement_demarrage'])
            ->whereIn('pointage_id', $pointagesInStage)
            ->get();

        $this->info('Found '.$instances->count().' workflow instances to fix.');

        if ($instances->isEmpty()) {
            return;
        }

        // Batch pre-fetch : tous les pointages concernés en une requête
        $pointageIds = $instances->pluck('pointage_id')->filter()->values()->toArray();
        $pointagesCollection = Pointage::whereIn('id', $pointageIds)->get()->keyBy('id');

        // Batch pre-fetch : tous les droits de paiement concernés en une requête
        // Construire la clause WHERE IN avec les paires (stage_id, periode_id)
        $droitsCollection = collect();
        if ($pointagesCollection->isNotEmpty()) {
            $droitsQuery = DroitPaiement::query();
            $droitsQuery->where(function ($q) use ($pointagesCollection) {
                foreach ($pointagesCollection as $p) {
                    $q->orWhere(fn ($sub) => $sub->where('stage_id', $p->stage_id)->where('periode_id', $p->periode_id));
                }
            });
            $droitsCollection = $droitsQuery->get();
        }
        $droitIds = $droitsCollection->pluck('id')->toArray();

        // Batch pre-fetch : tous les paiements concernés en une requête
        $paiementsCollection = ! empty($droitIds)
            ? Paiement::whereIn('droit_paiement_id', $droitIds)->get()
            : collect();

        $this->info('Pre-fetched '.count($pointagesCollection).' pointages, '.count($droitsCollection).' droits, '.count($paiementsCollection).' paiements.');

        $bar = $this->output->createProgressBar($instances->count());
        $bar->start();

        $deletedPaiements = 0;
        $deletedDroits = 0;

        // Batch delete paiements
        $paiementIdsToDelete = $paiementsCollection->pluck('id')->toArray();
        if (! empty($paiementIdsToDelete)) {
            Paiement::whereIn('id', $paiementIdsToDelete)->delete();
            $deletedPaiements = count($paiementIdsToDelete);
        }

        // Batch delete droits
        $droitIdsToDelete = $droitsCollection->pluck('id')->toArray();
        if (! empty($droitIdsToDelete)) {
            DroitPaiement::whereIn('id', $droitIdsToDelete)->delete();
            $deletedDroits = count($droitIdsToDelete);
        }

        // Batch update instances
        $instanceIds = $instances->pluck('id')->toArray();
        InstanceParcours::whereIn('id', $instanceIds)->update(['corbeille_actuelle' => 'ca_validation_pointages']);

        $bar->finish();
        $this->newLine();
        $this->info("Fixed {$instances->count()} instances.");
        $this->info("Deleted {$deletedDroits} DroitsPaiement and {$deletedPaiements} Paiements.");
    }

    /**
     * Étape fix_legacy_ca_validation : corrige les stages legacy validés par CA mais
     * bloqués dans ca_attente_validation_demarrage sans paiements.
     * Anciennement : FixLegacyChefAgenceValidationCommand
     */
    private function fixLegacyChefAgenceValidation(): void
    {
        $this->info('Fetching stuck instances...');

        $stages = Stage::whereNotNull('ancien_id')
            ->whereHas('instanceParcours', function ($q) {
                $q->where('corbeille_actuelle', 'ca_attente_validation_demarrage');
            })
            ->with(['instanceParcours', 'contrats'])
            ->get();

        $this->info("Found {$stages->count()} total stages in CA corbeille.");

        $adminUser = User::whereHas('roles', fn ($q) => $q->where('name', 'administrateur'))->first() ?? User::first();

        // Injecter le service de validation via le conteneur Laravel
        $validationService = app(ValidationChefAgenceService::class);

        // Batch pre-fetch : tous les rows legacy en une seule requête
        $ancienIds = $stages->pluck('ancien_id')->filter()->values()->toArray();
        $legacyRowsMap = ! empty($ancienIds)
            ? DB::connection('legacy')->table('contrats_pae')
                ->whereIn('id', $ancienIds)
                ->get()
                ->keyBy('id')
            : collect();

        $fixedCount = 0;

        $bar = $this->output->createProgressBar($stages->count());
        $bar->start();

        foreach ($stages as $stage) {
            try {
                $legacyRow = $legacyRowsMap->get($stage->ancien_id);
                $instance = $stage->instanceParcours;
                if ($legacyRow && $legacyRow->etat_chef_agence == 2 && $instance instanceof InstanceParcours) {
                    $validationService->validerDemarrage($instance, $adminUser);
                    $fixedCount++;
                }
            } catch (\Exception $e) {
                // Ignorer les erreurs pour chaque stage
            }
            $bar->advance();
        }

        $bar->finish();

        $this->info("\nFixed {$fixedCount} stages (Generated missing DroitPaiement and transitioned to DMG).");
    }

    /**
     * Étape update_missing_data : met à jour les données manquantes issues de l'ancienne base
     * (Situation Stage, Type Structure, Type Paiement, Numéros mobile).
     * Anciennement : UpdateLegacyMissingDataCommand
     */
    private function updateLegacyMissingData(): void
    {
        $this->info('Début de la mise à jour des données manquantes...');

        $this->updateMissingReferences();
        $this->updateBeneficiaires();
        $this->updateEntreprisesMissingData();
        $this->updateStagesMissingData();
        $this->updateSourcesFinancementMissing();

        $this->info('Mise à jour terminée avec succès !');
    }

    private function updateMissingReferences(): void
    {
        $this->info('Migration des référentiels manquants...');

        if (DB::connection('legacy')->getSchemaBuilder()->hasTable('type_paiements')) {
            $rows = DB::connection('legacy')->table('type_paiements')
                ->get()
                ->map(fn ($tp) => [
                    'ancien_id' => $tp->id,
                    'code' => 'TP-'.str_pad($tp->id, 3, '0', STR_PAD_LEFT),
                    'nom' => $tp->libelle,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
                ->toArray();
            if (! empty($rows)) {
                TypePaiement::upsert($rows, ['ancien_id'], ['code', 'nom', 'updated_at']);
            }
        }

        if (DB::connection('legacy')->getSchemaBuilder()->hasTable('type_structures')) {
            $rows = DB::connection('legacy')->table('type_structures')
                ->get()
                ->map(fn ($ts) => [
                    'ancien_id' => $ts->id,
                    'code' => 'TS-'.str_pad($ts->id, 3, '0', STR_PAD_LEFT),
                    'nom' => $ts->libelle_type_structure ?? $ts->libelle ?? 'Structure '.$ts->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
                ->toArray();
            if (! empty($rows)) {
                TypeStructure::upsert($rows, ['ancien_id'], ['code', 'nom', 'updated_at']);
            }
        }

        if (DB::connection('legacy')->getSchemaBuilder()->hasTable('situation_stage')) {
            $rows = DB::connection('legacy')->table('situation_stage')
                ->get()
                ->map(fn ($s) => [
                    'ancien_id' => $s->id_situation_stage,
                    'code' => 'SS-'.str_pad($s->id_situation_stage, 3, '0', STR_PAD_LEFT),
                    'nom' => $s->libelle_situation_stage,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
                ->toArray();
            if (! empty($rows)) {
                SituationStage::upsert($rows, ['ancien_id'], ['code', 'nom', 'updated_at']);
            }
        }
    }

    private function updateBeneficiaires(): void
    {
        $this->info('Mise à jour des bénéficiaires (Type paiement, Numéros mobile)...');

        $query = DB::connection('legacy')->table('contrats_pae');
        $total = $query->count();
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $typesPaiementMap = TypePaiement::pluck('id', 'ancien_id')->toArray();

        $this->processInChunks($query, 1000, function ($contrats) use ($bar, $typesPaiementMap) {
            $rows = [];
            foreach ($contrats as $legacyContrat) {
                if (empty($legacyContrat->numero_aej)) {
                    $bar->advance();

                    continue;
                }

                $rows[] = [
                    'numero_aej' => $legacyContrat->numero_aej,
                    'numero_tresor_money' => $legacyContrat->numero_yup ?? null,
                    'numero_wave' => $legacyContrat->numero_wave ?? null,
                    'type_paiement_id' => $typesPaiementMap[$legacyContrat->type_paiement_id] ?? null,
                    'updated_at' => now(),
                ];
                $bar->advance();
            }

            if (! empty($rows)) {
                $this->updateExistingRows('beneficiaires', 'numero_aej', $rows, [
                    'numero_tresor_money',
                    'numero_wave',
                    'type_paiement_id',
                    'updated_at',
                ]);
            }
        }, 'id', null, 'update_missing_data.beneficiaires');

        $bar->finish();
        $this->newLine();
    }

    private function updateEntreprisesMissingData(): void
    {
        $this->info('Mise à jour des entreprises (Type de structure)...');

        $query = DB::connection('legacy')->table('contrats_pae');
        $total = $query->count();
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $typesStructureMap = TypeStructure::pluck('id', 'ancien_id')->toArray();

        $this->processInChunks($query, 1000, function ($contrats) use ($bar, $typesStructureMap) {
            $rows = [];
            foreach ($contrats as $legacyContrat) {
                if (empty($legacyContrat->id_entreprise) || empty($legacyContrat->type_structure_id)) {
                    $bar->advance();

                    continue;
                }

                $type_structure_id = $typesStructureMap[$legacyContrat->type_structure_id] ?? null;
                if ($type_structure_id) {
                    $rows[] = [
                        'ancien_id' => $legacyContrat->id_entreprise,
                        'type_structure_id' => $type_structure_id,
                        'updated_at' => now(),
                    ];
                }
                $bar->advance();
            }

            if (! empty($rows)) {
                $this->updateExistingRows('entreprises', 'ancien_id', $rows, [
                    'type_structure_id',
                    'updated_at',
                ]);
            }
        }, 'id', null, 'update_missing_data.entreprises');

        $bar->finish();
        $this->newLine();
    }

    private function updateStagesMissingData(): void
    {
        $this->info('Mise à jour des stages (Situation de stage)...');

        $query = DB::connection('legacy')->table('contrats_pae');
        $total = $query->count();
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $situationsStageMap = SituationStage::pluck('code', 'ancien_id')->toArray();

        $this->processInChunks($query, 1000, function ($contrats) use ($bar, $situationsStageMap) {
            $rows = [];
            foreach ($contrats as $legacyContrat) {
                if (empty($legacyContrat->id) || empty($legacyContrat->id_situation_stage)) {
                    $bar->advance();

                    continue;
                }

                $code_situation = $situationsStageMap[$legacyContrat->id_situation_stage] ?? null;
                if ($code_situation) {
                    $rows[] = [
                        'ancien_id' => $legacyContrat->id,
                        'situation_stage' => $code_situation,
                        'updated_at' => now(),
                    ];
                }
                $bar->advance();
            }

            if (! empty($rows)) {
                $this->updateExistingRows('stages', 'ancien_id', $rows, [
                    'situation_stage',
                    'updated_at',
                ]);
            }
        }, 'id', null, 'update_missing_data.stages');

        $bar->finish();
        $this->newLine();
    }

    private function updateSourcesFinancementMissing(): void
    {
        $this->info('Mise à jour des sources de financement...');

        $query = DB::connection('legacy')->table('contrats_pae');
        $total = $query->count();
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $sourcesMap = SourceFinancement::pluck('id', 'ancien_id')->toArray();

        $this->processInChunks($query, 1000, function ($contrats) use ($bar, $sourcesMap) {
            $rows = [];
            foreach ($contrats as $legacyContrat) {
                if (empty($legacyContrat->id) || empty($legacyContrat->source_financement)) {
                    $bar->advance();

                    continue;
                }

                $source_financement_id = $sourcesMap[$legacyContrat->source_financement] ?? null;
                if ($source_financement_id) {
                    $rows[] = [
                        'ancien_id' => $legacyContrat->id,
                        'source_financement_id' => $source_financement_id,
                        'updated_at' => now(),
                    ];
                }
                $bar->advance();
            }

            if (! empty($rows)) {
                $this->updateExistingRows('stages', 'ancien_id', $rows, [
                    'source_financement_id',
                    'updated_at',
                ]);
            }
        }, 'id', null, 'update_missing_data.financements');

        $bar->finish();
        $this->newLine();
    }

    /**
     * Met à jour les lignes existantes sans tenter d'en créer de nouvelles.
     * Les phases de "missing data" ne fournissent pas les colonnes obligatoires
     * nécessaires à un insert complet, donc on force un update pur.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function updateExistingRows(string $table, string $keyColumn, array $rows, array $columnsToUpdate): void
    {
        if ($rows === []) {
            return;
        }

        if (DB::getDriverName() === 'pgsql') {
            $this->updateExistingRowsForPostgres($table, $keyColumn, $rows, $columnsToUpdate);

            return;
        }

        foreach ($rows as $row) {
            if (! array_key_exists($keyColumn, $row)) {
                continue;
            }

            $payload = [];
            foreach ($columnsToUpdate as $column) {
                $payload[$column] = $row[$column] ?? null;
            }

            DB::table($table)
                ->where($keyColumn, $row[$keyColumn])
                ->update($payload);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<int, string>  $columnsToUpdate
     */
    private function updateExistingRowsForPostgres(string $table, string $keyColumn, array $rows, array $columnsToUpdate): void
    {
        $columns = array_merge([$keyColumn], $columnsToUpdate);
        $bindings = [];
        $valuesSql = [];

        foreach ($rows as $row) {
            if (! array_key_exists($keyColumn, $row)) {
                continue;
            }

            $valuesSql[] = '('.implode(', ', array_fill(0, count($columns), '?')).')';

            foreach ($columns as $column) {
                $bindings[] = $row[$column] ?? null;
            }
        }

        if ($valuesSql === []) {
            return;
        }

        $quotedTable = '"'.str_replace('"', '""', $table).'"';
        $quotedKeyColumn = '"'.str_replace('"', '""', $keyColumn).'"';
        $quotedColumns = array_map(
            static fn (string $column) => '"'.str_replace('"', '""', $column).'"',
            $columns
        );
        $setClauses = array_map(
            static fn (string $column) => '"'.str_replace('"', '""', $column).'" = v."'.str_replace('"', '""', $column).'"',
            $columnsToUpdate
        );
        $typeCasts = array_map(
            fn (string $column) => $this->postgresValueCast($table, $column),
            $columns
        );
        $typedValuesSql = [];

        foreach ($valuesSql as $rowIndex => $rowSql) {
            $rowCasts = array_map(
                static fn (string $cast, int $columnIndex) => $cast !== '' ? '?::'.$cast : '?',
                $typeCasts,
                array_keys($typeCasts)
            );
            $typedValuesSql[] = '('.implode(', ', $rowCasts).')';
        }

        DB::statement(
            sprintf(
                'update %s as t set %s from (values %s) as v(%s) where t.%s = v.%s',
                $quotedTable,
                implode(', ', $setClauses),
                implode(', ', $typedValuesSql),
                implode(', ', $quotedColumns),
                $quotedKeyColumn,
                $quotedKeyColumn
            ),
            $bindings
        );
    }

    private function postgresValueCast(string $table, string $column): string
    {
        return match ($table.'.'.$column) {
            'beneficiaires.numero_aej',
            'beneficiaires.numero_tresor_money',
            'beneficiaires.numero_wave',
            'stages.situation_stage' => 'text',
            'entreprises.ancien_id',
            'entreprises.type_structure_id',
            'stages.ancien_id',
            'stages.source_financement_id',
            'beneficiaires.type_paiement_id' => 'bigint',
            'beneficiaires.updated_at',
            'entreprises.updated_at',
            'stages.updated_at' => 'timestamptz',
            default => '',
        };
    }
}
