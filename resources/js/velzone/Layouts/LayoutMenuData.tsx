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
