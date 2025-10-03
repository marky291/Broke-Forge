import { NavigationCard, NavigationSidebar } from '@/components/navigation-card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type NavItem } from '@/types';
import { usePage } from '@inertiajs/react';
import {
    CodeIcon,
    DatabaseIcon,
    Folder,
    Globe,
    Home,
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
    } else if (path.includes('/firewall')) {
        currentSection = 'firewall';
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
            title: 'Firewall',
            href: `/servers/${server.id}/firewall`,
            icon: Shield,
            isActive: currentSection === 'firewall',
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
            <div className="flex h-full">
                <NavigationSidebar>
                    <NavigationCard items={serverNavItems} />
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