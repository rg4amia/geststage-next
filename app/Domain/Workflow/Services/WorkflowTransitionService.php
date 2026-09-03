<?php

namespace App\Domain\Workflow\Services;

use App\Enums\CorbeilleEnum;
use App\Models\Attendance\Pointage;
use App\Models\Payment\BordereauPaiement;
use App\Models\Payment\DossierPaiement;
use App\Models\Payment\OrdrePaiement;
use App\Models\Payment\Paiement;
use App\Models\User;
use App\Models\Workflow\DefinitionParcours;
use App\Models\Workflow\EtapeParcours;
use App\Models\Workflow\EvenementParcours;
use App\Models\Workflow\InstanceParcours;
use App\Models\Workflow\TacheParcours;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use LogicException;

class WorkflowTransitionService
{
    /**
     * 0. Démarre une nouvelle instance de parcours sur l'étape initiale de la définition,
     * ouvre la première tâche pour son rôle responsable et journalise l'événement fondateur.
     *
     * @param  array<string, mixed>  $donneesInstance  Attributs supplémentaires de l'instance (ex. stage_id).
     * @param  array<string, mixed>  $donneesEvenement  Données libres à journaliser dans l'événement d'initialisation.
     */
    public function initier(
        DefinitionParcours $definition,
        User $acteur,
        array $donneesInstance = [],
        array $donneesEvenement = []
    ): InstanceParcours {
        $etapeInitiale = EtapeParcours::where('definition_parcours_id', $definition->id)
            ->where('initiale', true)
            ->firstOrFail();

        $instance = InstanceParcours::create(array_merge($donneesInstance, [
            'definition_parcours_id' => $definition->id,
            'etape_courante_id' => $etapeInitiale->id,
        ]));

        $this->ouvrirTache($instance, $etapeInitiale);

        EvenementParcours::create([
            'instance_parcours_id' => $instance->id,
            'etape_cible_id' => $etapeInitiale->id,
            'auteur_id' => $acteur->id,
            'type' => 'INITIALISATION',
            'cle_idempotence' => (string) Str::uuid(),
            'donnees' => $donneesEvenement,
        ]);

        return $instance;
    }

    /**
     * 0b. Fait transiter une instance vers une nouvelle étape : ferme sa tâche ouverte,
     * déplace l'instance, ouvre la tâche de l'étape cible et journalise la transition.
     *
     * @param  array<string, mixed>  $donnees  Données libres à journaliser dans l'événement de transition.
     */
    public function transitionner(
        InstanceParcours $instance,
        EtapeParcours $etapeCible,
        User $acteur,
        array $donnees = []
    ): TacheParcours {
        $tacheOuverte = TacheParcours::where('instance_parcours_id', $instance->id)
            ->where('statut', 'OUVERTE')
            ->first();

        if (! $tacheOuverte) {
            throw new LogicException("Aucune tâche active trouvée pour l'instance {$instance->id} : impossible de transitionner.");
        }

        $etapeSourceId = $tacheOuverte->etape_parcours_id;

        $tacheOuverte->update(['statut' => 'TERMINEE', 'fermee_le' => now()]);

        $instance->update(['etape_courante_id' => $etapeCible->id]);

        $nouvelleTache = $this->ouvrirTache($instance, $etapeCible);

        EvenementParcours::create([
            'instance_parcours_id' => $instance->id,
            'etape_source_id' => $etapeSourceId,
            'etape_cible_id' => $etapeCible->id,
            'auteur_id' => $acteur->id,
            'type' => 'TRANSITION',
            'cle_idempotence' => (string) Str::uuid(),
            'donnees' => $donnees,
        ]);

        return $nouvelleTache;
    }

    private function ouvrirTache(InstanceParcours $instance, EtapeParcours $etape): TacheParcours
    {
        return TacheParcours::create([
            'instance_parcours_id' => $instance->id,
            'etape_parcours_id' => $etape->id,
            'role_responsable_id' => $etape->role_responsable_id,
            'code_corbeille' => $etape->code_corbeille,
            'statut' => 'OUVERTE',
        ]);
    }

    /**
     * 1. Le CIP soumet le stagiaire.
     * Si la date de début est le mois en cours -> Démarrage.
     * Sinon -> Démarrage Omis.
     */
    public function submitToChefAgence(InstanceParcours $instance): void
    {
        $moisEnCours = Carbon::now()->format('Y-m');
        $dateDebutStage = $instance->stage?->date_debut;
        $moisDemarrage = $dateDebutStage ? substr((string) $dateDebutStage, 0, 7) : null;

        if ($moisDemarrage === $moisEnCours) {
            $instance->update(['corbeille_actuelle' => CorbeilleEnum::CA_ATTENTE_VALIDATION_DEMARRAGE]);
        } else {
            $instance->update(['corbeille_actuelle' => CorbeilleEnum::CA_ATTENTE_VALIDATION_OMIS]);
        }
    }

