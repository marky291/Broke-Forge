import { MainHeader } from '@/components/main-header';
import { NavigationCard, NavigationSidebar } from '@/components/navigation-card';
import { ServerDetail } from '@/components/server-detail';
import { ServerProviderIcon, type ServerProvider } from '@/components/server-provider-icon';
import {
    Breadcrumb,
    BreadcrumbItem as BreadcrumbComponent,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/components/ui/breadcrumb';
import { type BreadcrumbItem, type NavItem, type ServerMetric } from '@/types';
import { usePage } from '@inertiajs/react';
import { Activity, ArrowLeft, Check, Clock, CodeIcon, Cpu, DatabaseIcon, Eye, Globe, HardDrive, Layers, MemoryStick, Settings, Shield, XCircle } from 'lucide-react';
import { PropsWithChildren, useEffect, useState } from 'react';

interface ServerContentLayoutProps extends PropsWithChildren {
    server: {
        id: number;
        vanity_name: string;
        provider?: ServerProvider;
        connection?: string;
        public_ip?: string;
        private_ip?: string;
        monitoring_status?: 'installing' | 'active' | 'failed' | 'uninstalling' | 'uninstalled' | null;
    };
    breadcrumbs?: BreadcrumbItem[];
    latestMetrics?: ServerMetric | null;
}

/**
 * Layout for server pages with integrated sidebar navigation in content area
 */
export default function ServerContentLayout({ children, server, breadcrumbs, latestMetrics }: ServerContentLayoutProps) {
    const { url } = usePage();
    const [path = ''] = url.split('?');
    const [metrics, setMetrics] = useState<ServerMetric | null>(latestMetrics ?? null);

    // Update metrics when we receive latestMetrics from server
    useEffect(() => {
        if (latestMetrics) {
            setMetrics(latestMetrics);
        }
    }, [latestMetrics]);

    // Fetch metrics on mount if monitoring is active but no metrics provided
    useEffect(() => {
        if (server.monitoring_status === 'active' && !latestMetrics) {
            fetch(`/servers/${server.id}/monitoring/metrics?hours=1`, {
                headers: { Accept: 'application/json' },
            })
                .then((res) => res.json())
                .then((json) => {
                    if (json.success && json.data && json.data.length > 0) {
                        setMetrics(json.data[json.data.length - 1]);
                    }
                })
                .catch(() => {});
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []); // Run only on mount

    // Poll for metrics when monitoring is active
    useEffect(() => {
        if (server.monitoring_status !== 'active') return;

        const interval = setInterval(async () => {
            try {
                const res = await fetch(`/servers/${server.id}/monitoring/metrics?hours=1`, {
                    headers: { Accept: 'application/json' },
                });
                if (!res.ok) return;
                const json = await res.json();
                if (json.success && json.data && json.data.length > 0) {
                    setMetrics(json.data[json.data.length - 1]);
                }
            } catch (error) {
                console.error('Failed to fetch metrics:', error);
            }
        }, 30000); // Poll every 30 seconds

        return () => clearInterval(interval);
    }, [server.monitoring_status, server.id]);

    // Determine current active section
    let currentSection: string = 'server';

    if (path.endsWith(`/servers/${server.id}`) || path.endsWith(`/servers/${server.id}/`) || path.includes('/sites')) {
        currentSection = 'server';
    } else if (path.includes('/php')) {
        currentSection = 'php';
    } else if (path.includes('/services')) {
        currentSection = 'services';
    } else if (path.includes('/firewall')) {
        currentSection = 'firewall';
    } else if (path.includes('/monitoring')) {
        currentSection = 'monitoring';
    } else if (path.includes('/tasks')) {
        currentSection = 'tasks';
    } else if (path.includes('/settings')) {
        currentSection = 'settings';
    }

    // Back to dashboard navigation
    const backToDashboardNav: NavItem = {
        title: 'Back to Dashboard',
        href: '/dashboard',
        icon: ArrowLeft,
        isActive: false,
    };

    // Server navigation items
    const serverNavItems: NavItem[] = [
        {
            title: 'Manage Sites',
            href: `/servers/${server.id}/sites`,
            icon: Globe,
            isActive: currentSection === 'server',
        },
        {
            title: 'PHP',
            href: `/servers/${server.id}/php`,
            icon: CodeIcon,
            isActive: currentSection === 'php',
        },
        {
            title: 'Services',
            href: `/servers/${server.id}/services`,
            icon: HardDrive,
            isActive: currentSection === 'services',
        },
        {
            title: 'Firewall',
            href: `/servers/${server.id}/firewall`,
            icon: Shield,
            isActive: currentSection === 'firewall',
        },
        {
            title: 'Monitor',
            href: `/servers/${server.id}/monitoring`,
            icon: Activity,
            isActive: currentSection === 'monitoring',
        },
        {
            title: 'Tasks',
            href: `/servers/${server.id}/tasks`,
            icon: Clock,
            isActive: currentSection === 'tasks',
        },
        {
            title: 'Settings',
            href: `/servers/${server.id}/settings`,
            icon: Settings,
            isActive: currentSection === 'settings',
        },
    ];

    // Connection status indicator - Flat design
    const getStatusConfig = (status?: string) => {
        switch (status) {
            case 'connected':
                return { color: 'bg-emerald-500', label: 'Connected' };
            case 'failed':
                return { color: 'bg-red-500', label: 'Failed' };
            case 'disconnected':
                return { color: 'bg-slate-400', label: 'Disconnected' };
            default:
                return { color: 'bg-amber-500', label: 'Pending' };
        }
    };

    const statusConfig = getStatusConfig(server.connection);

    const getConnectionStatusBadge = (status?: string) => {
        switch (status) {
            case 'connected':
                return (
                    <span className="inline-flex items-center space-x-2 rounded-md border border-success-weak bg-success-weaker py-0.5 pl-1.5 pr-2 text-xssm font-medium text-success">
                        <span className="size-3.5 text-icon-success">
                            <svg className="overflow-visible" viewBox="0 0 14 14" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                                <path
                                    fillRule="evenodd"
                                    clipRule="evenodd"
                                    d="M7 14C10.866 14 14 10.866 14 7C14 3.13401 10.866 0 7 0C3.13401 0 0 3.13401 0 7C0 10.866 3.13401 14 7 14ZM10.1865 4.1874C9.82121 3.8975 9.28276 3.94972 8.98386 4.30405L5.9191 7.93721L4.95897 7.00596C4.62521 6.68224 4.08408 6.68224 3.75032 7.00596C3.41656 7.32969 3.41656 7.85454 3.75032 8.17827L5.37822 9.75721C5.54896 9.92281 5.78396 10.0106 6.02512 9.99897C6.26629 9.9873 6.49111 9.87723 6.64401 9.69597L10.3068 5.35389C10.6057 4.99956 10.5518 4.47731 10.1865 4.1874Z"
                                    fill="currentColor"
                                />
                            </svg>
                        </span>
                        <span>Connected</span>
                    </span>
                );
            case 'failed':
                return (
                    <span className="inline-flex items-center space-x-2 rounded-md border border-danger-weak bg-danger-weaker py-0.5 pl-1.5 pr-2 text-xssm font-medium text-danger">
                        <span className="size-3.5 text-icon-danger">
                            <svg className="overflow-visible" viewBox="0 0 14 14" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                                <path
                                    fillRule="evenodd"
                                    clipRule="evenodd"
                                    d="M7 14C10.866 14 14 10.866 14 7C14 3.13401 10.866 0 7 0C3.13401 0 0 3.13401 0 7C0 10.866 3.13401 14 7 14ZM4.28033 4.28033C4.57322 3.98744 5.04809 3.98744 5.34099 4.28033L7 5.93934L8.65901 4.28033C8.95191 3.98744 9.42678 3.98744 9.71967 4.28033C10.0126 4.57322 10.0126 5.04809 9.71967 5.34099L8.06066 7L9.71967 8.65901C10.0126 8.95191 10.0126 9.42678 9.71967 9.71967C9.42678 10.0126 8.95191 10.0126 8.65901 9.71967L7 8.06066L5.34099 9.71967C5.04809 10.0126 4.57322 10.0126 4.28033 9.71967C3.98744 9.42678 3.98744 8.95191 4.28033 8.65901L5.93934 7L4.28033 5.34099C3.98744 5.04809 3.98744 4.57322 4.28033 4.28033Z"
                                    fill="currentColor"
                                />
                            </svg>
                        </span>
                        <span>Failed</span>
                    </span>
                );
            case 'disconnected':
                return (
                    <span className="inline-flex items-center space-x-2 rounded-md border border-neutral-weak bg-neutral-weaker py-0.5 pl-1.5 pr-2 text-xssm font-medium text-neutral">
                        <span className="size-3.5 text-icon-neutral">
                            <svg className="overflow-visible" viewBox="0 0 14 14" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                                <path
                                    fillRule="evenodd"
                                    clipRule="evenodd"
                                    d="M7 14C10.866 14 14 10.866 14 7C14 3.13401 10.866 0 7 0C3.13401 0 0 3.13401 0 7C0 10.866 3.13401 14 7 14ZM4.28033 4.28033C4.57322 3.98744 5.04809 3.98744 5.34099 4.28033L7 5.93934L8.65901 4.28033C8.95191 3.98744 9.42678 3.98744 9.71967 4.28033C10.0126 4.57322 10.0126 5.04809 9.71967 5.34099L8.06066 7L9.71967 8.65901C10.0126 8.95191 10.0126 9.42678 9.71967 9.71967C9.42678 10.0126 8.95191 10.0126 8.65901 9.71967L7 8.06066L5.34099 9.71967C5.04809 10.0126 4.57322 10.0126 4.28033 9.71967C3.98744 9.42678 3.98744 8.95191 4.28033 8.65901L5.93934 7L4.28033 5.34099C3.98744 5.04809 3.98744 4.57322 4.28033 4.28033Z"
                                    fill="currentColor"
                                />
                            </svg>
                        </span>
                        <span>Disconnected</span>
                    </span>
                );
            default:
                return (
                    <span className="inline-flex items-center space-x-2 rounded-md border border-warning-weak bg-warning-weaker py-0.5 pl-1.5 pr-2 text-xssm font-medium text-warning">
                        <span className="size-3.5 text-icon-warning">
                            <svg className="overflow-visible" viewBox="0 0 14 14" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                                <path
                                    fillRule="evenodd"
                                    clipRule="evenodd"
                                    d="M7 14C10.866 14 14 10.866 14 7C14 3.13401 10.866 0 7 0C3.13401 0 0 3.13401 0 7C0 10.866 3.13401 14 7 14ZM7 3C7.55228 3 8 3.44772 8 4V7C8 7.55228 7.55228 8 7 8C6.44772 8 6 7.55228 6 7V4C6 3.44772 6.44772 3 7 3ZM7 11C7.55228 11 8 10.5523 8 10C8 9.44772 7.55228 9 7 9C6.44772 9 6 9.44772 6 10C6 10.5523 6.44772 11 7 11Z"
                                    fill="currentColor"
                                />
                            </svg>
                        </span>
                        <span>Pending</span>
                    </span>
                );
        }
    };

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

            {/* Server Header - Full Width */}
            <ServerDetail server={server} metrics={metrics} />

            <div className="container mx-auto max-w-7xl px-4">
                <div className="mt-6 flex h-full flex-col lg:flex-row">
                    <div className="hidden lg:block">
                        <NavigationSidebar>
                            <div className="space-y-8">
                                <NavigationCard items={[backToDashboardNav]} />
                                <NavigationCard title="Server" items={serverNavItems} />
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
