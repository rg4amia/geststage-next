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

    // ==========================================
    // CA (Chef d'Agence)
    // ==========================================
    case CA_ATTENTE_VALIDATION_DEMARRAGE = 'ca_attente_validation_demarrage';
    case CA_ATTENTE_VALIDATION_OMIS = 'ca_attente_validation_omis';
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
}
