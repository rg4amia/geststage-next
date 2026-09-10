<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Workflow\Services\ListeStagiairesCipService;
use App\Models\User;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * Génère en arrière-plan le CSV de l'écran CIP « Mes Stagiaires ».
 *
 * Même mécanisme que ExporterSupervisionJob / GenererExportPaiementJob : l'avancement est
 * suivi par le batch qui porte le job, le fichier est stocké sous un nom dérivé de
 * l'identifiant de batch et le job réauthentifie le demandeur pour que l'export reste
 * confiné à son périmètre d'agences (résolu par ListeStagiairesCipService::agencesAutorisees()).
 */
class ExporterStagiairesCipJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const DOSSIER = 'exports/cip-stagiaires';

    /**
     * @param  array<string, mixed>  $filtres
     */
    public function __construct(
        public array $filtres = [],
        public ?int $demandeParId = null
    ) {}

    public function handle(ListeStagiairesCipService $service): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        // Le périmètre d'agences est résolu depuis l'utilisateur authentifié : en file
        // d'attente il n'y a pas de session, on ré-authentifie donc le demandeur pour que
        // l'export ne puisse pas déborder de son périmètre.
        if ($this->demandeParId !== null) {
            $user = User::find($this->demandeParId);
            if ($user) {
                Auth::login($user);
            }
        }

        $query = $service->query($this->filtres, $service->agencesAutorisees());
        $chemin = self::chemin($this->batch()?->id ?? $this->job?->uuid() ?? uniqid('export_'));

        Storage::disk('temp_files')->makeDirectory(self::DOSSIER);

        $handle = fopen(Storage::disk('temp_files')->path($chemin), 'w');

        // BOM UTF-8 pour Excel
        fwrite($handle, "\xEF\xBB\xBF");

        foreach ($service->lignesExport($query) as $ligne) {
            fputcsv($handle, $ligne, ';');
        }

        fclose($handle);
    }

    public static function chemin(string $batchId): string
    {
        return self::DOSSIER.'/'.$batchId.'.csv';
    }
}
