<?php

namespace App\Domain\Supervision\Services;

use App\Enums\CorbeilleEnum;
use App\Enums\VisaDesseEnum;
use App\Models\Internship\Stage;
use App\Models\Payment\Paiement;
use App\Models\Reference\Agence;
use App\Models\Reference\TypeStage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Supervision régionale des dossiers : visa DESSE, dossiers validés par l'agence
 * régionale, stagiaires différés par l'agent comptable, extractions de suivi et
 * tableau statistique par agence.
 *
 * Portage des écrans legacy `Validation_Stagiaire_Desse`, `Liste_Stagiaires_Rejetes_Desse`,
 * `stagiaire_passe_etape_desse`, `liste-stagiaire-pae`, `daicg/stagiaire-valider-par-*`,
 * `cip/differed-by-agent-comptable`, `desse/suivie[-ar]/stagiaire-saved` et
 * `Tableau_Statistique`.
 *
 * Une seule source de vérité pour le statut DESSE : `stages.visa_desse`. Le visa est une
 * supervision parallèle, pas une étape bloquante : les 63 890 dossiers legacy en attente
 * poursuivaient déjà leur pointage, d'où une colonne de stage et non une corbeille.
 *
 * Le filtre legacy `agent_id = 3` n'est pas porté : il vaut 3 sur la totalité des
 * `contrats_pae`, c'est une constante et non un critère.
 */
class VisaRegionalService
{
    /**
     * Corbeilles portant un stagiaire différé par l'agent comptable. Le différé n'a pas de
     * drapeau dédié : il est porté par le couple statut `A_TRAITER` + corbeille, ce
     * qu'écrivent AgentComptableService::differerStagiaires et la phase de reprise
     * `backfill_stagiaires_differes_ac`.
     */
    private const CORBEILLES_DIFFERE_AC = [
        CorbeilleEnum::DMG_OP_DIFFERE_AC,
        CorbeilleEnum::CIP_DIFFERE_AC,
        CorbeilleEnum::CA_STAGIAIRE_DIFFERE_AC,
    ];

    private const STATUT_DIFFERE_AC = 'A_TRAITER';

    /**
     * Rôles à vision nationale : la DESSE et la DAICG supervisent toutes les agences,
     * les autres profils restent bornés à leur périmètre d'agences.
     */
    private const ROLES_VISION_NATIONALE = ['administrateur', 'desse', 'daicg'];

    private const EAGER_LOADS = [
        'beneficiaire',
        'entreprise.typeStructure',
        'agence',
        'sourceFinancement',
        'typeStage',
        'visaDessePar',
        'instanceParcours',
    ];

    // ─────────────────────────────────────────────────────────────────────
    //  Corbeilles de visa (stages.visa_desse)
    // ─────────────────────────────────────────────────────────────────────

    /** @param array<string, mixed> $filtres */
    public function attenteQuery(array $filtres = []): Builder
    {
        return $this->baseQuery($filtres)->where('visa_desse', VisaDesseEnum::EN_ATTENTE->value);
    }

    /** @param array<string, mixed> $filtres */
    public function rejetesQuery(array $filtres = []): Builder
    {
        return $this->baseQuery($filtres)->where('visa_desse', VisaDesseEnum::REJETE->value);
    }

    /** @param array<string, mixed> $filtres */
    public function visesQuery(array $filtres = []): Builder
    {
        return $this->baseQuery($filtres)->where('visa_desse', VisaDesseEnum::VISE->value);
    }

    /**
     * Dossiers validés par l'agence régionale (legacy `liste-stagiaire-pae` :
     * `active_chef_agence = 1 AND etat_chef_agence = 2`, et `daicg/stagiaire-valider-par-chef-agence`).
     *
     * Côté Gestage Next l'état n'est plus porté par deux drapeaux mais par la donnée
     * canonisée : un stage n'a de `visa_desse` que si le chef d'agence a validé son
     * démarrage (cf. VisaDesseEnum et la phase `backfill_visa_desse`). Les dossiers encore
     * dans une corbeille amont — CIP, attente ou retour chef d'agence — sont exclus, car
     * une réouverture par le CIP remet le dossier en attente de validation.
     *
     * @param  array<string, mixed>  $filtres
     */
    public function validesArQuery(array $filtres = []): Builder
    {
        return $this->baseQuery($filtres)
            ->whereNotNull('visa_desse')
            ->whereDoesntHave('instanceParcours', function (Builder $query): void {
                $query->whereNull('terminee_le')
                    ->whereIn('corbeille_actuelle', CorbeilleEnum::nonValideesParCa());
            });
    }

