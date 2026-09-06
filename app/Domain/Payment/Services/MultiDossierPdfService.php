<?php

declare(strict_types=1);

namespace App\Domain\Payment\Services;

use App\Models\Payment\DossierGroupe;
use App\Models\Payment\Paiement;
use App\Models\Reference\SourceFinancement;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class MultiDossierPdfService
{
    /**
     * Récupère tous les paiements d'un groupe de dossiers.
     */
    private function collectPaiements(DossierGroupe $groupe): Collection
    {
        $dossierIds = $groupe->dossiers()->pluck('dossiers_paiement.id');

        return Paiement::query()
            ->with([
                'droitPaiement.stage.beneficiaire',
                'droitPaiement.stage.entreprise',
                'droitPaiement.stage.agence',
                'droitPaiement.stage.sourceFinancement',
                'droitPaiement.stage.typeStage',
                'droitPaiement.stage.contrats',
            ])
            ->whereHas('dossiersPaiement', fn ($q) => $q->whereIn('dossiers_paiement.id', $dossierIds))
            ->orderBy('paiements.id')
            ->limit(500)
            ->get();
    }

    /**
     * Construit le PDF d'attestation de présence pour un lot de paiements (partagé entre les
     * multi-dossiers et les dossiers simples). Vue différenciée par source de financement
     * (PAPS-GOUV / Budget AEJ / C2D), paginée avec espace pied de page réservé.
     *
     * @param  Collection<int, Paiement>  $paiements
     */
    public function construireAttestation(Collection $paiements, ?int $sourceFinancementId, string $moisCode)
    {
        $codeFinancement = $this->codeFinancement($sourceFinancementId);
        $mois = \Carbon\Carbon::createFromFormat('Y-m', $moisCode)->locale('fr')->translatedFormat('F Y');

        $pdf = Pdf::loadView('pdf.dmg-attestation-presence', [
            'paiements' => $paiements,
            'financement' => $codeFinancement,
            'mois' => $mois,
            'moisCode' => $moisCode,
        ])->setPaper('a4', 'landscape');

        $this->configurerPdf($pdf);

        return $pdf;
    }

    /**
     * Construit le PDF d'état de paiement (état financier) pour un lot de paiements : paysage,
     * paginé avec espace pied de page réservé et solde total des primes en dernière page.
     *
     * @param  Collection<int, Paiement>  $paiements
     */
    public function construireEtatFinancier(Collection $paiements, string $moisCode)
    {
        $pages = preparePaginatedDataWithFooterSpace($paiements);
        $mois = \Carbon\Carbon::createFromFormat('Y-m', $moisCode)->locale('fr')->translatedFormat('F Y');

        $pdf = Pdf::loadView('pdf.dmg-etat-paiement', [
            'pages' => $pages,
            'solde' => (float) $paiements->sum('montant'),
            'total' => $paiements->count(),
            'mois' => $mois,
            'moisCode' => $moisCode,
        ])->setPaper('a4', 'landscape');

        $this->configurerPdf($pdf);

        return $pdf;
    }

    /**
     * Génère l'attestation de présence pour un groupe de dossiers.
     * Retourne le chemin du fichier sauvegardé.
     */
    public function genererAttestation(DossierGroupe $groupe): ?string
    {
        $paiements = $this->collectPaiements($groupe);
        if ($paiements->isEmpty()) {
            return null;
        }

        $moisCode = $groupe->periode?->code ?? now()->format('Y-m');
        $pdf = $this->construireAttestation($paiements, $groupe->source_financement_id, $moisCode);

        $filename = 'attestation_presence_'.$groupe->numero.'.pdf';
        $path = 'multi_dossiers/'.$groupe->id;

        Storage::disk('temp_files')->makeDirectory($path);
        $fullPath = $path.'/'.$filename;
        $pdf->save(Storage::disk('temp_files')->path($fullPath));

        return $fullPath;
    }

    /**
     * Génère l'état financier pour un groupe de dossiers.
     * Retourne le chemin du fichier sauvegardé.
     */
    public function genererEtatFinancier(DossierGroupe $groupe): ?string
    {
        $paiements = $this->collectPaiements($groupe);
        if ($paiements->isEmpty()) {
            return null;
        }

        $moisCode = $groupe->periode?->code ?? now()->format('Y-m');
        $pdf = $this->construireEtatFinancier($paiements, $moisCode);

        $filename = 'etat_financier_'.$groupe->numero.'.pdf';
        $path = 'multi_dossiers/'.$groupe->id;

        Storage::disk('temp_files')->makeDirectory($path);
        $fullPath = $path.'/'.$filename;
        $pdf->save(Storage::disk('temp_files')->path($fullPath));

        return $fullPath;
    }

    /**
     * Génère les deux PDFs (attestation + état financier) et sauvegarde les chemins sur le groupe.
     */
    public function genererPdfs(DossierGroupe $groupe): DossierGroupe
    {
        $attestationPath = $this->genererAttestation($groupe);
        $etatFinancierPath = $this->genererEtatFinancier($groupe);

        $groupe->update([
            'attestation_path' => $attestationPath,
            'etat_financier_path' => $etatFinancierPath,
        ]);

        return $groupe->fresh();
    }

    private function codeFinancement(?int $sourceFinancementId): ?string
    {
        if ($sourceFinancementId === null) {
            return null;
        }

        return SourceFinancement::whereKey($sourceFinancementId)->value('code');
    }

    private function configurerPdf($pdf): void
    {
        $pdf->getDomPDF()->setHttpContext(
            stream_context_create([
                'ssl' => [
                    'allow_self_signed' => true,
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ],
            ])
        );
        $pdf->output();
        $canvas = $pdf->get_canvas();
        $canvas->page_text(10, $canvas->get_height() - 20, 'P. {PAGE_NUM} / {PAGE_COUNT}', null, 10, [0, 0, 0]);
    }
}
