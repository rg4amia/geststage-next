export interface MenuHeaderItem {
    label: string;
    isHeader: true;
}

export interface MenuLinkItem {
    id: string;
    label: string;
    icon: string;
    link: string;
    actor?: string;
}

export type MenuItem = MenuHeaderItem | MenuLinkItem;

export interface BusinessMenuSection {
    id: string;
    label: string;
    icon: string;
    itemIds: string[];
}

/**
 * Regroupement du menu vertical par grandes étapes du parcours métier.
 *
 * Les entrées restent définies une seule fois dans `menuItems` afin que les
 * autres dispositions Velzon conservent leur présentation actuelle.
 */
export const businessMenuSections: BusinessMenuSection[] = [
    {
        id: 'stage-files',
        label: 'Dossiers de stage',
        icon: 'ri-team-line',
        itemIds: [
            'cip-mes-stagiaires',
            'cip-renouvellements',
            'ca-validations',
            'desse-stagiaires',
            'desse-suivi',
            'desse-beneficiaires',
            'desse-sans-contrat',
            'agence-regionale-visas',
            'daicg-stagiaires',
        ],
    },
    {
        id: 'attendance',
        label: 'Présences et pointages',
        icon: 'ri-calendar-check-line',
        itemIds: ['cip-pointages', 'ca-pointages', 'cip-ajourne-dmg'],
    },
    {
        id: 'situations',
        label: 'Suivi et situations',
        icon: 'ri-user-follow-line',
        itemIds: ['cip-suivi', 'cip-situations'],
    },
    {
        id: 'payments',
        label: 'Paiements et OP',
        icon: 'ri-money-dollar-circle-line',
        itemIds: ['dmg-paiements', 'cb-paiements', 'ac-paiements'],
    },
    {
        id: 'pejedec-aaf',
        label: 'PEJEDEC / AAF',
        icon: 'ri-stack-line',
        itemIds: [
            'pejedec-dossiers-validation',
            'pejedec-dossiers-valides',
            'pejedec-aaf-dashboard',
            'pejedec-aaf-validation',
            'pejedec-aaf-ajournes',
            'pejedec-aaf-corrections',
            'pejedec-aaf-paiement',
        ],
    },
    {
        id: 'parametre-aides',
        label: 'Parametre & Aides',
        icon: 'ri-settings-4-line',
        itemIds: [
            'parametre-aides-hub',
            'parametre-aides-comptes',
            'parametre-aides-conseillers',
            'parametre-aides-agences',
            'parametre-aides-entreprises',
            'parametre-aides-offres',
            'parametre-aides-systeme',
            'parametre-aides-journaux',
            'parametre-aides-aide',
        ],
    },
];