    /**
     * 2. Le CA valide le Démarrage -> Va à la DMG.
     */
    public function caValideDemarrage(InstanceParcours $instance): void
    {
        $instance->update(['corbeille_actuelle' => CorbeilleEnum::DMG_ATTENTE_PAIEMENT_DEMARRAGE]);
    }

    /**
     * 2b. Le CA ajourne la soumission initiale -> Retourne au CIP "Mes Stagiaires" pour correction.
     */
    public function caAjourneSoumission(InstanceParcours $instance): void
    {
        $instance->update(['corbeille_actuelle' => CorbeilleEnum::CIP_MES_STAGIAIRES]);
    }

    /**
     * 3. Le CA valide le Démarrage Omis -> Passe "EN_STAGE" pour que le CIP le voie dans le pointage mensuel.
     */
    public function caValideDemarrageOmis(InstanceParcours $instance): void
    {
        $instance->update(['corbeille_actuelle' => CorbeilleEnum::EN_STAGE]);
    }

    /**
     * 4. La DMG valide le paiement Démarrage -> Passe "EN_STAGE".
     */
    public function dmgValidePaiementDemarrage(InstanceParcours $instance): void
    {
        $instance->update(['corbeille_actuelle' => CorbeilleEnum::EN_STAGE]);
    }

    /**
     * 5. La DMG ajourne un pointage mensuel -> Le pointage passe AJOURNE_DMG
     */
    public function dmgAjournePointage(Pointage $pointage): void
    {
        $pointage->update(['statut' => 'AJOURNE_DMG']);
    }

    /**
     * 6. Le CIP corrige un pointage ajourné DMG -> Le CA le verra dans "Validation Ajourné ADP".
     */
    public function cipCorrigeAjournementDmg(Pointage $pointage): void
    {
        $pointage->update(['statut' => 'CORRIGE_CIP']);
    }

    /**
     * 6b. Le CA valide un pointage mensuel -> le pointage passe VALIDÉ et le paiement est en attente DMG.
     */
    public function caValidePointage(InstanceParcours $instance): void
    {
        // La validation du pointage par le CA est déjà faite dans le service.
        // Ici on s'assure que l'instance reste dans EN_STAGE
        // car le pointage validé génère un droit de paiement traité séparément.
        $instance->update(['corbeille_actuelle' => CorbeilleEnum::EN_STAGE]);
    }

    /**
     * 7. Le CA valide la correction -> Le pointage redevient SOUMIS pour le flux normal.
     */
    public function caValideAjournementAdp(Pointage $pointage): void
    {
        $pointage->update(['statut' => 'SOUMIS']);
    }

    /**
     * 8. Le CA rejette la correction du pointage -> Impact sur l'instance entière !
     * Retourne au CIP "Mes Stagiaires" pour correction du dossier.
     */
    public function caRejetteAjournementAdp(Pointage $pointage): void
    {
        // Rétrograde l'instance au début
        $pointage->stage->instanceParcours()->update([
            'corbeille_actuelle' => CorbeilleEnum::CIP_MES_STAGIAIRES,
        ]);

        // Optionnel: on supprime le pointage erroné ? Ou on le passe en "REJETE_DEFINITIF"
        $pointage->update(['statut' => 'REJETE_DEFINITIF']);
    }

    /**
     * 9. La DESSE valide un dossier PEJEDEC/AAF.
     * Le dossier devient visible côté DAICG dans la corbeille de suivi validé.
     */
    public function desseValidePejedec(InstanceParcours $instance): void
    {
        $instance->update(['corbeille_actuelle' => CorbeilleEnum::DAICG_VALIDES_DESSE->value]);
    }

    /**
     * 10. La DESSE ajourne un dossier vers l'agence.
     */
    public function desseAjournePejedec(InstanceParcours $instance): void
    {
        $instance->update(['corbeille_actuelle' => CorbeilleEnum::DESSE_RETOUR_AGENCE->value]);
    }

