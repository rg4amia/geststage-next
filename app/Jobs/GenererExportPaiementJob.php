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
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

/**
 * Génère en arrière-plan un export de la page DMG (état de paiement, attestation de présence
 * ou de démarrage, fusion Trésor Pay, canvas Excel).
 *
 * L'export synchrone (`GET /dmg/paiements/generer-pdf`) suffit pour une petite sélection ;
 * les mois entiers dépassent 2 000 lignes et sortent du temps de réponse HTTP (dompdf est
 * gourmand en mémoire et en temps), d'où ce job dont l'avancement est suivi par le batch qui
 * le porte, comme ExporterVisasRegionauxJob pour la supervision régionale.
 *
 * Pour donner un indicateur de progression granulaire (0 → 33 → 66 → 100 %), chaque étape
 * majeure (requête BDD, construction du fichier, sauvegarde) écrit sa progression dans le
 * cache sous la clé `export_progress:{batchId}`. Le contrôleur `/progression` fusionne
 * cette valeur avec l'état du batch Laravel pour renvoyer un pourcentage cohérent.
 */
class GenererExportPaiementJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** TTL de la clé cache de progression (légèrement au-delà du timeout max du job). */
    private const CACHE_TTL = 3600;

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

        $batchId = $this->batch()?->id ?? $this->job?->uuid() ?? uniqid('export_');

        // En file d'attente il n'y a pas de session : on ré-authentifie le demandeur pour que
        // l'export soit généré avec son périmètre exact (cf. ExporterVisasRegionauxJob).
        if ($this->demandeParId !== null) {
            $user = User::find($this->demandeParId);
            if ($user) {
                Auth::login($user);
            }
        }

        // Étape 1/3 — Requête BDD (33 %)
        $this->avancer($batchId, 33);
        $paiements = $service->paiementsPour($this->nature, $this->mois, $this->filtres, $this->ids);

        if ($paiements->isEmpty()) {
            Cache::forget(self::cleCache($batchId));
            throw new \RuntimeException('Aucun paiement eligible pour cet export.');
        }

        // Étape 2/3 — Construction du fichier (66 %)
        $this->avancer($batchId, 66);

        if ($this->type === 'excel') {
            $fichier = $service->construireExcel($paiements, $this->nature, $this->mois);
        } else {
            $fichier = $service->construirePdf($this->type, $paiements, $this->mois, $this->filtres);
        }

        // Étape 3/3 — Sauvegarde sur disque (100 %)
        $this->avancer($batchId, 100);

        if ($this->type === 'excel') {
            $service->sauverExcel($fichier, $batchId);
        } else {
            $service->sauverPdf($fichier, $batchId);
        }
    }

    /**
     * Clé cache pour la progression interne d'un batch donné.
     */
    public static function cleCache(string $batchId): string
    {
        return "export_progress:{$batchId}";
    }

    /**
     * Écrit le pourcentage d'avancement dans le cache.
     */
    private function avancer(string $batchId, int $pourcentage): void
    {
        Cache::put(self::cleCache($batchId), $pourcentage, self::CACHE_TTL);
    }
}
