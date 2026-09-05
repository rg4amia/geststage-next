<?php

namespace App\Domain\Attendance\Services;

use App\Domain\Workflow\Services\WorkflowTransitionService;
use App\Models\Attendance\DecisionPointage;
use App\Models\Attendance\Pointage;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PointageChefAgenceService
{
    public function __construct(
        private readonly WorkflowTransitionService $workflowService,
    ) {}

    /**
     * Récupère les mois distincts ayant des pointages en attente de validation CA.
     *
     * @return Collection<int, array{value: string, label: string, count: int}>
     */
    public function getMoisEnAttente(?int $agenceId = null): Collection
    {
        $query = Pointage::query()
            ->where('statut', 'SOUMIS')
            // ─── EXCLURE LES INSTANCES TERMINÉES ────────────────────────────
            ->whereHas('stage.instanceParcours', fn (Builder $q) => $q->whereNull('terminee_le'))
            // ─── ÉLIGIBILITÉ : AU MOINS UN CONTRAT ACTIF ────────────────────
            ->whereHas('stage.contrats')
            ->whereHas('stage', function (Builder $q) use ($agenceId) {
                if ($agenceId) {
                    $q->where('agence_id', $agenceId);
                }
            })
            ->join('periodes', 'periodes.id', '=', 'pointages.periode_id')
            ->selectRaw('periodes.code as mois, COUNT(DISTINCT pointages.id) as total')
            ->groupBy('periodes.code')
            ->orderBy('periodes.code', 'asc');

        $rows = $query->get();

        return $rows->map(function ($row) {
            $code = (string) $row->mois;
            $formatted = Str::upper(Carbon::parse($code)->isoFormat('MMMM YYYY'));

            return [
                'value' => $code,
                'label' => $formatted,
                'count' => (int) $row->total,
            ];
        });
    }

    /**
     * Récupère les pointages en attente de validation CA pour un mois donné.
     *
     * @param  array<string, mixed>  $filters
     */
    public function getPointagesEnAttente(string $mois, array $filters = []): Builder
    {
        $query = Pointage::with([
            'stage.beneficiaire',
            'stage.entreprise',
            'stage.agence',
            'stage.sourceFinancement',
            'stage.typeStage',
            'stage.conseiller',
            'periode',
            'versionCourante.saisiPar',
        ])
            ->where('statut', 'SOUMIS')
            // ─── EXCLURE LES INSTANCES TERMINÉES ────────────────────────────
            ->whereHas('stage.instanceParcours', fn (Builder $q) => $q->whereNull('terminee_le'))
            // ─── ÉLIGIBILITÉ : AU MOINS UN CONTRAT ACTIF ────────────────────
            ->whereHas('stage.contrats')
            ->whereHas('periode', function (Builder $q) use ($mois) {
                $q->where('code', $mois);
            });

        $user = Auth::user();

        // Scope agence du CA connecté
        if ($user !== null && $user->agence_id) {
            $query->whereHas('stage', function (Builder $q) use ($user) {
                $q->where('agence_id', $user->agence_id);
            });
        }

        // Filtres additionnels
        $this->applyFilters($query, $filters);

        return $query;
    }

    /**
     * Récupère les pointages CORRIGE_CIP (ajournés DMG puis corrigés par le CIP).
     * Ceux-ci sont dans la corbeille "Validation Ajourné ADP" du CA.
     *
     * @param  array<string, mixed>  $filters
     */
    public function getPointagesCorrigesAdp(string $mois, array $filters = []): Builder
    {
        $query = Pointage::with([
            'stage.beneficiaire',
            'stage.entreprise',
            'stage.agence',
            'stage.sourceFinancement',
            'stage.typeStage',
            'periode',
            'versionCourante.saisiPar',
        ])
            ->where('statut', 'CORRIGE_CIP')
            // ─── EXCLURE LES INSTANCES TERMINÉES ────────────────────────────
            ->whereHas('stage.instanceParcours', fn (Builder $q) => $q->whereNull('terminee_le'))
            // ─── ÉLIGIBILITÉ : AU MOINS UN CONTRAT ACTIF ────────────────────
            ->whereHas('stage.contrats')
            ->whereHas('periode', function (Builder $q) use ($mois) {
                $q->where('code', $mois);
            });

        $user = Auth::user();
        if ($user !== null && $user->agence_id) {
            $query->whereHas('stage', function (Builder $q) use ($user) {
                $q->where('agence_id', $user->agence_id);
            });
        }

        $this->applyFilters($query, $filters);

        return $query;
    }

    /**
     * Valide un pointage individuel par le CA.
     * Met à jour le statut, crée une décision, et déclenche le workflow.
     */
    public function validerPointage(Pointage $pointage, User $ca): void
    {
        DB::transaction(function () use ($pointage, $ca) {
            $pointage->update(['statut' => 'VALIDE']);

            $versionId = $pointage->versionCourante?->id;

            DecisionPointage::create([
                'pointage_id' => $pointage->id,
                'version_pointage_id' => $versionId,
                'auteur_id' => $ca->id,
                'decision' => 'VALIDE',
                'motif' => null,
            ]);

            // Déclencher la transition workflow vers la DMG
            $instance = $pointage->stage->instanceParcours;
            if ($instance !== null) {
                $this->workflowService->caValidePointage($instance);
            }
        });
    }

    /**
     * Valide un groupe de pointages (batch).
     *
     * @param  array<int, int>  $pointageIds
     * @return array{success: int, errors: list<array{pointage_id: int, message: string}>}
     */
    public function validerGroupe(array $pointageIds, User $ca): array
    {
        $results = ['success' => 0, 'errors' => []];

        DB::transaction(function () use ($pointageIds, $ca, &$results) {
            $pointages = Pointage::whereIn('id', $pointageIds)
                ->where('statut', 'SOUMIS')
                ->get();

            foreach ($pointages as $pointage) {
                try {
                    $this->validerPointage($pointage, $ca);
                    $results['success']++;
                } catch (\Exception $e) {
                    $results['errors'][] = [
                        'pointage_id' => $pointage->id,
                        'message' => $e->getMessage(),
                    ];
                    Log::error("Erreur validation pointage {$pointage->id}: ".$e->getMessage());
                }
            }
        });

        return $results;
    }

    /**
     * Valide tous les pointages correspondant aux filtres donnés (source_financement + type_stage).
     * Utilisé pour la validation groupée par filtre (legacy validationGroup).
     *
     * @param  array<string, mixed>  $filters  Doit contenir source_financement_id et type_stage_id.
     * @return array{success: int, total: int, errors: list<array{pointage_id: int, message: string}>}
     */
    public function validerParFiltre(string $mois, array $filters, User $ca): array
    {
        $query = Pointage::query()
            ->where('statut', 'SOUMIS')
            // ─── EXCLURE LES INSTANCES TERMINÉES ────────────────────────────
            ->whereHas('stage.instanceParcours', fn (Builder $q) => $q->whereNull('terminee_le'))
            // ─── ÉLIGIBILITÉ : AU MOINS UN CONTRAT ACTIF ────────────────────
            ->whereHas('stage.contrats')
            ->whereHas('periode', fn (Builder $q) => $q->where('code', $mois));

        if ($ca->agence_id) {
            $query->whereHas('stage', fn (Builder $q) => $q->where('agence_id', $ca->agence_id));
        }

        $this->applyFilters($query, $filters);

        $pointageIds = $query->pluck('id')->all();

        if (empty($pointageIds)) {
            return ['success' => 0, 'total' => 0, 'errors' => []];
        }

        $results = $this->validerGroupe($pointageIds, $ca);
        $results['total'] = count($pointageIds);

        return $results;
    }

    /**
     * Ajourne un pointage (retour au CIP pour correction).
     */
    public function ajournerPointage(Pointage $pointage, User $ca, ?string $motif = null): void
    {
        DB::transaction(function () use ($pointage, $ca, $motif) {
            // Le statut est AJOURNE_CA (retour au CIP pour correction)
            // PAS dmgAjournePointage qui mettrait AJOURNE_DMG
            $pointage->update(['statut' => 'AJOURNE_CA']);

            $versionId = $pointage->versionCourante?->id;

            DecisionPointage::create([
                'pointage_id' => $pointage->id,
                'version_pointage_id' => $versionId,
                'auteur_id' => $ca->id,
                'decision' => 'AJOURNE',
                'motif' => $motif,
            ]);
        });
    }

    /**
     * Valide une correction de pointage ajourné (ADP).
     * Le pointage redevient SOUMIS pour le flux normal.
     */
    public function validerAjournementAdp(Pointage $pointage, User $ca): void
    {
        DB::transaction(function () use ($pointage, $ca) {
            $this->workflowService->caValideAjournementAdp($pointage);

            $versionId = $pointage->versionCourante?->id;

            DecisionPointage::create([
                'pointage_id' => $pointage->id,
                'version_pointage_id' => $versionId,
                'auteur_id' => $ca->id,
                'decision' => 'VALIDE_ADP',
                'motif' => 'Correction acceptée',
            ]);
        });
    }

    /**
     * Rejette une correction de pointage ajourné (ADP).
     * Renvoie le dossier dans la corbeille "Mes Stagiaires" du CIP.
     */
    public function rejeterAjournementAdp(Pointage $pointage, User $ca, ?string $motif = null): void
    {
        DB::transaction(function () use ($pointage, $ca, $motif) {
            $this->workflowService->caRejetteAjournementAdp($pointage);

            $versionId = $pointage->versionCourante?->id;

            DecisionPointage::create([
                'pointage_id' => $pointage->id,
                'version_pointage_id' => $versionId,
                'auteur_id' => $ca->id,
                'decision' => 'REJETE_ADP',
                'motif' => $motif ?? 'Dossier rejeté',
            ]);
        });
    }

    /**
     * Applique les filtres de recherche sur la requête pointages.
     *
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['agence_id'])) {
            $query->whereHas('stage', fn (Builder $q) => $q->where('agence_id', $filters['agence_id']));
        }

        if (! empty($filters['entreprise_id'])) {
            $query->whereHas('stage', fn (Builder $q) => $q->where('entreprise_id', $filters['entreprise_id']));
        }

        if (! empty($filters['source_financement_id'])) {
            $query->whereHas('stage', fn (Builder $q) => $q->where('source_financement_id', $filters['source_financement_id']));
        }

        if (! empty($filters['type_stage_id'])) {
            $query->whereHas('stage', fn (Builder $q) => $q->where('type_stage_id', $filters['type_stage_id']));
        }

        if (! empty($filters['conseiller_id'])) {
            $query->whereHas('stage', fn (Builder $q) => $q->where('conseiller_id', $filters['conseiller_id']));
        }

        if (! empty($filters['search'])) {
            $search = (string) $filters['search'];
            $query->whereHas('stage.beneficiaire', function (Builder $q) use ($search) {
                $q->where('nom', 'ilike', "%{$search}%")
                    ->orWhere('prenoms', 'ilike', "%{$search}%")
                    ->orWhere('numero_aej', 'ilike', "%{$search}%");
            });
        }

        if (! empty($filters['date_debut']) && ! empty($filters['date_fin'])) {
            $query->whereHas('stage', function (Builder $q) use ($filters) {
                $q->whereBetween('date_debut', [$filters['date_debut'], $filters['date_fin']]);
            });
        }
    }
}