    // ─────────────────────────────────────────────────────────────────────
    //  Extractions de suivi DESSE (corbeilles de parcours)
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Extraction « stagiaires enregistrés » (legacy `desse/suivie/stagiaire-saved`).
     *
     * @param  array<string, mixed>  $filtres
     */
    public function suiviEnregistresQuery(array $filtres = []): Builder
    {
        return $this->corbeilleQuery(CorbeilleEnum::DESSE_SUIVI_ENREGISTRES, $filtres);
    }

    /**
     * Extraction « stagiaires validés agence régionale » (legacy `desse/suivie-ar/stagiaire-saved`).
     *
     * @param  array<string, mixed>  $filtres
     */
    public function suiviValidesArQuery(array $filtres = []): Builder
    {
        return $this->corbeilleQuery(CorbeilleEnum::DESSE_SUIVI_VALIDES_AR, $filtres);
    }

    /** @param array<string, mixed> $filtres */
    private function corbeilleQuery(CorbeilleEnum $corbeille, array $filtres): Builder
    {
        return $this->baseQuery($filtres)
            ->whereHas('instanceParcours', function (Builder $query) use ($corbeille): void {
                $query->whereNull('terminee_le')
                    ->where('corbeille_actuelle', $corbeille->value);
            });
    }

    // ─────────────────────────────────────────────────────────────────────
    //  Stagiaires différés par l'agent comptable
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Stagiaires différés par l'AC (legacy `cip/differed-by-agent-comptable`).
     *
     * Le workflow de paiement reste chez les services de paiement : cet écran ne fait que
     * lire les paiements différés pour les remonter à la supervision régionale.
     *
     * @param  array<string, mixed>  $filtres
     */
    public function differesAcQuery(array $filtres = []): Builder
    {
        $corbeilles = array_map(fn (CorbeilleEnum $c): string => $c->value, self::CORBEILLES_DIFFERE_AC);

        return Paiement::query()
            ->with([
                'droitPaiement.stage.beneficiaire',
                'droitPaiement.stage.entreprise',
                'droitPaiement.stage.agence',
                'droitPaiement.stage.sourceFinancement',
                'droitPaiement.stage.typeStage',
                'droitPaiement.periode',
                'decisions',
            ])
            ->where('statut', self::STATUT_DIFFERE_AC)
            ->whereIn('corbeille_actuelle', $corbeilles)
            ->whereHas('droitPaiement.stage', fn (Builder $q) => $this->appliquerFiltresStage($q, $filtres))
            ->orderByDesc('updated_at');
    }

    // ─────────────────────────────────────────────────────────────────────
    //  Requête de base et filtres
    // ─────────────────────────────────────────────────────────────────────

    /** @param array<string, mixed> $filtres */
    private function baseQuery(array $filtres = []): Builder
    {
        $query = Stage::query()->with(self::EAGER_LOADS);

        $this->appliquerFiltresStage($query, $filtres);

        return $query->orderBy('date_debut');
    }

