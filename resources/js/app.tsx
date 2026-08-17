import { createInertiaApp } from '@inertiajs/react';
import { configureStore } from '@reduxjs/toolkit';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { ComponentType } from 'react';
import { Provider } from 'react-redux';

import { Toaster } from '@/old/components/ui/sonner';
import { TooltipProvider } from '@/old/components/ui/tooltip';
import { initializeTheme } from '@/old/hooks/use-appearance';
import AppLayout from '@/old/layouts/app-layout';
import AuthLayout from '@/old/layouts/auth-layout';
import SettingsLayout from '@/old/layouts/settings/layout';
import fakeBackend from '@/velzone/helpers/AuthType/fakeBackend';
import VelzoneLayout from '@/velzone/Layouts';
import NonAuthLayout from '@/velzone/Layouts/NonAuthLayout';
import rootReducer from '@/velzone/slices';

import '@/velzone/i18n';
import '@/velzone/assets/scss/themes.scss';

fakeBackend();

const store = configureStore({
    reducer: rootReducer,
    devTools: import.meta.env.DEV,
});

const pages = import.meta.glob<{ default: ComponentType }>([
    './velzone/pages/DashboardAnalytics/index.tsx',
    './velzone/pages/Tables/GridJs/index.tsx',
    './velzone/pages/Authentication/**/*.tsx',
    './velzone/pages/Entreprises/**/*.tsx',
    './velzone/pages/Offres/**/*.tsx',
    './velzone/pages/Inscriptions/**/*.tsx',
    './velzone/pages/AgentComptable/**/*.tsx',
    './velzone/pages/Cb/**/*.tsx',
    './velzone/pages/ChefAgence/**/*.tsx',
    './velzone/pages/Cip/**/*.tsx',
    './velzone/pages/Daicg/**/*.tsx',
    './velzone/pages/Desse/**/*.tsx',
    './velzone/pages/Dmg/**/*.tsx',
    './velzone/pages/Pejedec/**/*.tsx',
    './velzone/pages/Reporting/**/*.tsx',
    './old/pages/**/*.tsx',
]);

const appName = import.meta.env.VITE_APP_NAME || 'GestStage';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: async (name) => {
        const page = await resolvePageComponent<{ default: ComponentType }>(
            [`./velzone/pages/${name}.tsx`, `./old/pages/${name}.tsx`],
            pages,
        );

        return page.default;
    },
    layout: (name) => {
        if (name === 'welcome') {
            return null;
        }

        if (name.startsWith('auth/')) {
            return AuthLayout;
        }

        if (name.startsWith('Authentication/')) {
            return NonAuthLayout;
        }

        if (name.startsWith('settings/')) {
            return [AppLayout, SettingsLayout];
        }

        return VelzoneLayout;
    },
    withApp: (app) => (
        <Provider store={store}>
            <TooltipProvider delayDuration={0}>
                {app}
                <Toaster />
            </TooltipProvider>
        </Provider>
    ),
    progress: {
        color: '#f47c20',
    },
});

initializeTheme();
