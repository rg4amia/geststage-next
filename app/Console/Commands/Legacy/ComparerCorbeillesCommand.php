<?php

namespace App\Console\Commands\Legacy;

use App\Enums\CorbeilleEnum;
use App\Services\Migration\LegacyMapperService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Rapprochement obligatoire du plan de migration (docs/05_PLAN_MIGRATION_LEGACY.md, §5) :
 * mesure l'écart de peuplement entre les corbeilles de l'ancien Gestage (MySQL) et
 * celles de Gestage Next (PostgreSQL).
 *
 * La corbeille legacy d'un dossier n'est pas stockée : elle se déduit de
 * `contrats_pae.etapetraitement_id` et des colonnes d'état Chef d'Agence.
 * C'est exactement ce que fait `LegacyMapperService`, qui est aussi la source
 * utilisée par `migrate:legacy-data` — comparer les deux revient donc à vérifier
 * que la migration a bien posé chaque dossier dans la corbeille attendue.
 *
 * Trois écarts sont distingués :
 * - `manquants`   : le contrat legacy n'a aucun stage cible (donnée perdue) ;
 * - `mal_classes` : le stage existe mais son instance est dans une autre corbeille ;
 * - `orphelins`   : l'instance cible n'a pas de contrat legacy correspondant.
 *
 * Les étapes terminales (30 « stagiaire payé », 31 « stagiaire non payé ») sont
 * exclues du rapprochement : la migration clôt leur instance (`terminee_le`), elles
 * n'appartiennent donc à aucune corbeille active. Elles sont rapprochées à part,
 * pour vérifier qu'elles sont bien clôturées et non laissées dans une corbeille.
 */
class ComparerCorbeillesCommand extends Command
{
    /** Financement PEJEDEC : payé par un circuit distinct de la file DMG classique. */
    private const FINANCEMENT_PEJEDEC = 5;

    /** Origines de stagiaires n'ouvrant aucun droit à paiement. */
    private const ORIGINES_SANS_DROIT_PAIEMENT = [3, 4, 19];

    /** Étape de traitement que PaiementDmgService::attentePaiementValidation() écarte. */
    private const ETAPE_TRAITEMENT_EXCLUE_DMG = 5;

    protected $signature = 'legacy:comparer-corbeilles
        {--corbeille= : Ne comparer que cette corbeille (valeur de CorbeilleEnum)}
        {--details : Affiche les identifiants legacy en écart (limités par --limite)}
        {--limite=20 : Nombre d\'identifiants listés par corbeille avec --details}
        {--json= : Écrit le rapport complet dans ce fichier JSON}
        {--avec-volumetrie : Ajoute le rapprochement de volumétrie table par table}';

    protected $description = "Compare le peuplement des corbeilles entre l'ancien Gestage et Gestage Next";

    /**
     * Colonnes strictement nécessaires au calcul de la corbeille legacy.
     * On évite `SELECT *` : `contrats_pae` porte 154 colonnes pour ~82 000 lignes.
     */
    private const COLONNES_SOURCE = [
        'id', 'etapetraitement_id', 'etat_chef_agence',
        'agent_id', 'avis_contrat', 'file_contrat', 'date_debut', 'deleted_at',
    ];

    /**
     * Rapprochements de volumétrie legacy → cible.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    private const VOLUMETRIE = [
        'Contrats / stages' => ['contrats_pae', 'stages'],
        'Entreprises' => ['entreprises', 'entreprises'],
        'Offres' => ['offre', 'offres_emploi'],
        'Pointages' => ['pointage_models', 'pointages'],
        'Paiements' => ['paiement_models', 'droits_paiement'],
        'Dossiers de paiement' => ['dossiers', 'dossiers_paiement'],
        'Dossiers groupés' => ['multi_dossiers', 'dossiers_groupes'],
        'Ordres de paiement' => ['operations', 'ordre_paiements'],
        'Bordereaux' => ['borderaus', 'bordereau_paiements'],
    ];

    /**
     * Contrats legacy à une étape terminale (30/31) : ils ne doivent apparaître dans
     * aucune corbeille active côté Gestage Next.
     *
     * @var array<int, int>
     */
    private array $closAttendus = [];

    public function __construct(private LegacyMapperService $mapper)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            DB::connection('legacy')->getPdo();
        } catch (Throwable $e) {
            $this->error("Connexion à la base legacy impossible : {$e->getMessage()}");

            return self::FAILURE;
        }

