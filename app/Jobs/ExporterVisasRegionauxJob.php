<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Supervision\Services\VisaRegionalService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * Génère en arrière-plan le CSV d'un onglet de la supervision régionale.
 *
 * L'export synchrone (`GET .../export`) suffit pour une corbeille filtrée ; les onglets
 * globaux dépassent la centaine de milliers de lignes et sortent du temps de réponse HTTP,
 * d'où ce job dont l'avancement est suivi par le batch qui le porte.
 */
class ExporterVisasRegionauxJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const DOSSIER = 'exports/visas';

    /**
     * @param  array<string, mixed>  $filtres
     */
    public function __construct(
        public string $onglet,
        public array $filtres = [],
        public ?int $demandeParId = null
    ) {}

    public function handle(VisaRegionalService $service): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        // Le périmètre d'agences est résolu depuis l'utilisateur authentifié : en file
        // d'attente il n'y a pas de session, on ré-authentifie donc le demandeur pour que
        // l'export ne puisse pas déborder de son périmètre.
        if ($this->demandeParId !== null) {
            Auth::loginUsingId($this->demandeParId);
        }

        $query = $service->queryPourOnglet($this->onglet, $this->filtres);
        $chemin = self::chemin($this->batch()?->id ?? $this->job?->uuid() ?? uniqid('export_'));

        Storage::disk('local')->makeDirectory(self::DOSSIER);

        $handle = fopen(Storage::disk('local')->path($chemin), 'w');

        // BOM UTF-8 pour Excel
        fwrite($handle, "\xEF\xBB\xBF");

        foreach ($service->lignesExport($query, $this->onglet === 'differes_ac') as $ligne) {
            fputcsv($handle, $ligne, ';');
        }

        fclose($handle);
    }

    public static function chemin(string $batchId): string
    {
        return self::DOSSIER.'/'.$batchId.'.csv';
    }
}