    /**
     * Filtres communs de l'écran, portés sur une requête de `stages`.
     *
     * @param  array<string, mixed>  $filtres
     */
    private function appliquerFiltresStage(Builder $query, array $filtres): Builder
    {
        $valeur = static fn (string $cle): mixed => ($filtres[$cle] ?? '') === '' ? null : $filtres[$cle];

        $query
            ->when($valeur('agence_id'), fn (Builder $q, $v) => $q->where('agence_id', $v))
            ->when($valeur('entreprise_id'), fn (Builder $q, $v) => $q->where('entreprise_id', $v))
            ->when($valeur('source_financement_id'), fn (Builder $q, $v) => $q->where('source_financement_id', $v))
            ->when($valeur('type_stage_id'), fn (Builder $q, $v) => $q->where('type_stage_id', $v))
            ->when($valeur('type_structure_id'), fn (Builder $q, $v) => $q->whereHas(
                'entreprise',
                fn (Builder $e) => $e->where('type_structure_id', $v)
            ))
            // La situation courante est lue sur `stages.situation_stage` (code) : la table
            // d'historique `situations_stages` n'est pas alimentée par la migration.
            ->when($valeur('situation_stage'), fn (Builder $q, $v) => $q->where('situation_stage', $v))
            ->when($valeur('corbeille'), fn (Builder $q, $v) => $q->whereHas(
                'instanceParcours',
                fn (Builder $i) => $i->whereNull('terminee_le')->where('corbeille_actuelle', $v)
            ))
            ->when($valeur('date_debut'), fn (Builder $q, $v) => $q->whereDate('date_debut', '>=', $v))
            ->when($valeur('date_fin'), fn (Builder $q, $v) => $q->whereDate('date_fin_prevue', '<=', $v))
            ->when($valeur('date_valid_ar_debut'), fn (Builder $q, $v) => $q->whereDate('date_validation_ar', '>=', $v))
            ->when($valeur('date_valid_ar_fin'), fn (Builder $q, $v) => $q->whereDate('date_validation_ar', '<=', $v))
            ->when($valeur('date_valid_desse_debut'), fn (Builder $q, $v) => $q->whereDate('visa_desse_le', '>=', $v))
            ->when($valeur('date_valid_desse_fin'), fn (Builder $q, $v) => $q->whereDate('visa_desse_le', '<=', $v))
            ->when($valeur('annee_saisie'), fn (Builder $q, $v) => $q->whereYear('created_at', $v))
            ->when($valeur('recherche'), fn (Builder $q, $v) => $this->appliquerRecherche($q, (string) $v));

        $agences = $this->agencesAutorisees();

        if ($agences !== null) {
            $query->whereIn('agence_id', $agences);
        }

        return $query;
    }

    /**
     * Recherche libre sur le bénéficiaire (nom, prénoms, n° AEJ, n° de pièce) et sur
     * l'entreprise d'accueil.
     *
     * `lower(...) like lower(...)` plutôt que `ilike` : la comparaison doit rester
     * identique sous PostgreSQL (production) et SQLite (tests).
     */
    private function appliquerRecherche(Builder $query, string $terme): Builder
    {
        $motif = '%'.mb_strtolower(trim($terme)).'%';

        return $query->where(function (Builder $q) use ($motif): void {
            $q->whereHas('beneficiaire', function (Builder $b) use ($motif): void {
                $b->whereRaw('lower(nom) like ?', [$motif])
                    ->orWhereRaw('lower(prenoms) like ?', [$motif])
                    ->orWhereRaw('lower(numero_aej) like ?', [$motif])
                    ->orWhereRaw('lower(numero_piece_identite) like ?', [$motif]);
            })->orWhereHas(
                'entreprise',
                fn (Builder $e) => $e->whereRaw('lower(raison_sociale) like ?', [$motif])
            );
        });
    }

    /**
     * Agences habilitées pour l'utilisateur courant, ou `null` pour une vision nationale.
     *
     * @return array<int, int>|null
     */
    public function agencesAutorisees(): ?array
    {
        $user = Auth::user();

        if (! $user) {
            return null;
        }

        foreach (self::ROLES_VISION_NATIONALE as $role) {
            if (method_exists($user, 'hasRole') && $user->hasRole($role)) {
                return null;
            }
        }

        $agenceIds = [];

        if (method_exists($user, 'perimetresAgences')) {
            $agenceIds = $user->perimetresAgences()
                ->where(function ($q): void {
                    $q->whereNull('valide_au')->orWhere('valide_au', '>=', now());
                })
                ->pluck('agences.id')
                ->all();
        }

        if ($agenceIds === [] && ! empty($user->agence_id)) {
            $agenceIds = [(int) $user->agence_id];
        }

        return $agenceIds === [] ? null : array_map('intval', $agenceIds);
    }

