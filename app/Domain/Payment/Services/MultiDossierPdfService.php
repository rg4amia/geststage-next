<?php

declare(strict_types=1);

namespace App\Domain\Payment\Services;

use App\Models\Payment\DossierGroupe;
use App\Models\Payment\DossierPaiement;
use App\Models\Payment\Paiement;
use App\Models\Reference\SourceFinancement;
use Barryvdh\DomPDF\Facades\Pdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
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
     * Détermine la vue Blade attestation selon la source de financement.
     */
    private function attestationView(?int $sourceFinancementId): string
    {
        if ($sourceFinancementId === null) {
            return 'attestation_presence.budget-aej';
        }

        $source = SourceFinancement::find($sourceFinancementId);
        if ($source === null) {
            return 'attestation_presence.budget-aej';
        }

        return match ($source->code) {
            'PAPS_GOUV' => 'attestation_presence.paps-gouv',
            'C2D' => 'attestation_presence.c2d',
            'PEJEDEC' => 'attestation_presence.pejedec',
            default => 'attestation_presence.budget-aej',
        };
    }

    /**
     * Détermine la vue Blade état financier selon la source de financement.
     */
    private function etatFinancierView(?int $sourceFinancementId): string
    {
        $source = $sourceFinancementId ? SourceFinancement::find($sourceFinancementId) : null;
        $code = $source?->code ?? '';

        return match ($code) {
            'PAPS_GOUV' => 'pdf.dmg-paiements',
            default => 'pdf.dmg-paiements',
        };
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

        $sourceFinancementId = $groupe->source_financement_id;
        $view = $this->attestationView($sourceFinancementId);
        $moisPointage = $groupe->periode?->code ?? '';

        $paginatedContrats = preparePaginatedDataWithFooterSpace($paiements);
        $totalContrats = $paiements->count();

        $typeStageLabel = $this->determinerTypeStageLabel($paiements);

        $user = Auth::user();
        $dataAgence = [
            'chef_agence' => $user?->agence?->chef_agence ?? 'N/A',
            'agence' => $user?->agence?->nom ?? 'N/A',
        ];

        $pdf = Pdf::loadView($view, [
            'paginatedContrats' => $paginatedContrats,
            'mois_pointage' => $moisPointage,
            'type_stage' => $typeStageLabel,
            'data_agence' => $dataAgence,
            'mode_traitement' => 1,
            'totalContrats' => $totalContrats,
            'contrats' => $paiements,
            'dossier' => null,
        ]);

        $pdf->getDomPDF()->setHttpContext(
            stream_context_create([
                'ssl' => [
                    'allow_self_signed' => true,
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ],
            ])
        );

        $pdf->setPaper('A4', 'landscape');
        $pdf->output();

        $canvas = $pdf->get_canvas();
        $canvas->page_text(10, $canvas->get_height() - 20, 'P. {PAGE_NUM} / {PAGE_COUNT}', null, 10, [0, 0, 0]);

        $filename = 'attestation_presence_' . $groupe->numero . '.pdf';
        $path = 'multi_dossiers/' . $groupe->id;

        Storage::disk('temp_files')->makeDirectory($path);
        $fullPath = $path . '/' . $filename;
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

        $sourceFinancementId = $groupe->source_financement_id;
        $moisPointage = $groupe->periode?->code ?? '';

        $paginatedContrats = preparePaginatedDataWithFooterSpace($paiements);

        $pdf = Pdf::loadView('pdf.dmg-paiements', [
            'paiements' => $paiements,
            'titre' => 'État financier — ' . $groupe->numero,
            'type' => 'etat_paiement',
            'mois' => $moisPointage,
        ])->setPaper('a4', 'landscape');

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

        $filename = 'etat_financier_' . $groupe->numero . '.pdf';
        $path = 'multi_dossiers/' . $groupe->id;

        Storage::disk('temp_files')->makeDirectory($path);
        $fullPath = $path . '/' . $filename;
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

    /**
     * Détermine le libellé du type de stage.
     */
    private function determinerTypeStageLabel(Collection $paiements): string
    {
        $firstTypeStage = $paiements->first()?->droitPaiement?->stage?->typeStage;
        if ($firstTypeStage) {
            return strtoupper($firstTypeStage->nom);
        }

        return "STAGE DE QUALIFICATION OU D'EXPERIENCE PROFESSIONNELLE";
    }
}
