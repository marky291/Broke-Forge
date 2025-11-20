import { FrameworkIcon } from '@/components/framework-icon';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { show as showServer } from '@/routes/servers';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { useEcho } from '@laravel/echo-react';
import { CheckCircle2, Clock, Loader2, XCircle } from 'lucide-react';
import { useEffect } from 'react';

interface InstallationStep {
    step: number;
    name: string;
    description: string;
    status: {
        isCompleted: boolean;
        isPending: boolean;
        isFailed: boolean;
        isInstalling: boolean;
    };
}

interface Site {
    id: number;
    domain: string;
    status: string;
    framework: string;
    created_at: string;
    updated_at: string;
    steps: InstallationStep[];
}

interface Server {
    id: number;
    vanity_name: string;
    public_ip: string;
}

interface InstallingPageProps {
    server: Server;
    site: Site;
}

export default function InstallingPage({ server, site }: InstallingPageProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: dashboard.url() },
        { title: server.vanity_name || `Server #${server.id}`, href: showServer(server.id).url },
        { title: 'Installing Site', href: '#' },
    ];

    // Listen for real-time site updates via Reverb
    useEcho(`sites.${site.id}`, 'ServerSiteUpdated', () => {
        router.reload({
            only: ['site'],
            preserveScroll: true,
            preserveState: true,
        });
    });

    // Auto-redirect only when installation completes successfully
    // Keep user on page when failed so they can see which step failed
    useEffect(() => {
        if (site.status === 'active') {
            router.visit(showServer(server.id).url);
        }
    }, [site.status, server.id]);

    // Get icon for installation status
    const getStatusIcon = () => {
        switch (site.status) {
            case 'pending':
                return <Clock className="h-5 w-5 text-amber-600" />;
            case 'installing':
                return <Loader2 className="h-5 w-5 animate-spin text-blue-600" />;
            case 'active':
                return <CheckCircle2 className="h-5 w-5 text-green-600" />;
            case 'failed':
                return <XCircle className="h-5 w-5 text-red-600" />;
            default:
                return <Clock className="h-5 w-5 text-muted-foreground" />;
        }
    };

    // Get label for installation status
    const getStatusLabel = () => {
        switch (site.status) {
            case 'pending':
                return 'Pending';
            case 'installing':
                return 'Installing';
            case 'active':
                return 'Completed';
            case 'failed':
                return 'Failed';
            default:
                return site.status;
        }
    };

    // Calculate time since creation
    const getTimeSinceCreation = () => {
        const created = new Date(site.created_at);
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

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Installing ${site.domain} — ${server.vanity_name}`} />

            <div className="mx-auto max-w-7xl space-y-8 p-6">
                {/* Header */}
                <div>
                    <h1 className="text-3xl font-semibold">We're installing your site</h1>
                    <p className="mt-2 text-muted-foreground">
                        This process typically takes a few minutes, and completely configures {site.domain} on your server.
                    </p>
                </div>

                {/* Action Buttons - Show at top when failed */}
                {site.status === 'failed' && (
                    <div className="space-y-4">
                        <div className="rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-800 dark:bg-red-950">
                            <p className="text-sm text-red-800 dark:text-red-200">
                                Installation failed. You can retry the installation or delete the site to start over.
                            </p>
                        </div>
                        <div className="flex gap-4">
                            <Button
                                onClick={() => {
                                    router.post(`/servers/${server.id}/sites/${site.id}/retry-installation`);
                                }}
                            >
                                Retry Installation
                            </Button>
                            <Button
                                variant="destructive"
                                onClick={() => {
                                    if (confirm('Are you sure you want to delete this failed site? This will clean up any partial installation.')) {
                                        router.delete(`/servers/${server.id}/sites/${site.id}`);
                                    }
                                }}
                            >
                                Delete Failed Site
                            </Button>
                            <Button
                                variant="outline"
                                onClick={() => {
                                    router.visit(showServer(server.id).url);
                                }}
                            >
                                Return to Server
                            </Button>
                        </div>
                    </div>
                )}

                {/* Main Content Grid */}
                <div className="grid gap-8 lg:grid-cols-3">
                    {/* Steps Section - Left */}
                    <div className="lg:col-span-2">
                        <div className="space-y-2 rounded-xl border bg-card p-6">
                            {site.steps.map((step, index) => {
                                const { isCompleted, isPending, isFailed, isInstalling } = step.status;

                                const getStepIcon = () => {
                                    if (isCompleted) {
                                        return <CheckCircle2 className="h-5 w-5 text-green-600" />;
                                    }
                                    if (isFailed) {
                                        return <XCircle className="h-5 w-5 text-red-600" />;
                                    }
                                    // If site failed and step was installing, show as failed
                                    if (isInstalling && site.status === 'failed') {
                                        return <XCircle className="h-5 w-5 text-red-600" />;
                                    }
                                    if (isInstalling) {
                                        return <Loader2 className="h-5 w-5 animate-spin text-blue-600" />;
                                    }
                                    if (isPending) {
                                        return <Clock className="h-5 w-5 text-amber-600" />;
                                    }
                                    return <Clock className="h-5 w-5 text-muted-foreground/40" />;
                                };

                                return (
                                    <div key={step.step} className="group">
                                        <div className="flex items-start gap-4">
                                            {/* Icon */}
                                            <div className="flex-shrink-0 pt-0.5">{getStepIcon()}</div>

                                            {/* Content */}
                                            <div className="flex-1 space-y-1 pb-4">
                                                <h3
                                                    className={`font-medium ${isCompleted || isInstalling ? 'text-foreground' : 'text-muted-foreground'}`}
                                                >
                                                    {step.name}
                                                </h3>

                                                {/* Show description for installing or failed steps */}
                                                {(isInstalling || isFailed) && <p className="text-sm text-muted-foreground">{step.description}</p>}
                                            </div>
                                        </div>

                                        {/* Connector line */}
                                        {index < site.steps.length - 1 && <div className="ml-2.5 h-8 w-px bg-border" />}
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
                                    <dt className="text-sm text-muted-foreground">Domain</dt>
                                    <dd className="text-sm font-medium">{site.domain}</dd>
                                </div>

                                <div className="flex items-center gap-x-2">
                                    <dt className="text-sm text-muted-foreground">Status</dt>
                                    <dd className="flex items-center gap-x-1.5 text-sm font-medium">
                                        {getStatusIcon()}
                                        {getStatusLabel()}
                                    </dd>
                                </div>

                                <div className="flex items-center gap-x-2">
                                    <dt className="text-sm text-muted-foreground">Framework</dt>
                                    <dd className="flex items-center gap-x-1.5 text-sm font-medium">
                                        <FrameworkIcon framework={site.framework} className="h-4 w-4" />
                                        <span className="capitalize">{site.framework.replace('-', ' ')}</span>
                                    </dd>
                                </div>

                                <div className="flex items-center gap-x-2">
                                    <dt className="text-sm text-muted-foreground">Server</dt>
                                    <dd className="text-sm font-medium">{server.vanity_name || server.public_ip}</dd>
                                </div>

                                <div className="flex items-center gap-x-2">
                                    <dt className="text-sm text-muted-foreground">Started</dt>
                                    <dd className="text-sm font-medium">{getTimeSinceCreation()}</dd>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
