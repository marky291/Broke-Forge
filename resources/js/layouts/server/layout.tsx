import ServerContentLayout from './server-content-layout';
import SiteLayout from './site-layout';
import { type BreadcrumbItem } from '@/types';
import { PropsWithChildren } from 'react';

interface ServerLayoutProps extends PropsWithChildren {
    server: {
        id: number;
        vanity_name: string;
        connection: string;
        public_ip?: string;
    };
    breadcrumbs?: BreadcrumbItem[];
    site?: {
        id: number;
        domain?: string | null;
        status?: string;
    };
}

/**
 * Layout wrapper that decides whether to use ServerContentLayout or SiteLayout
 */
export default function ServerLayout({ children, server, breadcrumbs, site }: ServerLayoutProps) {
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
        <ServerContentLayout server={server} breadcrumbs={breadcrumbs}>
            {children}
        </ServerContentLayout>
    );
}