export const menuItems: MenuItem[] = [
    {
        label: 'Menu Principal',
        isHeader: true,
    },
    {
        id: 'reporting',
        label: 'Reporting',
        icon: 'ri-bar-chart-2-line',
        link: '/reporting',
    },
    {
        label: 'Espace CIP',
        isHeader: true,
    },
    {
        id: 'cip-mes-stagiaires',
        label: 'Mes Stagiaires',
        icon: 'ri-team-line',
        link: '/cip/mes-stagiaires',
        actor: 'CIP',
    },
    {
        id: 'cip-renouvellements',
        label: 'Renouvellements',
        icon: 'ri-refresh-line',
        link: '/cip/renouvellements',
        actor: 'CIP',
    },
    {
        id: 'cip-pointages',
        label: 'Présence - Pointages',
        icon: 'ri-calendar-check-line',
        link: '/cip/pointages',
        actor: 'CIP',
    },
    {
        id: 'cip-ajourne-dmg',
        label: 'Pointage Ajourné (DMG)',
        icon: 'ri-error-warning-line',
        link: '/cip/pointage/ajourne-dmg',
        actor: 'CIP',
    },
    {
        id: 'cip-suivi',
        label: 'Suivi et anomalies',
        icon: 'ri-alarm-warning-line',
        link: '/cip/suivi',
        actor: 'CIP',
    },
    {
        id: 'cip-situations',
        label: 'Situation des stagiaires',
        icon: 'ri-user-follow-line',
        link: '/cip/situation-stagiaire',
        actor: 'CIP',
    },
    {
        label: 'Espace Chef Agence',
        isHeader: true,
    },
    {
        id: 'ca-validations',
        label: 'Attente de validation',
        icon: 'ri-checkbox-circle-line',
        link: '/chefagence/validations',
        actor: 'CA',
    },
    {
        id: 'ca-pointages',
        label: 'Validation des Pointages',
        icon: 'ri-calendar-todo-line',
        link: '/chefagence/pointages',
        actor: 'CA',
    },
    {
        label: 'Espace DMG',
        isHeader: true,
    },
    {
        id: 'dmg-paiements',
        label: 'Paiements / Elaboration OP',
        icon: 'ri-money-dollar-circle-line',
        link: '/dmg/paiements',
        actor: 'DMG',
    },
    {
        label: 'Espace CB',
        isHeader: true,
    },
    {
        id: 'cb-paiements',
        label: 'Contrôle des paiements',
        icon: 'ri-shield-check-line',
        link: '/cb/paiements',
        actor: 'CB',
    },
    {
        label: 'Espace DESSE',
        isHeader: true,
    },
    {
        id: 'desse-stagiaires',
        label: 'Validation et doublons',
        icon: 'ri-folder-warning-line',
        link: '/desse/stagiaires',
        actor: 'DESSE',
    },
    {
        id: 'desse-suivi',
        label: 'Suivi des stagiaires',
        icon: 'ri-folder-open-line',
        link: '/desse/suivi',
        actor: 'DESSE',
    },
    {
        id: 'desse-beneficiaires',
        label: 'Bénéficiaires',
        icon: 'ri-group-line',
        link: '/desse/beneficiaires',
        actor: 'DESSE',
    },
    {
        id: 'desse-sans-contrat',
        label: 'Stagiaires sans contrat',
        icon: 'ri-file-forbid-line',
        link: '/desse/stagiaires-sans-contrat',
        actor: 'DESSE',
    },
    {
        id: 'agence-regionale-visas',
        label: 'Suivi Agence Régionale',
        icon: 'ri-shield-check-line',
        link: '/agence-regionale/visas',
        actor: 'DESSE',
    },
    {
        label: 'Espace DAICG',
        isHeader: true,
    },
    {
        id: 'daicg-stagiaires',
        label: 'Vue globale stagiaires',
        icon: 'ri-eye-line',
        link: '/daicg/stagiaires',
        actor: 'DAICG',
    },
    {
        label: 'PEJEDEC / AAF',
        isHeader: true,
    },
    {
        id: 'pejedec-dossiers-validation',
        label: 'Dossiers à valider',
        icon: 'ri-checkbox-circle-line',
        link: '/pejedec/attente-validation',
        actor: 'PEJEDEC',
    },
    {
        id: 'pejedec-dossiers-valides',
        label: 'Dossiers validés',
        icon: 'ri-folder-check-line',
        link: '/pejedec/valides',
        actor: 'PEJEDEC',
    },
    {
        id: 'pejedec-aaf-dashboard',
        label: 'Tableau PEJEDEC / AAF',
        icon: 'ri-stack-line',
        link: '/pejedec/af',
    },
    {
        id: 'pejedec-aaf-validation',
        label: 'Validation PEJEDEC',
        icon: 'ri-checkbox-circle-line',
        link: '/pejedec/af/attente-validation',
    },
    {
        id: 'pejedec-aaf-ajournes',
        label: 'Paiements ajournés',
        icon: 'ri-error-warning-line',
        link: '/pejedec/af/paiements-ajournes',
    },
    {
        id: 'pejedec-aaf-corrections',
        label: 'Corrections à valider',
        icon: 'ri-refresh-line',
        link: '/pejedec/af/corrections-a-valider',
    },
    {
        id: 'pejedec-aaf-paiement',
        label: 'Attente paiement',
        icon: 'ri-bank-card-line',
        link: '/pejedec/af/attente-paiement',
    },
    {
        label: 'Parametre & Aides',
        isHeader: true,
    },
    {
        id: 'parametre-aides-hub',
        label: 'Vue d’ensemble',
        icon: 'ri-settings-4-line',
        link: '/parametre-aides',
    },
    {
        id: 'parametre-aides-comptes',
        label: 'Comptes utilisateurs',
        icon: 'ri-shield-user-line',
        link: '/parametre-aides/comptes',
    },
    {
        id: 'parametre-aides-conseillers',
        label: 'Conseillers',
        icon: 'ri-user-star-line',
        link: '/parametre-aides/conseillers',
    },
    {
        id: 'parametre-aides-agences',
        label: 'Agences',
        icon: 'ri-building-4-line',
        link: '/parametre-aides/agences',
    },
    {
        id: 'parametre-aides-entreprises',
        label: 'Entreprises',
        icon: 'ri-community-line',
        link: '/entreprises',
    },
    {
        id: 'parametre-aides-offres',
        label: 'Offres',
        icon: 'ri-briefcase-4-line',
        link: '/offres',
    },
    {
        id: 'parametre-aides-systeme',
        label: 'Paramètres système',
        icon: 'ri-tools-line',
        link: '/parametre-aides/parametres-systeme',
    },
    {
        id: 'parametre-aides-journaux',
        label: 'Journaux d’activité',
        icon: 'ri-history-line',
        link: '/parametre-aides/journaux',
    },
    {
        id: 'parametre-aides-aide',
        label: 'Aide / Guide utilisateur',
        icon: 'ri-question-answer-line',
        link: '/parametre-aides/aide',
    },
    {
        label: 'Espace Agent Comptable',
        isHeader: true,
    },
    {
        id: 'ac-paiements',
        label: 'Visas des Bordereaux',
        icon: 'ri-file-shield-2-line',
        link: '/agent-comptable/paiements',
        actor: 'AC',
    },
];

const Navdata = () => ({
    props: { children: menuItems.map((item) => ({ ...item })) },
});

export default Navdata;