    // ─────────────────────────────────────────────────────────────────────
    //  Décisions de visa
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Accorde le visa. Le dossier ne change pas de corbeille : le parcours suit son cours.
     */
    public function viser(Stage $stage, ?int $auteurId = null): void
    {
        $this->trancher($stage, VisaDesseEnum::VISE, null, $auteurId);
    }

    /**
     * Refuse le visa, motif obligatoire : c'est lui qui dit au CIP quoi corriger.
     */
    public function rejeter(Stage $stage, string $motif, ?int $auteurId = null): void
    {
        $this->trancher($stage, VisaDesseEnum::REJETE, $motif, $auteurId);
    }

    /**
     * Remet en attente un dossier rejeté, une fois le CIP passé dessus.
     */
    public function remettreEnAttente(Stage $stage): void
    {
        $stage->forceFill([
            'visa_desse' => VisaDesseEnum::EN_ATTENTE->value,
            'motif_visa_desse' => null,
            'visa_desse_le' => null,
            'visa_desse_par_id' => null,
        ])->save();
    }

    private function trancher(Stage $stage, VisaDesseEnum $visa, ?string $motif, ?int $auteurId): void
    {
        DB::transaction(function () use ($stage, $visa, $motif, $auteurId): void {
            $stage->forceFill([
                'visa_desse' => $visa->value,
                'motif_visa_desse' => $motif,
                'visa_desse_le' => now(),
                'visa_desse_par_id' => $auteurId,
            ])->save();
        });
    }

    // ─────────────────────────────────────────────────────────────────────
    //  Aiguillage par onglet
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Onglets de l'écran, dans leur ordre d'affichage.
     *
     * @var array<int, string>
     */
    public const ONGLETS = [
        'attente_visa_desse',
        'rejetes_desse',
        'vises_desse',
        'valides_ar',
        'differes_ac',
        'suivi_enregistres',
        'suivi_valides_ar',
        'statistiques',
        'pieces',
    ];

    /**
     * Onglets qui listent des paiements et non des stages : leur ligne et leur export
     * suivent un autre format.
     *
     * @var array<int, string>
     */
    public const ONGLETS_PAIEMENT = ['differes_ac'];

    /**
     * Onglets sans liste paginée (tableau agrégé, consultation de pièces).
     *
     * @var array<int, string>
     */
    public const ONGLETS_SANS_LISTE = ['statistiques'];

    /**
     * @param  array<string, mixed>  $filtres
     */
    public function queryPourOnglet(string $onglet, array $filtres = []): Builder
    {
        return match ($onglet) {
            'rejetes_desse' => $this->rejetesQuery($filtres),
            'vises_desse' => $this->visesQuery($filtres),
            'valides_ar' => $this->validesArQuery($filtres),
            'differes_ac' => $this->differesAcQuery($filtres),
            'suivi_enregistres' => $this->suiviEnregistresQuery($filtres),
            'suivi_valides_ar' => $this->suiviValidesArQuery($filtres),
            // L'onglet « pièces » liste les dossiers en stage, comme l'écran legacy
            // `Telechargement_Pieces` qui filtrait sur `id_situation_stage = 1`.
            'pieces' => $this->validesArQuery($filtres),
            default => $this->attenteQuery($filtres),
        };
    }