    /**
     * 10bis. La DESSE valide un dossier « retour Chef d'Agence » (doublon traité par l'agence).
     * Portage de la validation legacy (étape 7/8 → DMG, TraitementEtapeController etape_next=9 /
     * IndexDmgController::verification) : le dossier est libéré du circuit doublon et rejoint la
     * file DMG (équivalent de l'étape 9 « DMG : validé après vérification » côté mapper
     * LegacyMapperService), où le module DMG crée/reprend les droits et paiements.
     *
     * La file cible dépend de l'avancement du dossier : présence si le cycle mensuel de pointage a
     * déjà démarré (au moins un pointage validé par le Chef d'Agence), sinon démarrage. Sur les
     * dossiers de retour migrés du legacy, 86/91 ont déjà un cycle présence en cours — les orienter
     * vers la file « démarrage » les ferait apparaître au mauvais endroit côté DMG.
     */
    public function desseValideRetourAgence(InstanceParcours $instance): void
    {
        $cyclePresenceDemarre = $instance->stage?->pointages()
            ->where('statut', 'VALIDE')
            ->exists() ?? false;

        $corbeille = $cyclePresenceDemarre
            ? CorbeilleEnum::DMG_ATTENTE_PAIEMENT_PRESENCE
            : CorbeilleEnum::DMG_ATTENTE_PAIEMENT_DEMARRAGE;

        $instance->update(['corbeille_actuelle' => $corbeille->value]);
    }

    /**
     * 11. La DESSE traite un groupe de doublons.
     * "Avéré" -> les dossiers retournent à l'agence pour correction.
     * "Non avéré" -> les dossiers sont validés et transmis à la DAICG.
     */
    public function desseTraiteDoublons(Collection $instances, string $decision): void
    {
        $corbeille = $decision === 'avere'
            ? CorbeilleEnum::DESSE_RETOUR_AGENCE
            : CorbeilleEnum::DAICG_VALIDES_DESSE;

        InstanceParcours::whereIn('id', $instances->pluck('id'))
            ->update(['corbeille_actuelle' => $corbeille->value]);
    }

    /**
     * 12. Un paiement (Démarrage ou Présence) est généré -> attend son traitement par la DMG.
     */
    public function dmgReceptionnePaiement(Paiement $paiement): void
    {
        $corbeille = match ($paiement->droitPaiement?->nature) {
            'DEMARRAGE' => CorbeilleEnum::DMG_ATTENTE_PAIEMENT_DEMARRAGE,
            'PRESENCE' => CorbeilleEnum::DMG_ATTENTE_PAIEMENT_PRESENCE,
            default => null,
        };

        if ($corbeille) {
            $paiement->update(['corbeille_actuelle' => $corbeille]);
        }
    }

    /**
     * 13. La DMG regroupe des paiements dans un Dossier -> les paiements passent en élaboration OP.
     */
    public function dmgElaboreDossier(DossierPaiement $dossier): void
    {
        $dossier->paiements()->update(['corbeille_actuelle' => CorbeilleEnum::DMG_ELABORATION_OP]);
    }

    /**
     * 14. La DMG élabore un Ordre de Paiement à partir de dossiers validés CB -> les paiements attendent le bordereau.
     */
    public function dmgElaboreOp(OrdrePaiement $op): void
    {
        foreach ($op->dossiersPaiement as $dossier) {
            $dossier->paiements()->update(['corbeille_actuelle' => CorbeilleEnum::DMG_OP_ATTENTE_BORDEREAU]);
        }
    }

    /**
     * 15. La DMG transmet le Bordereau à l'Agent Comptable -> les paiements attendent le visa AC.
     */
    public function dmgTransmetBordereauAc(BordereauPaiement $bordereau): void
    {
        foreach ($bordereau->ordresPaiement as $op) {
            foreach ($op->dossiersPaiement as $dossier) {
                $dossier->paiements()->update(['corbeille_actuelle' => CorbeilleEnum::AC_BORDEREAU_OP_ATTENTE]);
            }
        }
    }

    /**
     * 16. L'AC vise le Bordereau -> le circuit est terminé, les paiements sortent des corbeilles.
     */
    public function acViseBordereau(BordereauPaiement $bordereau): void
    {
        foreach ($bordereau->ordresPaiement as $op) {
            foreach ($op->dossiersPaiement as $dossier) {
                $dossier->paiements()->update(['corbeille_actuelle' => null]);
            }
        }
    }

    /**
     * 17. L'AC diffère le Bordereau -> retour à la DMG pour correction, paiements différés.
     */
    public function acDiffereBordereau(BordereauPaiement $bordereau): void
    {
        foreach ($bordereau->ordresPaiement as $op) {
            foreach ($op->dossiersPaiement as $dossier) {
                $dossier->paiements()->update(['corbeille_actuelle' => CorbeilleEnum::DMG_OP_DIFFERE_AC]);
            }
        }
    }

    /**
     * 18. L'AC rejette définitivement le Bordereau -> les paiements sortent du circuit normal.
     */
    public function acRejetteBordereau(BordereauPaiement $bordereau): void
    {
        foreach ($bordereau->ordresPaiement as $op) {
            foreach ($op->dossiersPaiement as $dossier) {
                $dossier->paiements()->update([
                    'statut' => 'REJETE_DEFINITIF',
                    'corbeille_actuelle' => CorbeilleEnum::DMG_OP_REJETE_AC,
                ]);
            }
        }
    }
}
