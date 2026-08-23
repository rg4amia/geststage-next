<?php

namespace App\Domain\Workflow\Services;

use App\Enums\CorbeilleEnum;
use App\Enums\DoublonTypeEnum;
use App\Models\Internship\Stage;
use App\Models\Workflow\DesseDoublonDecision;
use App\Models\Workflow\InstanceParcours;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Détection et traitement des doublons DESSE (portage de la logique
 * legacy IndexDesseController::getDoublonValuesForDataTable()/buildDoublonDataTableQuery(),
 * adapté au schéma beneficiaires/stages de geststage-next).
 */
class DesseDoublonService
{
    public function __construct(private WorkflowTransitionService $workflow) {}

    /**
     * Expression SQL (Postgres) de la clé de regroupement par type de doublon,
     * ainsi que la condition garantissant que la clé n'est pas vide.
     * Utilisée à la fois pour le calcul des groupes en doublon (GROUP BY / HAVING)
     * et pour retrouver les lignes soeurs d'une ligne donnée (WHERE = valeur).
     */
    private const TYPES = [
        'aej' => [
            'expr' => 'UPPER(TRIM(beneficiaires.numero_aej))',
            'guard' => "beneficiaires.numero_aej IS NOT NULL AND TRIM(beneficiaires.numero_aej) != ''",
        ],
        'piece_identite' => [
            'expr' => 'UPPER(TRIM(beneficiaires.numero_piece_identite))',
            'guard' => "beneficiaires.numero_piece_identite IS NOT NULL AND TRIM(beneficiaires.numero_piece_identite) != ''",
        ],
        'contact_type_stage' => [
            'expr' => "CONCAT(UPPER(TRIM(beneficiaires.telephone_principal)), '|', stages.type_stage_id)",
            'guard' => "beneficiaires.telephone_principal IS NOT NULL AND TRIM(beneficiaires.telephone_principal) != ''",
        ],
        'identite_type_stage' => [
            // Pas de cast `::text` (Postgres uniquement, invalide sous SQLite) : CONCAT()
            // convertit déjà ses arguments en texte sur les deux drivers.
            'expr' => "CONCAT(UPPER(TRIM(beneficiaires.nom)), '|', UPPER(TRIM(beneficiaires.prenoms)), '|', beneficiaires.date_naissance, '|', stages.type_stage_id)",
            'guard' => 'beneficiaires.date_naissance IS NOT NULL',
        ],
        'diplome_type_stage' => [
            'expr' => "CONCAT(beneficiaires.diplome_id, '|', stages.type_stage_id, '|', UPPER(TRIM(beneficiaires.numero_piece_identite)))",
            'guard' => "beneficiaires.diplome_id IS NOT NULL AND beneficiaires.numero_piece_identite IS NOT NULL AND TRIM(beneficiaires.numero_piece_identite) != ''",
        ],
    ];

    /**
     * Calcule, pour un type de doublon donné, l'ensemble des valeurs de clé
     * qui apparaissent plusieurs fois dans le pool "Doublons à traiter".
     */
    public function computeDuplicateKeys(DoublonTypeEnum $type): Collection
    {
        if ($type === DoublonTypeEnum::COMPTE_PAIEMENT) {
            return $this->computeDuplicateKeysForComptePaiement();
        }

        $config = self::TYPES[$type->value];

        // COUNT(DISTINCT beneficiaires.id), et non COUNT(*) : un même bénéficiaire peut
        // légitimement cumuler plusieurs stages (donc plusieurs lignes "stages") partageant
        // ses propres piece_identite/téléphone/AEJ — ce n'est pas un doublon, juste son
        // historique. Un vrai doublon, c'est la même clé portée par au moins deux
        // bénéficiaires distincts (possible erreur de saisie / pièce partagée). Ceci rend
        // par ailleurs le type "aej" naturellement toujours vide : beneficiaires.numero_aej
        // est UNIQUE en base, donc un même numéro ne peut jamais être porté par deux
        // bénéficiaires distincts (cf. legacy, où l'onglet "Numéro AEJ" est vide).
        return $this->poolQuery()
            ->selectRaw("{$config['expr']} as cle")
            ->whereRaw($config['guard'])
            ->groupBy(DB::raw($config['expr']))
            ->havingRaw('COUNT(DISTINCT beneficiaires.id) > 1')
            ->pluck('cle');
    }

