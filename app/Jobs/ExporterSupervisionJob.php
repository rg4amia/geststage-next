<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Supervision\Services\VisaRegionalService;
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
 * Génère en arrière-plan le CSV d'une liste de consultation DESSE (bénéficiaires,
 * stagiaires sans contrat).
 *
 * Même mécanisme que ExporterVisasRegionauxJob : l'avancement est suivi par le batch
 * qui porte le job, le fichier est stocké sous un nom dérivé de l'identifiant de batch
 * et réauthentifie le demandeur pour que l'export reste confiné à son périmètre
 * d'agences.
 */
class ExporterSupervisionJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const DOSSIER = 'exports/supervision';

    /**
     * @param  array<string, mixed>  $filtres
     */
    public function __construct(
        public string $liste,
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
            $user = User::find($this->demandeParId);
            if ($user) {
                Auth::login($user);
            }
        }

        $query = $this->liste === 'stagiaires-sans-contrat'
            ? $service->sansContratQuery($this->filtres)
            : $service->beneficiairesQuery($this->filtres);
        $chemin = self::chemin($this->batch()?->id ?? $this->job?->uuid() ?? uniqid('export_'));

        Storage::disk('local')->makeDirectory(self::DOSSIER);

        $handle = fopen(Storage::disk('local')->path($chemin), 'w');

        // BOM UTF-8 pour Excel
        fwrite($handle, "\xEF\xBB\xBF");

        foreach ($service->lignesExportConsultation($query) as $ligne) {
            fputcsv($handle, $ligne, ';');
        }

        fclose($handle);
    }

    public static function chemin(string $batchId): string
    {
        return self::DOSSIER.'/'.$batchId.'.csv';
    }
}
