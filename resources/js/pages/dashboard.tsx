import { CardContainerAddButton } from '@/components/card-container-add-button';
import DeployServerModal from '@/components/deploy-server-modal';
import { ServerProviderIcon, type ServerProvider } from '@/components/server-provider-icon';
import { Button } from '@/components/ui/button';
import { CardContainer } from '@/components/ui/card-container';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { show as showServer } from '@/routes/servers';
import { show as showSite } from '@/routes/servers/sites';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useEffect } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard.url(),
    },
];

type Server = {
    id: number;
    name: string;
    provider?: ServerProvider;
    public_ip: string;
    ssh_port: number;
    php_version?: string;
    sites_count: number;
    supervisor_tasks_count: number;
    scheduled_tasks_count: number;
};

type Site = {
    id: number;
    domain: string;
    repository?: string;
    php_version?: string;
    server_id: number;
    server_name: string;
    last_deployed_at?: string;
};

type Activity = {
    id: number;
    type: string;
    label: string;
    description: string;
    detail?: string;
    created_at: string;
    created_at_human: string;
};

type DashboardData = {
    servers: Server[];
    sites: Site[];
    activities: Activity[];
};

export default function Dashboard({ dashboard }: { dashboard: DashboardData }) {
    const { servers, sites, activities } = dashboard;
    const { auth } = usePage<SharedData>().props;

    const getTimeAgo = (dateString?: string) => {
        if (!dateString) return null;

        const date = new Date(dateString);
        const now = new Date();
        const diffInSeconds = Math.floor((now.getTime() - date.getTime()) / 1000);

        if (diffInSeconds < 60) return 'just now';
        if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)} minutes ago`;
        if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)} hours ago`;
        if (diffInSeconds < 604800) return `${Math.floor(diffInSeconds / 86400)} days ago`;
        return date.toLocaleDateString();
    };

    const getSiteInitial = (domain: string) => {
        return domain.charAt(0).toUpperCase();
    };

    const getSiteColor = (domain: string) => {
        const colors = [
            'bg-blue-500',
            'bg-purple-500',
            'bg-pink-500',
            'bg-green-500',
            'bg-yellow-500',
            'bg-red-500',
            'bg-indigo-500',
        ];
        const index = domain.charCodeAt(0) % colors.length;
        return colors[index];
    };

    const getUserInitials = (name: string) => {
        return name
            .split(' ')
            .map((n) => n[0])
            .join('')
            .toUpperCase()
            .slice(0, 2);
    };

    // Subscribe to generic servers channel for real-time updates
    useEffect(() => {
        const channel = window.Echo?.private('servers').listen('.ServerUpdated', () => {
            router.reload({
                only: ['dashboard'],
                preserveScroll: true,
                preserveState: true,
            });
        });

        return () => {
            window.Echo?.leave('servers');
        };
    }, []);

    // Subscribe to generic sites channel for real-time updates
    useEffect(() => {
        const channel = window.Echo?.private('sites').listen('.ServerSiteUpdated', () => {
            router.reload({
                only: ['dashboard'],
                preserveScroll: true,
                preserveState: true,
            });
        });

        return () => {
            window.Echo?.leave('sites');
        };
    }, []);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />
            <div className="space-y-8">
                {/* User Header */}
                <div className="flex items-center gap-4 pt-6">
                    <div className="flex size-12 items-center justify-center rounded-lg bg-primary text-lg font-semibold text-primary-foreground">
                        {getUserInitials(auth.user.name)}
                    </div>
                    <div>
                        <h1 className="text-xl font-semibold">{auth.user.name}</h1>
                        <p className="text-sm text-muted-foreground">{auth.user.plan_name || 'Free'}</p>
                    </div>
                </div>

                {/* Recent Servers Section */}
                <CardContainer
                    title="Recent servers"
                    parentBorder={false}
                    action={
                        <DeployServerModal
                            trigger={<CardContainerAddButton label="Add Server" onClick={() => {}} aria-label="Deploy Server" />}
                        />
                    }
                >
                    {servers && servers.length > 0 ? (
                        <div className="space-y-2">
                            {servers.map((server) => (
                                <Link
                                    key={server.id}
                                    href={showServer(server.id)}
                                    className="group block rounded-lg border border-neutral-200 bg-white p-4 transition-all hover:border-neutral-300 hover:bg-neutral-50 dark:border-white/8 dark:bg-white/3 dark:hover:border-white/12 dark:hover:bg-white/5"
                                >
                                    <div className="flex items-center justify-between">
                                        <div className="flex items-center gap-3">
                                            <ServerProviderIcon provider={server.provider as ServerProvider} size="md" />
                                            <div>
                                                <h3 className="font-medium">{server.name}</h3>
                                                <p className="text-sm text-muted-foreground">
                                                    {server.public_ip}
                                                    {server.php_version && (
                                                        <>
                                                            {' '}
                                                            · App server · PHP {server.php_version}
                                                        </>
                                                    )}
                                                </p>
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-6 text-sm text-muted-foreground">
                                            <span>
                                                {server.sites_count} {server.sites_count === 1 ? 'site' : 'sites'}
                                            </span>
                                            <span>
                                                {server.supervisor_tasks_count}{' '}
                                                {server.supervisor_tasks_count === 1 ? 'background process' : 'background processes'}
                                            </span>
                                            <span>
                                                {server.scheduled_tasks_count}{' '}
                                                {server.scheduled_tasks_count === 1 ? 'scheduled job' : 'scheduled jobs'}
                                            </span>
                                        </div>
                                    </div>
                                </Link>
                            ))}
                        </div>
                    ) : (
                        <div className="py-12 text-center">
                            <p className="text-sm text-muted-foreground">No servers yet. Deploy your first server to get started.</p>
                            <DeployServerModal
                                trigger={
                                    <Button className="mt-4" size="sm">
                                        <Plus className="mr-2 size-4" />
                                        Deploy Server
                                    </Button>
                                }
                            />
                        </div>
                    )}
                </CardContainer>

                {/* Recent Sites Section */}
                <CardContainer title="Recent sites" parentBorder={false}>
                    {sites && sites.length > 0 ? (
                        <div className="space-y-2">
                            {sites.map((site) => (
                                <Link
                                    key={site.id}
                                    href={showSite({ server: site.server_id, site: site.id })}
                                    className="group block rounded-lg border border-neutral-200 bg-white p-4 transition-all hover:border-neutral-300 hover:bg-neutral-50 dark:border-white/8 dark:bg-white/3 dark:hover:border-white/12 dark:hover:bg-white/5"
                                >
                                    <div className="flex items-center justify-between">
                                        <div className="flex items-center gap-3">
                                            <div
                                                className={`flex size-10 shrink-0 items-center justify-center rounded-lg ${getSiteColor(site.domain)} text-white`}
                                            >
                                                <span className="text-lg font-semibold">{getSiteInitial(site.domain)}</span>
                                            </div>
                                            <div>
                                                <h3 className="font-medium">{site.domain}</h3>
                                                <p className="text-sm text-muted-foreground">
                                                    {site.repository ? site.repository : 'Other'}
                                                    {site.php_version && <> · PHP {site.php_version}</>}
                                                    {site.server_name && <> · {site.server_name}</>}
                                                </p>
                                            </div>
                                        </div>
                                        {site.last_deployed_at && (
                                            <div className="text-sm text-muted-foreground">Deployed {getTimeAgo(site.last_deployed_at)}</div>
                                        )}
                                    </div>
                                </Link>
                            ))}
                        </div>
                    ) : (
                        <div className="py-12 text-center">
                            <p className="text-sm text-muted-foreground">No sites yet. Create your first site to get started.</p>
                        </div>
                    )}
                </CardContainer>

                {/* Recent Activity Section */}
                <CardContainer title="Recent activity" parentBorder={false}>
                    {activities && activities.length > 0 ? (
                        <div className="space-y-2">
                            {activities.map((activity) => (
                                <div
                                    key={activity.id}
                                    className="rounded-lg border border-neutral-200 bg-white p-4 dark:border-white/8 dark:bg-white/3"
                                >
                                    <div className="flex items-start justify-between gap-4">
                                        <div className="flex-1">
                                            <div className="flex items-center gap-2">
                                                <span className="font-medium">{activity.label}</span>
                                                {activity.detail && (
                                                    <span className="text-sm text-muted-foreground">· {activity.detail}</span>
                                                )}
                                            </div>
                                            <p className="mt-1 text-sm text-muted-foreground">{activity.description}</p>
                                        </div>
                                        <div className="shrink-0 text-sm text-muted-foreground">{activity.created_at_human}</div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <div className="py-12 text-center">
                            <p className="text-sm text-muted-foreground">No recent activity to display.</p>
                        </div>
                    )}
                </CardContainer>
            </div>
        </AppLayout>
    );
}
