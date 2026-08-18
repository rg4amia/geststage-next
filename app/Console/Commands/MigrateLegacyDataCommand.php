<?php

namespace App\Console\Commands;

use App\Enums\CorbeilleEnum;
use App\Models\Attendance\Pointage;
use App\Models\Attendance\VersionPointage;
use App\Models\Beneficiary\Beneficiaire;
use App\Models\Company\Entreprise;
use App\Models\Company\OffreEmploi;
use App\Models\Contract\Contrat;
use App\Models\Internship\Stage;
use App\Models\Payment\DroitPaiement;
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
use App\Models\System\User;
use App\Models\Workflow\DefinitionParcours;
use App\Models\Workflow\EtapeParcours;
use App\Models\Workflow\EvenementParcours;
use App\Models\Workflow\InstanceParcours;
use App\Services\Migration\LegacyMapperService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

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

        if ($step === 'all' || $step === 'evenements') {
            $this->migrateEvenements();
        }

        $this->info('Migration terminée !');

        return 0;
    }

    private function migrateUsers()
    {
        $this->info('Migration des utilisateurs...');
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

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
    }

    private function migrateEntreprises()
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

            Entreprise::withTrashed()->updateOrCreate(
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
            $entreprise_id = Entreprise::where('ancien_id', $legacyOffre->entreprise_id)->value('id');
            $agence_id = Agence::where('ancien_id', $legacyOffre->agence_id)->value('id');
            $type_stage_id = TypeStage::where('ancien_id', $legacyOffre->type_stage_id)->value('id') ?? 1;
            $source_financement_id = SourceFinancement::where('ancien_id', $legacyOffre->source_financement_id)->value('id') ?? 1;

            if ($entreprise_id && $agence_id) {
                $publiee_le = $legacyOffre->date_de_publication;
                if ($publiee_le && (str_starts_with($publiee_le, '-') || str_starts_with($publiee_le, '0000'))) {
                    $publiee_le = null;
                }

                OffreEmploi::withTrashed()->updateOrCreate(
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
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
    }

    private function migrateBeneficiaires()
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
        // Le legacy ne relie pas contrats_pae.diplome (texte libre) à la table diplome par FK :
        // on rapproche par libellé normalisé (trim + majuscules), en meilleur effort.
        $diplomesParLibelle = Diplome::pluck('id', 'nom')
            ->mapWithKeys(fn ($id, $nom) => [mb_strtoupper(trim((string) $nom)) => $id])
            ->toArray();

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $query->orderBy('id')->chunk(1000, function ($contrats) use (
            &$bar, $typesPaiementMap, $handicapsMap, $typesHandicapMap,
            $liensParenteMap, $typesEnseignementMap, $communesMap, $diplomesParLibelle
        ) {
            foreach ($contrats as $legacyContrat) {
                if (empty($legacyContrat->numero_aej)) {
                    $bar->advance();

                    continue; // Skip if no numero_aej as it's required and unique
                }

                $niveau_etude_id = null;
                if (! empty($legacyContrat->niveau_etude)) {
                    $code = 'NE-'.strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $legacyContrat->niveau_etude), 0, 5));
                    $niveau = NiveauEtude::firstOrCreate(
                        ['code' => $code],
                        ['nom' => $legacyContrat->niveau_etude]
                    );
                    $niveau_etude_id = $niveau->id;
                }

                $diplome_id = null;
                if (! empty($legacyContrat->diplome)) {
                    $diplome_id = $diplomesParLibelle[mb_strtoupper(trim($legacyContrat->diplome))] ?? null;
                }

                $date_naissance = $legacyContrat->date_de_naissance;
                if ($date_naissance && (str_starts_with($date_naissance, '-') || str_starts_with($date_naissance, '0000'))) {
                    $date_naissance = null;
                }

                Beneficiaire::updateOrCreate(
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
                        'autre_handicap' => ! empty($legacyContrat->handicap) && strtolower($legacyContrat->handicap) !== 'non' ? ($legacyContrat->type_handicap ?? 'Handicap signalé') : null,
                        'numero_tresor_money' => $legacyContrat->numero_yup ?? null,
                        'numero_wave' => $legacyContrat->numero_wave ?? null,
                        'type_paiement_id' => isset($legacyContrat->type_paiement_id) ? ($typesPaiementMap[$legacyContrat->type_paiement_id] ?? null) : null,
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
        $defaultSourceId = SourceFinancement::orderBy('id')->value('id') ?? 1;
        $origineStagiaireMap = OrigineStagiaire::pluck('id', 'ancien_id')->toArray();
        // situation_stage / statut_stage restent des colonnes texte dénormalisées sur stages
        // (comme en legacy), mais on y stocke désormais le vrai code du référentiel migré par
        // legacy:migrer-referentiels au lieu d'un code fabriqué ("SS-001") qui ne correspondait
        // à rien dans la table situations_stage utilisée par le filtre de "Mes Stagiaires".
        $situationsStageMap = DB::table('situations_stage')->pluck('code', 'ancien_id')->toArray();
        $statutsStageMap = DB::table('statuts_stage')->pluck('code', 'ancien_id')->toArray();

        $query->orderBy('id')->chunk(1000, function ($contrats) use (
            &$bar, $agencesMap, $typesStageMap, $entreprisesMap, $sourcesFinancementMap,
            $defaultSourceId, $origineStagiaireMap, $situationsStageMap, $statutsStageMap
        ) {
            $aejNums = $contrats->pluck('numero_aej')->filter()->unique()->toArray();
            $beneficiairesMap = Beneficiaire::whereIn('numero_aej', $aejNums)->pluck('id', 'numero_aej')->toArray();

            foreach ($contrats as $legacyContrat) {
                $beneficiaire_id = $beneficiairesMap[$legacyContrat->numero_aej] ?? null;
                $entreprise_id = $entreprisesMap[$legacyContrat->id_entreprise] ?? null;
                $agence_id = $agencesMap[$legacyContrat->id_agence] ?? null;
                $type_stage_id = $typesStageMap[$legacyContrat->id_type_stage] ?? 1;
                $source_financement_id = $sourcesFinancementMap[$legacyContrat->source_financement] ?? $defaultSourceId;
                $origine_stagiaire_id = isset($legacyContrat->originestagiaire_id) ? ($origineStagiaireMap[$legacyContrat->originestagiaire_id] ?? null) : null;
                $situation_stage = isset($legacyContrat->id_situation_stage) ? ($situationsStageMap[$legacyContrat->id_situation_stage] ?? null) : null;
                $statut_stage = isset($legacyContrat->id_statut_stage) ? ($statutsStageMap[$legacyContrat->id_statut_stage] ?? null) : null;

                if (! $beneficiaire_id || ! $entreprise_id || ! $agence_id) {
                    $bar->advance();

                    continue;
                }

                // 1. Création du Stage (ancien contrats_pae)
                $date_entree = $this->mapper->normalizeLegacyDate($legacyContrat->date_entree ?? null);
                [$date_debut, $date_fin_prevue] = $this->mapper->normalizeLegacyDateRange(
                    $legacyContrat->date_debut ?? null,
                    $legacyContrat->date_fin ?? null
                );

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
                Contrat::withTrashed()->updateOrCreate(
                    ['ancien_id' => $legacyContrat->id],
                    [
                        'stage_id' => $stage->id,
                        'numero' => 'CT-'.str_pad($legacyContrat->id, 5, '0', STR_PAD_LEFT),
                        'date_debut' => $date_debut,
                        'date_fin' => $date_fin_prevue,
                        'prime_mensuelle' => $legacyContrat->montant_du ?? 45000,
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

                InstanceParcours::updateOrCreate(
                    ['stage_id' => $stage->id],
                    [
                        'definition_parcours_id' => $definition->id,
                        'etape_courante_id' => $etape->id,
                        'corbeille_actuelle' => $corbeilleEnum->value,
                        'terminee_le' => $termineeLe,
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

                if (! $stage_id) {
                    $bar->advance();

                    continue;
                }

                // Idempotence : si cette ligne legacy a déjà été migrée (re-run de la commande),
                // on ne la retraite pas.
                if (VersionPointage::where('ancien_id', $legacyPointage->id)->exists()) {
                    $bar->advance();

                    continue;
                }

                // Mapper le statut du pointage
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

                // Créer dynamiquement une période basée sur la date de création
                $date = $this->mapper->normalizeLegacyDate($legacyPointage->created_at ?? null) ?? now();
                $codePeriode = $date->format('Y-m');

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
                    // Une stagiaire peut cumuler plusieurs stages : on ne regroupe les
                    // resoumissions legacy que par (stage_id, periode_id, nature), jamais par
                    // bénéficiaire, pour ne jamais mélanger deux stages d'une même personne.
                    // Le nouveau schéma n'autorise qu'un seul pointage actif par
                    // (stage, periode, nature) — les lignes legacy suivantes pour la même
                    // période deviennent donc de nouvelles versions du même pointage au lieu
                    // d'un doublon qui violerait l'index unique et serait perdu silencieusement.
                    $pointage = Pointage::withTrashed()
                        ->where('stage_id', $stage_id)
                        ->where('periode_id', $periodeId)
                        ->where('nature', 'PRESENCE')
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
                            'nature' => 'PRESENCE',
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

                    // CREATE PARCOURS FOR POINTAGE
                    $definition = \App\Models\Workflow\DefinitionParcours::firstOrCreate(
                        ['code' => 'POINTAGE_LEGACY', 'version' => 1],
                        ['nom' => 'Parcours Pointage Legacy', 'active' => true]
                    );

                    $corbeilleEnum = 'cip_mes_stagiaires';
                    if ($statut === 'AJOURNE_DMG') {
                        $corbeilleEnum = 'cip_pointage_ajourne_dmg';
                    } elseif ($statut === 'AJOURNE_CA') {
                        $corbeilleEnum = 'cip_ajourne_ca';
                    } elseif ($statut === 'VALIDE') {
                        $corbeilleEnum = 'dmg_attente_paiement_presence';
                    } elseif ($statut === 'SOUMIS') {
                        $corbeilleEnum = 'ca_validation_pointages';
                    }

                    $etapeCode = strtoupper($corbeilleEnum);
                    $etapeNom = str_replace('_', ' ', $etapeCode);

                    $etape = \App\Models\Workflow\EtapeParcours::firstOrCreate(
                        ['definition_parcours_id' => $definition->id, 'code' => $etapeCode],
                        ['nom' => $etapeNom, 'initiale' => false, 'finale' => false]
                    );

                    // L'instance de workflow reflète toujours le dernier état connu du pointage,
                    // donc de sa dernière version (resoumission) traitée.
                    \App\Models\Workflow\InstanceParcours::updateOrCreate(
                        ['pointage_id' => $pointage->id],
                        [
                            'definition_parcours_id' => $definition->id,
                            'etape_courante_id' => $etape->id,
                            'corbeille_actuelle' => $corbeilleEnum,
                        ]
                    );
                } catch (\Throwable $e) {
                    $this->warn("Pointage legacy #{$legacyPointage->id} ignoré : {$e->getMessage()}");
                }

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
    }

    private function migratePaiements()
    {
        $this->info('Migration des paiements (paiement_models)...');
        $query = DB::connection('legacy')->table('paiement_models');
        $total = $query->count();

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $periodesMap = DB::table('periodes')->pluck('id', 'code')->toArray();
        // paiement_models ne porte pas lui-même de source de financement : c'est un attribut du
        // stage (contrats_pae.source_financement) auquel il se rattache. Auparavant, tous les
        // paiements migrés recevaient la MÊME source de financement (la première ligne de la
        // table), quel que soit le stage réel — on la reprend maintenant depuis le stage.
        $defaultSourceId = SourceFinancement::orderBy('id')->value('id')
            ?? DB::table('sources_financement')->insertGetId(['code' => 'DEF', 'nom' => 'Défaut', 'actif' => true, 'created_at' => now(), 'updated_at' => now()]);

        $query->orderBy('id')->chunk(5000, function ($paiements) use (&$bar, &$periodesMap, $defaultSourceId) {
            $stagiaireIds = $paiements->pluck('stagiaire_id')->filter()->unique()->toArray();
            $stagesMap = Stage::whereIn('ancien_id', $stagiaireIds)->pluck('id', 'ancien_id')->toArray();
            $sourceFinancementParStage = Stage::whereIn('ancien_id', $stagiaireIds)->pluck('source_financement_id', 'ancien_id')->toArray();

            foreach ($paiements as $legacyPaiement) {
                // Créer le droit de paiement (la base du paiement dans la nouvelle architecture)
                $stage_id = $stagesMap[$legacyPaiement->stagiaire_id] ?? null;

                if (! $stage_id) {
                    // On ne rattache jamais un paiement à un stage arbitraire : mieux vaut
                    // ignorer la ligne que corrompre les données financières d'un autre stage.
                    $bar->advance();

                    continue;
                }

                $source_financement_id = $sourceFinancementParStage[$legacyPaiement->stagiaire_id] ?? $defaultSourceId;

                // Idempotence : si cette ligne legacy a déjà été migrée (re-run de la commande),
                // on ne la retraite pas.
                if (DroitPaiement::where('ancien_id', $legacyPaiement->id)->exists()) {
                    $bar->advance();

                    continue;
                }

                // Créer dynamiquement une période basée sur la date de création du paiement
                $date = $this->mapper->normalizeLegacyDate($legacyPaiement->created_at ?? null) ?? now();
                $codePeriode = $date->format('Y-m');

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
                    // Une stagiaire peut cumuler plusieurs stages : on ne regroupe que par
                    // (stage_id, periode_id, nature), jamais par bénéficiaire. Le nouveau schéma
                    // n'autorise qu'un seul droit de paiement actif (annule_le IS NULL) par
                    // (stage, periode, nature) — on traite les lignes legacy par id croissant
                    // (= ordre chronologique), donc la ligne actuelle remplace toujours le droit
                    // actif précédent au lieu de créer un doublon qui violerait l'index unique.
                    $actif = DroitPaiement::where('stage_id', $stage_id)
                        ->where('periode_id', $periodeId)
                        ->where('nature', 'PRESENCE')
                        ->whereNull('annule_le')
                        ->first();

                    if ($actif) {
                        $actif->update([
                            'annule_le' => $date,
                            'motif_annulation' => "Remplacé par le paiement legacy #{$legacyPaiement->id}",
                        ]);
                    }

                    $droit = DroitPaiement::create([
                        'ancien_id' => $legacyPaiement->id,
                        'stage_id' => $stage_id,
                        'periode_id' => $periodeId,
                        'source_financement_id' => $source_financement_id,
                        'nature' => 'PRESENCE',
                        'montant' => $legacyPaiement->montant,
                        'statut' => 'OUVERT',
                    ]);

                    // Créer le paiement réel
                    Paiement::updateOrCreate(
                        ['ancien_id' => $legacyPaiement->id],
                        [
                            'droit_paiement_id' => $droit->id,
                            'montant' => $legacyPaiement->montant,
                            'statut' => 'A_TRAITER',
                        ]
                    );
                } catch (\Throwable $e) {
                    $this->warn("Paiement legacy #{$legacyPaiement->id} ignoré : {$e->getMessage()}");
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
        \DB::unprepared('ALTER TABLE evenements_parcours DISABLE TRIGGER evenements_parcours_immuables;');

        $query = DB::connection('legacy')->table('contrat_etape');
        $total = $query->count();

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $query->orderBy('id')->chunk(5000, function ($historique) use (&$bar) {
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
            $pointagesMap = \App\Models\Attendance\Pointage::whereIn('ancien_id', $legacyPointageIds)->pluck('id', 'ancien_id')->toArray();
            $manquants = array_values(array_diff($legacyPointageIds, array_keys($pointagesMap)));
            if (! empty($manquants)) {
                $pointagesMap += VersionPointage::whereIn('ancien_id', $manquants)->pluck('pointage_id', 'ancien_id')->toArray();
            }
            $instancesPointageMap = InstanceParcours::whereIn('pointage_id', array_values($pointagesMap))->get()->keyBy('pointage_id');

            foreach ($historique as $legacyEvent) {
                $instance = null;
                
                if ($legacyEvent->pointage_id) {
                    $pointage_id = $pointagesMap[$legacyEvent->pointage_id] ?? null;
                    if ($pointage_id) {
                        $instance = $instancesPointageMap[$pointage_id] ?? null;
                    }
                } else {
                    $stage_id = $stagesMap[$legacyEvent->contrat_id] ?? null;
                    if ($stage_id) {
                        $instance = $instancesStageMap[$stage_id] ?? null;
                    }
                }

                if ($instance) {
                        $corbeilleCible = $this->mapper->mapStatutStageToCorbeille($legacyEvent->etape_id ?? 1)->value;
                        
                        $etapeCode = strtoupper($corbeilleCible);
                        $etapeNom = str_replace('_', ' ', $etapeCode);

                        $etapeCible = EtapeParcours::firstOrCreate(
                            ['definition_parcours_id' => $instance->definition_parcours_id, 'code' => $etapeCode],
                            ['nom' => $etapeNom, 'initiale' => false, 'finale' => false]
                        );

                        EvenementParcours::updateOrCreate(
                            [
                                'instance_parcours_id' => $instance->id,
                                'cle_idempotence' => 'mig_'.$legacyEvent->id.'_'.$instance->id,
                            ],
                            [
                                'etape_cible_id' => $etapeCible->id,
                                'type' => 'MIGRATION_STATUT',
                                'donnees' => json_encode([
                                    'commentaire' => $legacyEvent->commentaire,
                                    'description' => "Passage à l'étape legacy ID : ".$legacyEvent->etape_id,
                                    'corbeille_cible' => $corbeilleCible,
                                ]),
                                'auteur_id' => 1, // default user
                                'survenu_le' => $this->mapper->normalizeLegacyDate($legacyEvent->created_at ?? null) ?? now(),
                            ]
                        );
                    }
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
    }
}
