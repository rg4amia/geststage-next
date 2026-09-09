<?php

namespace App\Domain\Payment\Services\Prime;

use App\Domain\Payment\Services\Prime\Strategies\PrimeStrategyInterface;
use App\Models\Internship\Stage;
use App\Models\Reference\Periode;
use Illuminate\Support\Facades\Log;

/**
 * Moteur de calcul de la prime **mensuelle** d'un stagiaire.
 *
 * Portage de `App\Services\PrimeCalculatorService` (legacy). Les stratégies
 * sont évaluées par priorité décroissante ; la première qui accepte le stage
 * calcule la prime du mois demandé.
 *
 * Ne pas confondre avec le montant total du contrat : `contrats.prime_mensuelle`
 * porte, pour les dossiers repris du legacy, le montant dû sur toute la durée
 * du stage (`contrats_pae.montant_du`). C'est ce service qui fait autorité pour
 * le montant d'un paiement, qui couvre un mois.
 */
class PrimeCalculatorService
{
    /** @var array<int, PrimeStrategyInterface> */
    private array $strategies;

    /**
     * @param  iterable<PrimeStrategyInterface>  $strategies
     */
    public function __construct(iterable $strategies)
    {
        $sorted = iterator_to_array($strategies, false);

        usort(
            $sorted,
            fn (PrimeStrategyInterface $a, PrimeStrategyInterface $b): int => $b->priority() <=> $a->priority()
        );

        $this->strategies = $sorted;
    }

    /**
     * Prime due au titre d'un mois donné.
     *
     * @param  string  $mois  Format « YYYY-MM » ou « YYYY-MM-DD »
     *
     * @throws PrimeCalculationException
     */
    public function calculerPourStage(Stage $stage, string $mois): float
    {
        return $this->calculer(ContexteCalculPrime::depuisStage($stage), $mois);
    }

    /**
     * Prime due au titre de la période portée par un droit de paiement.
     *
     * @throws PrimeCalculationException
     */
    public function calculerPourPeriode(Stage $stage, ?Periode $periode): float
    {
        $mois = $periode?->code ?? $stage->date_debut;

        if (! $mois) {
            throw new PrimeCalculationException(
                'Impossible de déterminer le mois de rattachement du paiement.',
                context: ['stage_id' => $stage->id]
            );
        }

        return $this->calculerPourStage($stage, (string) $mois);
    }

    /**
     * @throws PrimeCalculationException
     */
    public function calculer(ContexteCalculPrime $contexte, string $mois): float
    {
        $strategy = $this->resolveStrategy($contexte);

        try {
            return $strategy->calculate($contexte, $mois);
        } catch (PrimeCalculationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Erreur calcul prime', [
                'stage_id' => $contexte->stageId,
                'mois' => $mois,
                'strategy' => $strategy::class,
                'error' => $e->getMessage(),
            ]);

            throw new PrimeCalculationException(
                "Impossible de calculer la prime pour le stage ID: {$contexte->stageId}. {$e->getMessage()}",
                previous: $e,
                context: ['stage_id' => $contexte->stageId, 'mois' => $mois]
            );
        }
    }

    /**
     * Stratégie retenue pour un stage — exposé pour l'écran d'administration,
     * qui affiche la règle appliquée à côté du montant.
     */
    public function strategiePourStage(Stage $stage): PrimeStrategyInterface
    {
        return $this->resolveStrategy(ContexteCalculPrime::depuisStage($stage));
    }

    /**
     * Libellé de la stratégie retenue pour un contexte, tel qu'affiché par le
     * simulateur. Renvoie `null` si aucune stratégie ne couvre le contexte : le
     * montant n'a alors pas pu être calculé de toute façon.
     */
    public function libelleStrategie(ContexteCalculPrime $contexte): ?string
    {
        try {
            $cle = class_basename($this->resolveStrategy($contexte));
        } catch (PrimeCalculationException) {
            return null;
        }

        foreach ($this->catalogue() as $entree) {
            if ($entree['classe'] === $cle) {
                return $entree['label'];
            }
        }

        return $cle;
    }

    /**
     * Catalogue des stratégies enregistrées, pour l'écran d'administration.
     *
     * @return array<int, array{key:string, classe:string, label:string, description:string, priority:int, fallback:bool, config:string}>
     */
    public function catalogue(): array
    {
        $metadata = [
            'PrimeMirahStrategy' => [
                'key' => 'prime_mirah',
                'label' => 'Prime MIRAH forfaitaire',
                'description' => 'Forfait appliqué aux stages de qualification PAPS-GOUV réalisés chez MIRAH.',
                'config' => 'Code (montant forfaitaire)',
            ],
            'PrimeQualificationStrategy' => [
                'key' => 'prime_qualification',
                'label' => 'Prime de qualification',
                'description' => 'Grille proratisée selon les dates, le financement et la structure.',
                'config' => 'pae',
            ],
            'PrimeStageEcoleStrategy' => [
                'key' => 'prime_stage_ecole',
                'label' => 'Prime stage école',
                'description' => 'Grille stage école avec prorata début/fin et périodes Budget État.',
                'config' => 'stage_ecole',
            ],
            'PrimeSmigStrategy' => [
                'key' => 'prime_smig',
                'label' => 'Repli SMIG',
                'description' => 'Montant de secours lorsque aucune grille spécialisée ne couvre le stage.',
                'config' => 'smig.default',
            ],
        ];

        return array_map(function (PrimeStrategyInterface $strategy) use ($metadata): array {
            $class = class_basename($strategy);
            $item = $metadata[$class] ?? [
                'key' => $class,
                'label' => $class,
                'description' => 'Stratégie enregistrée dans le moteur de calcul.',
                'config' => 'Code source',
            ];

            return [
                'key' => $item['key'],
                'classe' => $class,
                'label' => $item['label'],
                'description' => $item['description'],
                'priority' => $strategy->priority(),
                'fallback' => $item['key'] === 'prime_smig',
                'config' => $item['config'],
            ];
        }, $this->strategies);
    }

    /**
     * @throws PrimeCalculationException
     */
    private function resolveStrategy(ContexteCalculPrime $contexte): PrimeStrategyInterface
    {
        foreach ($this->strategies as $strategy) {
            if ($strategy->supports($contexte)) {
                return $strategy;
            }
        }

        throw new PrimeCalculationException(
            "Aucune stratégie de calcul trouvée pour le stage ID: {$contexte->stageId}",
            context: [
                'stage_id' => $contexte->stageId,
                'type_stage_legacy_id' => $contexte->typeStageLegacyId,
            ]
        );
    }
}
