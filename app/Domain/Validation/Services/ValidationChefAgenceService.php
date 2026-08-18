<?php

namespace App\Domain\Validation\Services;

use App\Domain\Workflow\Services\WorkflowTransitionService;
use App\Models\Adjournment\Ajournement;
use App\Models\Payment\DroitPaiement;
use App\Models\Payment\Paiement;
use App\Models\Reference\Periode;

use App\Models\User;
use App\Models\Workflow\EtapeParcours;
use App\Models\Workflow\InstanceParcours;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ValidationChefAgenceService
{
    protected $workflowService;

    public function __construct(WorkflowTransitionService $workflowService)
    {
        $this->workflowService = $workflowService;
    }

    /**
     * Valide le démarrage d'un stage par le Chef d'Agence.
     * Cela génère un Droit de Paiement de nature DEMARRAGE.
     */
    public function validerDemarrage(InstanceParcours $instance, User $ca): DroitPaiement
    {
        return DB::transaction(function () use ($instance) {
            $stage = $instance->stage;

            if (! $stage) {
                throw new InvalidArgumentException("Cette instance de parcours n'est pas liée à un stage.");
            }

            // 1. Générer le droit de paiement (DÉMARRAGE)
            $contratActif = $stage->contrats()->latest()->first();
            $montantDemarrage = $contratActif ? $contratActif->prime_mensuelle : 0;

            // Source de financement liée au stage (et non la première de la table)
            $sourceFinancementId = $stage->source_financement_id;

            // Période dont la plage date_debut–date_fin couvre la date de début du stage.
            // Fallback sur la période la plus récente si aucune ne correspond exactement.
            $dateDebutStage = $stage->date_debut;
            $periodeCourante = $dateDebutStage
                ? Periode::query()
                    ->where('date_debut', '<=', $dateDebutStage)
                    ->where('date_fin', '>=', $dateDebutStage)
                    ->orderByDesc('date_debut')
                    ->first()
                    ?? Periode::query()->orderByDesc('date_debut')->first()
                : Periode::query()->orderByDesc('date_debut')->first();

            $droitPaiement = DroitPaiement::create([
                'stage_id'              => $stage->id,
                'pointage_id'           => null, // C'est un démarrage, pas un pointage de présence
                'periode_id'            => $periodeCourante?->id ?? 1,
                'source_financement_id' => $sourceFinancementId ?? 1,
                'nature'                => 'DEMARRAGE',
                'montant'               => $montantDemarrage,
                'statut'                => 'OUVERT',
            ]);

            // 2. Générer le paiement correspondant et le mettre en attente DMG
            $paiement = Paiement::create([
                'uuid_public'          => (string) Str::uuid(),
                'droit_paiement_id'    => $droitPaiement->id,
                'montant'              => $droitPaiement->montant,
                'statut'               => 'A_TRAITER',
                'version_verrouillage' => 0,
            ]);
            $this->workflowService->dmgReceptionnePaiement($paiement);

            // 3. Transitionner le workflow : l'instance passe à la DMG
            $this->workflowService->caValideDemarrage($instance);

            return $droitPaiement;
        });
    }

    /**
     * Ajourne le dossier (retour pour correction).
     */
    public function ajourner(InstanceParcours $instance, string $motif, EtapeParcours $etapeCible, User $ca): Ajournement
    {
        return DB::transaction(function () use ($instance, $motif, $etapeCible, $ca) {
            $etapeSourceId = $instance->etape_courante_id;

            // 1. Créer l'ajournement
            $ajournement = Ajournement::create([
                'instance_parcours_id' => $instance->id,
                'etape_source_id' => $etapeSourceId,
                'etape_cible_id' => $etapeCible->id,
                'auteur_id' => $ca->id,
                'motif' => $motif,
                'statut' => 'EN_ATTENTE',
            ]);

            // 2. Transitionner le workflow en arrière, vers le CIP
            $this->workflowService->caAjourneSoumission($instance);

            return $ajournement;
        });
    }
}
