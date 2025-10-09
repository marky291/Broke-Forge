import { NavigationCard, NavigationSidebar } from '@/components/navigation-card';
import AppLayout from '@/layouts/app-layout';
import { cn, formatRelativeTime } from '@/lib/utils';
import { type BreadcrumbItem, type NavItem } from '@/types';
import { usePage } from '@inertiajs/react';
import { AppWindow, ArrowLeft, Cpu, Folder, GitBranch, Globe, HardDrive, MemoryStick, Menu, Rocket, Terminal, X } from 'lucide-react';
import { PropsWithChildren, useState } from 'react';
import { ServerProviderIcon } from '@/components/server-provider-icon';

interface SiteLayoutProps extends PropsWithChildren {
    server: {
        id: number;
        vanity_name: string;
        provider?: string;
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
    const [mobileMenuOpen, setMobileMenuOpen] = useState(false);

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
            {/* Mobile Navigation Overlay */}
            {mobileMenuOpen && (
                <div className="fixed inset-0 z-50 lg:hidden">
                    {/* Backdrop */}
                    <div className="fixed inset-0 bg-black/50" onClick={() => setMobileMenuOpen(false)} />

                    {/* Menu Panel */}
                    <div className="fixed inset-y-0 left-0 w-64 bg-card shadow-xl">
                        <div className="flex h-full flex-col">
                            {/* Header */}
                            <div className="flex items-center justify-between border-b p-4">
                                <h2 className="text-lg font-semibold">Navigation</h2>
                                <button
                                    onClick={() => setMobileMenuOpen(false)}
                                    className="rounded-md p-2 hover:bg-muted"
                                >
                                    <X className="h-5 w-5" />
                                </button>
                            </div>

                            {/* Navigation Items */}
                            <div className="flex-1 overflow-auto p-4">
                                <div className="space-y-6">
                                    <NavigationCard items={[backToServerNav]} />
                                    <NavigationCard title="Site" items={siteNavItems} />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            )}

            {/* Site Header */}
            <div className="border-b bg-card py-4">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div className="flex flex-col gap-4 lg:flex-1 lg:flex-row lg:items-center lg:gap-8">
                        {/* Title with Mobile Menu Button */}
                        <div className="flex items-center gap-3">
                            {/* Mobile Menu Button */}
                            <button
                                onClick={() => setMobileMenuOpen(true)}
                                className="flex items-center justify-center rounded-md p-2 hover:bg-muted lg:hidden"
                            >
                                <Menu className="h-5 w-5" />
                            </button>

                            <div className="flex h-8 w-8 items-center justify-center rounded-md bg-primary/10">
                                <Globe className="h-4 w-4 text-primary" />
                            </div>
                            <h1 className="text-xl font-semibold text-foreground">{site.domain || 'Site'}</h1>
                        </div>

                        {/* Server Info - Hide some items on mobile */}
                        <div className="flex flex-wrap items-center gap-4 text-sm lg:gap-8 lg:border-l lg:pl-8">
                            <div>
                                <div className="mb-0.5 text-[10px] tracking-wide text-muted-foreground uppercase">Public IP</div>
                                <div className="font-medium">{server.public_ip || 'N/A'}</div>
                            </div>
                            <div className="hidden md:block">
                                <div className="mb-0.5 text-[10px] tracking-wide text-muted-foreground uppercase">Private IP</div>
                                <div className="font-medium">{server.private_ip || 'N/A'}</div>
                            </div>
                            <div className="hidden md:block">
                                <div className="mb-0.5 text-[10px] tracking-wide text-muted-foreground uppercase">Region</div>
                                <div className="font-medium">Frankfurt</div>
                            </div>
                            <div className="hidden lg:block">
                                <div className="mb-0.5 text-[10px] tracking-wide text-muted-foreground uppercase">OS</div>
                                <div className="font-medium">Ubuntu 24.04</div>
                            </div>
                        </div>

                        {/* Git Info - Responsive */}
                        {site.git_status === 'installed' && site.git_repository && (
                            <div className="flex flex-wrap items-center gap-4 text-sm lg:gap-8 lg:border-l lg:pl-8">
                                <div>
                                    <div className="mb-0.5 text-[10px] tracking-wide text-muted-foreground uppercase">Repository</div>
                                    <div className="font-medium">{site.git_repository}</div>
                                </div>
                                {site.git_branch && (
                                    <div>
                                        <div className="mb-0.5 text-[10px] tracking-wide text-muted-foreground uppercase">Branch</div>
                                        <div className="flex items-center gap-1.5 font-medium">
                                            <GitBranch className="h-3.5 w-3.5 text-muted-foreground" />
                                            {site.git_branch}
                                        </div>
                                    </div>
                                )}
                                <div className="hidden md:block">
                                    <div className="mb-0.5 text-[10px] tracking-wide text-muted-foreground uppercase">Last Deployment</div>
                                    <div className="font-medium">{site.last_deployed_at ? formatRelativeTime(site.last_deployed_at) : 'Never'}</div>
                                </div>
                            </div>
                        )}
                    </div>

                    {/* Health Indicator */}
                    <div>
                        <div className="mb-0.5 text-[10px] tracking-wide text-muted-foreground uppercase">Health</div>
                        <div className={cn('font-medium', healthConfig.color)}>{healthConfig.label}</div>
                    </div>
                </div>
            </div>

            <div className="mt-6 flex h-full flex-col lg:flex-row">
                <div className="hidden lg:block">
                    <NavigationSidebar>
                        <div className="space-y-6">
                            <NavigationCard items={[backToServerNav]} />
                            <NavigationCard title="Site" items={siteNavItems} />
                        </div>
                    </NavigationSidebar>
                </div>

                {/* Main Content */}
                <main className="flex-1 overflow-auto">
                    <div className="p-4 md:p-6">{children}</div>
                </main>
            </div>
        </AppLayout>
    );
}
