import { CardContainerAddButton } from '@/components/card-container-add-button';
import DeployServerModal from '@/components/deploy-server-modal';
import { ServerProviderIcon, type ServerProvider } from '@/components/server-provider-icon';
import { Button } from '@/components/ui/button';
import { CardContainer } from '@/components/ui/card-container';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import { provisioning as provisioningServer, show as showServer } from '@/routes/servers';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { Activity, ArrowRight, Clock, FileText, Globe, Plus, Server as ServerIcon, Users, Zap } from 'lucide-react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard.url(),
    },
];

type Activity = { id: number; type: string; label: string; detail?: string | null; created_at: string };
type Server = {
    id: number;
    name: string;
    provider?: ServerProvider;
    public_ip: string;
    ssh_port: number;
    private_ip?: string | null;
    connection?: 'pending' | 'connected' | 'failed' | 'disconnected' | string;
    provision_status: 'pending' | 'connecting' | 'installing' | 'completed' | 'failed';
    created_at: string;
};

export default function Dashboard({ activities, servers }: { activities: Activity[]; servers: Server[] }) {
    const { auth } = usePage<SharedData>().props;
    const [showDeployModal, setShowDeployModal] = useState(false);

    const getTimeAgo = (dateString: string) => {
        const date = new Date(dateString);
        const now = new Date();
        const diffInSeconds = Math.floor((now.getTime() - date.getTime()) / 1000);

        if (diffInSeconds < 60) return 'just now';
        if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)}m ago`;
        if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)}h ago`;
        if (diffInSeconds < 604800) return `${Math.floor(diffInSeconds / 86400)}d ago`;
        return date.toLocaleDateString();
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />
            <div className="space-y-6">
                {/* Servers Section */}
                <CardContainer
                    title="Servers"
                    action={
                        <DeployServerModal
                            trigger={
                                <CardContainerAddButton label="Add Server" onClick={() => setShowDeployModal(true)} aria-label="Deploy Server" />
                            }
                        />
                    }
                >
                    {servers && servers.length > 0 ? (
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            {servers.map((s) => {
                                const status = s.connection ?? 'pending';
                                const provisionStatus = s.provision_status ?? 'pending';
                                const serverUrl = provisionStatus === 'completed' ? showServer(s.id) : provisioningServer(s.id);

                                const statusConfig = {
                                    connected: { color: 'text-green-600', bg: 'bg-green-500', label: 'Online', icon: Zap },
                                    failed: { color: 'text-red-600', bg: 'bg-red-500', label: 'Failed', icon: Activity },
                                    disconnected: { color: 'text-gray-600', bg: 'bg-gray-500', label: 'Offline', icon: Activity },
                                    pending: { color: 'text-amber-600', bg: 'bg-amber-500', label: 'Pending', icon: Clock },
                                }[status] || { color: 'text-gray-600', bg: 'bg-gray-500', label: status, icon: Activity };

                                return (
                                    <Link
                                        key={s.id}
                                        href={serverUrl}
                                        className="group relative block rounded-lg border border-neutral-200 bg-white p-4 transition-all hover:border-primary hover:shadow-sm dark:border-white/8 dark:bg-white/3 dark:hover:border-primary"
                                    >
                                        <div className="space-y-3">
                                            <div className="flex items-start justify-between">
                                                <ServerProviderIcon provider={s.provider as ServerProvider} size="md" />
                                                <div
                                                    className={cn(
                                                        'flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-medium',
                                                        status === 'connected'
                                                            ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
                                                            : status === 'failed'
                                                              ? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'
                                                              : status === 'pending'
                                                                ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400'
                                                                : 'bg-gray-100 text-gray-700 dark:bg-gray-900/30 dark:text-gray-400',
                                                    )}
                                                >
                                                    <span className={cn('size-1.5 rounded-full', statusConfig.bg)} />
                                                    {statusConfig.label}
                                                </div>
                                            </div>
                                            <div>
                                                <h3 className="truncate text-sm font-semibold">{s.name}</h3>
                                                <p className="mt-1 truncate text-xs text-muted-foreground">
                                                    {s.public_ip}:{s.ssh_port}
                                                </p>
                                            </div>
                                            <div className="flex items-center justify-between text-xs text-muted-foreground">
                                                <span>{getTimeAgo(s.created_at)}</span>
                                                <ArrowRight className="size-3 opacity-0 transition-opacity group-hover:opacity-100" />
                                            </div>
                                        </div>
                                    </Link>
                                );
                            })}
                        </div>
                    ) : (
                        <div className="py-12 text-center">
                            <ServerIcon className="mx-auto size-12 text-muted-foreground/30" />
                            <h3 className="mt-4 text-sm font-medium">No servers yet</h3>
                            <p className="mt-1 text-sm text-muted-foreground">Get started by deploying your first server</p>
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

                {/* Recent Activity Section */}
                <CardContainer title="Recent Activity">
                    <div className="space-y-4">
                        {activities && activities.length > 0 ? (
                            activities.slice(0, 10).map((activity) => {
                                const activityIcons: Record<string, any> = {
                                    server: ServerIcon,
                                    site: Globe,
                                    database: FileText,
                                    user: Users,
                                    default: Activity,
                                };
                                const Icon = activityIcons[activity.type] || activityIcons.default;

                                return (
                                    <div key={activity.id} className="flex gap-3 border-b pb-3 last:border-0 last:pb-0">
                                        <div className="mt-0.5 flex-shrink-0">
                                            <div className="flex size-8 items-center justify-center rounded-full bg-muted">
                                                <Icon className="size-4 text-muted-foreground" />
                                            </div>
                                        </div>
                                        <div className="min-w-0 flex-1">
                                            <p className="text-sm leading-tight font-medium">{activity.label}</p>
                                            {activity.detail && <p className="mt-1 text-xs text-muted-foreground">{activity.detail}</p>}
                                            <p className="mt-1 text-xs text-muted-foreground">{getTimeAgo(activity.created_at)}</p>
                                        </div>
                                    </div>
                                );
                            })
                        ) : (
                            <div className="py-8 text-center">
                                <Activity className="mx-auto size-8 text-muted-foreground/30" />
                                <p className="mt-2 text-sm text-muted-foreground">No recent activity</p>
                                <p className="mt-1 text-xs text-muted-foreground">Activity will appear here as you use the platform</p>
                            </div>
                        )}
                    </div>
                </CardContainer>
            </div>
        </AppLayout>
    );
}
