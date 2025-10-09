import { type BreadcrumbItem, type ServerMetric } from '@/types';
import { PropsWithChildren } from 'react';
import ServerContentLayout from './server-content-layout';
import SiteLayout from './site-layout';

interface ServerLayoutProps extends PropsWithChildren {
    server: {
        id: number;
        vanity_name: string;
        provider?: string;
        connection: string;
        public_ip?: string;
        private_ip?: string;
        monitoring_status?: 'installing' | 'active' | 'failed' | 'uninstalling' | 'uninstalled' | null;
    };
    breadcrumbs?: BreadcrumbItem[];
    site?: {
        id: number;
        domain?: string | null;
        status?: string;
    };
    latestMetrics?: ServerMetric | null;
}

/**
 * Layout wrapper that decides whether to use ServerContentLayout or SiteLayout
 */
export default function ServerLayout({ children, server, breadcrumbs, site, latestMetrics }: ServerLayoutProps) {
    // If we have a site, use the SiteLayout
    if (site) {
        return (
            <SiteLayout server={server} site={site} breadcrumbs={breadcrumbs}>
                {children}
            </SiteLayout>
        );
    }
    // Otherwise, use the ServerContentLayout
    return (
        <ServerContentLayout server={server} breadcrumbs={breadcrumbs} latestMetrics={latestMetrics}>
            {children}
        </ServerContentLayout>
    );
}
