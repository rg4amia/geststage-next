import React, { useEffect, useState } from 'react';

const Navdata = () => {
    const [isCip, setIsCip] = useState(false);
    const [isChefAgence, setIsChefAgence] = useState(false);
    const [isDmg, setIsDmg] = useState(false);
    const [isAc, setIsAc] = useState(false);

    useEffect(() => {
        setIsCip(false);
        setIsChefAgence(false);
        setIsDmg(false);
        setIsAc(false);
    }, []);

    const menuItems: any = [
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
        },
        {
            id: 'cip-pointages',
            label: 'Présence - Pointages',
            icon: 'ri-calendar-check-line',
            link: '/cip/pointages',
        },
        {
            id: 'cip-ajourne-dmg',
            label: 'Pointage Ajourné (DMG)',
            icon: 'ri-error-warning-line',
            link: '/cip/pointage/ajourne-dmg',
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
        },
        {
            id: 'ca-pointages',
            label: 'Validation des Pointages',
            icon: 'ri-calendar-todo-line',
            link: '/chefagence/pointages',
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
        }
    ];

    return <React.Fragment>{menuItems}</React.Fragment>;
};

export default Navdata;
