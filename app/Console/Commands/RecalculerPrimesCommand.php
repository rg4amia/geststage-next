<?php

namespace App\Console\Commands;

use App\Domain\Payment\Services\Prime\PrimeCalculationException;
use App\Domain\Payment\Services\Prime\PrimeCalculatorService;
use App\Models\Payment\DroitPaiement;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Recalcule le montant des droits de paiement et de leurs paiements à partir du
 * barème.
 *
 * Les dossiers repris du legacy portent, comme montant de paiement,
 * `contrats_pae.montant_du` — le dû sur **toute** la durée du stage. Chaque
 * paiement ne couvre pourtant qu'un mois. Cette commande rétablit la prime
 * mensuelle réelle en rejouant les mêmes règles que le legacy.
 *
 * Les paiements déjà engagés (ordonnancés, mis en bordereau, payés) ne sont pas
 * touchés par défaut : leur montant est celui qui a été ordonnancé, le corriger
 * désaccorderait les pièces comptables déjà émises.
 */
class RecalculerPrimesCommand extends Command
{
    protected $signature = 'primes:recalculer
        {--dry-run : Affiche les écarts sans rien écrire}
        {--nature= : Restreint à une nature de droit (DEMARRAGE ou PRESENCE)}
        {--tous-statuts : Recalcule aussi les paiements déjà engagés (OP, bordereau, payés)}
        {--chunk=500 : Nombre de droits traités par lot}';

    protected $description = 'Recalcule les primes mensuelles des droits de paiement selon le barème';

    /**
     * Statuts pour lesquels le montant est encore modifiable : au-delà, il a
     * été repris dans un ordre de paiement ou un bordereau.
     */
    private const STATUTS_MODIFIABLES = ['A_TRAITER', 'AJOURNE', 'REJETE'];

    public function handle(PrimeCalculatorService $calculator): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $tousStatuts = (bool) $this->option('tous-statuts');
        $chunk = max(50, min((int) $this->option('chunk'), 5000));

        $query = DroitPaiement::query()
            ->whereNull('annule_le')
            ->with(['stage.entreprise', 'periode', 'paiements']);

        if (! $tousStatuts) {
            $query->whereHas('paiements', fn ($q) => $q->whereIn('statut', self::STATUTS_MODIFIABLES));
        }

        if ($nature = $this->option('nature')) {
            $query->where('nature', strtoupper((string) $nature));
        }

        $total = (clone $query)->count();
        $this->info("Droits de paiement à examiner : {$total}");

        if ($total === 0) {
            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $corriges = 0;
        $inchanges = 0;
        $erreurs = 0;
        $ecartTotal = 0.0;

        $query->chunkById($chunk, function ($droits) use ($calculator, $dryRun, $tousStatuts, $bar, &$corriges, &$inchanges, &$erreurs, &$ecartTotal): void {
            foreach ($droits as $droit) {
                try {
                    $stage = $droit->stage;

                    if (! $stage) {
                        $erreurs++;
                        $bar->advance();

                        continue;
                    }

                    $montant = $calculator->calculerPourPeriode($stage, $droit->periode);
                    $ancien = (float) $droit->montant;

                    if (abs($montant - $ancien) < 0.01) {
                        $inchanges++;
                        $bar->advance();

                        continue;
                    }

                    $ecartTotal += $ancien - $montant;
                    $corriges++;

                    if (! $dryRun) {
                        DB::transaction(function () use ($droit, $montant, $tousStatuts): void {
                            $droit->update(['montant' => $montant]);

                            $paiements = $droit->paiements()
                                ->when(! $tousStatuts, fn ($q) => $q->whereIn('statut', self::STATUTS_MODIFIABLES));

                            $paiements->update(['montant' => $montant]);
                        });
                    }
                } catch (PrimeCalculationException $e) {
                    $erreurs++;
                }

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);

        $this->table(['Résultat', 'Nombre'], [
            ['Montants corrigés', $corriges],
            ['Déjà conformes', $inchanges],
            ['Non calculables', $erreurs],
        ]);

        $this->info('Écart cumulé retiré des montants : '.number_format($ecartTotal, 0, ',', ' ').' FCFA');

        if ($dryRun) {
            $this->warn('Mode --dry-run : aucune écriture effectuée.');
        }

        return self::SUCCESS;
    }
}
