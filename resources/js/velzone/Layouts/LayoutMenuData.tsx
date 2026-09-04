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
            'ca-validations',
            'desse-stagiaires',
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
            'pejedec-aaf-dashboard',
            'pejedec-aaf-validation',
            'pejedec-aaf-ajournes',
            'pejedec-aaf-corrections',
            'pejedec-aaf-paiement',
        ],
    },
];

export const menuItems: MenuItem[] = [
    {
        label: 'Menu Principal',
        isHeader: true,
    },
    {
        id: 'dashboard',
        label: 'Tableau de bord',
        icon: 'ri-dashboard-2-line',
        link: '/dashboard',
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
