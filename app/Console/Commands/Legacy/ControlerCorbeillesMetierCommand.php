<?php

namespace App\Console\Commands\Legacy;

use App\Domain\Payment\Services\DmgService;
use App\Domain\Workflow\Services\DesseDoublonService;
use App\Enums\CorbeilleEnum;
use App\Models\Contract\Contrat;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Contrôle croisé « requête contre requête » des corbeilles à enjeu financier.
 *
 * `legacy:comparer-corbeilles` vérifie que la migration a rangé chaque dossier là
 * où `LegacyMapperService` l'attend. Ce contrôle-ci est indépendant : il rejoue les
 * requêtes réellement servies par l'ancien Gestage, puis les confronte aux requêtes
 * réellement servies par Gestage Next. Un écart signale donc soit une donnée non
 * migrée, soit une règle métier divergente entre les deux applications.
 *
 * Requêtes legacy rejouées :
 * - Chef d'Agence  : WaitCheckedChefAgenceService::stagiaireWaitValidation()
 * - DMG démarrage  : PaiementDmgService::attentePaiementValidation()
 *                    + ContratsPae::scopeAttestationDemarrage() + scopeSansPaiement()
 * - DMG présence   : idem avec ContratsPae::scopeAttestationPresence()
 *
 * Les filtres liés à l'utilisateur connecté (scope `mine()`, périmètre d'agence)
 * sont volontairement omis des deux côtés : on compare des périmètres complets.
 */
