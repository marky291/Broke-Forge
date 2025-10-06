import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Separator } from '@/components/ui/separator';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { edit as editServer, show as showServer } from '@/routes/servers';
import { type BreadcrumbItem, type Server, type ServerEvent } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import copyToClipboard from 'copy-to-clipboard';
import { CheckIcon, ChevronDownIcon, Loader2Icon, RefreshCwIcon, Trash2Icon, XCircleIcon } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

type ProvisionInfo = { command: string; root_password: string } | null;

type StepState = 'pending' | 'active' | 'complete' | 'failed';

export default function Provisioning({
    server,
    provision,
    events,
    latestProgress = [],
    webServiceMilestones,
    packageNameLabels,
    statusLabels,
}: {
    server: Server;
    provision?: ProvisionInfo;
    events: ServerEvent[];
    latestProgress: ServerEvent[];
    webServiceMilestones: Record<string, string>;
    packageNameLabels: Record<string, string>;
    statusLabels: Record<string, string>;
}) {
    const { props } = usePage<{ name?: string }>();
    const [copied, setCopied] = useState<'cmd' | 'root' | null>(null);
    const [isRetrying, setIsRetrying] = useState(false);
    const [isDestroying, setIsDestroying] = useState(false);
    const [showDetails, setShowDetails] = useState(false);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: dashboard.url() },
        { title: `Server #${server.id}`, href: showServer(server.id).url },
        { title: 'Provisioning', href: '#' },
    ];

    const latestSnapshots = useMemo(
        () =>
            latestProgress.map((progress) => {
                const parsed = Number(progress.progress_percentage ?? 0);
                const progressValue = Number.isFinite(parsed) ? parsed : 0;

                return {
                    ...progress,
                    progressValue,
                    label:
                        packageNameLabels[progress.service_type] ??
                        progress.label ??
                        progress.service_type.charAt(0).toUpperCase() + progress.service_type.slice(1),
                };
            }),
        [latestProgress, packageNameLabels],
    );

    const overallProgress = useMemo(() => {
        const clamp = (value: number): number => Math.min(100, Math.max(0, value));

        if (latestSnapshots.length === 0) {
            if (server.provision_status === 'completed') {
                return 100;
            }

            if (server.provision_status === 'failed') {
                return 0;
            }

            const latestEvent = events.length > 0 ? events[events.length - 1] : null;
            const fallback = latestEvent ? Number(latestEvent.progress_percentage ?? 0) : 0;

            return clamp(Number.isFinite(fallback) ? fallback : 0);
        }

        const total = latestSnapshots.reduce((sum, snapshot) => sum + snapshot.progressValue, 0);
        const average = total / latestSnapshots.length;

        return clamp(Number.isFinite(average) ? average : 0);
    }, [events, latestSnapshots, server.provision_status]);

    const summary = useMemo(() => {
        const reversed = [...events].reverse();
        const failedEvent = reversed.find((event) => event.status === 'failed');
        const pendingEvent = reversed.find((event) => event.status === 'pending');
        const successfulEvent = reversed.find((event) => event.status === 'success');

        if (server.provision_status === 'completed') {
            const referenceEvent = successfulEvent ?? events[events.length - 1];
            return {
                tone: 'success' as const,
                title: 'Provisioning complete',
                description: referenceEvent?.label || 'All services installed successfully.',
            };
        }

        if (failedEvent) {
            return {
                tone: 'error' as const,
                title: 'Provisioning failed',
                description: failedEvent.label || statusLabels[failedEvent.milestone] || 'Review the event log below to resolve the issue.',
            };
        }

        if (pendingEvent) {
            return {
                tone: 'progress' as const,
                title: 'Installation in progress',
                description: pendingEvent.label || statusLabels[pendingEvent.milestone] || 'We are configuring your server.',
            };
        }

        if (events.length === 0) {
            return {
                tone: 'idle' as const,
                title: 'Waiting to start',
                description: 'Run the provisioning command to begin installing services.',
            };
        }

        const latestEvent = events[events.length - 1];

        return {
            tone: server.provision_status === 'failed' ? ('error' as const) : ('progress' as const),
            title: server.provision_status_label,
            description: latestEvent.label || statusLabels[latestEvent.milestone] || 'Provisioning status has been updated.',
        };
    }, [events, server.provision_status, server.provision_status_label, statusLabels]);

    const lastUpdatedAt = useMemo(() => {
        const latestEvent = events.length > 0 ? events[events.length - 1] : null;
        if (!latestEvent?.created_at) {
            return null;
        }

        const timestamp = new Date(latestEvent.created_at);
        return Number.isNaN(timestamp.getTime()) ? null : timestamp;
    }, [events]);

    const connectionMeta = useMemo(() => {
        switch (server.connection) {
            case 'connected':
                return {
                    label: 'Connection established',
                    dotClass: 'bg-emerald-500',
                    animate: false,
                };
            case 'connecting':
                return {
                    label: 'Connecting to server',
                    dotClass: 'bg-amber-500',
                    animate: true,
                };
            case 'failed':
                return {
                    label: 'Connection failed',
                    dotClass: 'bg-destructive',
                    animate: false,
                };
            case 'disconnected':
                return {
                    label: 'Disconnected',
                    dotClass: 'bg-gray-400',
                    animate: false,
                };
            default:
                return {
                    label: 'Waiting for provisioning command',
                    dotClass: 'bg-gray-400',
                    animate: false,
                };
        }
    }, [server.connection]);

    const roundedProgress = Math.round(overallProgress);

    const progressByPackageName = useMemo(() => {
        const groupedProgress = events.reduce(
            (acc, event) => {
                if (!acc[event.service_type]) {
                    acc[event.service_type] = [];
                }
                acc[event.service_type].push(event);
                return acc;
            },
            {} as Record<string, ServerEvent[]>,
        );

        return Object.entries(groupedProgress).map(([packageName, serviceEvents]) => {
            const latestEvent = serviceEvents[serviceEvents.length - 1];
            const progress = Number(latestEvent.progress_percentage ?? 0) || 0;
            const isComplete = serviceEvents.some((e) => e.milestone === 'complete');
            const hasFailed = serviceEvents.some((e) => e.status === 'failed');
            const isActive = !hasFailed && !isComplete && server.provision_status === 'installing';

            return {
                packageName,
                events: serviceEvents,
                latestEvent,
                progress,
                isComplete,
                isActive,
                hasFailed,
                state: hasFailed ? ('failed' as const) : isComplete ? ('complete' as const) : isActive ? ('active' as const) : ('pending' as const),
                label: packageNameLabels[packageName] || packageName.charAt(0).toUpperCase() + packageName.slice(1),
                statusLabel: hasFailed ? 'Failed' : isComplete ? 'Installed' : isActive ? 'Installing' : 'Waiting',
            };
        });
    }, [events, server.provision_status, packageNameLabels]);

    const packageMetrics = useMemo(() => {
        if (progressByPackageName.length === 0) {
            return {
                total: 0,
                completed: 0,
                active: 0,
                failed: 0,
            };
        }

        const completed = progressByPackageName.filter((service) => service.state === 'complete').length;
        const active = progressByPackageName.filter((service) => service.state === 'active').length;
        const failed = progressByPackageName.filter((service) => service.state === 'failed').length;

        return {
            total: progressByPackageName.length,
            completed,
            active,
            failed,
        };
    }, [progressByPackageName]);

    const copy = (text: string, which: 'cmd' | 'root') => {
        const ok = copyToClipboard(text, { format: 'text/plain' });
        if (ok) {
            setCopied(which);
            setTimeout(() => setCopied(null), 1400);
        }
    };

    const handleRetryProvisioning = () => {
        const confirmed = window.confirm('Retry provisioning? This will reset the progress and issue a new root password.');

        if (!confirmed) {
            return;
        }

        setIsRetrying(true);

        router.post(
            `/servers/${server.id}/provision/retry`,
            {},
            {
                preserveScroll: true,
                onFinish: () => setIsRetrying(false),
                onError: () => setIsRetrying(false),
            },
        );
    };
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

    // Auto-refresh server status while provisioning is in progress
    useEffect(() => {
        // Redirect if fully provisioned
        if (server.provision_status === 'completed') {
            router.visit(showServer(server.id).url);
            return;
        }

        // Check if we have any pending events
        const hasPendingEvents = events.some((event) => event.status === 'pending');

        // Check if we have any failed events
        const hasFailedEvents = events.some((event) => event.status === 'failed');

        // Check if all events are either success or failed (no pending)
        const allEventsFinished = events.length > 0 && !hasPendingEvents;

        const isInitialProvision = server.provision_status === 'pending';
        const isActiveProvisioning = server.provision_status === 'connecting' || server.provision_status === 'installing';

        // Continue polling while provisioning is active
        // Stop polling only when:
        // 1. server.provision_status === 'completed' (handled above with redirect)
        // 2. server.provision_status === 'failed' AND no pending events
        const shouldPoll = isInitialProvision || isActiveProvisioning || hasPendingEvents || events.length === 0; // Keep polling if no events yet

        // Stop polling if provisioning failed and all events are finished
        if (server.provision_status === 'failed' && allEventsFinished && hasFailedEvents) {
            return;
        }

        if (!shouldPoll) {
            return;
        }

        // Faster polling for active provisioning, slower for initial
        const intervalMs = isActiveProvisioning ? 1000 : isInitialProvision ? 3000 : 2000;

        const id = window.setInterval(() => {
            router.reload({
                only: ['server', 'events', 'latestProgress', 'provision', 'packageNameLabels', 'statusLabels'],
                preserveScroll: true,
                preserveState: true,
            });
        }, intervalMs);

        return () => window.clearInterval(id);
    }, [server.provision_status, server.id, events]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Server Provisioning - #${server.id}`} />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="mb-2 flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <h1 className="text-2xl font-semibold">Server Provisioning</h1>
                        {(() => {
                            const color = server.provision_status_color;
                            const dotClass =
                                color === 'green'
                                    ? 'bg-green-500'
                                    : color === 'red'
                                      ? 'bg-red-500'
                                      : color === 'blue'
                                        ? 'bg-blue-500'
                                        : color === 'amber'
                                          ? 'bg-amber-500'
                                          : 'bg-gray-500';
                            const pingClass =
                                color === 'green'
                                    ? 'bg-green-400'
                                    : color === 'red'
                                      ? 'bg-red-400'
                                      : color === 'blue'
                                        ? 'bg-blue-400'
                                        : color === 'amber'
                                          ? 'bg-amber-400'
                                          : 'bg-gray-400';
                            const shouldAnimate = server.provision_status === 'connecting' || server.provision_status === 'installing';
                            return (
                                <span className="inline-flex items-center gap-2 text-xs">
                                    <span className="relative inline-flex h-2 w-2">
                                        {shouldAnimate && (
                                            <span
                                                className={`absolute inline-flex h-full w-full animate-ping rounded-full opacity-60 ${pingClass}`}
                                            ></span>
                                        )}
                                        <span className={`relative inline-flex h-2 w-2 rounded-full ${dotClass}`}></span>
                                    </span>
                                    <span className="text-muted-foreground">{server.provision_status_label}</span>
                                </span>
                            );
                        })()}
                    </div>
                    <div className="space-x-2">
                        <Button variant="outline" asChild>
                            <Link href={dashboard.url()}>Back to Dashboard</Link>
                        </Button>
                        {server.provision_status === 'failed' && (
                            <Button variant="outline" onClick={handleRetryProvisioning} disabled={isRetrying}>
                                {isRetrying ? (
                                    <>
                                        <Loader2Icon className="mr-2 h-4 w-4 animate-spin" />
                                        Retrying...
                                    </>
                                ) : (
                                    <>
                                        <RefreshCwIcon className="mr-2 h-4 w-4" />
                                        Retry Provisioning
                                    </>
                                )}
                            </Button>
                        )}
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

                {/* Provision Custom VPS Section - shown only when connection is pending */}
                {server.connection === 'pending' && (
                    <div className="rounded-xl border border-sidebar-border/70 bg-background shadow-sm dark:border-sidebar-border">
                        <div className="px-4 py-3">
                            <div className="flex items-center justify-between">
                                <div className="text-sm font-medium tracking-wide text-neutral-600 uppercase dark:text-neutral-300">
                                    Provision Custom VPS
                                </div>
                                <span className="rounded-full border border-sidebar-border/70 bg-background px-2 py-0.5 text-xs text-muted-foreground dark:border-sidebar-border">
                                    Status: {server.connection || 'pending'}
                                </span>
                            </div>
                        </div>
                        <Separator />
                        <div className="px-4 py-4">
                            <div className="mb-4 text-sm text-muted-foreground">
                                Almost there! SSH into your server as root and run the command below. This will begin provisioning and configure the
                                server so it can be managed by {props?.name ?? 'App'}.
                            </div>

                            {/* Command */}
                            <div className="mb-4 space-y-2">
                                <div className="text-xs font-medium text-muted-foreground uppercase">Provisioning Command</div>
                                <div
                                    className="relative cursor-pointer"
                                    role="button"
                                    tabIndex={0}
                                    aria-label="Copy provisioning command"
                                    onClick={() => provision?.command && copy(provision.command, 'cmd')}
                                    onKeyDown={(e) => {
                                        if ((e.key === 'Enter' || e.key === ' ') && provision?.command) {
                                            e.preventDefault();
                                            copy(provision.command, 'cmd');
                                        }
                                    }}
                                >
                                    <pre className="max-h-56 w-full overflow-auto rounded-md bg-muted p-3 font-mono text-sm break-all whitespace-pre-wrap">
                                        <code className="break-all whitespace-pre-wrap">{provision?.command}</code>
                                    </pre>
                                    <div className="absolute top-2 right-2 rounded border border-sidebar-border/70 bg-background/80 px-2 py-0.5 text-xs text-muted-foreground backdrop-blur dark:border-sidebar-border">
                                        {copied === 'cmd' ? 'Copied' : 'Click to copy'}
                                    </div>
                                </div>
                            </div>

                            <Separator className="my-4" />

                            {/* Credentials */}
                            <div className="space-y-3">
                                <div className="text-xs font-medium text-muted-foreground uppercase">Server Credentials</div>
                                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                    <div className="space-y-1">
                                        <div className="text-xs text-muted-foreground">Root Password</div>
                                        <div
                                            className="relative cursor-pointer"
                                            role="button"
                                            tabIndex={0}
                                            aria-label="Copy root password"
                                            onClick={() => provision?.root_password && copy(provision.root_password, 'root')}
                                            onKeyDown={(e) => {
                                                if ((e.key === 'Enter' || e.key === ' ') && provision?.root_password) {
                                                    e.preventDefault();
                                                    copy(provision.root_password, 'root');
                                                }
                                            }}
                                        >
                                            <code className="block rounded-md bg-muted px-2 py-1 text-sm break-all">{provision?.root_password}</code>
                                            {provision?.root_password && (
                                                <div className="absolute top-1 right-1 rounded border border-sidebar-border/70 bg-background/80 px-2 py-0.5 text-xs text-muted-foreground backdrop-blur dark:border-sidebar-border">
                                                    {copied === 'root' ? 'Copied' : 'Click to copy'}
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                </div>
                                <div className="text-xs text-muted-foreground">
                                    Please store these credentials safely; they will not be shown again.
                                </div>
                            </div>
                        </div>
                    </div>
                )}

                <div className="grid gap-4">
                    <div>
                        <div className="rounded-xl border border-sidebar-border/70 bg-background shadow-sm dark:border-sidebar-border">
                            <div className="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                                <div className="text-sm font-medium tracking-wide text-neutral-600 uppercase dark:text-neutral-300">
                                    Provisioning Progress
                                </div>
                                <Badge
                                    variant={summary.tone === 'error' ? 'destructive' : 'secondary'}
                                    className={
                                        summary.tone === 'success'
                                            ? 'border-emerald-500/20 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'
                                            : summary.tone === 'error'
                                              ? ''
                                              : 'border-blue-500/20 bg-blue-500/10 text-blue-600 dark:text-blue-300'
                                    }
                                >
                                    {server.provision_status_label}
                                </Badge>
                            </div>
                            <Separator />
                            <div className="space-y-6 px-4 py-5">
                                <div className="space-y-3">
                                    <div className="flex items-start justify-between gap-3">
                                        <div>
                                            <p className="text-lg font-semibold">{summary.title}</p>
                                            <p className="text-sm text-muted-foreground">{summary.description}</p>
                                        </div>
                                        <span className="text-sm font-semibold text-muted-foreground">{roundedProgress}%</span>
                                    </div>
                                    <div className="h-3 w-full overflow-hidden rounded-full bg-muted">
                                        <div
                                            className={`h-full rounded-full transition-all duration-500 ease-out ${
                                                summary.tone === 'success'
                                                    ? 'bg-emerald-500'
                                                    : summary.tone === 'error'
                                                      ? 'bg-destructive'
                                                      : 'bg-primary'
                                            }`}
                                            style={{ width: `${roundedProgress}%` }}
                                        />
                                    </div>
                                    {packageMetrics.total > 0 && (
                                        <div className="flex flex-wrap gap-2 text-xs">
                                            <Badge variant="outline" className="border-transparent bg-muted/70 text-foreground">
                                                {packageMetrics.completed} / {packageMetrics.total} services installed
                                            </Badge>
                                            {packageMetrics.active > 0 && (
                                                <Badge
                                                    variant="outline"
                                                    className="border-transparent bg-blue-500/10 text-blue-600 dark:text-blue-300"
                                                >
                                                    {packageMetrics.active} installing
                                                </Badge>
                                            )}
                                            {packageMetrics.failed > 0 && <Badge variant="destructive">{packageMetrics.failed} failed</Badge>}
                                        </div>
                                    )}
                                </div>
                                <div className="flex flex-wrap items-center gap-3 text-xs text-muted-foreground">
                                    <span className={`inline-flex items-center gap-2 ${summary.tone === 'error' ? 'text-destructive' : ''}`}>
                                        <span
                                            className={`h-2.5 w-2.5 rounded-full ${connectionMeta.dotClass} ${connectionMeta.animate ? 'animate-pulse' : ''}`}
                                            aria-hidden="true"
                                        ></span>
                                        {connectionMeta.label}
                                    </span>
                                    {lastUpdatedAt && <span>Last update {lastUpdatedAt.toLocaleTimeString()}</span>}
                                    <span className="ml-auto text-[0.7rem] tracking-wide text-muted-foreground/70 uppercase">
                                        {server.public_ip}:{server.ssh_port}
                                    </span>
                                </div>
                                {progressByPackageName.length > 0 && (
                                    <div className="grid gap-3 pt-2 sm:grid-cols-2 xl:grid-cols-3">
                                        {progressByPackageName.map((service) => {
                                            const stateStyles =
                                                service.state === 'failed'
                                                    ? 'border-destructive/40 bg-destructive/10 text-destructive'
                                                    : service.state === 'complete'
                                                      ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'
                                                      : 'border-blue-500/30 bg-blue-500/10 text-blue-600 dark:text-blue-300';
                                            const barColor =
                                                service.state === 'failed'
                                                    ? 'bg-destructive'
                                                    : service.state === 'complete'
                                                      ? 'bg-emerald-500'
                                                      : 'bg-primary';

                                            return (
                                                <div
                                                    key={service.packageName}
                                                    className="rounded-xl border border-border/60 bg-muted/30 p-4 transition hover:border-border/80 hover:shadow-sm dark:border-border/40"
                                                >
                                                    <div className="flex items-start justify-between gap-2">
                                                        <div>
                                                            <p className="text-sm font-semibold text-foreground">{service.label}</p>
                                                            {service.latestEvent.label && (
                                                                <p className="text-xs text-muted-foreground">{service.latestEvent.label}</p>
                                                            )}
                                                        </div>
                                                        <span
                                                            className={`rounded-full px-2 py-0.5 text-[0.65rem] font-semibold tracking-wide uppercase ${stateStyles}`}
                                                        >
                                                            {service.statusLabel}
                                                        </span>
                                                    </div>
                                                    <div className="mt-4 h-2 overflow-hidden rounded-full bg-muted">
                                                        <div
                                                            className={`h-full transition-all duration-300 ${barColor}`}
                                                            style={{ width: `${Math.min(100, Math.max(0, service.progress))}%` }}
                                                        />
                                                    </div>
                                                    <div className="mt-3 flex items-center justify-between text-xs text-muted-foreground">
                                                        <span>{Math.round(service.progress)}%</span>
                                                        {service.latestEvent.created_at && (
                                                            <span className="whitespace-nowrap">
                                                                {new Date(service.latestEvent.created_at).toLocaleTimeString()}
                                                            </span>
                                                        )}
                                                    </div>
                                                </div>
                                            );
                                        })}
                                    </div>
                                )}
                                <Collapsible open={showDetails} onOpenChange={setShowDetails}>
                                    <CollapsibleTrigger asChild>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            className="flex items-center gap-2 px-0 text-sm text-primary hover:text-primary"
                                        >
                                            <span>{showDetails ? 'Hide installation activity' : 'Show installation activity'}</span>
                                            <ChevronDownIcon className={`size-4 transition-transform ${showDetails ? 'rotate-180' : ''}`} />
                                        </Button>
                                    </CollapsibleTrigger>
                                    <CollapsibleContent className="space-y-6 pt-4">
                                        {events.length > 0 && (
                                            <div className="space-y-3">
                                                <div className="text-xs font-medium text-muted-foreground uppercase">Provision Events</div>
                                                <ul className="space-y-4 text-sm">
                                                    {events.map((event) => {
                                                        const displayLabel =
                                                            event.label ||
                                                            webServiceMilestones[event.milestone] ||
                                                            statusLabels[event.milestone] ||
                                                            event.milestone;

                                                        let state: StepState;
                                                        if (event.status === 'success') {
                                                            state = 'complete';
                                                        } else if (event.status === 'failed') {
                                                            state = 'failed';
                                                        } else {
                                                            const pendingEvents = events.filter((e) => e.status === 'pending');
                                                            const latestPendingEvent = pendingEvents[pendingEvents.length - 1];
                                                            if (latestPendingEvent && latestPendingEvent.id === event.id) {
                                                                state = 'active';
                                                            } else {
                                                                state = 'pending';
                                                            }
                                                        }
                                                        const dotColor =
                                                            state === 'failed'
                                                                ? 'bg-destructive'
                                                                : state === 'complete'
                                                                  ? 'bg-emerald-500'
                                                                  : state === 'active'
                                                                    ? 'bg-primary'
                                                                    : 'bg-muted-foreground/60';
                                                        const isLastEvent = events[events.length - 1]?.id === event.id;

                                                        return (
                                                            <li key={event.id} className="relative pl-7">
                                                                <span
                                                                    className={`absolute top-1.5 left-0 flex h-3 w-3 items-center justify-center rounded-full ${dotColor} ring-4 ring-background`}
                                                                >
                                                                    {state === 'complete' && <CheckIcon className="size-2.5 text-white" />}
                                                                    {state === 'failed' && <XCircleIcon className="size-2.5 text-white" />}
                                                                    {state === 'active' && (
                                                                        <Loader2Icon className="size-2.5 animate-spin text-white" />
                                                                    )}
                                                                </span>
                                                                <div className="pb-4">
                                                                    <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                                                        <div className="flex flex-col gap-1">
                                                                            <span className="font-medium text-foreground">{displayLabel}</span>
                                                                            <span className="text-xs text-muted-foreground">
                                                                                {event.service_type}
                                                                            </span>
                                                                        </div>
                                                                        <div className="flex items-center gap-3 text-xs text-muted-foreground">
                                                                            {parseFloat(String(event.progress_percentage)) > 0 && (
                                                                                <span>
                                                                                    {parseFloat(String(event.progress_percentage)).toFixed(0)}%
                                                                                </span>
                                                                            )}
                                                                            {event.created_at && (
                                                                                <span className="whitespace-nowrap">
                                                                                    {new Date(event.created_at).toLocaleTimeString()}
                                                                                </span>
                                                                            )}
                                                                        </div>
                                                                    </div>
                                                                    {event.error_log && (
                                                                        <div className="mt-2 rounded-md border border-red-200 bg-red-50 p-2 dark:border-red-800 dark:bg-red-950/20">
                                                                            <p className="font-mono text-xs break-all whitespace-pre-wrap text-red-700 dark:text-red-400">
                                                                                {event.error_log.split('\n')[0]}
                                                                            </p>
                                                                            {event.error_log.split('\n').length > 1 && (
                                                                                <details className="mt-1">
                                                                                    <summary className="cursor-pointer text-xs text-red-600 hover:underline dark:text-red-500">
                                                                                        Show full error ({event.error_log.split('\n').length - 1} more
                                                                                        lines)
                                                                                    </summary>
                                                                                    <pre className="mt-1 font-mono text-xs break-all whitespace-pre-wrap text-red-700 dark:text-red-400">
                                                                                        {event.error_log.split('\n').slice(1).join('\n')}
                                                                                    </pre>
                                                                                </details>
                                                                            )}
                                                                        </div>
                                                                    )}
                                                                </div>
                                                                {!isLastEvent && (
                                                                    <span className="absolute top-4 left-[5px] block h-full w-px bg-border/60"></span>
                                                                )}
                                                            </li>
                                                        );
                                                    })}
                                                </ul>
                                            </div>
                                        )}
                                    </CollapsibleContent>
                                </Collapsible>
                                <Separator />
                                <div className="grid grid-cols-2 gap-4 text-sm">
                                    <div>
                                        <div className="text-muted-foreground">Name</div>
                                        <div className="font-medium">{server.vanity_name}</div>
                                    </div>
                                    <div>
                                        <div className="text-muted-foreground">IP Address</div>
                                        <div className="font-medium">
                                            {server.public_ip}:{server.ssh_port}
                                        </div>
                                    </div>
                                    <div>
                                        <div className="text-muted-foreground">Private IP</div>
                                        <div className="font-medium">{server.private_ip ?? '-'}</div>
                                    </div>
                                    <div>
                                        <div className="text-muted-foreground">Created</div>
                                        <div className="font-medium">{new Date(server.created_at).toLocaleString()}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
