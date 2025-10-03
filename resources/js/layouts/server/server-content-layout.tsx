import { NavigationCard, NavigationSidebar } from '@/components/navigation-card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type NavItem } from '@/types';
import { usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    CodeIcon,
    DatabaseIcon,
    Globe,
    Home,
    Server,
    Settings,
    Shield,
} from 'lucide-react';
import { PropsWithChildren } from 'react';

interface ServerContentLayoutProps extends PropsWithChildren {
    server: {
        id: number;
        vanity_name: string;
        connection?: string;
        public_ip?: string;
        private_ip?: string;
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
    let currentSection: string = 'server';

    if (path.endsWith(`/servers/${server.id}`) || path.endsWith(`/servers/${server.id}/`) || path.includes('/sites')) {
        currentSection = 'server';
    } else if (path.includes('/php')) {
        currentSection = 'php';
    } else if (path.includes('/database')) {
        currentSection = 'database';
    } else if (path.includes('/firewall')) {
        currentSection = 'firewall';
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
                            <div className="flex items-center justify-center w-8 h-8 rounded-md bg-secondary/50">
                                <Server className="h-4 w-4 text-muted-foreground" />
                            </div>
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