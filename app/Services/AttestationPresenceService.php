<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Attendance\Services\PointageChefAgenceService;
use App\Models\Attendance\Pointage;
use App\Models\HistoriqueGeneration;
use App\Models\Reference\SourceFinancement;
use App\Models\Reference\TypeStage;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AttestationPresenceService
{
    public function __construct(
        private readonly PointageChefAgenceService $pointageCaService,
    ) {}

    /**
     * Génère le PDF d'attestation de présence pour un mois et des filtres donnés.
     *
     * @param  array<string, mixed>  $filters
     */
    public function genererAttestation(
        string $moisPointage,
        array $filters = [],
        ?array $pointageIds = null,
        ?int $typeStageId = null,
        int $modeTraitement = 1,
    ): mixed {
        // 1. Récupérer les pointages validés pour ce mois
        // On construit une requête directe (pas via getPointagesEnAttente qui filtre SOUMIS)
        $query = Pointage::with([
            'stage.beneficiaire',
            'stage.entreprise',
            'stage.agence',
            'stage.sourceFinancement',
            'stage.typeStage',
            'periode',
            'versionCourante.saisiPar',
        ])
            ->where('statut', 'VALIDE')
            ->whereHas('periode', function ($q) use ($moisPointage) {
                $q->where('code', $moisPointage);
            });

        // Appliquer le scope agence du CA connecté
        $user = Auth::user();
        if ($user !== null && $user->agence_id) {
            $query->whereHas('stage', function ($q) use ($user) {
                $q->where('agence_id', $user->agence_id);
            });
        }

        // Appliquer les filtres additionnels
        if (! empty($filters['source_financement_id'])) {
            $query->whereHas('stage', function ($q) use ($filters) {
                $q->where('source_financement_id', $filters['source_financement_id']);
            });
        }

        if (! empty($filters['agence_id'])) {
            $query->whereHas('stage', function ($q) use ($filters) {
                $q->where('agence_id', $filters['agence_id']);
            });
        }

        if (! empty($filters['entreprise_id'])) {
            $query->whereHas('stage', function ($q) use ($filters) {
                $q->where('entreprise_id', $filters['entreprise_id']);
            });
        }

        if (! empty($filters['type_stage_id'])) {
            $query->whereHas('stage', function ($q) use ($filters) {
                $q->where('type_stage_id', $filters['type_stage_id']);
            });
        }

        // Si des IDs spécifiques sont fournis, les filtrer
        if ($pointageIds !== null && count($pointageIds) > 0) {
            $query->whereIn('pointages.id', $pointageIds);
        }

        $pointages = $query->get();

        if ($pointages->isEmpty()) {
            throw new \RuntimeException('Aucun pointage validé trouvé pour cette période et ces filtres.');
        }

        // 2. Déterminer le type de stage pour le libellé
        $typeStageLabel = $this->determinerTypeStageLabel($typeStageId, $pointages);

        // 3. Déterminer la vue Blade selon la source de financement
        $sourceFinancementId = $filters['source_financement_id'] ?? $pointages->first()?->stage?->source_financement_id;
        $view = $this->determinerVue($sourceFinancementId);

        // 4. Préparer les données agence
        $user = Auth::user();
        $dataAgence = [
            'chef_agence' => $user?->agence?->chef_agence ?? 'N/A',
            'agence' => $user?->agence?->nom ?? 'N/A',
        ];

        // 5. Préparer les données pour la pagination
        $paginatedContrats = preparePaginatedDataWithFooterSpace($pointages);

        // 6. Calculer le nombre total
        $totalContrats = $pointages->count();

        // 7. Générer le PDF
        $pdf = Pdf::loadView($view, [
            'paginatedContrats' => $paginatedContrats,
            'mois_pointage' => $moisPointage,
            'type_stage' => $typeStageLabel,
            'data_agence' => $dataAgence,
            'mode_traitement' => $modeTraitement,
            'totalContrats' => $totalContrats,
            'contrats' => $pointages,
            'dossier' => null,
        ]);

        // 8. Configurer le contexte SSL et le papier
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

        // 9. Ajouter la numérotation des pages
        $canvas = $pdf->get_canvas();
        $canvas->page_text(
            10,
            $canvas->get_height() - 20,
            'P. {PAGE_NUM} / {PAGE_COUNT}',
            null,
            10,
            [0, 0, 0]
        );

        // 10. Logger l'historique
        $this->logGeneration($moisPointage, $totalContrats, $sourceFinancementId);

        return $pdf;
    }

    /**
     * Détermine le libellé du type de stage.
     */
    private function determinerTypeStageLabel(?int $typeStageId, Collection $pointages): string
    {
        if ($typeStageId !== null) {
            $typeStage = TypeStage::find($typeStageId);
            if ($typeStage) {
                // IDs legacy 1, 3, 4 => STAGE DE QUALIFICATION OU D'EXPERIENCE PROFESSIONNELLE
                // Autres => STAGE DE VALIDATION
                $legacyIds = [1, 3, 4];
                if (in_array($typeStage->id, $legacyIds)) {
                    return "STAGE DE QUALIFICATION OU D'EXPERIENCE PROFESSIONNELLE";
                }

                return 'STAGE DE VALIDATION';
            }
        }

        // Fallback : utiliser le premier type de stage trouvé dans les pointages
        $firstTypeStage = $pointages->first()?->stage?->typeStage;
        if ($firstTypeStage) {
            return Str::upper($firstTypeStage->nom);
        }

        return "STAGE DE QUALIFICATION OU D'EXPERIENCE PROFESSIONNELLE";
    }

    /**
     * Détermine la vue Blade à utiliser selon la source de financement.
     */
    private function determinerVue(?int $sourceFinancementId): string
    {
        if ($sourceFinancementId === null) {
            return 'attestation_presence.budget-aej';
        }

        $source = SourceFinancement::find($sourceFinancementId);
        if ($source === null) {
            return 'attestation_presence.budget-aej';
        }

        $code = $source->code ?? '';

        return match ($code) {
            'PAPS_GOUV' => 'attestation_presence.paps-gouv',
            'C2D' => 'attestation_presence.c2d',
            'PEJEDEC' => 'attestation_presence.pejedec',
            'BUDGET_ETAT' => 'attestation_presence.budget-aej',
            default => 'attestation_presence.budget-aej',
        };
    }

    /**
     * Log la génération dans l'historique.
     */
    private function logGeneration(string $moisPointage, int $totalContrats, ?int $sourceFinancementId): void
    {
        try {
            $source = $sourceFinancementId ? SourceFinancement::find($sourceFinancementId) : null;

            HistoriqueGeneration::create([
                'uuid_public' => Str::uuid(),
                'type_document' => 'ATTESTATION_PRESENCE',
                'instance_parcours_id' => null,
                'user_id' => Auth::id(),
                'nom_fichier' => "ATTESTATION_PRESENCE_{$moisPointage}.pdf",
                'parametres' => [
                    'mois' => $moisPointage,
                    'total_stagiaires' => $totalContrats,
                ],
                'source_financement' => $source?->nom ?? 'N/A',
                'type_stage' => null,
                'nombre_stagiaires' => $totalContrats,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Impossible de logger la génération d\'attestation', [
                'mois' => $moisPointage,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Génère le nom de fichier pour l'attestation.
     */
    public function genererNomFichier(string $moisPointage): string
    {
        $moisLettre = Str::upper(Carbon::parse($moisPointage)->isoFormat('MMMM_YYYY'));

        return "ATTESTATION_PRESENCE_{$moisLettre}.pdf";
    }
}
