<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Contract\Services\RenouvellementService;
use App\Models\Internship\Stage;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RenewStagesJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param  array<int, int>  $stageIds
     */
    public function __construct(
        public array $stageIds,
        public int $dureeMois,
        public ?string $motif = null,
        public ?int $proposeParId = null
    ) {}

    public function handle(RenouvellementService $service): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $stages = Stage::query()
            ->with(['contrats.avenants'])
            ->whereIn('id', $this->stageIds)
            ->get();

        foreach ($stages as $stage) {
            try {
                $service->renouveler(
                    $stage,
                    $this->dureeMois,
                    $this->motif,
                    null,
                    null,
                    null,
                    null,
                    $this->proposeParId
                );
            } catch (\Throwable $e) {
                Log::error('Erreur renouvellement massif stage', [
                    'stage_id' => $stage->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
