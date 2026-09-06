<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Payment\Services\ExportPaiementDmgService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Auth;

/**
 * Génère en arrière-plan un export de la page DMG (état de paiement, attestation de présence
 * ou de démarrage, fusion Trésor Pay, canvas Excel).
 *
 * L'export synchrone (`GET /dmg/paiements/generer-pdf`) suffit pour une petite sélection ;
 * les mois entiers dépassent 2 000 lignes et sortent du temps de réponse HTTP (dompdf est
 * gourmand en mémoire et en temps), d'où ce job dont l'avancement est suivi par le batch qui
 * le porte, comme ExporterVisasRegionauxJob pour la supervision régionale.
 */
class GenererExportPaiementJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param  string  $type  etat_paiement|attestation_demarrage|attestation_presence|fusion_tresor|excel
     * @param  array<string, mixed>  $filtres
     * @param  list<int>|null  $ids
     */
    public function __construct(
        public string $type,
        public string $mois,
        public string $nature,
        public array $filtres = [],
        public ?array $ids = null,
        public ?int $demandeParId = null
    ) {}

    public function handle(ExportPaiementDmgService $service): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        // En file d'attente il n'y a pas de session : on ré-authentifie le demandeur pour que
        // l'export soit généré avec son périmètre exact (cf. ExporterVisasRegionauxJob).
        if ($this->demandeParId !== null) {
            Auth::loginUsingId($this->demandeParId);
        }

        $paiements = $service->paiementsPour($this->nature, $this->mois, $this->filtres, $this->ids);

        if ($paiements->isEmpty()) {
            throw new \RuntimeException('Aucun paiement eligible pour cet export.');
        }

        $batchId = $this->batch()?->id ?? $this->job?->uuid() ?? uniqid('export_');

        if ($this->type === 'excel') {
            $service->sauverExcel($service->construireExcel($paiements, $this->nature, $this->mois), $batchId);

            return;
        }

        $service->sauverPdf($service->construirePdf($this->type, $paiements, $this->mois, $this->filtres), $batchId);
    }
}