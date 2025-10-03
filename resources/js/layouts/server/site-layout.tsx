import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { NavigationCard, NavigationSidebar } from '@/components/navigation-card';
import AppLayout from '@/layouts/app-layout';
import { cn, formatRelativeTime } from '@/lib/utils';
import { type BreadcrumbItem, type NavItem } from '@/types';
import { usePage } from '@inertiajs/react';
import {
    AppWindow,
    ArrowLeft,
    Folder,
    GitBranch,
    Globe,
    Rocket,
    Terminal
} from 'lucide-react';
import { PropsWithChildren } from 'react';

interface SiteLayoutProps extends PropsWithChildren {
    server: {
        id: number;
        vanity_name: string;
        connection: string;
        public_ip?: string;
        private_ip?: string;
    };
    site: {
        id: number;
        domain?: string | null;
        status?: string;
        health?: string;
        git_status?: string | null;
        git_provider?: string | null;
        git_repository?: string | null;
        git_branch?: string | null;
        last_deployed_at?: string | null;
    };
    breadcrumbs?: BreadcrumbItem[];
}

/**
 * Layout for site-scoped pages with integrated sidebar navigation
 */
export default function SiteLayout({ children, server, site, breadcrumbs }: SiteLayoutProps) {
    const { url } = usePage();
    const [path = ''] = url.split('?');

    // Determine current active section
    let currentSection: string = 'site-application';

    if (path.includes('/commands')) {
        currentSection = 'site-commands';
    } else if (path.includes('/deployments')) {
        currentSection = 'site-deployments';
    } else if (path.includes('/explorer')) {
        currentSection = 'explorer';
    }

    // Back to server navigation
    const backToServerNav: NavItem = {
        title: 'Back to Server',
        href: `/servers/${server.id}`,
        icon: ArrowLeft,
        isActive: false,
    };

    // Site-specific navigation items
    const siteNavItems: NavItem[] = [
        {
            title: 'Application',
            href: `/servers/${server.id}/sites/${site.id}/application`,
            icon: AppWindow,
            isActive: currentSection === 'site-application',
        },
        {
            title: 'Commands',
            href: `/servers/${server.id}/sites/${site.id}/commands`,
            icon: Terminal,
            isActive: currentSection === 'site-commands',
        },
        {
            title: 'Explorer',
            href: `/servers/${server.id}/sites/${site.id}/explorer`,
            icon: Folder,
            isActive: currentSection === 'explorer',
        },
    ];

    // Conditionally add Deployments if Git is installed
    if (site.git_status === 'installed') {
        siteNavItems.push({
            title: 'Deployments',
            href: `/servers/${server.id}/sites/${site.id}/deployments`,
            icon: Rocket,
            isActive: currentSection === 'site-deployments',
        });
    }

    // Site status indicator
    const getStatusColor = (status?: string) => {
        switch (status) {
            case 'active':
                return 'bg-green-500';
            case 'provisioning':
                return 'bg-blue-500';
            case 'failed':
                return 'bg-red-500';
            default:
                return 'bg-gray-500';
        }
    };

    // Site health indicator
    const getHealthConfig = (health?: string) => {
        switch (health) {
            case 'healthy':
                return { color: 'text-green-600', label: 'Healthy' };
            case 'unhealthy':
                return { color: 'text-red-600', label: 'Unhealthy' };
            default:
                return { color: 'text-gray-600', label: 'Unknown' };
        }
    };

    const healthConfig = getHealthConfig(site.health);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            {/* Site Header */}
            <div className="bg-card px-8 py-4 border-b">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-8 flex-1">
                        {/* Title */}
                        <div className="flex items-center gap-3">
                            <div className="flex items-center justify-center w-8 h-8 rounded-md bg-primary/10">
                                <Globe className="h-4 w-4 text-primary" />
                            </div>
                            <h1 className="text-xl font-semibold text-foreground">{site.domain || 'Site'}</h1>
                        </div>

                        {/* Server Info */}
                        <div className="flex items-center gap-8 text-sm border-l pl-8">
                            <div>
                                <div className="text-[10px] text-muted-foreground uppercase tracking-wide mb-0.5">Public IP</div>
                                <div className="font-medium">{server.public_ip || 'N/A'}</div>
                            </div>
                            <div>
                                <div className="text-[10px] text-muted-foreground uppercase tracking-wide mb-0.5">Private IP</div>
                                <div className="font-medium">{server.private_ip || 'N/A'}</div>
                            </div>
                            <div>
                                <div className="text-[10px] text-muted-foreground uppercase tracking-wide mb-0.5">Region</div>
                                <div className="font-medium">Frankfurt</div>
                            </div>
                            <div>
                                <div className="text-[10px] text-muted-foreground uppercase tracking-wide mb-0.5">OS</div>
                                <div className="font-medium">Ubuntu 24.04</div>
                            </div>
                        </div>

                        {/* Git Info */}
                        {site.git_status === 'installed' && site.git_repository && (
                            <div className="flex items-center gap-8 text-sm border-l pl-8">
                                <div>
                                    <div className="text-[10px] text-muted-foreground uppercase tracking-wide mb-0.5">Repository</div>
                                    <div className="font-medium">{site.git_repository}</div>
                                </div>
                                {site.git_branch && (
                                    <div>
                                        <div className="text-[10px] text-muted-foreground uppercase tracking-wide mb-0.5">Branch</div>
                                        <div className="flex items-center gap-1.5 font-medium">
                                            <GitBranch className="h-3.5 w-3.5 text-muted-foreground" />
                                            {site.git_branch}
                                        </div>
                                    </div>
                                )}
                                <div>
                                    <div className="text-[10px] text-muted-foreground uppercase tracking-wide mb-0.5">Last Deployment</div>
                                    <div className="font-medium">
                                        {site.last_deployed_at ? formatRelativeTime(site.last_deployed_at) : 'Never'}
                                    </div>
                                </div>
                            </div>
                        )}
                    </div>

                    {/* Health Indicator */}
                    <div>
                        <div className="text-[10px] text-muted-foreground uppercase tracking-wide mb-0.5">Health</div>
                        <div className={cn("font-medium", healthConfig.color)}>
                            {healthConfig.label}
                        </div>
                    </div>
                </div>
            </div>

            <div className="flex h-full mt-6">
                <NavigationSidebar>
                    <div className="space-y-6">
                        <NavigationCard items={[backToServerNav]} />
                        <NavigationCard title="Site" items={siteNavItems} />
                    </div>
                </NavigationSidebar>

                {/* Main Content */}
                <main className="flex-1 overflow-auto">
                    <div className="p-6">
                        {children}
                    </div>
                </main>
            </div>
        </AppLayout>
    );
}