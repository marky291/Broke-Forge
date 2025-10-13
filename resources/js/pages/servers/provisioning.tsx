import { ServerDetail } from '@/components/server-detail';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { show as showServer } from '@/routes/servers';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { useEcho } from '@laravel/echo-react';
import { AlertCircle, CheckCircle2, Circle, Clock, Loader2, XCircle } from 'lucide-react';
import { useEffect, useState } from 'react';

interface ProvisionStep {
    name: string;
    description: string;
    status: {
        isCompleted: boolean;
        isPending: boolean;
        isFailed: boolean;
        isInstalling: boolean;
    };
}

interface Server {
    id: number;
    vanity_name: string;
    provider?: string;
    public_ip: string;
    private_ip?: string | null;
    ssh_port: number;
    connection: string;
    monitoring_status?: 'installing' | 'active' | 'failed' | 'uninstalling' | 'uninstalled' | null;
    provision_status: string;
    provision_status_label: string;
    provision_status_color: string;
    os_name?: string;
    os_version?: string;
    os_codename?: string;
    database_type?: string;
    php_version?: string;
    created_at: string;
    updated_at: string;
    steps: ProvisionStep[];
}

interface ProvisionData {
    command: string;
    root_password: string;
}

interface ProvisioningPageProps {
    server: Server;
    provision: ProvisionData | null;
}

