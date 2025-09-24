import {
    Breadcrumb,
    BreadcrumbItem as BreadcrumbComponent,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/components/ui/breadcrumb';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { show as showServer } from '@/routes/servers';
import { show as showSite } from '@/routes/servers/sites';
import { type BreadcrumbItem, type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import {
    AppWindow,
    CodeIcon,
    DatabaseIcon,
    ExternalLink,
    Folder,
    Globe,
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
    };
    site: {
        id: number;
        domain?: string | null;
        status?: string;
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
            href: `/servers/${server.id}/sites/${site.id}`,
            icon: AppWindow,
            isActive: currentSection === 'site-application',
        },
        {
            title: 'Commands',
            href: `/servers/${server.id}/sites/${site.id}/commands`,
            icon: Terminal,
            isActive: currentSection === 'site-commands',
        },
    ];

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
            title: 'Explorer',
            href: `/servers/${server.id}/explorer`,
            icon: Folder,
            isActive: currentSection === 'explorer',
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
            <div className="flex h-full">
                {/* Content Area Sidebar */}
                <aside className="w-64 border-r bg-muted/30">
                    <div className="flex h-full flex-col">
                        {/* Site Header */}
                        <div className="border-b bg-card p-4">
                            <div className="flex items-center gap-3">
                                <div className="flex aspect-square size-9 items-center justify-center rounded-lg bg-primary/10">
                                    <Globe className="size-5 text-primary" />
                                </div>
                                <div className="flex-1 min-w-0">
                                    <h3 className="font-semibold text-sm truncate">{site.domain || 'Site'}</h3>
                                    <div className="flex items-center gap-2">
                                        <span className={cn("size-2 rounded-full", getStatusColor(site.status))} />
                                        <span className="text-xs text-muted-foreground capitalize">
                                            {site.status || 'pending'}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            {/* Site Quick Actions */}
                            <div className="mt-4 flex gap-2">
                                <Button variant="outline" size="sm" className="flex-1" asChild>
                                    <a href={`https://${site.domain}`} target="_blank" rel="noopener noreferrer">
                                        <ExternalLink className="mr-1.5 size-3" />
                                        Visit
                                    </a>
                                </Button>
                                <Button variant="outline" size="sm" className="flex-1">
                                    Deploy
                                </Button>
                            </div>
                        </div>

                        {/* Navigation Sections */}
                        <nav className="flex-1 overflow-y-auto p-3 space-y-6">
                            {/* Site Navigation */}
                            <div>
                                <h4 className="mb-2 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider">
                                    Current Site
                                </h4>
                                <div className="space-y-1">
                                    {siteNavItems.map((item) => {
                                        const Icon = item.icon;
                                        return (
                                            <Link
                                                key={item.href}
                                                href={item.href}
                                                className={cn(
                                                    "flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-all",
                                                    item.isActive
                                                        ? "bg-primary text-primary-foreground shadow-sm"
                                                        : "hover:bg-accent hover:text-accent-foreground"
                                                )}
                                            >
                                                {Icon && <Icon className="size-4 flex-shrink-0" />}
                                                <span>{item.title}</span>
                                            </Link>
                                        );
                                    })}
                                </div>
                            </div>

                            {/* Divider */}
                            <div className="border-t" />

                            {/* Server Navigation */}
                            <div>
                                <h4 className="mb-2 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider">
                                    Server
                                </h4>
                                <div className="space-y-1">
                                    {serverNavItems.map((item) => {
                                        const Icon = item.icon;
                                        return (
                                            <Link
                                                key={item.href}
                                                href={item.href}
                                                className={cn(
                                                    "flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-all",
                                                    item.isActive
                                                        ? "bg-accent text-accent-foreground"
                                                        : "text-muted-foreground hover:bg-accent hover:text-accent-foreground"
                                                )}
                                            >
                                                {Icon && <Icon className="size-4 flex-shrink-0" />}
                                                <span>{item.title}</span>
                                            </Link>
                                        );
                                    })}
                                </div>
                            </div>
                        </nav>

                        {/* Server Info Footer */}
                        <div className="border-t p-3">
                            <Link
                                href={showServer(server.id)}
                                className="flex items-center gap-3 rounded-lg px-3 py-2 text-sm hover:bg-accent transition-colors"
                            >
                                <Server className="size-4 text-muted-foreground flex-shrink-0" />
                                <div className="flex-1 min-w-0">
                                    <p className="font-medium truncate">{server.vanity_name}</p>
                                    <p className="text-xs text-muted-foreground">Server #{server.id}</p>
                                </div>
                            </Link>
                        </div>
                    </div>
                </aside>

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