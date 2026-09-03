<?php

namespace App\Domain\Attendance\Services;

use App\Domain\Workflow\Services\WorkflowTransitionService;
use App\Enums\CorbeilleEnum;
use App\Models\Attendance\DecisionPointage;
use App\Models\Attendance\Pointage;
use App\Models\Attendance\VersionPointage;
use App\Models\Internship\Stage;
use App\Models\Payment\DroitPaiement;
use App\Models\Payment\Paiement;
use App\Models\Reference\Periode;
use App\Models\Reference\SituationStage;
use App\Models\Reference\SourceFinancement;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class PointageService
{
    public function __construct(private WorkflowTransitionService $workflowService) {}

    public function getCountsByTab(?int $periodeId, $stageFilters = []): array
    {
        $counts = [
            'attente' => 0,
            'attente_pejedec' => 0,
            'effectue' => 0,
            'ajourne_ca' => 0,
            'ajourne_dmg' => 0,
        ];

        if (! $periodeId) {
            return $counts;
        }

        $periode = Periode::find($periodeId);
        if (! $periode) {
            return $counts;
        }

        $queryAttente = Stage::where('situation_stage', SituationStage::CODE_EN_COURS)
            ->where('date_debut', '<=', $periode->date_fin)
            ->where(function ($q) use ($periode) {
                $q->whereNull('date_fin_prevue')
                    ->orWhere('date_fin_prevue', '>=', $periode->date_debut);
            })
            ->whereDoesntHave('pointages', function ($q) use ($periodeId) {
                $q->where('periode_id', $periodeId)
                    ->whereIn('statut', ['SOUMIS', 'VALIDE', 'CORRIGE_CIP', 'AJOURNE_CA', 'AJOURNE_DMG']);
            })
            // Le CA doit avoir validé le dossier avant que le CIP ne pointe un mois :
            // légacy `etat_chef_agence = 2` (WaitCheckedChefAgenceService).
            ->whereDoesntHave('instanceParcours', function ($q) {
                $q->whereIn('corbeille_actuelle', CorbeilleEnum::nonValideesParCa());
            });

        // Appliquer les filtres de stage si présents
        if (! empty($stageFilters['agence_id'])) {
            $queryAttente->where('agence_id', $stageFilters['agence_id']);
        }
        if (! empty($stageFilters['entreprise_id'])) {
            $queryAttente->where('entreprise_id', $stageFilters['entreprise_id']);
        }
        if (! empty($stageFilters['source_financement_id'])) {
            $queryAttente->where('source_financement_id', $stageFilters['source_financement_id']);
        }
        if (! empty($stageFilters['type_stage_id'])) {
            $queryAttente->where('type_stage_id', $stageFilters['type_stage_id']);
        }

        $attenteCounts = (clone $queryAttente)
            ->selectRaw('SUM(CASE WHEN source_financement_id = 4 THEN 1 ELSE 0 END) as pejedec, SUM(CASE WHEN source_financement_id != 4 THEN 1 ELSE 0 END) as normal')
            ->first();
        $counts['attente'] = (int) ($attenteCounts->normal ?? 0);
        $counts['attente_pejedec'] = (int) ($attenteCounts->pejedec ?? 0);

        $stageFilterScope = function ($q) use ($stageFilters) {
            if (! empty($stageFilters['agence_id'])) {
                $q->where('agence_id', $stageFilters['agence_id']);
            }
            if (! empty($stageFilters['entreprise_id'])) {
                $q->where('entreprise_id', $stageFilters['entreprise_id']);
            }
            if (! empty($stageFilters['source_financement_id'])) {
                $q->where('source_financement_id', $stageFilters['source_financement_id']);
            }
            if (! empty($stageFilters['type_stage_id'])) {
                $q->where('type_stage_id', $stageFilters['type_stage_id']);
            }
        };

        $pointageStatuts = Pointage::where('periode_id', $periodeId)
            ->whereIn('statut', ['SOUMIS', 'VALIDE', 'CORRIGE_CIP'])
            ->whereHas('stage', $stageFilterScope)
            ->selectRaw('statut, COUNT(*) as total')
            ->groupBy('statut')
            ->pluck('total', 'statut');

        $counts['effectue'] = (int) (($pointageStatuts['SOUMIS'] ?? 0) + ($pointageStatuts['VALIDE'] ?? 0) + ($pointageStatuts['CORRIGE_CIP'] ?? 0));

        // Legacy `PointageService::getPointageAjournerByChefAgence()` restreint aux stagiaires encore
        // dans le dispositif (`contrats_pae.id_situation_stage = 1`) : même filtre que l'onglet
        // correspondant dans PointageCipController, sinon le badge et la liste divergent.
        $counts['ajourne_ca'] = Pointage::where('periode_id', $periodeId)
            ->where('statut', 'AJOURNE_CA')
            ->whereHas('stage', function ($q) use ($stageFilterScope) {
                $q->where('situation_stage', SituationStage::CODE_EN_COURS);
                $stageFilterScope($q);
            })
            ->count();

        $user = Auth::user();

        $counts['ajourne_dmg'] = Paiement::where('statut', 'AJOURNE_DMG')
            ->whereHas('droitPaiement', function ($q) use ($periodeId) {
                $q->where('periode_id', $periodeId)
                    ->whereNotNull('pointage_id')
                    ->whereHas('pointage', fn ($pointage) => $pointage->where('statut', 'VALIDE'));
            })
            ->whereHas('droitPaiement.pointage.stage', function ($q) use ($stageFilterScope, $user) {

                if ($user?->agence_id) {
                    $q->where('agence_id', $user->agence_id);
                }

                $stageFilterScope($q);
            })
            ->count();

        return $counts;
    }

    public function soumettreIndividuel(
        Stage $stage,
        Periode $periode,
        User $cip,
        ?string $situationStageCode = null,
        ?string $observation = null,
        ?string $justificatifPath = null
    ): Pointage {
        $isAbsent = false;
        if ($situationStageCode) {
            $code = strtolower($situationStageCode);
            if (str_contains($code, 'abandon') || str_contains($code, 'suspension') || str_contains($code, 'fin')) {
                $isAbsent = true;
            }
        }

        $joursPresents = $isAbsent ? 0 : 30;
        $joursAbsents = $isAbsent ? 30 : 0;

        return $this->soumettreMensuel(
            $stage,
            $periode,
            $joursPresents,
            $joursAbsents,
            $cip,
            $observation,
            $situationStageCode,
            $justificatifPath
        );
    }

    /**
     * Le CIP soumet les présences mensuelles d'un stagiaire.
     */
    public function soumettreMensuel(
        Stage $stage,
        Periode $periode,
        int $joursPresents,
        int $joursAbsents,
        User $cip,
        ?string $observation = null,
        ?string $situationStageCode = null,
        ?string $justificatifPath = null
    ): Pointage {
        return DB::transaction(function () use ($stage, $periode, $joursPresents, $joursAbsents, $cip, $observation, $situationStageCode, $justificatifPath) {
            // 1. Chercher s'il existe déjà un pointage pour cette période
            $pointage = Pointage::firstOrCreate(
                ['stage_id' => $stage->id, 'periode_id' => $periode->id, 'nature' => 'MENSUEL'],
                ['statut' => 'SOUMIS', 'version_courante' => 0]
            );

            // 2. Incrémenter la version
            $pointage->increment('version_courante');
            $pointage->update(['statut' => 'SOUMIS']);

            $donneesComplementaires = [];
            if ($situationStageCode) {
                $donneesComplementaires['situation'] = $situationStageCode;
            }
            if ($justificatifPath) {
                $donneesComplementaires['justificatif'] = $justificatifPath;
            }

            // 3. Créer la nouvelle version (snapshot)
            $version = VersionPointage::create([
                'pointage_id' => $pointage->id,
                'saisi_par_id' => $cip->id,
                'numero_version' => $pointage->version_courante,
                'presence' => $joursPresents > 0 ? 'PRESENT' : 'ABSENT',
                'jours_presents' => $joursPresents,
                'jours_absents' => $joursAbsents,
                'observation' => $observation,
                'donnees_complementaires' => empty($donneesComplementaires) ? null : json_encode($donneesComplementaires),
            ]);

            return $pointage;
        });
    }

    /**
     * Le Chef d'Agence valide le pointage, ce qui génère un Droit de Paiement.
     */
    public function validerMensuel(Pointage $pointage, User $ca): DroitPaiement
    {
        return DB::transaction(function () use ($pointage, $ca) {
            if ($pointage->statut !== 'SOUMIS') {
                throw new InvalidArgumentException("Le pointage n'est pas dans l'état SOUMIS.");
            }

            // 1. Mettre à jour le statut du pointage
            $pointage->update(['statut' => 'VALIDE']);

            // 2. Enregistrer la décision
            $versionCourante = $pointage->versionCourante;
            DecisionPointage::create([
                'pointage_id' => $pointage->id,
                'version_pointage_id' => $versionCourante->id,
                'auteur_id' => $ca->id,
                'decision' => 'VALIDE',
            ]);

            // 3. Générer le Droit de Paiement de nature PRESENCE
            $stage = $pointage->stage;
            $contratActif = $stage->contrats()->latest()->first();

            // Calcul au prorata si nécessaire. Simplifié ici :
            $montantPaiement = $contratActif ? $contratActif->prime_mensuelle : 0;

            $sourceFinancement = SourceFinancement::first();

            $droitPaiement = DroitPaiement::create([
                'stage_id' => $stage->id,
                'pointage_id' => $pointage->id,
                'periode_id' => $pointage->periode_id,
                'source_financement_id' => $sourceFinancement ? $sourceFinancement->id : 1,
                'nature' => 'PRESENCE',
                'montant' => $montantPaiement,
                'statut' => 'OUVERT',
            ]);

            // 4. Générer le paiement correspondant et le mettre en attente DMG
            $paiement = Paiement::create([
                'uuid_public' => (string) Str::uuid(),
                'droit_paiement_id' => $droitPaiement->id,
                'montant' => $droitPaiement->montant,
                'statut' => 'A_TRAITER',
                'version_verrouillage' => 0,
            ]);
            $this->workflowService->dmgReceptionnePaiement($paiement);

            return $droitPaiement;
        });
    }
}
