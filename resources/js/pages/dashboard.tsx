import { CardContainerAddButton } from '@/components/card-container-add-button';
import { CardList } from '@/components/card-list';
import DeployServerModal from '@/components/deploy-server-modal';
import { ServerProviderIcon, type ServerProvider } from '@/components/server-provider-icon';
import { SiteAvatar } from '@/components/site-avatar';
import { Button } from '@/components/ui/button';
import { CardContainer } from '@/components/ui/card-container';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { show as showServer } from '@/routes/servers';
import { show as showSite } from '@/routes/servers/sites';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
import { Activity, Globe, Plus, Server as ServerIcon } from 'lucide-react';
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
    last_deployed_at_human?: string;
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

        const minutes = Math.floor(diffInSeconds / 60);
        if (minutes < 60) return `${minutes} ${minutes === 1 ? 'minute' : 'minutes'} ago`;

        const hours = Math.floor(diffInSeconds / 3600);
        if (hours < 24) return `${hours} ${hours === 1 ? 'hour' : 'hours'} ago`;

        const days = Math.floor(diffInSeconds / 86400);
        if (days < 7) return `${days} ${days === 1 ? 'day' : 'days'} ago`;

        const weeks = Math.floor(days / 7);
        if (weeks < 4) return `${weeks} ${weeks === 1 ? 'week' : 'weeks'} ago`;

        const months = Math.floor(days / 30);
        if (months < 12) return `${months} ${months === 1 ? 'month' : 'months'} ago`;

        const years = Math.floor(days / 365);
        return `${years} ${years === 1 ? 'year' : 'years'} ago`;
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
                {servers && servers.length > 0 ? (
                    <CardList<Server>
                        title="Recent servers"
                        icon={
                            <svg width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <rect
                                    x="1.5"
                                    y="2.5"
                                    width="9"
                                    height="7"
                                    rx="1"
                                    stroke="currentColor"
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                />
                                <path d="M3 5h6M3 7h6" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" />
                            </svg>
                        }
                        items={servers}
                        keyExtractor={(server) => server.id}
                        onItemClick={(server) => router.visit(showServer(server.id).url)}
                        renderItem={(server) => (
                            <div className="flex items-center justify-between gap-3">
                                {/* Left: Server icon + info */}
                                <div className="flex min-w-0 flex-1 items-center gap-3">
                                    <ServerProviderIcon provider={server.provider as ServerProvider} size="md" />
                                    <div className="min-w-0 flex-1">
                                        <div className="text-sm font-medium text-foreground">{server.name}</div>
                                        <p className="truncate text-xs text-muted-foreground">
                                            {server.public_ip}
                                            {server.php_version && <> · App server · PHP {server.php_version}</>}
                                        </p>
                                    </div>
                                </div>

                                {/* Right: Counts */}
                                <div className="hidden flex-shrink-0 text-xs text-muted-foreground md:block">
                                    {server.sites_count} {server.sites_count === 1 ? 'site' : 'sites'} · {server.supervisor_tasks_count}{' '}
                                    {server.supervisor_tasks_count === 1 ? 'background process' : 'background processes'} ·{' '}
                                    {server.scheduled_tasks_count} {server.scheduled_tasks_count === 1 ? 'scheduled job' : 'scheduled jobs'}
                                </div>
                            </div>
                        )}
                        actions={[]}
                        emptyStateMessage="No servers yet. Deploy your first server to get started."
                        emptyStateIcon={<ServerIcon className="h-6 w-6 text-muted-foreground" />}
                    />
                ) : (
                    <CardContainer
                        title="Recent servers"
                        icon={
                            <svg width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <rect
                                    x="1.5"
                                    y="2.5"
                                    width="9"
                                    height="7"
                                    rx="1"
                                    stroke="currentColor"
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                />
                                <path d="M3 5h6M3 7h6" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" />
                            </svg>
                        }
                        action={
                            <DeployServerModal
                                trigger={<CardContainerAddButton label="Add Server" onClick={() => {}} aria-label="Deploy Server" />}
                            />
                        }
                    >
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
                    </CardContainer>
                )}

                {/* Recent Sites Section */}
                <CardList<Site>
                    title="Recent sites"
                    icon={
                        <svg width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <circle cx="6" cy="6" r="5" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" />
                            <path
                                d="M1.5 6h9M6 1.5c-1.5 1.5-1.5 4.5 0 9M6 1.5c1.5 1.5 1.5 4.5 0 9"
                                stroke="currentColor"
                                strokeLinecap="round"
                                strokeLinejoin="round"
                            />
                        </svg>
                    }
                    items={sites}
                    keyExtractor={(site) => site.id}
                    onItemClick={(site) => router.visit(showSite({ server: site.server_id, site: site.id }).url)}
                    renderItem={(site) => (
                        <div className="flex items-center justify-between gap-3">
                            {/* Left: Site icon + info */}
                            <div className="flex min-w-0 flex-1 items-center gap-3">
                                <SiteAvatar domain={site.domain} />
                                <div className="min-w-0 flex-1">
                                    <div className="text-sm font-medium text-foreground">{site.domain}</div>
                                    <p className="truncate text-xs text-muted-foreground">
                                        {site.repository ? site.repository : 'Other'}
                                        {site.php_version && <> · PHP {site.php_version}</>}
                                        {site.server_name && <> · {site.server_name}</>}
                                    </p>
                                </div>
                            </div>

                            {/* Right: Deployment info */}
                            <div className="flex-shrink-0 text-xs text-muted-foreground">
                                {site.last_deployed_at_human ? `Deployed ${site.last_deployed_at_human}` : 'Not deployed'}
                            </div>
                        </div>
                    )}
                    actions={[]}
                    emptyStateMessage="No sites yet. Create your first site to get started."
                    emptyStateIcon={<Globe className="h-6 w-6 text-muted-foreground" />}
                />

                {/* Recent Activity Section */}
                <CardList<Activity>
                    title="Recent activity"
                    icon={
                        <svg width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <circle cx="6" cy="6" r="5" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" />
                            <path d="M6 3v3l2 1" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" />
                        </svg>
                    }
                    items={activities}
                    keyExtractor={(activity) => activity.id}
                    renderItem={(activity) => (
                        <div className="flex items-center justify-between gap-4">
                            <div className="flex-1">
                                <div className="flex items-center gap-2">
                                    <span className="text-sm font-medium">{activity.label}</span>
                                    {activity.detail && <span className="text-xs text-muted-foreground">· {activity.detail}</span>}
                                </div>
                                <p className="mt-0.5 text-xs text-muted-foreground">{activity.description}</p>
                            </div>
                            <div className="shrink-0 text-xs text-muted-foreground">{activity.created_at_human}</div>
                        </div>
                    )}
                    actions={[]}
                    emptyStateMessage="No recent activity to display."
                    emptyStateIcon={<Activity className="h-6 w-6 text-muted-foreground" />}
                />
            </div>
        </AppLayout>
    );
}