        $filtre = $this->resoudreFiltre();

        if ($filtre === false) {
            return self::INVALID;
        }

        $this->info('Lecture des corbeilles legacy…');
        $attendu = $this->corbeillesAttenduesDepuisLegacy();

        $this->info('Lecture des corbeilles Gestage Next…');
        $reel = $this->corbeillesReellesDepuisCible();

        $rapport = $this->construireRapport($attendu, $reel, $filtre);

        $this->afficherRapport($rapport, $filtre);

        if ($this->option('avec-volumetrie')) {
            $rapport['volumetrie'] = $this->volumetrie();
            $this->afficherVolumetrie($rapport['volumetrie']);
        }

        if ($chemin = $this->option('json')) {
            file_put_contents($chemin, json_encode($rapport, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $this->line("Rapport écrit dans {$chemin}");
        }

        return $rapport['totaux']['ecart_total'] === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @return string|null|false `false` si la valeur fournie est inconnue
     */
    private function resoudreFiltre(): string|null|false
    {
        $valeur = $this->option('corbeille');

        if ($valeur === null) {
            return null;
        }

        if (CorbeilleEnum::tryFrom($valeur) === null) {
            $this->error("Corbeille inconnue : {$valeur}.");
            $this->line('Valeurs possibles : '.implode(', ', array_column(CorbeilleEnum::cases(), 'value')));

            return false;
        }

        return $valeur;
    }

    /**
     * Corbeille attendue pour chaque contrat legacy encore actif.
     *
     * @return array<int, string> ancien_id => code corbeille
     */
    private function corbeillesAttenduesDepuisLegacy(): array
    {
        $attendu = [];

        DB::connection('legacy')->table('contrats_pae')
            ->select(self::COLONNES_SOURCE)
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->chunk(5000, function ($contrats) use (&$attendu): void {
                foreach ($contrats as $contrat) {
                    $ancienId = (int) $contrat->id;

                    // Étape terminale : le dossier est archivé, pas en corbeille.
                    if ($this->mapper->estStatutStageTermine((int) ($contrat->etapetraitement_id ?? 0))) {
                        $this->closAttendus[] = $ancienId;

                        continue;
                    }

                    $attendu[$ancienId] = $this->mapper->mapChefAgenceCorbeille($contrat)->value;
                }
            });

        return $this->appliquerRoutageDmg($attendu);
    }

    /**
     * Applique la règle de l'étape `backfill_corbeilles_dmg` de MigrateLegacyDataCommand.
     *
     * `mapChefAgenceCorbeille()` ne voit que la ligne `contrats_pae` : elle ignore les
     * pointages et ne peut donc pas savoir qu'un dossier validé par le CA attend un paiement.
     * Le legacy, lui, construit sa file DMG à partir du pointage sans regarder l'étape. Sans
     * ce rattrapage, la comparaison signale comme « mal classés » des dossiers que la
     * migration a délibérément — et correctement — placés en corbeille DMG.
     *
     * @param  array<int, string>  $attendu
     * @return array<int, string>
     */
    private function appliquerRoutageDmg(array $attendu): array
    {
        $corbeillesDmg = [
            CorbeilleEnum::DMG_ATTENTE_PAIEMENT_DEMARRAGE->value,
            CorbeilleEnum::DMG_ATTENTE_PAIEMENT_PRESENCE->value,
        ];

        // Hors périmètre de la file DMG classique : PEJEDEC (circuit dédié), origines sans droit
        // à paiement, étape de traitement écartée et contrats non validés par le chef d'agence.
        // Cette comparaison porte sur l'instance du stage, dont le repli est toujours `en_stage` ;
        // seule l'instance du pointage PEJEDEC rejoint `cip_pointage_pejedec`.
        $base = fn () => DB::connection('legacy')->table('contrats_pae')->whereNull('deleted_at');

        $exclus = [];

        $horsDroit = $base()->where(fn ($q) => $q
            ->whereIn('originestagiaire_id', self::ORIGINES_SANS_DROIT_PAIEMENT)
            ->orWhere('etapetraitement_id', self::ETAPE_TRAITEMENT_EXCLUE_DMG)
            ->orWhere('etat_chef_agence', '!=', 2)
            ->orWhere('avis_contrat', '!=', 1)
            ->orWhere('active_chef_agence', '!=', 1));

        foreach ($horsDroit->pluck('id') as $id) {
            $exclus[(int) $id] = CorbeilleEnum::EN_STAGE->value;
        }

        foreach ($base()->where('source_financement', self::FINANCEMENT_PEJEDEC)->pluck('id') as $id) {
            $exclus[(int) $id] = CorbeilleEnum::EN_STAGE->value;
        }

        foreach ($exclus as $ancienId => $repli) {
            if (isset($attendu[$ancienId]) && in_array($attendu[$ancienId], $corbeillesDmg, true)) {
                $attendu[$ancienId] = $repli;
            }
        }

        // Corbeille DMG du pointage impayé le plus récent, par contrat legacy.
        $routage = DB::table('instances_parcours as ip')
            ->join('pointages as p', 'p.id', '=', 'ip.pointage_id')
            ->join('stages as s', 's.id', '=', 'p.stage_id')
            ->join('droits_paiement as d', 'd.pointage_id', '=', 'p.id')
            ->join('paiements as pa', 'pa.droit_paiement_id', '=', 'd.id')
            ->join('periodes as pe', 'pe.id', '=', 'd.periode_id')
            ->whereNull('ip.terminee_le')
            ->whereNull('d.annule_le')
            ->whereNull('s.deleted_at')
            ->whereNotNull('s.ancien_id')
            ->where('pa.statut', 'A_TRAITER')
            ->whereIn('ip.corbeille_actuelle', $corbeillesDmg)
            ->select('s.ancien_id', 'ip.corbeille_actuelle')
            ->orderBy('s.ancien_id')
            ->orderByDesc('pe.date_debut');

        $vus = [];
        foreach ($routage->cursor() as $ligne) {
            $ancienId = (int) $ligne->ancien_id;

            if (isset($vus[$ancienId]) || isset($exclus[$ancienId])) {
                continue;
            }

            $vus[$ancienId] = true;

            // Seuls les dossiers que le mapper laisse en `en_stage` sont routés vers la DMG :
            // on ne masque pas une corbeille CA/CB/DESSE qui porte une décision métier.
            if (($attendu[$ancienId] ?? null) === CorbeilleEnum::EN_STAGE->value) {
                $attendu[$ancienId] = $ligne->corbeille_actuelle;
            }
        }

        return $attendu;
    }

    /**
     * Rapprochement des dossiers archivés : combien sont effectivement clôturés côté
     * cible, et combien traînent encore dans une corbeille active.
     *
     * @return array{legacy: int, clotures: int, encore_en_corbeille: int, absents: int}
     */
    private function rapprocherDossiersClos(): array
    {
        $clotures = 0;
        $encoreEnCorbeille = 0;
        $trouves = 0;

        foreach (array_chunk($this->closAttendus, 5000) as $lot) {
            $instances = DB::table('stages')
                ->join('instances_parcours', 'instances_parcours.stage_id', '=', 'stages.id')
                ->whereIn('stages.ancien_id', $lot)
                ->whereNull('stages.deleted_at')
                ->select('stages.ancien_id', 'instances_parcours.terminee_le')
                ->get();

            foreach ($instances as $instance) {
                $trouves++;
                $instance->terminee_le === null ? $encoreEnCorbeille++ : $clotures++;
            }
        }

        return [
            'legacy' => count($this->closAttendus),
            'clotures' => $clotures,
            'encore_en_corbeille' => $encoreEnCorbeille,
            'absents' => count($this->closAttendus) - $trouves,
        ];
    }

    /**
     * Corbeille réelle de chaque stage migré, indexée par identifiant legacy.
     *
     * @return array<int, string> ancien_id => code corbeille
     */
    private function corbeillesReellesDepuisCible(): array
    {
        return DB::table('stages')
            ->join('instances_parcours', 'instances_parcours.stage_id', '=', 'stages.id')
            ->whereNotNull('stages.ancien_id')
            ->whereNull('stages.deleted_at')
            ->whereNull('instances_parcours.terminee_le')
            ->pluck('instances_parcours.corbeille_actuelle', 'stages.ancien_id')
            ->map(fn ($corbeille) => (string) $corbeille)
            ->all();
    }

    /**
     * @param  array<int, string>  $attendu
     * @param  array<int, string>  $reel
     * @return array<string, mixed>
     */
    private function construireRapport(array $attendu, array $reel, ?string $filtre): array
    {
        $lignes = [];

        foreach (CorbeilleEnum::cases() as $corbeille) {
            $lignes[$corbeille->value] = [
                'libelle' => $corbeille->label(),
                'legacy' => 0,
                'next' => 0,
                'conformes' => 0,
                'manquants' => 0,
                'mal_classes' => 0,
                'ids_manquants' => [],
                'ids_mal_classes' => [],
            ];
        }

        foreach ($attendu as $ancienId => $codeAttendu) {
            $lignes[$codeAttendu]['legacy']++;

            $codeReel = $reel[$ancienId] ?? null;

            if ($codeReel === null) {
                $lignes[$codeAttendu]['manquants']++;
                $lignes[$codeAttendu]['ids_manquants'][] = $ancienId;

                continue;
            }

            if ($codeReel === $codeAttendu) {
                $lignes[$codeAttendu]['conformes']++;

                continue;
            }

            $lignes[$codeAttendu]['mal_classes']++;
            $lignes[$codeAttendu]['ids_mal_classes'][] = ['ancien_id' => $ancienId, 'trouve_dans' => $codeReel];
        }

        foreach ($reel as $codeReel) {
            if (isset($lignes[$codeReel])) {
                $lignes[$codeReel]['next']++;
            }
        }

        // Une instance sans contrat legacy actif correspondant est un orphelin :
        // soit le contrat a été supprimé côté legacy, soit l'instance a été fabriquée.
        $orphelins = count(array_diff_key($reel, $attendu));

        if ($filtre !== null) {
            $lignes = array_intersect_key($lignes, [$filtre => true]);
        }

        $totaux = [
            'contrats_legacy_actifs' => count($attendu),
            'stages_avec_instance' => count($reel),
            'conformes' => array_sum(array_column($lignes, 'conformes')),
            'manquants' => array_sum(array_column($lignes, 'manquants')),
            'mal_classes' => array_sum(array_column($lignes, 'mal_classes')),
            'orphelins' => $orphelins,
        ];
        $clos = $this->rapprocherDossiersClos();

        // Un dossier archivé encore posé dans une corbeille active est un écart réel :
        // le legacy ne l'affiche plus nulle part.
        $totaux['ecart_total'] = $totaux['manquants'] + $totaux['mal_classes'] + $clos['encore_en_corbeille'];

        return ['corbeilles' => $lignes, 'totaux' => $totaux, 'dossiers_clos' => $clos];
    }

    /**
     * @param  array<string, mixed>  $rapport
     */
    private function afficherRapport(array $rapport, ?string $filtre): void
    {
        $lignes = [];

        foreach ($rapport['corbeilles'] as $code => $donnees) {
            if ($filtre === null && $donnees['legacy'] === 0 && $donnees['next'] === 0) {
                continue;
            }

            $lignes[] = [
                $code,
                $donnees['legacy'],
                $donnees['next'],
                $donnees['conformes'],
                $donnees['manquants'] ?: '-',
                $donnees['mal_classes'] ?: '-',
            ];
        }

        $this->newLine();
        $this->info('Rapprochement des corbeilles legacy → Gestage Next');
        $this->table(
            ['Corbeille', 'Legacy', 'Next', 'Conformes', 'Manquants', 'Mal classés'],
            $lignes
        );

        $totaux = $rapport['totaux'];
        $this->line("Contrats legacy actifs : {$totaux['contrats_legacy_actifs']}");
        $this->line("Stages avec instance   : {$totaux['stages_avec_instance']}");
        $this->line("Conformes              : {$totaux['conformes']}");
        $this->line("Manquants              : {$totaux['manquants']}");
        $this->line("Mal classés            : {$totaux['mal_classes']}");
        $this->line("Orphelins côté Next    : {$totaux['orphelins']}");

        $clos = $rapport['dossiers_clos'];
        $this->newLine();
        $this->line("Dossiers archivés legacy (étapes 30/31) : {$clos['legacy']}");
        $this->line("  clôturés côté Next                    : {$clos['clotures']}");
        $this->line("  encore dans une corbeille active      : {$clos['encore_en_corbeille']}");
        $this->line("  sans stage cible                      : {$clos['absents']}");

        if ($totaux['ecart_total'] === 0) {
            $this->info('Aucun écart : les corbeilles sont alignées.');
        } else {
            $this->warn("Écart total : {$totaux['ecart_total']} dossier(s).");
        }

        if ($this->option('details')) {
            $this->afficherDetails($rapport['corbeilles']);
        }
    }

    /**
     * @param  array<string, mixed>  $corbeilles
     */
    private function afficherDetails(array $corbeilles): void
    {
        $limite = max(1, (int) $this->option('limite'));

        foreach ($corbeilles as $code => $donnees) {
            if ($donnees['manquants'] === 0 && $donnees['mal_classes'] === 0) {
                continue;
            }

            $this->newLine();
            $this->warn($code);

            if ($donnees['ids_manquants'] !== []) {
                $extrait = array_slice($donnees['ids_manquants'], 0, $limite);
                $this->line('  manquants   : '.implode(', ', $extrait).($donnees['manquants'] > $limite ? ' …' : ''));
            }

            foreach (array_slice($donnees['ids_mal_classes'], 0, $limite) as $ecart) {
                $this->line("  mal classé  : {$ecart['ancien_id']} → {$ecart['trouve_dans']}");
            }
        }
    }

    /**
     * @return array<string, array{legacy: int, next: int, ecart: int}>
     */
    private function volumetrie(): array
    {
        $resultat = [];

        foreach (self::VOLUMETRIE as $libelle => [$tableLegacy, $tableCible]) {
            $legacy = (int) DB::connection('legacy')->table($tableLegacy)->count();
            $next = (int) DB::table($tableCible)->count();

            $resultat[$libelle] = ['legacy' => $legacy, 'next' => $next, 'ecart' => $next - $legacy];
        }

        return $resultat;
    }

    /**
     * @param  array<string, array{legacy: int, next: int, ecart: int}>  $volumetrie
     */
    private function afficherVolumetrie(array $volumetrie): void
    {
        $this->newLine();
        $this->info('Rapprochement de volumétrie');
        $this->table(
            ['Domaine', 'Legacy', 'Next', 'Écart'],
            collect($volumetrie)->map(fn (array $l, string $nom) => [$nom, $l['legacy'], $l['next'], $l['ecart']])->values()->all()
        );
    }
}
