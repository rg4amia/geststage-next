<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Contract\Services\RenouvellementService;
use App\Models\Contract\AvenantContrat;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DecideRenewalAvenantsJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param  array<int, int>  $avenantIds
     */
    public function __construct(
        public array $avenantIds,
        public string $action, // 'valider' | 'ajourner'
        public ?string $observation,
        public int $decideurId
    ) {}

    public function handle(RenouvellementService $service): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $avenants = AvenantContrat::query()
            ->whereIn('id', $this->avenantIds)
            ->where('statut', AvenantContrat::STATUT_ATTENTE_CA)
            ->get();

        foreach ($avenants as $avenant) {
            try {
                if ($this->action === 'valider') {
                    $service->validerParChefAgence($avenant, $this->decideurId);
                } else {
                    $service->ajournerParChefAgence($avenant, $this->observation ?? '', $this->decideurId);
                }
            } catch (\Throwable $e) {
                Log::error('Erreur décision massive avenant', [
                    'avenant_id' => $avenant->id,
                    'action' => $this->action,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
