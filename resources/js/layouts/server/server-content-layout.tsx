import {
    Breadcrumb,
    BreadcrumbItem as BreadcrumbComponent,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/components/ui/breadcrumb';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import { type BreadcrumbItem, type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import {
    Activity,
    ChevronLeft,
    ChevronRight,
    CodeIcon,
    DatabaseIcon,
    Folder,
    Globe,
    Home,
    LayoutDashboard,
    Server,
    Settings,
    Terminal,
} from 'lucide-react';
import { PropsWithChildren, useEffect, useState } from 'react';

interface ServerContentLayoutProps extends PropsWithChildren {
    server: {
        id: number;
        vanity_name: string;
        connection?: string;
        public_ip?: string;
    };
    breadcrumbs?: BreadcrumbItem[];
}

/**
 * Layout for server pages with integrated sidebar navigation in content area
 */
export default function ServerContentLayout({ children, server, breadcrumbs }: ServerContentLayoutProps) {
    const { url } = usePage();
    const [path = ''] = url.split('?');

    // Determine current active section
    let currentSection: string = 'overview';

    if (path.endsWith(`/servers/${server.id}`) || path.endsWith(`/servers/${server.id}/`)) {
        currentSection = 'overview';
    } else if (path.includes('/sites')) {
        currentSection = 'sites';
    } else if (path.includes('/php')) {
        currentSection = 'php';
    } else if (path.includes('/database')) {
        currentSection = 'database';
    } else if (path.includes('/explorer')) {
        currentSection = 'explorer';
    } else if (path.includes('/settings')) {
        currentSection = 'settings';
    }

    // Server navigation items
    const serverNavItems: NavItem[] = [
        {
            title: 'Overview',
            href: `/servers/${server.id}`,
            icon: Home,
            isActive: currentSection === 'overview',
        },
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

    // Connection status indicator
    const getStatusConfig = (status?: string) => {
        switch (status) {
            case 'connected':
                return { color: 'bg-green-500', label: 'Connected', pulse: true };
            case 'failed':
                return { color: 'bg-red-500', label: 'Failed', pulse: false };
            case 'disconnected':
                return { color: 'bg-gray-500', label: 'Disconnected', pulse: false };
            default:
                return { color: 'bg-amber-500', label: 'Pending', pulse: true };
        }
    };

    const statusConfig = getStatusConfig(server.connection);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <div className="flex h-full">
                {/* Content Area Sidebar */}
                <aside className="w-64 border-r bg-muted/30">
                    <div className="flex h-full flex-col">
                        {/* Server Header */}
                        <div className="border-b bg-card p-4">
                            <div className="flex items-center gap-3">
                                <div className="flex aspect-square size-9 items-center justify-center rounded-lg bg-primary/10">
                                    <Server className="size-5 text-primary" />
                                </div>
                                <div className="flex-1 min-w-0">
                                    <h3 className="font-semibold text-sm truncate">{server.vanity_name}</h3>
                                    <div className="flex items-center gap-2">
                                        <div className="relative flex items-center">
                                            {statusConfig.pulse && (
                                                <span className={cn(
                                                    "absolute inline-flex h-2 w-2 animate-ping rounded-full opacity-75",
                                                    statusConfig.color
                                                )} />
                                            )}
                                            <span className={cn("relative inline-flex h-2 w-2 rounded-full", statusConfig.color)} />
                                        </div>
                                        <span className="text-xs text-muted-foreground">
                                            {statusConfig.label}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            {/* Server Info */}
                            {server.public_ip && (
                                <div className="mt-3 space-y-1">
                                    <p className="text-xs text-muted-foreground">
                                        <span className="font-medium">IP:</span> {server.public_ip}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        <span className="font-medium">ID:</span> #{server.id}
                                    </p>
                                </div>
                            )}

                            {/* Quick Actions */}
                            <div className="mt-4 flex gap-2">
                                <Button variant="outline" size="sm" className="flex-1">
                                    SSH
                                </Button>
                                <Button variant="outline" size="sm" className="flex-1">
                                    Restart
                                </Button>
                            </div>
                        </div>

                        {/* Navigation */}
                        <nav className="flex-1 overflow-y-auto p-3">
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
                        </nav>

                        {/* Footer Actions */}
                        <div className="border-t p-3">
                            <Link
                                href={dashboard()}
                                className="flex items-center gap-3 rounded-lg px-3 py-2 text-sm hover:bg-accent transition-colors"
                            >
                                <LayoutDashboard className="size-4 text-muted-foreground flex-shrink-0" />
                                <span className="font-medium">Dashboard</span>
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