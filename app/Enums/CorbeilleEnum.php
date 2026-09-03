<?php

namespace App\Enums;

enum CorbeilleEnum: string
{
    // ==========================================
    // CIP (Conseiller)
    // ==========================================
    case CIP_MES_STAGIAIRES = 'cip_mes_stagiaires';
    case CIP_POINTAGE = 'cip_pointage';
    case CIP_POINTAGE_AJOURNE_DMG = 'cip_pointage_ajourne_dmg';
    case CIP_AJOURNE_CA = 'cip_ajourne_ca';
    case CIP_AJOURNE_DESSE = 'cip_ajourne_desse';
    case CIP_AJOURNE_DMG = 'cip_ajourne_dmg';
    case CIP_AJOURNE_AAF = 'cip_ajourne_aaf';
    case CIP_DIFFERE_AC = 'cip_differe_ac';
    case CIP_POINTAGE_PEJEDEC = 'cip_pointage_pejedec';
    case CIP_FIN_CONTRAT = 'cip_fin_contrat';

    // ==========================================
    // CA (Chef d'Agence)
    // ==========================================
    case CA_ATTENTE_VALIDATION_DEMARRAGE = 'ca_attente_validation_demarrage';
    case CA_ATTENTE_VALIDATION_OMIS = 'ca_attente_validation_omis';
    case CA_RETOUR_AJOURNEMENT = 'ca_retour_ajournement';
    case EN_STAGE = 'en_stage'; // Déclenche le cycle dynamique des pointages mensuels
    case CA_VALIDATION_POINTAGES = 'ca_validation_pointages';
    case CA_VALIDATION_POINTAGE_AJOURNE_ADP = 'ca_validation_pointage_ajourne_adp';
    case CA_STAGIAIRE_DIFFERE_AC = 'ca_stagiaire_differe_ac';

    // ==========================================
    // DMG (Direction des Moyens Généraux)
    // ==========================================
    case DMG_ATTENTE_PAIEMENT_DEMARRAGE = 'dmg_attente_paiement_demarrage';
    case DMG_ATTENTE_PAIEMENT_PRESENCE = 'dmg_attente_paiement_presence';
    case DMG_ELABORATION_OP = 'dmg_elaboration_op';
    case DMG_OP_ATTENTE_BORDEREAU = 'dmg_op_attente_bordereau';
    case DMG_OP_DIFFERE_AC = 'dmg_op_differe_ac';
    case DMG_OP_REJETE_AC = 'dmg_op_rejete_ac';

    // ==========================================
    // CB (Chef de Bureau)
    // ==========================================
    case CB_DOSSIER_MULTIPLE = 'cb_dossier_multiple';
    case CB_ETAT_PAIEMENT_AJOURNE = 'cb_etat_paiement_ajourne';

    // ==========================================
    // AC (Agent Comptable)
    // ==========================================
    case AC_BORDEREAU_OP_ATTENTE = 'ac_bordereau_op_attente';

    // ==========================================
    // DESSE
    // ==========================================
    case DESSE_DOUBLONS_A_TRAITER = 'desse_doublons_a_traiter';
    case DESSE_ATTENTE_VERIFICATION_DMG = 'desse_attente_verification_dmg';
    case DESSE_RETOUR_AGENCE = 'desse_retour_agence';
    case DESSE_DOUBLONS_TRAITES = 'desse_doublons_traites';
    case DESSE_SUIVI_PROCESSUS = 'desse_suivi_processus';
    case DESSE_BENEFICIAIRES_2023 = 'desse_beneficiaires_2023';
    case DESSE_ATTENTE_CA = 'desse_attente_ca';
    case DESSE_SUIVI_ENREGISTRES = 'desse_suivi_enregistres';
    case DESSE_SUIVI_VALIDES_AR = 'desse_suivi_valides_ar';

    // ==========================================
    // DAICG
    // ==========================================
    case DAICG_VALIDES_CA = 'daicg_valides_ca';
    case DAICG_VALIDES_DESSE = 'daicg_valides_desse';
    case DAICG_SANS_CONTRAT = 'daicg_sans_contrat';
    case DAICG_ATTENTE_DMG = 'daicg_attente_dmg';

