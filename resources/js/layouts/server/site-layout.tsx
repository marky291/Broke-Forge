import { MainHeader } from '@/components/main-header';
import { NavigationCard, NavigationSidebar } from '@/components/navigation-card';
import { SiteDetail } from '@/components/site-detail';
import { type BreadcrumbItem, type NavItem } from '@/types';
import { usePage } from '@inertiajs/react';
import { AppWindow, ArrowLeft, FileCode2, Folder, Rocket, Terminal, X } from 'lucide-react';
import { PropsWithChildren, useState } from 'react';

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
        site_framework: {
            env: {
                supports: boolean;
                file_path: string | null;
            };
        };
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
    let currentSection: string = 'site-settings';

    if (path.includes('/commands')) {
        currentSection = 'site-commands';
    } else if (path.includes('/deployments')) {
        currentSection = 'site-deployments';
    } else if (path.includes('/explorer')) {
        currentSection = 'explorer';
    } else if (path.includes('/environment')) {
        currentSection = 'site-environment';
    } else if (path.includes('/settings')) {
        currentSection = 'site-settings';
    }

    // Back to server navigation
    const backToServerNav: NavItem = {
        title: 'Back to Server',
        href: `/servers/${server.id}`,
        icon: ArrowLeft,
        isActive: false,
    };

    // Site-specific navigation items
    const siteNavItems: NavItem[] = [];

    // Conditionally add Deployments first if Git is installed
    if (site.git_status === 'success') {
        siteNavItems.push({
            title: 'Deployments',
            href: `/servers/${server.id}/sites/${site.id}/deployments`,
            icon: Rocket,
            isActive: currentSection === 'site-deployments',
        });
    }

    // Add Settings (formerly Application)
    siteNavItems.push({
        title: 'Settings',
        href: `/servers/${server.id}/sites/${site.id}/settings`,
        icon: AppWindow,
        isActive: currentSection === 'site-settings',
    });

    // Add Commands
    siteNavItems.push({
        title: 'Commands',
        href: `/servers/${server.id}/sites/${site.id}/commands`,
        icon: Terminal,
        isActive: currentSection === 'site-commands',
    });

    // Add Explorer
    siteNavItems.push({
        title: 'Explorer',
        href: `/servers/${server.id}/sites/${site.id}/explorer`,
        icon: Folder,
        isActive: currentSection === 'explorer',
    });

    // Conditionally add Environment if framework supports it
    if (site.site_framework.env.supports) {
        siteNavItems.push({
            title: 'Environment',
            href: `/servers/${server.id}/sites/${site.id}/environment`,
            icon: FileCode2,
            isActive: currentSection === 'site-environment',
        });
    }

    return (
        <div className="flex min-h-screen flex-col bg-background">
            <MainHeader />

            {/* Breadcrumbs Section */}
            {/* {breadcrumbs && breadcrumbs.length > 0 && (
                <div className="border-b bg-muted/30">
                    <div className="container mx-auto max-w-7xl px-4">
                        <div className="flex h-12 items-center">
                            <Breadcrumb>
                                <BreadcrumbList>
                                    {breadcrumbs.map((breadcrumb, index) => (
                                        <div key={index} className="flex items-center gap-2">
                                            {index > 0 && <BreadcrumbSeparator />}
                                            <BreadcrumbComponent>
                                                {index === breadcrumbs.length - 1 ? (
                                                    <BreadcrumbPage>{breadcrumb.title}</BreadcrumbPage>
                                                ) : (
                                                    <BreadcrumbLink href={breadcrumb.href}>{breadcrumb.title}</BreadcrumbLink>
                                                )}
                                            </BreadcrumbComponent>
                                        </div>
                                    ))}
                                </BreadcrumbList>
                            </Breadcrumb>
                        </div>
                    </div>
                </div>
            )} */}

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
                                <button onClick={() => setMobileMenuOpen(false)} className="rounded-md p-2 hover:bg-muted">
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

            {/* Site Header - Full Width */}
            <SiteDetail server={server} site={site} onMobileMenuClick={() => setMobileMenuOpen(true)} />

            <div className="container mx-auto max-w-7xl px-4">
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
            </div>
        </div>
    );
}
