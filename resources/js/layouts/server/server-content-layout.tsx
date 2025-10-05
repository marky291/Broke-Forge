import { NavigationCard, NavigationSidebar } from '@/components/navigation-card';
import { ServerProviderIcon, type ServerProvider } from '@/components/server-provider-icon';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type NavItem, type ServerMetric } from '@/types';
import { usePage } from '@inertiajs/react';
import {
    Activity,
    ArrowLeft,
    Clock,
    CodeIcon,
    Cpu,
    DatabaseIcon,
    Globe,
    HardDrive,
    Home,
    MemoryStick,
    Settings,
    Shield,
} from 'lucide-react';
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
    } else if (path.includes('/database')) {
        currentSection = 'database';
    } else if (path.includes('/firewall')) {
        currentSection = 'firewall';
    } else if (path.includes('/monitoring')) {
        currentSection = 'monitoring';
    } else if (path.includes('/scheduler')) {
        currentSection = 'scheduler';
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
            href: `/servers/${server.id}`,
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
            title: 'Database',
            href: `/servers/${server.id}/database`,
            icon: DatabaseIcon,
            isActive: currentSection === 'database',
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
            title: 'Scheduler',
            href: `/servers/${server.id}/scheduler`,
            icon: Clock,
            isActive: currentSection === 'scheduler',
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

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            {/* Server Header */}
            <div className="bg-card px-8 py-4 border-b">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-8">
                        {/* Title */}
                        <div className="flex items-center gap-3">
                            <ServerProviderIcon provider={server.provider} size="lg" />
                            <h1 className="text-xl font-semibold text-foreground">{server.vanity_name}</h1>
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
                    </div>

                    {/* Monitoring Metrics - Far Right */}
                    {server.monitoring_status === 'active' && metrics && (
                        <div className="flex items-center gap-4 text-sm border-l pl-8">
                            <div className="flex items-center gap-2">
                                <Cpu className="h-3.5 w-3.5 text-blue-600" />
                                <div>
                                    <div className="text-[10px] text-muted-foreground uppercase tracking-wide mb-0.5">CPU</div>
                                    <div className="font-medium">{Number(metrics.cpu_usage).toFixed(1)}%</div>
                                </div>
                            </div>
                            <div className="flex items-center gap-2">
                                <MemoryStick className="h-3.5 w-3.5 text-purple-600" />
                                <div>
                                    <div className="text-[10px] text-muted-foreground uppercase tracking-wide mb-0.5">Memory</div>
                                    <div className="font-medium">{Number(metrics.memory_usage_percentage).toFixed(1)}%</div>
                                </div>
                            </div>
                            <div className="flex items-center gap-2">
                                <HardDrive className="h-3.5 w-3.5 text-orange-600" />
                                <div>
                                    <div className="text-[10px] text-muted-foreground uppercase tracking-wide mb-0.5">Storage</div>
                                    <div className="font-medium">{Number(metrics.storage_usage_percentage).toFixed(1)}%</div>
                                </div>
                            </div>
                        </div>
                    )}
                </div>
            </div>

            <div className="flex h-full mt-6">
                <NavigationSidebar>
                    <div className="space-y-6">
                        <NavigationCard items={[backToDashboardNav]} />
                        <NavigationCard title="Server" items={serverNavItems} />
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