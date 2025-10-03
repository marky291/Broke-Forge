import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { CardContainer } from '@/components/ui/card-container';
import ServerLayout from '@/layouts/server/layout';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import {
    CheckCircle2,
    Clock,
    Edit,
    Loader2,
    RefreshCw,
    Server,
    Terminal,
    Trash2,
    Wifi,
    XCircle
} from 'lucide-react';
import { useState } from 'react';

type Server = {
    id: number;
    vanity_name: string;
    public_ip: string;
    private_ip?: string | null;
    connection: 'pending' | 'connecting' | 'connected' | 'failed' | 'disconnected' | string;
    ssh_port: number;
    created_at: string;
    updated_at: string;
};

export default function Show({ server }: { server: Server }) {
    const [isDestroying, setIsDestroying] = useState(false);
    const [isRestarting, setIsRestarting] = useState(false);
    const status = server.connection ?? 'pending';

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: dashboard.url() },
        { title: `Server #${server.id}`, href: '#' },
    ];

    const getStatusConfig = () => {
        switch (status) {
            case 'connected':
                return { color: 'bg-green-500', badge: 'border-green-200 bg-green-50 text-green-700', icon: CheckCircle2, label: 'Connected' };
            case 'failed':
                return { color: 'bg-red-500', badge: 'border-red-200 bg-red-50 text-red-700', icon: XCircle, label: 'Failed' };
            case 'disconnected':
                return { color: 'bg-gray-500', badge: 'border-gray-200 bg-gray-50 text-gray-700', icon: XCircle, label: 'Disconnected' };
            case 'connecting':
                return { color: 'bg-blue-500', badge: 'border-blue-200 bg-blue-50 text-blue-700', icon: Wifi, label: 'Connecting' };
            default:
                return { color: 'bg-amber-500', badge: 'border-amber-200 bg-amber-50 text-amber-700', icon: Clock, label: 'Pending' };
        }
    };

    const statusConfig = getStatusConfig();
    const StatusIcon = statusConfig.icon;

    const handleDestroyServer = () => {
        const confirmed = window.confirm('Are you sure you want to destroy this server? This action cannot be undone.');
        if (confirmed) {
            setIsDestroying(true);
            router.delete(`/servers/${server.id}`, {
                onFinish: () => setIsDestroying(false),
                onError: () => setIsDestroying(false),
            });
        }
    };

    const handleRestartServer = () => {
        setIsRestarting(true);
        // Simulate restart - replace with actual API call
        setTimeout(() => setIsRestarting(false), 2000);
    };

    return (
        <ServerLayout server={server} breadcrumbs={breadcrumbs}>
            <Head title={`${server.vanity_name} - Server Overview`} />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-start justify-between">
                    <div className="space-y-1">
                        <div className="flex items-center gap-3">
                            <h1 className="text-2xl font-semibold">{server.vanity_name}</h1>
                            <Badge variant="outline" className={cn("gap-1.5", statusConfig.badge)}>
                                <StatusIcon className="size-3" />
                                {statusConfig.label}
                            </Badge>
                        </div>
                        <p className="text-sm text-muted-foreground">
                            Server #{server.id} • {server.public_ip}:{server.ssh_port}
                        </p>
                    </div>

                    {/* Actions */}
                    <div className="flex gap-2">
                        <Button variant="outline" size="sm" onClick={handleRestartServer} disabled={isRestarting}>
                            {isRestarting ? (
                                <Loader2 className="size-4 animate-spin" />
                            ) : (
                                <RefreshCw className="size-4" />
                            )}
                            <span className="ml-2 hidden sm:inline">Restart</span>
                        </Button>
                        <Button variant="outline" size="sm" asChild>
                            <Link href={`/servers/${server.id}/terminal`}>
                                <Terminal className="size-4" />
                                <span className="ml-2 hidden sm:inline">Terminal</span>
                            </Link>
                        </Button>
                        <Button variant="outline" size="sm" asChild>
                            <Link href={`/servers/${server.id}/edit`}>
                                <Edit className="size-4" />
                                <span className="ml-2 hidden sm:inline">Edit</span>
                            </Link>
                        </Button>
                    </div>
                </div>

                {/* Main Content Grid */}
                <div className="grid gap-6 lg:grid-cols-2">
                    {/* Server Information */}
                    <CardContainer title="Server Information">
                        <div className="space-y-3">
                            <div className="flex justify-between py-2 border-b">
                                <span className="text-sm text-muted-foreground">Public IP</span>
                                <span className="text-sm font-medium">{server.public_ip}</span>
                            </div>
                            <div className="flex justify-between py-2 border-b">
                                <span className="text-sm text-muted-foreground">Private IP</span>
                                <span className="text-sm font-medium">{server.private_ip || 'Not configured'}</span>
                            </div>
                            <div className="flex justify-between py-2 border-b">
                                <span className="text-sm text-muted-foreground">SSH Port</span>
                                <span className="text-sm font-medium">{server.ssh_port}</span>
                            </div>
                            <div className="flex justify-between py-2">
                                <span className="text-sm text-muted-foreground">Created</span>
                                <span className="text-sm font-medium">
                                    {new Date(server.created_at).toLocaleDateString()}
                                </span>
                            </div>
                        </div>
                    </CardContainer>

                    {/* Quick Links */}
                    <CardContainer title="Quick Access" description="Common server management tasks">
                        <div className="grid grid-cols-2 gap-3">
                            <Button variant="outline" className="justify-start" asChild>
                                <Link href={`/servers/${server.id}/sites`}>
                                    Sites
                                </Link>
                            </Button>
                            <Button variant="outline" className="justify-start" asChild>
                                <Link href={`/servers/${server.id}/database`}>
                                    Database
                                </Link>
                            </Button>
                            <Button variant="outline" className="justify-start" asChild>
                                <Link href={`/servers/${server.id}/php`}>
                                    PHP
                                </Link>
                            </Button>
                            <Button variant="outline" className="justify-start" asChild>
                                <Link href={`/servers/${server.id}/explorer`}>
                                    File Explorer
                                </Link>
                            </Button>
                        </div>
                    </CardContainer>
                </div>

                {/* Danger Zone */}
                <CardContainer title="Danger Zone" description="Irreversible and destructive actions" className="border-red-200 dark:border-red-900">
                    <Button
                        variant="destructive"
                        size="sm"
                        onClick={handleDestroyServer}
                        disabled={isDestroying}
                    >
                        {isDestroying ? (
                            <>
                                <Loader2 className="mr-2 size-4 animate-spin" />
                                Destroying...
                            </>
                        ) : (
                            <>
                                <Trash2 className="mr-2 size-4" />
                                Destroy Server
                            </>
                        )}
                    </Button>
                </CardContainer>
            </div>
        </ServerLayout>
    );
}