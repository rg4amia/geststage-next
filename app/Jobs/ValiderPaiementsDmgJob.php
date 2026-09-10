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
use Illuminate\Support\Facades\Log;
use Throwable;

class ValiderPaiementsDmgJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 500;

    public int $tries = 3;

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

        try {
            $auteur = User::findOrFail($this->auteurId);
            Auth::login($auteur);

            if ($this->action === 'ajourner') {
                $service->ajournerPaiements(
                    $this->paiementIds,
                    $this->observation ?: 'Ajournement DMG depuis la validation des paiements.',
                    $auteur,
                );

                return;
            }

            $service->genererDossiersPaiement(
                $this->periodeId,
                $this->paiementIds,
                $auteur,
                $this->batch()?->id,
            );
        } catch (Throwable $exception) {
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
}
