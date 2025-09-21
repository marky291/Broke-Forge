import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { edit as editServer, show as showServer } from '@/routes/servers';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import copyToClipboard from 'copy-to-clipboard';
import { CheckIcon, CircleIcon, Loader2Icon, RefreshCwIcon, Trash2Icon, XCircleIcon } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

type Server = {
    id: number;
    vanity_name: string;
    public_ip: string;
    ssh_port: number;
    private_ip?: string | null;
    connection: 'pending' | 'connecting' | 'connected' | 'failed' | 'disconnected';
    server_type?: string | null;
    provision_status: 'pending' | 'connecting' | 'installing' | 'completed' | 'failed';
    provision_status_label: string;
    provision_status_color: string;
    created_at: string;
    updated_at: string;
};
type ProvisionInfo = { command: string; root_password: string } | null;
type ProvisionEvent = {
    id: number | string;
    status: string;
    label?: string | null;
    step?: number | null;
    total_steps?: number | null;
    occurred_at?: string | null;
};

const WEB_SERVICE_SEQUENCE = [
    'prepare_system',
    'setup_repository',
    'remove_conflicts',
    'install_software',
    'enable_services',
    'configure_firewall',
    'setup_default_site',
    'set_permissions',
    'configure_nginx',
    'verify_install',
    'complete',
] as const;

const STATUS_LABELS: Record<string, string> = {
    pending: 'Pending',
    failed: 'Provisioning failed',
    completed: 'Setup complete',
};

type StepState = 'pending' | 'active' | 'complete' | 'failed';

export default function Provisioning({
    server,
    provision,
    events,
    webServiceMilestones,
}: {
    server: Server;
    provision?: ProvisionInfo;
    events: ProvisionEvent[];
    webServiceMilestones: Record<string, string>;
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

        // Step 1: Initial server access
        stages.push({
            key: 'access',
            label: 'Configuring access on remote server',
            state: (() => {
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
                if (server.provision_status === 'installing') return 'active';
                if (server.provision_status === 'completed') return 'complete';
                if (server.provision_status === 'failed' && server.connection === 'connected') return 'failed';
                return 'pending';
            })() as StepState,
        });

        return stages;
    }, [server.connection, server.provision_status]);

    const webProvisionSteps = useMemo(() => {
        // Show web provision steps if we have events or if we're installing
        const shouldShow = events.length > 0 || server.provision_status === 'installing';
        if (!shouldShow) {
            return [] as Array<{ status: (typeof WEB_SERVICE_SEQUENCE)[number]; label: string; state: StepState }>;
        }

        const completed = new Set(events.map((event) => event.status));
        const activeIndex = (() => {
            const firstPending = WEB_SERVICE_SEQUENCE.findIndex((status) => !completed.has(status));
            return firstPending === -1 ? WEB_SERVICE_SEQUENCE.length : firstPending;
        })();

        return WEB_SERVICE_SEQUENCE.map((status, index) => {
            let state: StepState = 'pending';

            if (index < activeIndex || completed.has(status)) {
                state = 'complete';
            } else if (index === activeIndex) {
                state = 'active';
            }

            return {
                status,
                label: webServiceMilestones[status] ?? STATUS_LABELS[status] ?? status,
                state,
            };
        });
    }, [events, server.provision_status, webServiceMilestones]);

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

        // Stop polling once provisioning has definitively failed
        if (server.provision_status === 'failed') {
            return;
        }

        // Check if we have the complete milestone event
        const hasCompleteEvent = events.some(event => event.status === 'complete');

        const isInitialProvision = server.provision_status === 'pending';
        const isActiveProvisioning = server.provision_status === 'connecting' || server.provision_status === 'installing';

        // Continue polling if we haven't seen the complete event yet or if provisioning is active
        const shouldPoll = isInitialProvision || isActiveProvisioning || (!hasCompleteEvent && events.length > 0);

        if (!shouldPoll) {
            return;
        }

        const intervalMs = isInitialProvision ? 3000 : 1000;
        const id = window.setInterval(() => {
            router.reload({ only: ['server', 'events', 'provision'] });
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
                                {webProvisionSteps.length > 0 && (
                                    <>
                                        <Separator className="my-4" />
                                        <div className="text-xs font-medium text-muted-foreground uppercase">Upcoming Web Service Steps</div>
                                        <ul className="mt-3 space-y-2 text-sm">
                                            {webProvisionSteps.map((step) => (
                                                <li key={step.status} className="flex items-center gap-3">
                                                    <span className="inline-flex items-center justify-center">
                                                        {step.state === 'complete' && <CheckIcon className="size-4 text-green-600" />}
                                                        {step.state === 'active' && <Loader2Icon className="size-4 animate-spin text-primary" />}
                                                        {step.state === 'pending' && <CircleIcon className="size-4 text-muted-foreground/50" />}
                                                    </span>
                                                    <span className={step.state === 'active' ? 'font-medium' : 'text-muted-foreground'}>
                                                        {step.label}
                                                    </span>
                                                </li>
                                            ))}
                                        </ul>
                                    </>
                                )}
                                {events.length > 0 && (
                                    <>
                                        <Separator className="my-4" />
                                        <div className="text-xs font-medium text-muted-foreground uppercase">Web Service Installation Progress</div>
                                        <ul className="mt-3 space-y-2 text-sm">
                                            {events.map((event) => {
                                                // Use the label from the event (milestone label) as primary display
                                                const displayLabel =
                                                    event.label || webServiceMilestones[event.status] || STATUS_LABELS[event.status] || event.status;
                                                const stepInfo =
                                                    event.step && event.total_steps ? `Step ${event.step} of ${event.total_steps}` : null;
                                                const timestamp = event.occurred_at ? new Date(event.occurred_at).toLocaleTimeString() : '';
                                                const tone =
                                                    event.status === 'failed'
                                                        ? 'bg-red-500'
                                                        : event.status === 'complete' || event.status === 'completed'
                                                          ? 'bg-emerald-500'
                                                          : 'bg-primary';

                                                return (
                                                    <li key={event.id} className="flex items-center justify-between gap-4">
                                                        <div className="flex min-w-0 items-center gap-3">
                                                            <span className={`h-2.5 w-2.5 rounded-full ${tone}`}></span>
                                                            <div className="min-w-0 flex-1 truncate">
                                                                <div className="truncate font-medium">{displayLabel}</div>
                                                                {stepInfo && <div className="truncate text-xs text-muted-foreground">{stepInfo}</div>}
                                                            </div>
                                                        </div>
                                                        {timestamp && (
                                                            <span className="text-xs whitespace-nowrap text-muted-foreground">{timestamp}</span>
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
