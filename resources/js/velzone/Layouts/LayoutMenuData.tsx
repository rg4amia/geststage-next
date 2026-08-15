import React from 'react';

const Navdata = () => {
    const menuItems: any = [
        {
            label: 'Menu',
            isHeader: true,
        },
        {
            id: 'dashboard',
            label: 'Dashboards',
            icon: 'ri-dashboard-2-line',
            link: '/dashboard',
        },
        {
            id: 'apps',
            label: 'Apps',
            icon: 'ri-apps-2-line',
            link: '#',
        },
        {
            id: 'layouts',
            label: 'Layouts',
            icon: 'ri-layout-3-line',
            link: '#',
            badge: 'Hot',
        },
        {
            label: 'Pages',
            isHeader: true,
        },
        {
            id: 'authentication',
            label: 'Authentication',
            icon: 'ri-user-3-line',
            link: '/login',
        },
        {
            id: 'pages',
            label: 'Pages',
            icon: 'ri-pages-line',
            link: '#',
        },
        {
            id: 'landing',
            label: 'Landing',
            icon: 'ri-rocket-line',
            link: '#',
        },
        {
            label: 'Components',
            isHeader: true,
        },
        {
            id: 'base-ui',
            label: 'Base UI',
            icon: 'ri-pencil-ruler-2-line',
            link: '#',
        },
        {
            id: 'advance-ui',
            label: 'Advance UI',
            icon: 'ri-stack-line',
            link: '#',
        },
        {
            id: 'widgets',
            label: 'Widgets',
            icon: 'ri-honour-line',
            link: '#',
        },
        {
            id: 'forms',
            label: 'Forms',
            icon: 'ri-file-list-3-line',
            link: '#',
        },
        {
            id: 'tables',
            label: 'Tables',
            icon: 'ri-table-line',
            link: '/',
            subItems: [
                {
                    id: 'basic-tables',
                    label: 'Basic Tables',
                    link: '/',
                },
            ],
        },
    ];

    return <React.Fragment>{menuItems}</React.Fragment>;
};

export default Navdata;
