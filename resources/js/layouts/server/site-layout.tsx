import { Button } from '@/components/ui/button';
import { NavigationCard, NavigationSidebar } from '@/components/navigation-card';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { show as showServer } from '@/routes/servers';
import { type BreadcrumbItem, type NavItem } from '@/types';
import { usePage } from '@inertiajs/react';
import {
    AppWindow,
    CodeIcon,
    DatabaseIcon,
    Folder,
    Globe,
    Rocket,
    Server,
    Settings,
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
        git_status?: string | null;
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
    } else if (path.includes('/database')) {
        currentSection = 'database';
    } else if (path.includes('/php')) {
        currentSection = 'php';
    } else if (path.includes('/sites') && !path.includes(`/sites/${site.id}`)) {
        currentSection = 'sites';
    } else if (path.includes('/settings')) {
        currentSection = 'settings';
    }

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

    // Server navigation items
    const serverNavItems: NavItem[] = [
        {
            title: 'Sites',
            href: `/servers/${server.id}/sites`,
            icon: Globe,
            isActive: currentSection === 'sites',
        },
        {
            title: 'PHP',
            href: `/servers/${server.id}/php`,
            icon: CodeIcon,
            isActive: currentSection === 'php',
        },
        {
            title: 'Database',
            href: `/servers/${server.id}/database`,
            icon: DatabaseIcon,
            isActive: currentSection === 'database',
        },
        {
            title: 'Settings',
            href: `/servers/${server.id}/settings`,
            icon: Settings,
            isActive: currentSection === 'settings',
        },
    ];

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

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            {/* Site Header */}
            <div className="bg-card px-8 py-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-xl font-semibold text-foreground mb-4">{site.domain || 'Site'}</h1>
                        <div className="flex items-center gap-6 text-sm">
                            <div className="flex items-center gap-2">
                                <span className="text-muted-foreground">Public IP</span>
                                <span className="font-medium">{server.public_ip || 'N/A'}</span>
                            </div>
                            <div className="flex items-center gap-2">
                                <span className="text-muted-foreground">Private IP</span>
                                <span className="font-medium">{server.private_ip || 'N/A'}</span>
                            </div>
                            <div className="flex items-center gap-2">
                                <span className="text-muted-foreground">Region</span>
                                <span className="font-medium">Frankfurt</span>
                            </div>
                            <div className="flex items-center gap-2">
                                <span className="text-muted-foreground">OS</span>
                                <span className="font-medium">Ubuntu 24.04</span>
                            </div>
                        </div>
                    </div>
                    <div className="flex items-center gap-2">
                        <Button variant="outline" size="default">
                            Self Help
                        </Button>
                        <Button variant="outline" size="default">
                            Edit Files
                        </Button>
                        <Button size="default" className="bg-emerald-600 hover:bg-emerald-700">
                            Deploy Now
                        </Button>
                    </div>
                </div>
            </div>

            <div className="flex h-full">
                <NavigationSidebar>
                    <div className="space-y-4">
                        <NavigationCard items={siteNavItems} />
                        <NavigationCard
                            items={[
                                {
                                    title: 'Back to Server',
                                    href: showServer(server.id).url,
                                    icon: Server,
                                    isActive: false,
                                },
                            ]}
                        />
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