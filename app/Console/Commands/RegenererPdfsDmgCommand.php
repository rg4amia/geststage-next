<?php

namespace App\Console\Commands;

use App\Domain\Payment\Services\MultiDossierPdfService;
use App\Models\Payment\DossierGroupe;
use App\Models\Payment\DossierPaiement;
use App\Models\Payment\Paiement;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Régénère les PDF des dossiers et multi-dossiers DMG (attestation de présence /
 * démarrage et état de paiement).
 *
 * Avant le correctif, la double sérialisation du canvas dompdf (`output()` puis
 * `save()`) corrompait les flux CIDToGIDMap des polices embarquées : les fichiers
 * générés étaient illisibles dans les visionneuses. Cette commande régénère avec
 * le code corrigé tous les dossiers / groupes ayant déjà un PDF enregistré, puis
 * vérifie l'intégrité des flux compressés de chaque fichier régénéré.
 */
class RegenererPdfsDmgCommand extends Command
{
    protected $signature = 'dmg:regenerer-pdfs
        {--type=tous : tous|dossier|groupe — ce qui est régénéré}
        {--id= : ID d\'un dossier ou d\'un groupe précis (avec --type=dossier ou --type=groupe)}
        {--dry-run : Affiche les éléments concernés sans régénérer}';

    protected $description = 'Régénère les attestations et états de paiement (PDF) des dossiers et multi-dossiers DMG';

    public function handle(MultiDossierPdfService $pdfService): int
    {
        $type = (string) $this->option('type');
        $id = $this->option('id');
        $dryRun = (bool) $this->option('dry-run');

        if (! in_array($type, ['tous', 'dossier', 'groupe'], true)) {
            $this->error("Type invalide : {$type} (attendu : tous, dossier ou groupe).");

            return self::FAILURE;
        }

        if ($id !== null && $type === 'tous') {
            $this->error('Précisez --type=dossier ou --type=groupe avec --id.');

            return self::FAILURE;
        }

        $dossiers = $this->dossiers($type, $id);
        $groupes = $this->groupes($type, $id);

        $total = $dossiers->count() + $groupes->count();
        $this->info("Éléments à régénérer : {$total} (".$dossiers->count().' dossier(s), '.$groupes->count().' multi-dossier(s))');

        if ($total === 0) {
            $this->warn('Aucun élément avec PDF déjà généré.');

            return self::SUCCESS;
        }

        if ($dryRun) {
            $dossiers->each(fn (DossierPaiement $d) => $this->line("  dossier {$d->id} — {$d->numero}"));
            $groupes->each(fn (DossierGroupe $g) => $this->line("  groupe {$g->id} — {$g->numero}"));
            $this->warn('Mode --dry-run : aucune régénération effectuée.');

            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $regeneres = 0;
        $erreurs = 0;
        $suspects = 0;

        foreach ($dossiers as $dossier) {
            try {
                // On reprend les initiales du valideur enregistrées sur le dossier pour
                // conserver le suffixe du numéro (ex. DOS-PS-202609-XXXXXX-AB) sur le PDF.
                $pdfService->genererPdfsDossier($dossier, $dossier->valideur_initiales);
                $regeneres++;
                $suspects += $this->verifierPdf($dossier->fresh()->attestation_path, "dossier {$dossier->id}");
                $suspects += $this->verifierPdf($dossier->fresh()->etat_financier_path, "dossier {$dossier->id}");
            } catch (Throwable $e) {
                $erreurs++;
                $this->newLine();
                $this->error("  dossier {$dossier->id} ({$dossier->numero}) : ".$e->getMessage());
            }
            $bar->advance();
        }

        foreach ($groupes as $groupe) {
            try {
                // `genererPdfs()` écraserait les deux chemins par null si le groupe n'a aucun
                // paiement rattaché : on saute ces groupes pour ne pas perdre les chemins existants.
                if (! $this->groupeAvecPaiements($groupe)) {
                    $this->newLine();
                    $this->warn("  groupe {$groupe->id} ({$groupe->numero}) : aucun paiement rattaché, ignoré.");
                    $bar->advance();

                    continue;
                }

                $pdfService->genererPdfs($groupe);
                $regeneres++;
                $suspects += $this->verifierPdf($groupe->fresh()->attestation_path, "groupe {$groupe->id}");
                $suspects += $this->verifierPdf($groupe->fresh()->etat_financier_path, "groupe {$groupe->id}");
            } catch (Throwable $e) {
                $erreurs++;
                $this->newLine();
                $this->error("  groupe {$groupe->id} ({$groupe->numero}) : ".$e->getMessage());
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->table(['Résultat', 'Nombre'], [
            ['PDF régénérés', $regeneres],
            ['Fichiers encore suspects', $suspects],
            ['Erreurs', $erreurs],
        ]);

        return $erreurs > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Dossiers ayant déjà un PDF généré (attestation ou état de paiement).
     *
     * @return Collection<int, DossierPaiement>
     */
    private function dossiers(string $type, ?string $id): Collection
    {
        if ($type === 'groupe') {
            return collect();
        }

        $query = DossierPaiement::query()
            ->where(fn ($q) => $q->whereNotNull('attestation_path')->orWhereNotNull('etat_financier_path'));

        if ($id !== null) {
            $query->whereKey((int) $id);
        }

        return $query->orderBy('id')->get();
    }

    /**
     * Multi-dossiers ayant déjà un PDF généré.
     *
     * @return Collection<int, DossierGroupe>
     */
    private function groupes(string $type, ?string $id): Collection
    {
        if ($type === 'dossier') {
            return collect();
        }

        $query = DossierGroupe::query()
            ->where(fn ($q) => $q->whereNotNull('attestation_path')->orWhereNotNull('etat_financier_path'));

        if ($id !== null) {
            $query->whereKey((int) $id);
        }

        return $query->orderBy('id')->get();
    }

    /**
     * Un groupe n'est régénérable que s'il a au moins un paiement rattaché via ses dossiers
     * (même requête que MultiDossierPdfService::collectPaiements).
     */
    private function groupeAvecPaiements(DossierGroupe $groupe): bool
    {
        $dossierIds = $groupe->dossiers()->pluck('dossiers_paiement.id');

        if ($dossierIds->isEmpty()) {
            return false;
        }

        return Paiement::query()
            ->whereHas('dossiersPaiement', fn ($q) => $q->whereIn('dossiers_paiement.id', $dossierIds))
            ->exists();
    }

    /**
     * Vérifie qu'un PDF régénéré est sain : présence de l'en-tête PDF et flux
     * /FlateDecode tous décompressibles (les flux CIDToGIDMap corrompus par
     * l'ancien bug échouent à gzuncompress()).
     */
    private function verifierPdf(?string $chemin, string $libelle): int
    {
        if (! $chemin || ! Storage::disk('temp_files')->exists($chemin)) {
            $this->newLine();
            $this->warn("  {$libelle} : fichier absent ({$chemin}).");

            return 1;
        }

        $pdf = Storage::disk('temp_files')->get($chemin);
        if (str_starts_with((string) $pdf, '%PDF') === false || ! $this->flateStreamsValides((string) $pdf)) {
            $this->newLine();
            $this->warn("  {$libelle} : PDF régénéré mais flux de polices encore suspects ({$chemin}).");

            return 1;
        }

        return 0;
    }

    private function flateStreamsValides(string $pdf): bool
    {
        if (substr_count($pdf, '/FlateDecode') === 0) {
            return false;
        }

        preg_match_all('/(\d+) 0 obj\s*<<(.*?)>>\s*stream\r?\n(.*?)\r?\nendstream/s', $pdf, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            if (strpos($match[2], '/FlateDecode') === false) {
                continue;
            }
            if (gzuncompress($match[3]) === false) {
                return false;
            }
        }

        return true;
    }
}
