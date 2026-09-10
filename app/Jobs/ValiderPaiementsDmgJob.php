<?php

namespace App\Jobs;

use App\Domain\Payment\Services\DmgService;
use App\Models\User;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class ValiderPaiementsDmgJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 500;

    public int $tries = 3;

    /** TTL de la clé cache de progression (légèrement au-delà du timeout max du job). */
    private const CACHE_TTL = 3600;

    /**
     * @param  list<int>  $paiementIds
     */
    public function __construct(
        public int $periodeId,
        public array $paiementIds,
        public string $action,
        public ?string $observation,
        public int $auteurId,
    ) {}

    public function handle(DmgService $service): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $batchId = $this->batch()?->id ?? $this->job?->uuid() ?? uniqid('validation_');

        // Étape 1/2 — Authentification + début du traitement (50 %)
        $this->avancer($batchId, 50);

        try {
            $auteur = User::findOrFail($this->auteurId);
            Auth::login($auteur);

            if ($this->action === 'ajourner') {
                $service->ajournerPaiements(
                    $this->paiementIds,
                    $this->observation ?: 'Ajournement DMG depuis la validation des paiements.',
                    $auteur,
                );
            } else {
                $service->genererDossiersPaiement(
                    $this->periodeId,
                    $this->paiementIds,
                    $auteur,
                    $batchId,
                );
            }

            // Étape 2/2 — Traitement terminé (100 %)
            $this->avancer($batchId, 100);
        } catch (Throwable $exception) {
            Cache::forget(self::cleCache($batchId));
            Log::error('Echec validation paiements DMG', [
                'periode_id' => $this->periodeId,
                'paiement_ids' => $this->paiementIds,
                'action' => $this->action,
                'auteur_id' => $this->auteurId,
                'exception' => $exception,
            ]);

            throw $exception;
        }
    }

    /**
     * Clé cache pour la progression interne d'un batch de validation.
     */
    public static function cleCache(string $batchId): string
    {
        return "validation_progress:{$batchId}";
    }

    /**
     * Écrit le pourcentage d'avancement dans le cache.
     */
    private function avancer(string $batchId, int $pourcentage): void
    {
        Cache::put(self::cleCache($batchId), $pourcentage, self::CACHE_TTL);
    }
}