export default function ProvisioningPage({ server, provision }: ProvisioningPageProps) {
    const [showInstructions, setShowInstructions] = useState(false);
    const [activeStepIndex, setActiveStepIndex] = useState(0);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: dashboard.url() },
        { title: `Server #${server.id}`, href: showServer(server.id).url },
        { title: 'Provisioning', href: '#' },
    ];

    const handleCancelProvisioning = () => {
        if (confirm('Are you sure you want to cancel provisioning? This will delete the server.')) {
            router.delete(`/servers/${server.id}`);
        }
    };

    // Listen for real-time server updates via Reverb
    useEcho(
        `servers.${server.id}`,
        'ServerUpdated',
        () => {
            router.reload({
                only: ['server'],
                preserveScroll: true,
                preserveState: true,
            });
        }
    );

    // Auto-redirect when provisioning completes
    useEffect(() => {
        if (server.provision_status === 'completed') {
            router.visit(showServer(server.id).url);
        }
    }, [server.provision_status, server.id]);

    // Calculate time since creation
    const getTimeSinceCreation = () => {
        const created = new Date(server.created_at);
        const now = new Date();
        const diffMs = now.getTime() - created.getTime();
        const diffMins = Math.floor(diffMs / 60000);

        if (diffMins < 1) return 'just now';
        if (diffMins === 1) return '1 minute ago';
        if (diffMins < 60) return `${diffMins} minutes ago`;

        const diffHours = Math.floor(diffMins / 60);
        if (diffHours === 1) return '1 hour ago';
        return `${diffHours} hours ago`;
    };

    // Get icon for provision status
    const getProvisionStatusIcon = () => {
        switch (server.provision_status) {
            case 'pending':
                return <Clock className="h-5 w-5 text-amber-600" />;
            case 'installing':
                return <Loader2 className="h-5 w-5 animate-spin text-blue-600" />;
            case 'completed':
                return <CheckCircle2 className="h-5 w-5 text-green-600" />;
            case 'failed':
                return <XCircle className="h-5 w-5 text-red-600" />;
            default:
                return <AlertCircle className="h-5 w-5 text-muted-foreground" />;
        }
    };

    // Get label for provision status
    const getProvisionStatusLabel = () => {
        switch (server.provision_status) {
            case 'pending':
                return 'Pending';
            case 'installing':
                return 'Installing';
            case 'completed':
                return 'Completed';
            case 'failed':
                return 'Failed';
            default:
                return server.provision_status;
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Provisioning — ${server.vanity_name}`} />

            {/* Server Detail Header - Full Width, under breadcrumbs */}
            <ServerDetail server={server} />

            <div className="mx-auto max-w-7xl space-y-8 p-6">
                    {/* Header */}
                    <div>
                        <h1 className="text-3xl font-semibold">We're provisioning your server</h1>
                        <p className="mt-2 text-muted-foreground">
                            This process typically takes about 10 minutes, and completely configures your new server.
                        </p>
                    </div>

                {/* Main Content Grid */}
                <div className="grid gap-8 lg:grid-cols-3">
                    {/* Steps Section - Left */}
                    <div className="lg:col-span-2">
                        <div className="space-y-2 rounded-xl border bg-card p-6">
                            {server.steps.map((step, index) => {
                                const isActive = index === activeStepIndex;
                                const { isCompleted, isPending, isFailed, isInstalling } = step.status;

                                const getStepIcon = () => {
                                    if (isCompleted) {
                                        return <CheckCircle2 className="h-5 w-5 text-green-600" />;
                                    }
                                    if (isFailed) {
                                        return <XCircle className="h-5 w-5 text-red-600" />;
                                    }
                                    if (isInstalling) {
                                        return <Loader2 className="h-5 w-5 animate-spin text-blue-600" />;
                                    }
                                    if (isPending) {
                                        return <Clock className="h-5 w-5 text-amber-600" />;
                                    }
                                    return <Circle className="h-5 w-5 text-muted-foreground/40" />;
                                };

                                return (
                                    <div key={index} className="group">
                                        <div className="flex items-start gap-4">
                                            {/* Icon */}
                                            <div className="flex-shrink-0 pt-0.5">
                                                {getStepIcon()}
                                            </div>

                                            {/* Content */}
                                            <div className="flex-1 space-y-1 pb-4">
                                                <h3
                                                    className={`font-medium ${
                                                        isActive || isCompleted ? 'text-foreground' : 'text-muted-foreground'
                                                    }`}
                                                >
                                                    {step.name}
                                                </h3>

                                                {/* Show description for installing or failed steps */}
                                                {(isInstalling || isFailed) && (
                                                    <p className="text-sm text-muted-foreground">{step.description}</p>
                                                )}
                                            </div>
                                        </div>

                                        {/* Connector line */}
                                        {index < server.steps.length - 1 && (
                                            <div className="ml-2.5 h-8 w-px bg-border" />
                                        )}
                                    </div>
                                );
                            })}
                        </div>
                    </div>

                    {/* Details Section - Right */}
                    <div className="space-y-6">
                        {/* Details Card */}
                        <div className="space-y-3 rounded-xl border bg-card p-6">
                            <h2 className="text-base font-medium">Details</h2>

                            <div className="flex flex-col gap-y-2">
                                <div className="flex items-center gap-x-2">
                                    <dt className="text-sm text-muted-foreground">ID</dt>
                                    <dd className="text-sm font-medium">{server.id}</dd>
                                </div>

                                <div className="flex items-center gap-x-2">
                                    <dt className="text-sm text-muted-foreground">Status</dt>
                                    <dd className="flex items-center gap-x-1.5 text-sm font-medium">
                                        {getProvisionStatusIcon()}
                                        {getProvisionStatusLabel()}
                                    </dd>
                                </div>

                                <div className="flex items-center gap-x-2">
                                    <dt className="text-sm text-muted-foreground">Type</dt>
                                    <dd className="text-sm font-medium">App server</dd>
                                </div>

                                {server.database_type && (
                                    <div className="flex items-center gap-x-2">
                                        <dt className="text-sm text-muted-foreground">Database Type</dt>
                                        <dd className="text-sm font-medium capitalize">{server.database_type}</dd>
                                    </div>
                                )}

                                {server.php_version && (
                                    <div className="flex items-center gap-x-2">
                                        <dt className="text-sm text-muted-foreground">PHP</dt>
                                        <dd className="text-sm font-medium">{server.php_version}</dd>
                                    </div>
                                )}

                                {server.os_version && (
                                    <div className="flex items-center gap-x-2">
                                        <dt className="text-sm text-muted-foreground">Ubuntu</dt>
                                        <dd className="text-sm font-medium">{server.os_version}</dd>
                                    </div>
                                )}

                                <div className="flex items-center gap-x-2">
                                    <dt className="text-sm text-muted-foreground">Created</dt>
                                    <dd className="text-sm font-medium">{getTimeSinceCreation()}</dd>
                                </div>
                            </div>
                        </div>

                        {/* Networking Card */}
                        <div className="space-y-3 rounded-xl border bg-card p-6">
                            <h2 className="text-base font-medium">Networking</h2>

                            <div className="flex flex-col gap-y-2">
                                <div className="flex items-center gap-x-2">
                                    <dt className="text-sm text-muted-foreground">Public IP</dt>
                                    <dd className="text-sm font-medium">{server.public_ip}</dd>
                                </div>

                                {server.private_ip && (
                                    <div className="flex items-center gap-x-2">
                                        <dt className="text-sm text-muted-foreground">Private IP</dt>
                                        <dd className="text-sm font-medium">{server.private_ip}</dd>
                                    </div>
                                )}

                                <div className="flex items-center gap-x-2">
                                    <dt className="text-sm text-muted-foreground">SSH Port</dt>
                                    <dd className="text-sm font-medium">{server.ssh_port}</dd>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Action Buttons */}
                <div className="flex gap-4">
                    <Button
                        variant="outline"
                        onClick={() => setShowInstructions(!showInstructions)}
                    >
                        {showInstructions ? 'Hide instructions' : 'Show instructions'}
                    </Button>
                    <Button
                        variant="destructive"
                        onClick={handleCancelProvisioning}
                    >
                        Cancel provisioning
                    </Button>
                </div>

                {/* Instructions (conditionally shown) */}
                {showInstructions && provision && (
                    <div className="rounded-xl border bg-muted/50 p-6">
                        <h3 className="mb-4 font-semibold">Provisioning Instructions</h3>

                        <div className="space-y-4">
                            <div>
                                <label className="mb-2 block text-sm font-medium text-muted-foreground">
                                    Provisioning Command
                                </label>
                                <pre className="overflow-auto rounded-md bg-background p-3 text-sm">
                                    <code>{provision.command}</code>
                                </pre>
                            </div>

                            <div>
                                <label className="mb-2 block text-sm font-medium text-muted-foreground">
                                    Root Password
                                </label>
                                <pre className="overflow-auto rounded-md bg-background p-3 text-sm">
                                    <code>{provision.root_password}</code>
                                </pre>
                            </div>

                            <p className="text-sm text-muted-foreground">
                                SSH into your server as root and run the command above. Store the credentials safely.
                            </p>
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}