import { CardContainerAddButton } from '@/components/card-container-add-button';
import DeployServerModal from '@/components/deploy-server-modal';
import { ServerProviderIcon, type ServerProvider } from '@/components/server-provider-icon';
import { Button } from '@/components/ui/button';
import { CardContainer } from '@/components/ui/card-container';
import { cn } from '@/lib/utils';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { provisioning as provisioningServer, show as showServer } from '@/routes/servers';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import {
    Activity,
    ArrowRight,
    Clock,
    FileText,
    Globe,
    Plus,
    Server as ServerIcon,
    Users,
    Zap
} from 'lucide-react';
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
                                <CardContainerAddButton
                                    label="Add Server"
                                    onClick={() => setShowDeployModal(true)}
                                    aria-label="Deploy Server"
                                />
                            }
                        />
                    }
                >
                    {servers && servers.length > 0 ? (
                                    <div className="space-y-4">
                                        {servers.slice(0, 3).map((s) => {
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
                                                <Link key={s.id} href={serverUrl} className="block group">
                                                    <div className="">
                                                        <div className="flex items-start justify-between">
                                                            <div className="flex-1">
                                                                <div className="flex items-center gap-3 mb-2">
                                                                    <ServerProviderIcon provider={s.provider as ServerProvider} size="md" />
                                                                    <h3 className="font-semibold text-base">{s.name}</h3>
                                                                </div>
                                                                <div className="space-y-1">
                                                                    <p className="text-sm text-muted-foreground">
                                                                        <span className="font-medium">IP:</span> {s.public_ip}:{s.ssh_port}
                                                                    </p>
                                                                    {s.private_ip && (
                                                                        <p className="text-sm text-muted-foreground">
                                                                            <span className="font-medium">Private:</span> {s.private_ip}
                                                                        </p>
                                                                    )}
                                                                </div>
                                                            </div>
                                                            <div className="flex flex-col items-end gap-2">
                                                                <div className={cn("flex items-center gap-2 px-2.5 py-1 rounded-full text-xs font-medium",
                                                                    status === 'connected' ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' :
                                                                    status === 'failed' ? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' :
                                                                    status === 'pending' ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400' :
                                                                    'bg-gray-100 text-gray-700 dark:bg-gray-900/30 dark:text-gray-400'
                                                                )}>
                                                                    <span className={cn("size-1.5 rounded-full", statusConfig.bg)} />
                                                                    {statusConfig.label}
                                                                </div>
                                                                <p className="text-xs text-muted-foreground">
                                                                    {getTimeAgo(s.created_at)}
                                                                </p>
                                                            </div>
                                                        </div>
                                                        <div className="absolute top-0 right-0 opacity-0 group-hover:opacity-100 transition-opacity p-2">
                                                            <ArrowRight className="size-4 text-muted-foreground" />
                                                        </div>
                                                    </div>
                                                </Link>
                                            );
                                        })}
                                    </div>
                                ) : (
                                    <div className="text-center py-12">
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

                {/* Sites Section */}
                <CardContainer title="Sites">
                    <div className="text-center py-12">
                        <Globe className="mx-auto size-12 text-muted-foreground/30" />
                        <h3 className="mt-4 text-sm font-medium">No sites configured</h3>
                        <p className="mt-1 text-sm text-muted-foreground">Sites will appear here once you add them to your servers</p>
                    </div>
                </CardContainer>

                {/* Recent Activity Section */}
                <CardContainer title="Recent Activity">
                    <div className="space-y-4">
                                    {activities && activities.length > 0 ? (
                                        activities.slice(0, 10).map((activity) => {
                                            const activityIcons: Record<string, any> = {
                                                'server': ServerIcon,
                                                'site': Globe,
                                                'database': FileText,
                                                'user': Users,
                                                'default': Activity
                                            };
                                            const Icon = activityIcons[activity.type] || activityIcons.default;

                                            return (
                                                <div key={activity.id} className="flex gap-3 pb-3 last:pb-0 border-b last:border-0">
                                                    <div className="flex-shrink-0 mt-0.5">
                                                        <div className="size-8 rounded-full bg-muted flex items-center justify-center">
                                                            <Icon className="size-4 text-muted-foreground" />
                                                        </div>
                                                    </div>
                                                    <div className="flex-1 min-w-0">
                                                        <p className="text-sm font-medium leading-tight">
                                                            {activity.label}
                                                        </p>
                                                        {activity.detail && (
                                                            <p className="text-xs text-muted-foreground mt-1">
                                                                {activity.detail}
                                                            </p>
                                                        )}
                                                        <p className="text-xs text-muted-foreground mt-1">
                                                            {getTimeAgo(activity.created_at)}
                                                        </p>
                                                    </div>
                                                </div>
                                            );
                                        })
                                    ) : (
                                        <div className="text-center py-8">
                                            <Activity className="mx-auto size-8 text-muted-foreground/30" />
                                            <p className="mt-2 text-sm text-muted-foreground">No recent activity</p>
                                            <p className="text-xs text-muted-foreground mt-1">Activity will appear here as you use the platform</p>
                                        </div>
                                    )}
                    </div>
                </CardContainer>
            </div>
        </AppLayout>
    );
}