    /**
     * Restreint une requête InstanceParcours aux lignes appartenant à un groupe
     * de doublons pour le type donné.
     */
    public function applyDuplicateFilter(Builder $query, DoublonTypeEnum $type): Builder
    {
        $keys = $this->computeDuplicateKeys($type);

        if ($keys->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        if ($type === DoublonTypeEnum::COMPTE_PAIEMENT) {
            return $query->whereHas('stage', function ($q) use ($keys) {
                $q->join('beneficiaires', 'beneficiaires.id', '=', 'stages.beneficiaire_id')
                    ->where(function ($q2) use ($keys) {
                        $q2->whereIn(DB::raw("CONCAT('TM:', UPPER(TRIM(beneficiaires.numero_tresor_money)))"), $keys)
                            ->orWhereIn(DB::raw("CONCAT('WV:', UPPER(TRIM(beneficiaires.numero_wave)))"), $keys);
                    });
            });
        }

        $expr = self::TYPES[$type->value]['expr'];

        return $query->whereHas('stage', function ($q) use ($expr, $keys) {
            $q->join('beneficiaires', 'beneficiaires.id', '=', 'stages.beneficiaire_id')
                ->whereIn(DB::raw($expr), $keys);
        });
    }

    /**
     * Requête (non paginée) des instances éligibles à un type de doublon donné.
     */
    public function eligibleInstancesQuery(DoublonTypeEnum $type): Builder
    {
        return $this->applyDuplicateFilter($this->basePoolQuery(), $type);
    }

    /**
     * Nombre de dossiers en doublon, par type, pour l'affichage des badges des sous-onglets.
     *
     * @return array<string, int>
     */
    public function countsByType(): array
    {
        $counts = [];

        foreach (DoublonTypeEnum::cases() as $type) {
            $counts[$type->value] = $this->eligibleInstancesQuery($type)->count();
        }

        return $counts;
    }

    /**
     * Retrouve toutes les instances du pool "Doublons à traiter" partageant exactement
     * la même clé de regroupement que l'instance donnée, pour le type de doublon donné.
     */
    public function siblingsFor(InstanceParcours $instance, DoublonTypeEnum $type): Collection
    {
        $stage = $instance->stage;

        if (! $stage) {
            return collect([$instance]);
        }

        if ($type === DoublonTypeEnum::COMPTE_PAIEMENT) {
            return $this->siblingsForComptePaiement($stage);
        }

        $keyValue = $this->keyValueFor($instance, $type);

        if ($keyValue === null) {
            return collect([$instance]);
        }

        $config = self::TYPES[$type->value];

        return InstanceParcours::query()
            ->where('corbeille_actuelle', CorbeilleEnum::DESSE_DOUBLONS_A_TRAITER->value)
            ->whereNull('terminee_le')
            ->whereHas('stage', function ($q) use ($config, $keyValue) {
                $q->join('beneficiaires', 'beneficiaires.id', '=', 'stages.beneficiaire_id')
                    ->whereRaw("{$config['expr']} = ?", [$keyValue]);
            })
            ->get();
    }

    /**
     * Traite un groupe de doublons pour une ligne donnée : journalise la décision pour
     * chaque ligne du groupe (mêmes clé de regroupement) et applique la transition de
     * workflow correspondante à tout le groupe, en une seule transaction.
     */
    public function treatDuplicateGroup(InstanceParcours $instance, DoublonTypeEnum $type, string $decision, string $motif, ?int $decideParId): int
    {
        return DB::transaction(function () use ($instance, $type, $decision, $motif, $decideParId) {
            $siblings = $this->siblingsFor($instance, $type);
            $cle = $this->keyValueFor($instance, $type) ?? '-';

            foreach ($siblings as $sibling) {
                DesseDoublonDecision::create([
                    'instance_parcours_id' => $sibling->id,
                    'type_doublon' => $type->value,
                    'cle_doublon' => $cle,
                    'decision' => $decision,
                    'motif' => $motif,
                    'decide_par_id' => $decideParId,
                    'decide_le' => now(),
                ]);
            }

            $this->workflow->desseTraiteDoublons($siblings, $decision);

            return $siblings->count();
        });
    }

    /**
     * Valeur de la clé de regroupement pour l'instance donnée et le type de doublon donné,
     * ou null si l'un des champs nécessaires est absent.
     */
    private function keyValueFor(InstanceParcours $instance, DoublonTypeEnum $type): ?string
    {
        $stage = $instance->stage;

        if (! $stage) {
            return null;
        }

        if ($type === DoublonTypeEnum::COMPTE_PAIEMENT) {
            $beneficiaire = $stage->beneficiaire;
            $parts = array_filter([
                filled($beneficiaire?->numero_tresor_money) ? 'TM:'.mb_strtoupper(trim($beneficiaire->numero_tresor_money)) : null,
                filled($beneficiaire?->numero_wave) ? 'WV:'.mb_strtoupper(trim($beneficiaire->numero_wave)) : null,
            ]);

            return $parts === [] ? null : implode('|', $parts);
        }

        $config = self::TYPES[$type->value];

        $keyValue = DB::table('stages')
            ->join('beneficiaires', 'beneficiaires.id', '=', 'stages.beneficiaire_id')
            ->where('stages.id', $stage->id)
            ->selectRaw("{$config['expr']} as cle")
            ->value('cle');

        return ($keyValue === null || $keyValue === '') ? null : $keyValue;
    }

    /**
     * Pour un stage donné, détermine à quel(s) type(s) de doublon il appartient
     * actuellement, à partir d'ensembles de clés en doublon pré-calculés (via
     * computeDuplicateKeys(), un appel par type). Utilisé par la migration legacy
     * pour reconstituer le type_doublon des décisions déjà prises (statuts legacy
     * 5/6), qui n'est pas conservé tel quel côté legacy.
     *
     * @param  array<string, Collection<int, string>>  $duplicateKeysByType  type_doublon->value() => clés en doublon
     * @return array<string, string> type_doublon->value() => clé de regroupement correspondante
     */
    public function matchingTypesForStage(Stage $stage, array $duplicateKeysByType): array
    {
        $selects = [];
        foreach (self::TYPES as $value => $config) {
            $selects[] = "{$config['expr']} as \"{$value}\"";
        }

        $row = DB::table('stages')
            ->join('beneficiaires', 'beneficiaires.id', '=', 'stages.beneficiaire_id')
            ->where('stages.id', $stage->id)
            ->selectRaw(implode(', ', $selects))
            ->first();

        $matches = [];

        foreach (self::TYPES as $value => $config) {
            $cle = $row?->{$value};
            if ($cle !== null && $cle !== '' && ($duplicateKeysByType[$value] ?? collect())->contains($cle)) {
                $matches[$value] = $cle;
            }
        }

        $beneficiaire = $stage->beneficiaire;
        $comptePaiementKeys = $duplicateKeysByType[DoublonTypeEnum::COMPTE_PAIEMENT->value] ?? collect();
        foreach ([
            filled($beneficiaire?->numero_tresor_money) ? 'TM:'.mb_strtoupper(trim($beneficiaire->numero_tresor_money)) : null,
            filled($beneficiaire?->numero_wave) ? 'WV:'.mb_strtoupper(trim($beneficiaire->numero_wave)) : null,
        ] as $cle) {
            if ($cle !== null && ! isset($matches[DoublonTypeEnum::COMPTE_PAIEMENT->value]) && $comptePaiementKeys->contains($cle)) {
                $matches[DoublonTypeEnum::COMPTE_PAIEMENT->value] = $cle;
            }
        }

        return $matches;
    }

    private function computeDuplicateKeysForComptePaiement(): Collection
    {
        $tresor = $this->poolQuery()
            ->selectRaw("CONCAT('TM:', UPPER(TRIM(beneficiaires.numero_tresor_money))) as cle")
            ->whereRaw("beneficiaires.numero_tresor_money IS NOT NULL AND TRIM(beneficiaires.numero_tresor_money) != ''")
            ->groupBy(DB::raw('UPPER(TRIM(beneficiaires.numero_tresor_money))'))
            ->havingRaw('COUNT(DISTINCT beneficiaires.id) > 1')
            ->pluck('cle');

        $wave = $this->poolQuery()
            ->selectRaw("CONCAT('WV:', UPPER(TRIM(beneficiaires.numero_wave))) as cle")
            ->whereRaw("beneficiaires.numero_wave IS NOT NULL AND TRIM(beneficiaires.numero_wave) != ''")
            ->groupBy(DB::raw('UPPER(TRIM(beneficiaires.numero_wave))'))
            ->havingRaw('COUNT(DISTINCT beneficiaires.id) > 1')
            ->pluck('cle');

        return $tresor->merge($wave)->values();
    }

    private function siblingsForComptePaiement(Stage $stage): Collection
    {
        $beneficiaire = $stage->beneficiaire;
        $tresorMoney = $beneficiaire?->numero_tresor_money;
        $wave = $beneficiaire?->numero_wave;

        if (blank($tresorMoney) && blank($wave)) {
            return collect([$stage->instanceParcours]);
        }

        return InstanceParcours::query()
            ->where('corbeille_actuelle', CorbeilleEnum::DESSE_DOUBLONS_A_TRAITER->value)
            ->whereNull('terminee_le')
            ->whereHas('stage', function ($q) use ($tresorMoney, $wave) {
                $q->join('beneficiaires', 'beneficiaires.id', '=', 'stages.beneficiaire_id')
                    ->where(function ($q2) use ($tresorMoney, $wave) {
                        if (filled($tresorMoney)) {
                            $q2->orWhereRaw('UPPER(TRIM(beneficiaires.numero_tresor_money)) = ?', [mb_strtoupper(trim($tresorMoney))]);
                        }
                        if (filled($wave)) {
                            $q2->orWhereRaw('UPPER(TRIM(beneficiaires.numero_wave)) = ?', [mb_strtoupper(trim($wave))]);
                        }
                    });
            })
            ->get();
    }

    /**
     * Périmètre de détection des doublons : l'ensemble de la base (comme le legacy),
     * et non la seule corbeille "Doublons à traiter" — un même numéro de pièce peut
     * être dupliqué entre des dossiers à des étapes de traitement très différentes
     * (ex: carte d'identité "BLANC" réutilisée pour plusieurs bénéficiaires distincts).
     */
    private function basePoolQuery(): Builder
    {
        return InstanceParcours::query()->whereNull('terminee_le');
    }

    private function poolQuery(): QueryBuilder
    {
        // stages.deleted_at IS NULL réplique le global scope SoftDeletes d'Eloquent,
        // implicitement appliqué par whereHas('stage') dans applyDuplicateFilter() :
        // sans ce filtre, un stage supprimé pourrait faire paraître un groupe en
        // doublon alors qu'aucune ligne correspondante ne sera jamais affichée.
        return DB::table('stages')
            ->join('beneficiaires', 'beneficiaires.id', '=', 'stages.beneficiaire_id')
            ->join('instances_parcours', 'instances_parcours.stage_id', '=', 'stages.id')
            ->whereNull('instances_parcours.terminee_le')
            ->whereNull('stages.deleted_at');
    }
}
