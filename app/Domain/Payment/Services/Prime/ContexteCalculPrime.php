<?php

namespace App\Domain\Payment\Services\Prime;

use App\Models\Internship\Stage;
use App\Models\Reference\SourceFinancement;
use App\Models\Reference\TypeStage;
use App\Models\Reference\TypeStructure;
use Carbon\Carbon;

/**
 * Entrée normalisée du moteur de calcul des primes.
 *
 * Le legacy fait tourner ses règles sur `contrats_pae`, qui porte directement
 * `id_type_stage`, `source_financement` et `type_structure_id`. Next a éclaté
 * ces informations (le type de structure vit désormais sur l'entreprise) et a
 * renuméroté ses référentiels. Ce DTO rétablit les identifiants legacy — seuls
 * ceux dans lesquels les règles sont écrites — via la colonne `ancien_id`, de
 * sorte que les stratégies restent la transcription littérale du legacy.
 */
final class ContexteCalculPrime
{
    /** @var array<string, array<int, int>>|null Cache par requête : table → [id Next => ancien_id]. */
    private static ?array $tablesAnciensIds = null;

    public function __construct(
        public readonly ?int $stageId,
        public readonly ?int $typeStageLegacyId,
        public readonly ?int $sourceFinancementLegacyId,
        public readonly ?int $typeStructureLegacyId,
        public readonly ?Carbon $dateDebut,
        public readonly ?Carbon $dateFin,
        public readonly float $dureeMois,
        public readonly ?string $nomEntreprise,
    ) {}

    public static function depuisStage(Stage $stage): self
    {
        $dateDebut = $stage->date_debut ? Carbon::parse($stage->date_debut) : null;
        $dateFin = $stage->date_fin_prevue ? Carbon::parse($stage->date_fin_prevue) : null;

        return new self(
            stageId: $stage->id,
            typeStageLegacyId: self::ancienId(TypeStage::class, $stage->type_stage_id),
            sourceFinancementLegacyId: self::ancienId(SourceFinancement::class, $stage->source_financement_id),
            typeStructureLegacyId: self::ancienId(TypeStructure::class, $stage->entreprise?->type_structure_id),
            dateDebut: $dateDebut,
            dateFin: $dateFin,
            dureeMois: self::dureeEnMois($dateDebut, $dateFin),
            nomEntreprise: $stage->entreprise?->raison_sociale,
        );
    }

    /**
     * Durée contractuelle en mois, arrondie au demi-mois.
     *
     * Le legacy lit `nbre_mois_prev`, colonne que Next n'a pas reprise : la
     * durée est redéduite des dates. Le demi-mois est significatif, la grille
     * stage école ayant une règle dédiée aux contrats de 1,5 mois.
     */
    public static function dureeEnMois(?Carbon $dateDebut, ?Carbon $dateFin): float
    {
        if (! $dateDebut || ! $dateFin) {
            return 0.0;
        }

        $jours = $dateDebut->diffInDays($dateFin) + 1;

        return round(($jours / 30) * 2) / 2;
    }

    /**
     * `ancien_id` d'une ligne de référentiel, en une seule requête par table.
     */
    private static function ancienId(string $modelClass, ?int $id): ?int
    {
        if ($id === null) {
            return null;
        }

        $model = new $modelClass;
        $table = $model->getTable();

        if (! isset(self::$tablesAnciensIds[$table])) {
            self::$tablesAnciensIds[$table] = $modelClass::query()
                ->pluck('ancien_id', 'id')
                ->filter()
                ->map(fn ($ancien): int => (int) $ancien)
                ->all();
        }

        return self::$tablesAnciensIds[$table][$id] ?? null;
    }

    /**
     * À appeler lorsque les référentiels changent en cours de processus
     * (migration, tests) pour que la correspondance soit relue.
     */
    public static function oublierReferentiels(): void
    {
        self::$tablesAnciensIds = null;
    }
}
