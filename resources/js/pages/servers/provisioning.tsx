import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { edit as editServer, show as showServer } from '@/routes/servers';
import { type BreadcrumbItem, type Server, type ServerPackageEvent } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import copyToClipboard from 'copy-to-clipboard';
import { CheckIcon, CircleIcon, Loader2Icon, RefreshCwIcon, Trash2Icon, XCircleIcon } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

type ProvisionInfo = { command: string; root_password: string } | null;

type StepState = 'pending' | 'active' | 'complete' | 'failed';

export default function Provisioning({
    server,
    provision,
    events,
    webServiceMilestones,
    packageNameLabels,
    statusLabels,
}: {
    server: Server;
    provision?: ProvisionInfo;
    events: ServerPackageEvent[];
    webServiceMilestones: Record<string, string>;
    packageNameLabels: Record<string, string>;
    statusLabels: Record<string, string>;
}) {
    const { props } = usePage<{ name?: string }>();
    const [copied, setCopied] = useState<'cmd' | 'root' | null>(null);
    const [isRetrying, setIsRetrying] = useState(false);
    const [isDestroying, setIsDestroying] = useState(false);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: dashboard().url },
        { title: `Server #${server.id}`, href: showServer(server.id).url },
        { title: 'Provisioning', href: '#' },
    ];

    const steps = useMemo(() => {
        const stages = [];

        // Check if we have any webserver events - this indicates access was successful
        const hasWebserverEvents = events.some(e => e.service_type === 'webserver');
        const hasFailedWebserverEvent = events.some(e => e.service_type === 'webserver' && e.status === 'failed');

        // Step 1: Initial server access
        stages.push({
            key: 'access',
            label: 'Configuring access on remote server',
            state: (() => {
                // If we have webserver events, access was successful
                if (hasWebserverEvents) return 'complete';
                // Otherwise use the original logic
                if (server.provision_status === 'pending') return 'pending';
                if (server.provision_status === 'connecting') return 'active';
                if (server.provision_status === 'failed' && server.connection !== 'connected') return 'failed';
                return 'complete';
            })() as StepState,
        });

        // Step 2: Installing web services
        stages.push({
            key: 'webservice',
            label: 'Installing web server and PHP',
            state: (() => {
                // If we don't have webserver events yet, it's pending
                if (!hasWebserverEvents && server.provision_status !== 'installing') return 'pending';
                // If we have a failed webserver event, this step failed
                if (hasFailedWebserverEvent) return 'failed';
                // Otherwise use original logic
                if (server.provision_status === 'installing') return 'active';
                if (server.provision_status === 'completed') return 'complete';
                if (server.provision_status === 'failed' && server.connection === 'connected') return 'failed';
                return 'pending';
            })() as StepState,
        });

        return stages;
    }, [server.connection, server.provision_status, events]);

    const progressByPackageName = useMemo(() => {
        const groupedProgress = events.reduce(
            (acc, event) => {
                if (!acc[event.service_type]) {
                    acc[event.service_type] = [];
                }
                acc[event.service_type].push(event);
                return acc;
            },
            {} as Record<string, ServerPackageEvent[]>,
        );

        // Get the latest event for each package name with progress calculation
        return Object.entries(groupedProgress).map(([packageName, serviceEvents]) => {
            const latestEvent = serviceEvents[serviceEvents.length - 1];
            const isComplete = serviceEvents.some((e) => e.milestone === 'complete');
            const isActive = server.provision_status === 'installing' && !isComplete;

            return {
                packageName,
                events: serviceEvents,
                latestEvent,
                progress: parseFloat(latestEvent.progress_percentage) || 0,
                isComplete,
                isActive,
                label: packageNameLabels[packageName] || packageName.charAt(0).toUpperCase() + packageName.slice(1),
            };
        });
    }, [events, server.provision_status, packageNameLabels]);


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
        const hasPendingEvents = events.some(event => event.status === 'pending');

        // Check if we have any failed events
        const hasFailedEvents = events.some(event => event.status === 'failed');

        // Check if we have the complete milestone event
        const hasCompleteEvent = events.some(event => event.milestone === 'complete');

        // Check if all events are either success or failed (no pending)
        const allEventsFinished = events.length > 0 && !hasPendingEvents;

        const isInitialProvision = server.provision_status === 'pending';
        const isActiveProvisioning = server.provision_status === 'connecting' || server.provision_status === 'installing';

        // Continue polling if:
        // 1. Initial provisioning state (pending)
        // 2. Active provisioning (connecting or installing)
        // 3. We have pending events that haven't finished
        // 4. We haven't seen the complete event yet and have events
        // 5. OR if no events exist yet (waiting for first event)
        const shouldPoll = isInitialProvision ||
                         isActiveProvisioning ||
                         hasPendingEvents ||
                         (!hasCompleteEvent && !hasFailedEvents && events.length === 0) ||
                         (!hasCompleteEvent && events.length > 0 && !allEventsFinished);

        // Stop polling only if:
        // 1. Provisioning failed AND all events are finished
        // 2. OR we have a complete event
        // 3. OR all events have failed
        if ((server.provision_status === 'failed' && allEventsFinished && hasFailedEvents) || hasCompleteEvent) {
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
                preserveState: true
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
                            <Link href={dashboard().url}>Back to Dashboard</Link>
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
                            <div className="px-4 py-3">
                                <div className="text-sm font-medium tracking-wide text-neutral-600 uppercase dark:text-neutral-300">
                                    Provisioning Progress
                                </div>
                            </div>
                            <Separator />
                            <div className="px-4 py-4">
                                <div className="mb-2 flex items-center gap-2">
                                    {(() => {
                                        const connection = server.connection;
                                        const color =
                                            connection === 'connected'
                                                ? 'green'
                                                : connection === 'failed'
                                                  ? 'red'
                                                  : connection === 'connecting'
                                                    ? 'amber'
                                                    : 'gray';
                                        const dot =
                                            color === 'green'
                                                ? 'bg-green-500'
                                                : color === 'red'
                                                  ? 'bg-red-500'
                                                  : color === 'amber'
                                                    ? 'bg-amber-500'
                                                    : 'bg-gray-500';
                                        const ping =
                                            color === 'green'
                                                ? 'bg-green-400'
                                                : color === 'red'
                                                  ? 'bg-red-400'
                                                  : color === 'amber'
                                                    ? 'bg-amber-400'
                                                    : 'bg-gray-400';
                                        const label =
                                            connection === 'pending'
                                                ? 'Waiting to start'
                                                : connection === 'connecting'
                                                  ? 'Provisioning in progress'
                                                  : connection === 'connected'
                                                    ? 'Successfully provisioned'
                                                    : connection === 'failed'
                                                      ? 'Provisioning failed'
                                                      : connection;
                                        const shouldAnimate = connection === 'connecting';
                                        return (
                                            <span className="inline-flex items-center gap-2 text-xs">
                                                <span className="relative inline-flex h-2 w-2">
                                                    {shouldAnimate && (
                                                        <span
                                                            className={`absolute inline-flex h-full w-full animate-ping rounded-full opacity-60 ${ping}`}
                                                        ></span>
                                                    )}
                                                    <span className={`relative inline-flex h-2 w-2 rounded-full ${dot}`}></span>
                                                </span>
                                                <span className="text-muted-foreground">{label}</span>
                                            </span>
                                        );
                                    })()}
                                </div>
                                <div className="mb-4 text-sm text-muted-foreground">Follow along as we provision your server.</div>
                                <ul className="space-y-3">
                                    {steps.map((s) => (
                                        <li key={s.key} className="flex items-center gap-3">
                                            <span className="inline-flex items-center justify-center">
                                                {s.state === 'complete' && <CheckIcon className="size-4 text-green-600" />}
                                                {s.state === 'active' && <Loader2Icon className="size-4 animate-spin text-primary" />}
                                                {s.state === 'failed' && <XCircleIcon className="size-4 text-red-600" />}
                                                {s.state === 'pending' && <CircleIcon className="size-4 text-muted-foreground/50" />}
                                            </span>
                                            <div
                                                className={
                                                    'text-sm ' +
                                                    (s.state === 'active' ? 'font-medium' : s.state === 'failed' ? 'text-red-600' : 'text-foreground')
                                                }
                                            >
                                                {s.label}
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                                {progressByPackageName.length > 0 && (
                                    <>
                                        <Separator className="my-4" />
                                        <div className="text-xs font-medium text-muted-foreground uppercase">Package Installation Progress</div>
                                        <div className="mt-3 space-y-3">
                                            {progressByPackageName.map((service) => (
                                                <div key={service.packageName} className="space-y-2">
                                                    <div className="flex items-center justify-between">
                                                        <span className="text-sm font-medium">{service.label}</span>
                                                        <span className="text-xs text-muted-foreground">
                                                            {service.progress.toFixed(0)}%
                                                        </span>
                                                    </div>
                                                    <div className="h-2 bg-muted rounded-full overflow-hidden">
                                                        <div
                                                            className={`h-full transition-all duration-300 ${
                                                                service.isComplete
                                                                    ? 'bg-green-500'
                                                                    : service.isActive
                                                                      ? 'bg-blue-500'
                                                                      : 'bg-gray-400'
                                                            }`}
                                                            style={{ width: `${Math.max(service.progress, 0)}%` }}
                                                        />
                                                    </div>
                                                    {service.latestEvent.label && (
                                                        <p className="text-xs text-muted-foreground">
                                                            {service.latestEvent.label}
                                                        </p>
                                                    )}
                                                </div>
                                            ))}
                                        </div>
                                    </>
                                )}
                                {events.length > 0 && (
                                    <>
                                        <Separator className="my-4" />
                                        <div className="text-xs font-medium text-muted-foreground uppercase">Provision Events</div>
                                        <ul className="mt-3 space-y-3 text-sm">
                                            {events.map((event) => {
                                                // Use the label from the event (milestone label) as primary display
                                                const displayLabel =
                                                    event.label || webServiceMilestones[event.milestone] || statusLabels[event.milestone] || event.milestone;

                                                // Determine the state based on status
                                                let state: StepState;
                                                if (event.status === 'success') {
                                                    state = 'complete';
                                                } else if (event.status === 'failed') {
                                                    state = 'failed';
                                                } else {
                                                    // Check if this is the latest pending event (currently being processed)
                                                    const pendingEvents = events.filter(e => e.status === 'pending');
                                                    const isLatest = event === pendingEvents[pendingEvents.length - 1];
                                                    state = isLatest && server.provision_status === 'installing' ? 'active' : 'pending';
                                                }

                                                return (
                                                    <li key={event.id}>
                                                        <div className="flex items-center justify-between">
                                                            <div className="flex items-center gap-3">
                                                                <span className="inline-flex items-center justify-center">
                                                                    {state === 'complete' && <CheckIcon className="size-4 text-green-600" />}
                                                                    {state === 'active' && <Loader2Icon className="size-4 animate-spin text-primary" />}
                                                                    {state === 'failed' && <XCircleIcon className="size-4 text-red-600" />}
                                                                    {state === 'pending' && <CircleIcon className="size-4 text-muted-foreground/50" />}
                                                                </span>
                                                                <div className="flex-1">
                                                                    <span className={
                                                                        state === 'active' ? 'font-medium' :
                                                                        state === 'failed' ? 'text-red-600' :
                                                                        state === 'complete' ? 'text-foreground' :
                                                                        'text-muted-foreground'
                                                                    }>
                                                                        {displayLabel}
                                                                    </span>
                                                                    <span className="ml-2 text-xs text-muted-foreground">
                                                                        ({event.service_type})
                                                                    </span>
                                                                </div>
                                                            </div>
                                                            <div className="flex items-center gap-3 text-xs text-muted-foreground">
                                                                {parseFloat(event.progress_percentage) > 0 && (
                                                                    <span>{parseFloat(event.progress_percentage).toFixed(0)}%</span>
                                                                )}
                                                                {event.created_at && (
                                                                    <span className="whitespace-nowrap">
                                                                        {new Date(event.created_at).toLocaleTimeString()}
                                                                    </span>
                                                                )}
                                                            </div>
                                                        </div>
                                                        {event.error_log && (
                                                            <div className="ml-7 mt-2 rounded-md bg-red-50 dark:bg-red-950/20 border border-red-200 dark:border-red-800 p-2">
                                                                <p className="text-xs text-red-700 dark:text-red-400 font-mono whitespace-pre-wrap break-all">
                                                                    {event.error_log.split('\n')[0]}
                                                                </p>
                                                                {event.error_log.split('\n').length > 1 && (
                                                                    <details className="mt-1">
                                                                        <summary className="cursor-pointer text-xs text-red-600 dark:text-red-500 hover:underline">
                                                                            Show full error ({event.error_log.split('\n').length - 1} more lines)
                                                                        </summary>
                                                                        <pre className="mt-1 text-xs text-red-700 dark:text-red-400 font-mono whitespace-pre-wrap break-all">
                                                                            {event.error_log.split('\n').slice(1).join('\n')}
                                                                        </pre>
                                                                    </details>
                                                                )}
                                                            </div>
                                                        )}
                                                    </li>
                                                );
                                            })}
                                        </ul>
                                    </>
                                )}
                                <Separator className="my-4" />
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
