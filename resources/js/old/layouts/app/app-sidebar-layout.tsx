import { AppContent } from '@/old/components/app-content';
import { AppShell } from '@/old/components/app-shell';
import { AppSidebar } from '@/old/components/app-sidebar';
import { AppSidebarHeader } from '@/old/components/app-sidebar-header';
import type { AppLayoutProps } from '@/old/types';

export default function AppSidebarLayout({
    children,
    breadcrumbs = [],
}: AppLayoutProps) {
    return (
        <AppShell variant="sidebar">
            <AppSidebar />
            <AppContent variant="sidebar" className="overflow-x-hidden">
                <AppSidebarHeader breadcrumbs={breadcrumbs} />
                {children}
            </AppContent>
        </AppShell>
    );
}
