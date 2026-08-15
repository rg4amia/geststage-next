<?php

namespace App\Domain\Workflow\Services;

use App\Enums\CorbeilleEnum;
use App\Models\Workflow\InstanceParcours;
use App\Models\Attendance\Pointage;
use Illuminate\Support\Facades\DB;

class WorkflowTransitionService
{
    /**
     * 1. Le CIP soumet le stagiaire.
     * Si la date de début est le mois en cours -> Démarrage.
     * Sinon -> Démarrage Omis.
     */
    public function submitToChefAgence(InstanceParcours $instance): void
    {
        $moisEnCours = date('Y-m');
        $moisDemarrage = substr($instance->date_debut_reelle ?? $instance->date_debut_prevue, 0, 7);

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
            'corbeille_actuelle' => CorbeilleEnum::CIP_MES_STAGIAIRES
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
     * 11. La DESSE traite un doublon et le retire de la corbeille de traitement.
     */
    public function desseTraiteDoublon(InstanceParcours $instance): void
    {
        $instance->update(['corbeille_actuelle' => CorbeilleEnum::DESSE_DOUBLONS_TRAITES->value]);
    }
}