class ControlerCorbeillesMetierCommand extends Command
{
    protected $signature = 'legacy:controler-corbeilles-metier
        {--mois= : Mois de pointage au format Y-m pour les corbeilles DMG (défaut : mois courant)}
        {--details : Liste les identifiants legacy en écart}
        {--limite=20 : Nombre d\'identifiants listés par écart}
        {--json= : Écrit le rapport dans ce fichier JSON}';

    protected $description = "Rejoue les requêtes de corbeilles de l'ancien Gestage et les confronte à celles de Gestage Next";

    /** Financement PEJEDEC : traité par un circuit distinct, exclu des corbeilles DMG classiques. */
    private const FINANCEMENT_PEJEDEC = 5;

    /** Origines exclues du paiement (ContratsPae::scopeSansPaiement). */
    private const ORIGINES_SANS_PAIEMENT = [4, 3, 19];

    /** DESSE : ajournement pour doublon avéré, hors circuit de paiement. */
    private const ETAPE_DESSE_AJOURNE = 5;

    /** Étapes exemptées du contrôle de doublons (DoublonDetectionService::applyDoublonFilters). */
    private const ETAPES_HORS_CONTROLE_DOUBLON = [6, 8];

    private const CHAMP_DOUBLON_TYPE_STAGE_CMU = 'typestage_cmu';

    /** Champs de détection des doublons (DoublonDetectionService::DOUBLON_TYPES). */
    private const CHAMPS_DOUBLON = [
        'numero_aej', 'numero_cmu', 'num_piece', 'contact_stage_full',
        'doublon_full', 'doublon_yup', 'doublon_wave', self::CHAMP_DOUBLON_TYPE_STAGE_CMU,
    ];

    /** Champs retenus pour les renouvellements (DOUBLON_TYPES_RENOUVELLEMENT). */
    private const CHAMPS_DOUBLON_RENOUVELLEMENT = [
        'numero_aej', 'numero_cmu', 'num_piece', 'doublon_yup', self::CHAMP_DOUBLON_TYPE_STAGE_CMU,
    ];

    public function __construct(private DmgService $dmg)
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

        $mois = $this->resoudreMois();

        if ($mois === null) {
            return self::INVALID;
        }

        $this->info("Contrôle croisé des corbeilles (mois DMG : {$mois})");

        $controles = [
            CorbeilleEnum::CA_ATTENTE_VALIDATION_DEMARRAGE->value => [
                'legacy' => fn () => $this->legacyChefAgence('demarrage'),
                'next' => fn () => $this->nextInstances(CorbeilleEnum::CA_ATTENTE_VALIDATION_DEMARRAGE),
            ],
            CorbeilleEnum::CA_ATTENTE_VALIDATION_OMIS->value => [
                'legacy' => fn () => $this->legacyChefAgence('omis'),
                'next' => fn () => $this->nextInstances(CorbeilleEnum::CA_ATTENTE_VALIDATION_OMIS),
            ],
            CorbeilleEnum::CA_RETOUR_AJOURNEMENT->value => [
                'legacy' => fn () => $this->legacyChefAgenceAjournes(),
                'next' => fn () => $this->nextInstances(CorbeilleEnum::CA_RETOUR_AJOURNEMENT),
            ],
            CorbeilleEnum::DMG_ATTENTE_PAIEMENT_DEMARRAGE->value => [
                'legacy' => fn () => $this->legacyDmgDemarrage($mois),
                'next' => fn () => $this->nextDmg('demarrage', $mois),
            ],
            CorbeilleEnum::DMG_ATTENTE_PAIEMENT_PRESENCE->value => [
                'legacy' => fn () => $this->legacyDmgPresence($mois),
                'next' => fn () => $this->nextDmg('presence', $mois),
            ],
        ];

        $rapport = ['mois' => $mois, 'corbeilles' => []];
        $lignes = [];
        $ecartTotal = 0;

        foreach ($controles as $code => $sources) {
            $legacy = $sources['legacy']();
            $next = $sources['next']();

            $seulementLegacy = array_values(array_diff($legacy, $next));
            $seulementNext = array_values(array_diff($next, $legacy));

            // Les deux applications appliquent le même pare-feu doublons, mais pas sur les mêmes
            // clés (5 types côté Next, 8 champs côté legacy). On isole donc, de chaque côté, les
            // dossiers que l'autre pare-feu retient : l'écart résiduel ne désigne alors que ce
            // qui reste vraiment à expliquer.
            $bloquesNext = $this->doublonsNext($seulementLegacy);
            $seulementLegacy = array_values(array_diff($seulementLegacy, $bloquesNext));

            $bloquesLegacy = $this->doublonsLegacyParmi($seulementNext);
            $seulementNext = array_values(array_diff($seulementNext, $bloquesLegacy));

            $ecartTotal += count($seulementLegacy) + count($seulementNext);

            $rapport['corbeilles'][$code] = [
                'legacy' => count($legacy),
                'next' => count($next),
                'communs' => count(array_intersect($legacy, $next)),
                'bloques_pare_feu_next' => $bloquesNext,
                'bloques_pare_feu_legacy' => $bloquesLegacy,
                'seulement_legacy' => $seulementLegacy,
                'seulement_next' => $seulementNext,
            ];

            $lignes[] = [
                $code,
                count($legacy),
                count($next),
                count(array_intersect($legacy, $next)),
                count($bloquesNext) ?: '-',
                count($seulementLegacy) ?: '-',
                count($bloquesLegacy) ?: '-',
                count($seulementNext) ?: '-',
            ];
        }

        $this->newLine();
        $this->table(
            ['Corbeille', 'Legacy', 'Next', 'Communs', 'Bloqué Next', 'Legacy seul', 'Bloqué legacy', 'Next seul'],
            $lignes
        );

        if ($ecartTotal === 0) {
            $this->info('Aucun écart inexpliqué : les deux applications servent le même périmètre.');
        } else {
            $this->warn("Écart total : {$ecartTotal} dossier(s), hors doublons bloqués par l'un des deux pare-feux.");
        }

        if ($this->option('details')) {
            $this->afficherDetails($rapport['corbeilles']);
        }

        if ($chemin = $this->option('json')) {
            file_put_contents($chemin, json_encode($rapport, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $this->line("Rapport écrit dans {$chemin}");
        }

        return $ecartTotal === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function resoudreMois(): ?string
    {
        $mois = $this->option('mois') ?: Carbon::now()->format('Y-m');

        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $mois) !== 1) {
            $this->error("Mois invalide : {$mois}. Format attendu : Y-m (ex. 2026-08).");

            return null;
        }

        return $mois;
    }

    // ────────────────────────────────────────────────────────────────────
    //  Côté legacy — rejeu des requêtes de l'ancien Gestage
    // ────────────────────────────────────────────────────────────────────

    /**
     * WaitCheckedChefAgenceService::stagiaireWaitValidation(), sans le périmètre
     * utilisateur. Le legacy sépare « démarrage » et « démarrage omis » sur le mois
     * de `date_debut` comparé au mois courant.
     *
     * @return array<int, int>
     */
    private function legacyChefAgence(string $onglet): array
    {
        $moisCourant = Carbon::now()->format('Y-m');

        $query = DB::connection('legacy')->table('contrats_pae')
            ->whereNull('deleted_at')
            ->where('etat_chef_agence', 0)
            ->where('agent_id', 3)
            ->where('avis_contrat', 1)
            ->whereNotNull('file_contrat')
            ->whereIn('etapetraitement_id', [1, 4]);

        if ($onglet === 'omis') {
            $query->whereNotNull('date_debut')
                ->whereRaw("DATE_FORMAT(date_debut, '%Y-%m') < ?", [$moisCourant]);
        } else {
            $query->where(function (Builder $q) use ($moisCourant): void {
                $q->whereNull('date_debut')
                    ->orWhereRaw("DATE_FORMAT(date_debut, '%Y-%m') >= ?", [$moisCourant]);
            });
        }

        return $query->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * Onglet « stagiaires ajournées » du Chef d'Agence
     * (IndexChefAgenceController legacy) : la vue ne retient que les dossiers
     * renvoyés au CIP par le CA, soit `etapetraitement_id=2 AND etat_chef_agence=1`.
     * Aucune autre combinaison n'alimente cette corbeille côté legacy — surtout pas
     * la seule présence d'une `date_chef_agence`.
     *
     * @return array<int, int>
     */
    private function legacyChefAgenceAjournes(): array
    {
        return DB::connection('legacy')->table('contrats_pae')
            ->whereNull('deleted_at')
            ->where('etapetraitement_id', 2)
            ->where('etat_chef_agence', 1)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * PaiementDmgService::attentePaiementValidation() + scopeAttestationDemarrage()
     * + `etatrenouvellement_id != 1` + scopeSansPaiement().
     *
     * @return array<int, int>
     */
    private function legacyDmgDemarrage(string $mois): array
    {
        $date = Carbon::createFromFormat('!Y-m', $mois);

        return $this->legacyDmgBase($mois)
            ->whereYear('date_debut', $date->year)
            ->whereMonth('date_debut', $date->month)
            ->where('etatrenouvellement_id', '!=', 1)
            ->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * PaiementDmgService::attentePaiementValidation() + scopeAttestationPresence()
     * + scopeSansPaiement().
     *
     * @return array<int, int>
     */
    private function legacyDmgPresence(string $mois): array
    {
        $annee = substr($mois, 0, 4);
        $numeroMois = substr($mois, -2);

        return $this->legacyDmgBase($mois)
            ->where(function (Builder $q) use ($annee, $numeroMois): void {
                // Renouvellements actifs : toutes les périodes sont éligibles.
                $q->where(function (Builder $renouvele) use ($annee, $numeroMois): void {
                    $renouvele->where('etatrenouvellement_id', 1)
                        ->where(function (Builder $periode) use ($annee, $numeroMois): void {
                            $periode->where(function (Builder $dansLeMois) use ($annee, $numeroMois): void {
                                $dansLeMois->whereYear('date_debut', $annee)
                                    ->whereMonth('date_debut', $numeroMois);
                            })->orWhere(function (Builder $horsDuMois) use ($annee, $numeroMois): void {
                                $horsDuMois->whereYear('date_debut', '!=', $annee)
                                    ->orWhereMonth('date_debut', '!=', $numeroMois);
                            });
                        });
                })
                    // Hors renouvellement : le démarrage du mois relève de la corbeille Démarrage.
                    ->orWhere(function (Builder $simple) use ($annee, $numeroMois): void {
                        $simple->where('etatrenouvellement_id', 0)
                            ->where(function (Builder $horsDuMois) use ($annee, $numeroMois): void {
                                $horsDuMois->whereYear('date_debut', '!=', $annee)
                                    ->orWhereMonth('date_debut', '!=', $numeroMois);
                            });
                    });
            })
            ->where('date_debut', '<=', $mois.'-31')
            ->where('date_fin', '>=', $mois.'-01')
            ->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * Tronc commun des deux corbeilles DMG : pointage validé par le CIP puis par le
     * Chef d'Agence sur le mois, dossier non encore engagé dans un état de paiement.
     */
    private function legacyDmgBase(string $mois): Builder
    {
        return DB::connection('legacy')->table('contrats_pae')
            ->whereNull('deleted_at')
            ->where('etapetraitement_id', '!=', self::ETAPE_DESSE_AJOURNE)
            ->where('etat_chef_agence', 2)
            ->where('avis_contrat', 1)
            ->where('active_chef_agence', 1)
            ->where('source_financement', '!=', self::FINANCEMENT_PEJEDEC)
            ->whereNotIn('originestagiaire_id', self::ORIGINES_SANS_PAIEMENT)
            ->whereExists(function (Builder $pointage) use ($mois): void {
                // `pointage_models` utilise SoftDeletes côté legacy (scope Eloquent implicite sur
                // la relation `mespointages`) : sans ce filtre, un pointage corrigé puis resupprimé
                // logiquement se faisait quand même compter ici, créant un faux « legacy seul ».
                $pointage->selectRaw('1')
                    ->from('pointage_models')
                    ->whereColumn('pointage_models.stagiaire_id', 'contrats_pae.id')
                    ->where('pointage_models.mois', $mois)
                    ->where('pointage_models.situationstage_id', 1)
                    ->where('pointage_models.status_cip', 1)
                    ->where('pointage_models.status_ca', 1)
                    ->whereNotNull('pointage_models.date_ca')
                    ->whereNull('pointage_models.deleted_at');
            })
            ->where(function (Builder $paiement) use ($mois): void {
                // Idem pour `paiement_models` (relation `mespaiements`, elle aussi SoftDeletes).
                $paiement->whereNotExists(function (Builder $aucun) use ($mois): void {
                    $aucun->selectRaw('1')
                        ->from('paiement_models')
                        ->whereColumn('paiement_models.stagiaire_id', 'contrats_pae.id')
                        ->where('paiement_models.mois', $mois)
                        ->whereNull('paiement_models.deleted_at');
                })->orWhereExists(function (Builder $enAttente) use ($mois): void {
                    $enAttente->selectRaw('1')
                        ->from('paiement_models')
                        ->whereColumn('paiement_models.stagiaire_id', 'contrats_pae.id')
                        ->where('paiement_models.mois', $mois)
                        ->where('paiement_models.status_dmg', 2)
                        ->where('paiement_models.status_cb', 0)
                        ->whereNull('paiement_models.dossier_id')
                        ->whereNull('paiement_models.created_by_cb')
                        ->whereNull('paiement_models.date_vise_cb')
                        ->whereNull('paiement_models.deleted_at');
                });
            })
            ->where(fn (Builder $doublons) => $this->appliquerFiltreDoublonsLegacy($doublons));
    }

    /**
     * Réplique DoublonDetectionService::applyDoublonFilters() tel que la file DMG l'appelle
     * (`exclude_etapes` 6/8, renouvellements et dossiers déjà vérifiés inclus).
     *
     * Un dossier passe s'il est à une étape exemptée, si la DESSE l'a déjà tranché
     * (`doubloncheck != 0`), s'il est en renouvellement sans dépasser deux lignes, ou s'il
     * n'est simplement pas en doublon.
     */
    private function appliquerFiltreDoublonsLegacy(Builder $query): Builder
    {
        $normaux = $this->doublonsLegacy(1);
        $renouvellement = $this->doublonsLegacy(2, self::CHAMPS_DOUBLON_RENOUVELLEMENT);

        return $query
            ->whereIn('etapetraitement_id', self::ETAPES_HORS_CONTROLE_DOUBLON)
            ->orWhere('doubloncheck', '!=', 0)
            ->orWhere(function (Builder $renouvele) use ($renouvellement): void {
                $renouvele->where('etatrenouvellement_id', '!=', 0);
                $this->exclureValeursDoublon($renouvele, $renouvellement);
            })
            ->orWhere(function (Builder $normal) use ($normaux): void {
                $normal->where('etatrenouvellement_id', 0)
                    ->where('doubloncheck', 0)
                    ->whereNotIn('etapetraitement_id', self::ETAPES_HORS_CONTROLE_DOUBLON);
                $this->exclureValeursDoublon($normal, $normaux);
            });
    }

    /**
     * Valeurs présentes plus de `$minimum` fois, par champ de détection.
     * Équivalent legacy : DoublonDetectionService::getDoublonsByField().
     *
     * @param  array<int, string>  $champs
     * @return array<string, array<int, string>>
     */
    private function doublonsLegacy(int $minimum, array $champs = self::CHAMPS_DOUBLON): array
    {
        $valeurs = [];

        foreach ($champs as $champ) {
            $expression = $champ === self::CHAMP_DOUBLON_TYPE_STAGE_CMU
                ? "CONCAT(id_type_stage, '|', TRIM(LOWER(numero_cmu)))"
                : $champ;

            $requete = DB::connection('legacy')->table('contrats_pae')
                ->selectRaw("{$expression} as cle")
                ->whereNull('deleted_at')
                ->where('etapetraitement_id', '>=', 2)
                ->whereNotIn('etapetraitement_id', self::ETAPES_HORS_CONTROLE_DOUBLON)
                ->groupByRaw($expression)
                ->havingRaw('COUNT(*) > ?', [$minimum]);

            if ($champ === self::CHAMP_DOUBLON_TYPE_STAGE_CMU) {
                $requete->whereNotNull('id_type_stage')->whereNotNull('numero_cmu')->where('numero_cmu', '!=', '');
            } else {
                $requete->whereNotNull($champ)->where($champ, '!=', '');
            }

            $valeurs[$champ] = $requete->pluck('cle')->all();
        }

        return $valeurs;
    }

    /**
     * @param  array<string, array<int, string>>  $valeurs
     */
    private function exclureValeursDoublon(Builder $query, array $valeurs): void
    {
        foreach ($valeurs as $champ => $liste) {
            if ($liste === []) {
                continue;
            }

            if ($champ === self::CHAMP_DOUBLON_TYPE_STAGE_CMU) {
                // `CONCAT` vaut NULL dès qu'une des deux colonnes l'est, et `NULL NOT IN (...)`
                // n'est jamais vrai : sans ces trois échappatoires, tout dossier sans type de
                // stage ou sans numéro CMU serait pris pour un doublon.
                $query->where(fn (Builder $q) => $q
                    ->whereNull('id_type_stage')
                    ->orWhereNull('numero_cmu')
                    ->orWhere('numero_cmu', '')
                    ->orWhereNotIn(DB::raw("CONCAT(id_type_stage, '|', TRIM(LOWER(numero_cmu)))"), $liste));

                continue;
            }

            $query->where(fn (Builder $q) => $q->whereNull($champ)->orWhereNotIn($champ, $liste));
        }
    }

    // ────────────────────────────────────────────────────────────────────
    //  Côté Gestage Next — requêtes réellement servies par l'application
    // ────────────────────────────────────────────────────────────────────

    /**
     * @return array<int, int>
     */
    private function nextInstances(CorbeilleEnum $corbeille): array
    {
        return DB::table('instances_parcours')
            ->join('stages', 'stages.id', '=', 'instances_parcours.stage_id')
            ->where('instances_parcours.corbeille_actuelle', $corbeille->value)
            ->whereNull('instances_parcours.terminee_le')
            ->whereNull('stages.deleted_at')
            ->whereNotNull('stages.ancien_id')
            ->pluck('stages.ancien_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Parmi des dossiers servis par le legacy, ceux que le pare-feu doublons retient côté Next.
     *
     * Les deux applications bloquent les doublons dans leurs files de paiement ; ce reliquat
     * mesure ce que Next détecte en plus, ses clés (nom/prénoms/date de naissance, diplôme,
     * compte de paiement) n'ayant pas toutes d'équivalent dans les champs du legacy.
     *
     * @param  array<int, int>  $anciensIds  `contrats_pae.id`
     * @return array<int, int>
     */
    private function doublonsNext(array $anciensIds): array
    {
        if ($anciensIds === []) {
            return [];
        }

        $survivants = Contrat::query()->whereHas('stage', fn ($s) => $s->whereIn('ancien_id', $anciensIds));
        app(DesseDoublonService::class)->applyDuplicateExclusionFilter($survivants, 'stage');

        $conserves = $survivants->join('stages', 'stages.id', '=', 'contrats.stage_id')
            ->pluck('stages.ancien_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return array_values(array_diff($anciensIds, $conserves));
    }

    /**
     * Parmi des dossiers servis par Gestage Next, ceux que le pare-feu doublons de l'ancien
     * Gestage retient (miroir de doublonsNext(), sur les champs de `contrats_pae`).
     *
     * @param  array<int, int>  $anciensIds  `contrats_pae.id`
     * @return array<int, int>
     */
    private function doublonsLegacyParmi(array $anciensIds): array
    {
        if ($anciensIds === []) {
            return [];
        }

        $conserves = DB::connection('legacy')->table('contrats_pae')
            ->whereNull('deleted_at')
            ->whereIn('id', $anciensIds)
            ->where(fn (Builder $doublons) => $this->appliquerFiltreDoublonsLegacy($doublons))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return array_values(array_diff($anciensIds, $conserves));
    }

    /**
     * @return array<int, int>
     */
    private function nextDmg(string $nature, string $mois): array
    {
        $query = $nature === 'demarrage'
            ? $this->dmg->attentePaiementDemarrage([], $mois)
            : $this->dmg->attentePaiementPresence([], $mois);

        return $query->with('droitPaiement.stage:id,ancien_id')
            ->get()
            ->map(fn ($paiement) => $paiement->droitPaiement?->stage?->ancien_id)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $corbeilles
     */
    private function afficherDetails(array $corbeilles): void
    {
        $limite = max(1, (int) $this->option('limite'));

        foreach ($corbeilles as $code => $donnees) {
            if ($donnees['seulement_legacy'] === [] && $donnees['seulement_next'] === []) {
                continue;
            }

            $this->newLine();
            $this->warn($code);

            foreach (['seulement_legacy' => 'legacy seul', 'seulement_next' => 'next seul  '] as $cle => $etiquette) {
                if ($donnees[$cle] === []) {
                    continue;
                }

                $extrait = array_slice($donnees[$cle], 0, $limite);
                $suite = count($donnees[$cle]) > $limite ? ' …' : '';
                $this->line("  {$etiquette} : ".implode(', ', $extrait).$suite);
            }
        }
    }
}