    public function label(): string
    {
        return match ($this) {
            self::CIP_MES_STAGIAIRES => 'CIP : Mes Stagiaires',
            self::CIP_POINTAGE => 'CIP : Pointage',
            self::CIP_POINTAGE_AJOURNE_DMG => 'CIP : Pointage ajourné DMG',
            self::CIP_AJOURNE_CA => "CIP : Ajourné par le Chef d'Agence",
            self::CIP_AJOURNE_DESSE => 'CIP : Ajourné par la DESSE',
            self::CIP_AJOURNE_DMG => 'CIP : Ajourné par la DMG',
            self::CIP_AJOURNE_AAF => 'CIP : Ajourné AAF',
            self::CIP_DIFFERE_AC => "CIP : Différé par l'Agent Comptable",
            self::CIP_POINTAGE_PEJEDEC => 'CIP : Pointage PEJEDEC',
            self::CIP_FIN_CONTRAT => 'CIP : Fin de contrat',
            self::CA_ATTENTE_VALIDATION_DEMARRAGE => "Chef d'Agence : Validation du démarrage",
            self::CA_ATTENTE_VALIDATION_OMIS => "Chef d'Agence : Validation du démarrage omis",
            self::CA_RETOUR_AJOURNEMENT => "Chef d'Agence : Retour d'ajournement",
            self::EN_STAGE => 'En stage',
            self::CA_VALIDATION_POINTAGES => "Chef d'Agence : Validation du pointage",
            self::CA_VALIDATION_POINTAGE_AJOURNE_ADP => "Chef d'Agence : Validation pointage ajourné",
            self::CA_STAGIAIRE_DIFFERE_AC => "Chef d'Agence : Stagiaire différé par l'AC",
            self::DMG_ATTENTE_PAIEMENT_DEMARRAGE => 'DMG : Attente paiement démarrage',
            self::DMG_ATTENTE_PAIEMENT_PRESENCE => 'DMG : Attente paiement présence',
            self::DMG_ELABORATION_OP => "DMG : Élaboration de l'ordre de paiement",
            self::DMG_OP_ATTENTE_BORDEREAU => 'DMG : Ordre de paiement en attente de bordereau',
            self::DMG_OP_DIFFERE_AC => "DMG : Ordre de paiement différé par l'AC",
            self::DMG_OP_REJETE_AC => "DMG : Ordre de paiement rejeté par l'AC",
            self::CB_DOSSIER_MULTIPLE => 'CB : Dossier multiple',
            self::CB_ETAT_PAIEMENT_AJOURNE => 'CB : État de paiement ajourné',
            self::AC_BORDEREAU_OP_ATTENTE => 'AC : Bordereau en attente',
            self::DESSE_DOUBLONS_A_TRAITER => 'DESSE : Doublons à traiter',
            self::DESSE_ATTENTE_VERIFICATION_DMG => 'DESSE : Attente vérification',
            self::DESSE_RETOUR_AGENCE => 'DESSE : Retour agence',
            self::DESSE_DOUBLONS_TRAITES => 'DESSE : Doublons traités',
            self::DESSE_SUIVI_PROCESSUS => 'DESSE : Suivi du processus',
            self::DESSE_BENEFICIAIRES_2023 => 'DESSE : Bénéficiaires 2023',
            self::DESSE_ATTENTE_CA => "DESSE : Attente Chef d'Agence",
            self::DESSE_SUIVI_ENREGISTRES => 'DESSE : Suivi des enregistrés',
            self::DESSE_SUIVI_VALIDES_AR => 'DESSE : Suivi des validés AR',
            self::DAICG_VALIDES_CA => "DAICG : Validés par le Chef d'Agence",
            self::DAICG_VALIDES_DESSE => 'DAICG : Validés par la DESSE',
            self::DAICG_SANS_CONTRAT => 'DAICG : Sans contrat',
            self::DAICG_ATTENTE_DMG => 'DAICG : Attente DMG',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c) => [$c->value => $c->label()])->all();
    }

    /**
     * Corbeilles où le Chef d'Agence n'a pas (ou plus) validé le dossier : le stage n'est pas
     * encore entré dans le cycle mensuel de pointage (`EN_STAGE`). Légacy : `etat_chef_agence != 2`.
     *
     * @return array<int, string>
     */
    public static function nonValideesParCa(): array
    {
        return [
            self::CIP_MES_STAGIAIRES->value,
            self::CA_ATTENTE_VALIDATION_DEMARRAGE->value,
            self::CA_ATTENTE_VALIDATION_OMIS->value,
            self::CA_RETOUR_AJOURNEMENT->value,
        ];
    }
}
