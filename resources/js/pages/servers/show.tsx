import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import ServerLayout from '@/layouts/server/layout';
import { dashboard } from '@/routes';
import { edit as editServer } from '@/routes/servers';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Loader2Icon, Trash2Icon } from 'lucide-react';
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
    const status = server.connection ?? 'pending';
    const formattedStatus = status.charAt(0).toUpperCase() + status.slice(1);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: dashboard().url },
        { title: `Server #${server.id}`, href: '#' },
    ];

    const handleDestroyServer = () => {
        const action = server.connection === 'pending' ? 'cancel provisioning' : 'delete';
        const confirmed = window.confirm(`Are you sure you want to ${action} this server? This action cannot be undone.`);

        if (confirmed) {
            setIsDestroying(true);
            router.delete(`/servers/${server.id}`, {
                onFinish: () => setIsDestroying(false),
                onError: () => setIsDestroying(false),
            });
        }
    };

    return (
        <ServerLayout server={server} breadcrumbs={breadcrumbs}>
            <Head title={`Server #${server.id} — ${server.vanity_name}`} />
            <div className="space-y-6">
                <div className="mb-2 flex items-center justify-between">
                <div className="flex items-center gap-3">
                        <h1 className="text-2xl font-semibold">{server.vanity_name}</h1>
                        {(() => {
                            const color = status === 'connected' ? 'green' : status === 'failed' ? 'red' : status === 'disconnected' ? 'gray' : 'amber';
                            const dot = color === 'green' ? 'bg-green-500' : color === 'red' ? 'bg-red-500' : color === 'gray' ? 'bg-gray-500' : 'bg-amber-500';
                            const ping = color === 'green' ? 'bg-green-400' : color === 'red' ? 'bg-red-400' : color === 'gray' ? 'bg-gray-400' : 'bg-amber-400';
                            return (
                                <span className="inline-flex items-center gap-2 text-xs">
                                    <span className="relative inline-flex h-2 w-2">
                                        <span className={`absolute inline-flex h-full w-full animate-ping rounded-full opacity-60 ${ping}`}></span>
                                        <span className={`relative inline-flex h-2 w-2 rounded-full ${dot}`}></span>
                                    </span>
                                    <span className="text-muted-foreground">{formattedStatus}</span>
                                </span>
                            );
                        })()}
                    </div>
                    <div className="space-x-2">
                        <Button variant="outline" asChild>
                            <Link href={dashboard().url}>Back</Link>
                        </Button>
                        <Button variant="destructive" onClick={handleDestroyServer} disabled={isDestroying}>
                            {isDestroying ? (
                                <>
                                    <Loader2Icon className="mr-2 h-4 w-4 animate-spin" />
                                    Destroying...
                                </>
                            ) : (
                                <>
                                    <Trash2Icon className="mr-2 h-4 w-4" />
                                    Destroy Server
                                </>
                            )}
                        </Button>
                        <Button asChild>
                            <Link href={editServer(server.id)}>Edit</Link>
                        </Button>
                    </div>
                </div>

                {/* Server Details */}
                <div className="grid gap-4">
                    <div>
                        <div className="rounded-xl border border-sidebar-border/70 bg-background shadow-sm dark:border-sidebar-border">
                            <div className="px-4 py-3">
                                <div className="text-sm font-medium tracking-wide text-neutral-600 uppercase dark:text-neutral-300">
                                    Server Details
                                </div>
                            </div>
                            <Separator />
                            <div className="px-4 py-4">
                                <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                                    <div className="space-y-4">
                                        <div>
                                            <div className="text-sm text-muted-foreground">Name</div>
                                            <div className="font-medium">{server.vanity_name}</div>
                                        </div>
                                        <div>
                                            <div className="text-sm text-muted-foreground">Public IP Address</div>
                                            <div className="font-medium">{server.public_ip}</div>
                                        </div>
                                        <div>
                                            <div className="text-sm text-muted-foreground">SSH Port</div>
                                            <div className="font-medium">{server.ssh_port}</div>
                                        </div>
                                    </div>
                                    <div className="space-y-4">
                                        <div>
                                            <div className="text-sm text-muted-foreground">Private IP</div>
                                            <div className="font-medium">{server.private_ip ?? '-'}</div>
                                        </div>
                                        <div>
                                            <div className="text-sm text-muted-foreground">Status</div>
                                            <div className="font-medium">
                                                {formattedStatus}
                                            </div>
                                        </div>
                                        <div>
                                            <div className="text-sm text-muted-foreground">Created</div>
                                            <div className="font-medium">{new Date(server.created_at).toLocaleString()}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </ServerLayout>
    );
}
