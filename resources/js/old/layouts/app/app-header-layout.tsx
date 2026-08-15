import { AppContent } from '@/old/components/app-content';
import { AppHeader } from '@/old/components/app-header';
import { AppShell } from '@/old/components/app-shell';
import type { AppLayoutProps } from '@/old/types';

export default function AppHeaderLayout({
    children,
    breadcrumbs,
}: AppLayoutProps) {
    return (
        <AppShell variant="header">
            <AppHeader breadcrumbs={breadcrumbs} />
            <AppContent variant="header">{children}</AppContent>
        </AppShell>
    );
}
