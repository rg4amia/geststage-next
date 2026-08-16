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
}
