<?php

namespace App\Domain\Workflow\Services;

use App\Enums\CorbeilleEnum;
use App\Models\Attendance\Pointage;
use App\Models\Payment\BordereauPaiement;
use App\Models\Payment\DossierPaiement;
use App\Models\Payment\OrdrePaiement;
use App\Models\Payment\Paiement;
use App\Models\Workflow\InstanceParcours;
use Carbon\Carbon;

class WorkflowTransitionService
{
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
     * 11. La DESSE traite un doublon et le retire de la corbeille de traitement.
     */
    public function desseTraiteDoublon(InstanceParcours $instance): void
    {
        $instance->update(['corbeille_actuelle' => CorbeilleEnum::DESSE_DOUBLONS_TRAITES->value]);
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