    // ─────────────────────────────────────────────────────────────────────
    //  Compteurs d'onglets
    // ─────────────────────────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $filtres
     * @return array<string, int>
     */
    public function compteurs(array $filtres = []): array
    {
        return [
            'attente_visa_desse' => $this->attenteQuery($filtres)->count(),
            'rejetes_desse' => $this->rejetesQuery($filtres)->count(),
            'vises_desse' => $this->visesQuery($filtres)->count(),
            'valides_ar' => $this->validesArQuery($filtres)->count(),
            'differes_ac' => $this->differesAcQuery($filtres)->count(),
            'suivi_enregistres' => $this->suiviEnregistresQuery($filtres)->count(),
            'suivi_valides_ar' => $this->suiviValidesArQuery($filtres)->count(),
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    //  Formatage des lignes
    // ─────────────────────────────────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    public function formatLigne(Stage $stage): array
    {
        $beneficiaire = $stage->beneficiaire;

        return [
            'id' => $stage->id,
            'beneficiaire' => [
                'nom' => $beneficiaire?->nom ?? 'Inconnu',
                'prenoms' => $beneficiaire?->prenoms ?? '',
                'matricule' => $beneficiaire?->numero_aej ?? '',
            ],
            'numero_aej' => $beneficiaire?->numero_aej ?? '-',
            'entreprise' => $stage->entreprise?->raison_sociale ?? '-',
            'agence' => $stage->agence?->nom ?? '-',
            'source_financement' => $stage->sourceFinancement?->nom ?? '-',
            'type_stage' => $stage->typeStage?->nom ?? '-',
            'date_debut' => $stage->date_debut?->format('d/m/Y') ?? '-',
            'date_fin_prevue' => $stage->date_fin_prevue?->format('d/m/Y') ?? '-',
            'visa_desse' => $stage->visa_desse?->value,
            'visa_desse_label' => $stage->visa_desse?->label(),
            'motif_visa_desse' => $stage->motif_visa_desse,
            'visa_desse_le' => $stage->visa_desse_le?->format('d/m/Y'),
            'decideur' => $stage->visaDessePar?->nom,
            'date_validation_ar' => $stage->date_validation_ar?->format('d/m/Y'),
            'statut_ca' => $stage->date_validation_ar !== null || $stage->visa_desse !== null
                ? 'Validé'
                : 'En attente',
            'corbeille' => $stage->instanceParcours?->corbeille_actuelle,
            'statut_parcours' => $stage->instanceParcours?->corbeille_actuelle !== null
                ? (CorbeilleEnum::tryFrom($stage->instanceParcours->corbeille_actuelle)?->label() ?? '-')
                : '-',
        ];
    }

    /**
     * Ligne de l'onglet « différés AC » : le paiement porte la décision, le stage porte
     * l'identité du bénéficiaire.
     *
     * @return array<string, mixed>
     */
    public function formatLignePaiement(Paiement $paiement): array
    {
        $stage = $paiement->droitPaiement?->stage;
        $beneficiaire = $stage?->beneficiaire;
        $decision = $paiement->decisions
            ->firstWhere('decision', 'DIFFERE_STAGIAIRE_AC');

        return [
            'id' => $paiement->id,
            'stage_id' => $stage?->id,
            'beneficiaire' => [
                'nom' => $beneficiaire?->nom ?? 'Inconnu',
                'prenoms' => $beneficiaire?->prenoms ?? '',
                'matricule' => $beneficiaire?->numero_aej ?? '',
            ],
            'numero_aej' => $beneficiaire?->numero_aej ?? '-',
            'entreprise' => $stage?->entreprise?->raison_sociale ?? '-',
            'agence' => $stage?->agence?->nom ?? '-',
            'source_financement' => $stage?->sourceFinancement?->nom ?? '-',
            'type_stage' => $stage?->typeStage?->nom ?? '-',
            'periode' => $paiement->droitPaiement?->periode?->code ?? '-',
            'montant' => $paiement->montant,
            'motif' => $decision?->motif,
            'date_differe' => $decision?->decide_le?->format('d/m/Y'),
            'corbeille' => $paiement->corbeille_actuelle,
            'statut_parcours' => CorbeilleEnum::tryFrom((string) $paiement->corbeille_actuelle)?->label() ?? '-',
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    //  Tableau statistique par agence régionale
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Tableau statistique par agence régionale (legacy `Tableau_Statistique`).
     *
     * Trois blocs — mis en stage (validés AR), validés DESSE, validés DMG — déclinés en
     * stage PAE / stage école / total. Le legacy lançait une requête par cellule, soit
     * 9 requêtes par agence plus 9 totaux ; ici une seule requête agrégée par `CASE WHEN`
     * couvre tout le tableau, bornée comme le legacy sur la date de validation du chef
     * d'agence (`date_validation_ar`, ex `date_chef_agence`).
     *
     * @return array{lignes: array<int, array<string, mixed>>, totaux: array<string, int>, periode: array<string, string>}
     */
    public function statistiques(?string $dateDebut, ?string $dateFin): array
    {
        $debut = $dateDebut ? Carbon::parse($dateDebut)->startOfDay() : Carbon::now()->startOfYear();
        $fin = $dateFin ? Carbon::parse($dateFin)->endOfDay() : Carbon::now()->endOfDay();

        // Le legacy comptait le PAE sur `id_type_stage = 1` ; la reprise a scindé ce type en
        // deux codes (dont un hérité), les deux comptent donc pour la colonne PAE.
        $idsPae = TypeStage::idsPourCodes([TypeStage::CODE_QUALIFICATION, TypeStage::CODE_QUALIFICATION_HERITE]);
        $idsEcole = TypeStage::idsPourCodes([TypeStage::CODE_ECOLE]);

        // `visa_desse IS NOT NULL` est le marqueur canonisé du feu vert du chef d'agence
        // (cf. validesArQuery) ; les dossiers validés DMG sont ceux dont le parcours a
        // dépassé la vérification DMG, portée par les corbeilles de paiement.
        $corbeillesDmg = [
            CorbeilleEnum::DMG_ATTENTE_PAIEMENT_DEMARRAGE->value,
            CorbeilleEnum::DMG_ATTENTE_PAIEMENT_PRESENCE->value,
            CorbeilleEnum::DMG_ELABORATION_OP->value,
            CorbeilleEnum::DMG_OP_ATTENTE_BORDEREAU->value,
            CorbeilleEnum::AC_BORDEREAU_OP_ATTENTE->value,
        ];

        $condVise = "stages.visa_desse = '".VisaDesseEnum::VISE->value."'";
        $condDmg = "instances_parcours.corbeille_actuelle in ('".implode("','", $corbeillesDmg)."')";

        $lignes = Stage::query()
            ->leftJoin('instances_parcours', function ($join): void {
                $join->on('instances_parcours.stage_id', '=', 'stages.id')
                    ->whereNull('instances_parcours.terminee_le');
            })
            ->join('agences', 'agences.id', '=', 'stages.agence_id')
            ->whereNull('stages.deleted_at')
            ->whereNotNull('stages.date_validation_ar')
            ->whereBetween('stages.date_validation_ar', [$debut, $fin])
            ->when(
                $this->agencesAutorisees(),
                fn ($query, array $agences) => $query->whereIn('stages.agence_id', $agences)
            )
            ->groupBy('agences.id', 'agences.nom')
            ->orderBy('agences.nom')
            ->select([
                'agences.id as agence_id',
                'agences.nom as agence',
                DB::raw($this->expressionCompteur('1 = 1', $idsPae).' as inseres_pae'),
                DB::raw($this->expressionCompteur('1 = 1', $idsEcole).' as inseres_ecole'),
                DB::raw($this->expressionCompteur('1 = 1').' as inseres_total'),
                DB::raw($this->expressionCompteur($condVise, $idsPae).' as desse_pae'),
                DB::raw($this->expressionCompteur($condVise, $idsEcole).' as desse_ecole'),
                DB::raw($this->expressionCompteur($condVise).' as desse_total'),
                DB::raw($this->expressionCompteur($condDmg, $idsPae).' as dmg_pae'),
                DB::raw($this->expressionCompteur($condDmg, $idsEcole).' as dmg_ecole'),
                DB::raw($this->expressionCompteur($condDmg).' as dmg_total'),
            ])
            ->get()
            ->map(fn ($ligne): array => [
                'agence_id' => (int) $ligne->agence_id,
                'agence' => $ligne->agence,
                'inseres_pae' => (int) $ligne->inseres_pae,
                'inseres_ecole' => (int) $ligne->inseres_ecole,
                'inseres_total' => (int) $ligne->inseres_total,
                'desse_pae' => (int) $ligne->desse_pae,
                'desse_ecole' => (int) $ligne->desse_ecole,
                'desse_total' => (int) $ligne->desse_total,
                'dmg_pae' => (int) $ligne->dmg_pae,
                'dmg_ecole' => (int) $ligne->dmg_ecole,
                'dmg_total' => (int) $ligne->dmg_total,
            ])
            ->all();

        $colonnes = [
            'inseres_pae', 'inseres_ecole', 'inseres_total',
            'desse_pae', 'desse_ecole', 'desse_total',
            'dmg_pae', 'dmg_ecole', 'dmg_total',
        ];

        $totaux = [];
        foreach ($colonnes as $colonne) {
            $totaux[$colonne] = (int) array_sum(array_column($lignes, $colonne));
        }

        return [
            'lignes' => $lignes,
            'totaux' => $totaux,
            'periode' => [
                'date_debut' => $debut->format('Y-m-d'),
                'date_fin' => $fin->format('Y-m-d'),
            ],
        ];
    }

    /**
     * Fragment `SUM(CASE WHEN ...)` d'une cellule du tableau statistique.
     *
     * Les conditions sont construites à partir de constantes d'enum et d'identifiants
     * entiers issus du référentiel : aucune valeur de la requête HTTP n'y entre.
     *
     * @param  array<int, int>|null  $typeStageIds
     */
    private function expressionCompteur(string $condition, ?array $typeStageIds = null): string
    {
        $clause = $condition;

        if ($typeStageIds !== null) {
            $ids = implode(',', array_map('intval', $typeStageIds)) ?: '0';
            $clause .= " and stages.type_stage_id in ({$ids})";
        }

        return "sum(case when {$clause} then 1 else 0 end)";
    }

    /**
     * Agences visibles par l'utilisateur, pour alimenter les listes déroulantes.
     *
     * @return array<int, array<string, mixed>>
     */
    public function agencesVisibles(): array
    {
        $autorisees = $this->agencesAutorisees();
        $agences = Agence::cachedOptions('nom');

        if ($autorisees === null) {
            return $agences;
        }

        return array_values(array_filter(
            $agences,
            fn (array $agence): bool => in_array((int) $agence['id'], $autorisees, true)
        ));
    }

    /**
     * Lignes CSV d'un export (en-tête inclus).
     *
     * @return \Generator<int, array<int, string|null>>
     */
    public function lignesExport(Builder $query, bool $paiements = false): \Generator
    {
        yield $paiements
            ? ['N° AEJ', 'Nom', 'Prénoms', 'Agence', 'Entreprise', 'Financement', 'Type de stage', 'Période', 'Montant', 'Motif du différé', 'Différé le']
            : ['N° AEJ', 'Nom', 'Prénoms', 'Agence', 'Entreprise', 'Financement', 'Type de stage', 'Début', 'Fin prévue', 'Validé AR le', 'Visa DESSE', 'Visa le', 'Motif'];

        foreach ($query->cursor() as $modele) {
            if ($paiements) {
                $ligne = $this->formatLignePaiement($modele);

                yield [
                    $ligne['numero_aej'],
                    $ligne['beneficiaire']['nom'],
                    $ligne['beneficiaire']['prenoms'],
                    $ligne['agence'],
                    $ligne['entreprise'],
                    $ligne['source_financement'],
                    $ligne['type_stage'],
                    $ligne['periode'],
                    (string) $ligne['montant'],
                    $ligne['motif'],
                    $ligne['date_differe'],
                ];

                continue;
            }

            $ligne = $this->formatLigne($modele);

            yield [
                $ligne['numero_aej'],
                $ligne['beneficiaire']['nom'],
                $ligne['beneficiaire']['prenoms'],
                $ligne['agence'],
                $ligne['entreprise'],
                $ligne['source_financement'],
                $ligne['type_stage'],
                $ligne['date_debut'],
                $ligne['date_fin_prevue'],
                $ligne['date_validation_ar'],
                $ligne['visa_desse_label'],
                $ligne['visa_desse_le'],
                $ligne['motif_visa_desse'],
            ];
        }
    }
}
