import { CardContainerAddButton } from '@/components/card-container-add-button';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { CardContainer } from '@/components/ui/card-container';
import { CardFormModal } from '@/components/ui/card-form-modal';
import { CardTable, type CardTableColumn } from '@/components/ui/card-table';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { PageHeader } from '@/components/ui/page-header';
import ServerLayout from '@/layouts/server/layout';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { useEcho } from '@laravel/echo-react';
import { AlertCircle, CheckCircle2, Clock, Loader2, Shield, Trash2 } from 'lucide-react';
import { useState } from 'react';

type Server = {
    id: number;
    vanity_name: string;
    provider?: string;
    public_ip: string;
    private_ip?: string | null;
    ssh_port: number;
    connection: string;
    monitoring_status?: string;
    provision_status?: string;
    created_at: string;
    updated_at: string;
    isFirewallInstalled: boolean;
    firewallStatus: string;
    rules: FirewallRule[];
    recentEvents: FirewallEvent[];
    latestMetrics?: any;
};

type FirewallRule = {
    id?: number;
    name: string;
    port: string;
    from_ip_address?: string | null;
    rule_type: string;
    status?: string;
    created_at?: string;
};

type FirewallEvent = {
    id: number;
    milestone: string;
    status: string;
    current_step: number;
    total_steps: number;
    details?: any;
    created_at: string;
};

interface FirewallProps {
    server: Server;
}

export default function Firewall({ server }: FirewallProps) {
    const [showAddRuleDialog, setShowAddRuleDialog] = useState(false);
    const [isDeletingRule, setIsDeletingRule] = useState<number | null>(null);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: dashboard.url() },
        { title: `Server #${server.id}`, href: `/servers/${server.id}` },
        { title: 'Firewall', href: '#' },
    ];

    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        port: '',
        from_ip_address: '',
        rule_type: 'allow',
    });

    // Listen for real-time server updates via Reverb
    useEcho(`servers.${server.id}`, 'ServerUpdated', () => {
        router.reload({
            only: ['server'],
            preserveScroll: true,
            preserveState: true,
        });
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        // Validate required fields (only name is required)
        if (!data.name) {
            return;
        }

        // Submit to backend and let it handle the creation
        post(`/servers/${server.id}/firewall`, {
            preserveScroll: true,
            preserveState: true, // Keep form state to show validation errors
            onSuccess: () => {
                reset();
                setShowAddRuleDialog(false);
                // The backend will return with the new rule in 'pending' status
                // and polling will automatically start to track status updates
            },
            onError: () => {
                // Keep the form data on error so user can fix it
                // Errors will be automatically displayed via the errors object
            },
        });
    };

    const handleDeleteRule = (ruleId: number, index: number) => {
        if (window.confirm('Are you sure you want to delete this firewall rule?')) {
            setIsDeletingRule(index);
            router.delete(`/servers/${server.id}/firewall/${ruleId}`, {
                onFinish: () => setIsDeletingRule(null),
            });
        }
    };

    const getActionBadge = (action: string) => {
        if (action === 'allow') {
            return <Badge className="bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">Allow</Badge>;
        }
        return <Badge className="bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">Deny</Badge>;
    };

    const getStatusBadge = (status?: string) => {
        if (status === 'pending') {
            return (
                <Badge className="bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200">
                    <Clock className="mr-1 size-3" />
                    Pending
                </Badge>
            );
        }
        if (status === 'installing') {
            return (
                <Badge className="bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200">
                    <Loader2 className="mr-1 size-3 animate-spin" />
                    Installing
                </Badge>
            );
        }
        if (status === 'active') {
            return (
                <Badge className="bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                    <CheckCircle2 className="mr-1 size-3" />
                    Active
                </Badge>
            );
        }
        if (status === 'failed') {
            return (
                <Badge className="bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                    <AlertCircle className="mr-1 size-3" />
                    Failed
                </Badge>
            );
        }
        if (status === 'removing') {
            return (
                <Badge className="bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200">
                    <Loader2 className="mr-1 size-3 animate-spin" />
                    Removing
                </Badge>
            );
        }
        return null;
    };

    const columns: CardTableColumn<FirewallRule>[] = [
        {
            header: 'Name',
            accessor: (rule) => <span className="text-sm">{rule.name}</span>,
        },
        {
            header: 'Port',
            accessor: (rule) => <span className="font-mono text-sm">{rule.port || 'All'}</span>,
        },
        {
            header: 'Protocol',
            accessor: () => <span className="text-sm">TCP/UDP</span>,
        },
        {
            header: 'Source',
            accessor: (rule) => <span className="font-mono text-sm">{rule.from_ip_address || 'Any'}</span>,
        },
        {
            header: 'Action',
            accessor: (rule) => getActionBadge(rule.rule_type),
        },
        {
            header: 'Status',
            accessor: (rule) => getStatusBadge(rule.status),
        },
        {
            header: 'Actions',
            align: 'right',
            cell: (rule, index) => (
                <>
                    {(!rule.status || rule.status === 'active' || rule.status === 'failed') && rule.id && (
                        <Button variant="ghost" size="sm" onClick={() => handleDeleteRule(rule.id!, index)} disabled={isDeletingRule === index}>
                            {isDeletingRule === index ? <Loader2 className="size-4 animate-spin" /> : <Trash2 className="size-4 text-red-500" />}
                        </Button>
                    )}
                </>
            ),
        },
    ];

    if (!server.isFirewallInstalled) {
        return (
            <ServerLayout server={server} breadcrumbs={breadcrumbs}>
                <Head title={`${server.vanity_name} - Firewall`} />

                <div className="space-y-6">
                    <div>
                        <h1 className="text-2xl font-semibold">Firewall</h1>
                        <p className="mt-1 text-sm text-muted-foreground">Manage firewall rules for your server</p>
                    </div>

                    <Alert className="border-amber-200 bg-amber-50 dark:border-amber-800 dark:bg-amber-950">
                        <AlertCircle className="size-4" />
                        <AlertDescription>
                            Firewall (UFW) is not installed on this server. Please install UFW through the server provisioning process first.
                        </AlertDescription>
                    </Alert>
                </div>
            </ServerLayout>
        );
    }

    return (
        <ServerLayout server={server} breadcrumbs={breadcrumbs}>
            <Head title={`${server.vanity_name} - Firewall`} />

            <PageHeader title="Firewall" description="Configure and manage firewall rules to control network traffic to your server.">
                {/* Firewall Rules */}
                <CardContainer
                    title="Firewall Rules"
                    icon={
                        <svg width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path
                                d="M6 1L10.5 2.5V5.5C10.5 8 8.5 10 6 11C3.5 10 1.5 8 1.5 5.5V2.5L6 1Z"
                                stroke="currentColor"
                                strokeLinecap="round"
                                strokeLinejoin="round"
                            />
                            <path d="M6 4.5V7.5M6 8.5V9" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" />
                        </svg>
                    }
                    action={<CardContainerAddButton label="Add Rule" onClick={() => setShowAddRuleDialog(true)} aria-label="Add Firewall Rule" />}
                >
                    <CardTable
                        columns={columns}
                        data={server.rules}
                        getRowKey={(rule) => rule.id || Math.random()}
                        rowClassName={(rule) =>
                            cn(
                                rule.status === 'pending'
                                    ? 'bg-gray-50/50 dark:bg-gray-950/20'
                                    : rule.status === 'installing'
                                      ? 'animate-pulse bg-amber-50/50 dark:bg-amber-950/20'
                                      : rule.status === 'removing'
                                        ? 'animate-pulse bg-orange-50/50 dark:bg-orange-950/20'
                                        : rule.status === 'failed'
                                          ? 'bg-red-50/50 dark:bg-red-950/20'
                                          : 'hover:bg-muted/50',
                            )
                        }
                        emptyState={
                            <div className="py-8 text-center text-muted-foreground">
                                <Shield className="mx-auto mb-3 size-12 opacity-20" />
                                <p>No firewall rules configured</p>
                                <p className="mt-1 text-sm">Add your first rule above</p>
                            </div>
                        }
                    />
                </CardContainer>

                {/* Recent Events */}
                {server.recentEvents.length > 0 && (
                    <CardContainer
                        title="Recent Activity"
                        icon={
                            <svg width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <circle cx="6" cy="6" r="5" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" />
                                <path d="M6 3v3l2 1" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" />
                            </svg>
                        }
                    >
                        <div className="space-y-2">
                            {server.recentEvents.map((event) => (
                                <div key={event.id} className="flex items-center justify-between border-b py-2 last:border-0">
                                    <div className="flex items-center gap-3">
                                        {event.status === 'success' ? (
                                            <CheckCircle2 className="size-4 text-green-500" />
                                        ) : event.status === 'failed' ? (
                                            <AlertCircle className="size-4 text-red-500" />
                                        ) : (
                                            <Clock className="size-4 text-amber-500" />
                                        )}
                                        <div>
                                            <p className="text-sm font-medium">{event.milestone}</p>
                                            {event.details?.server_name && (
                                                <p className="text-xs text-muted-foreground">{event.details.server_name}</p>
                                            )}
                                        </div>
                                    </div>
                                    <div className="text-xs text-muted-foreground">{new Date(event.created_at).toLocaleString()}</div>
                                </div>
                            ))}
                        </div>
                    </CardContainer>
                )}

                {/* Info Alert */}
                <Alert>
                    <Shield className="size-4" />
                    <AlertDescription>
                        Changes to firewall rules are applied immediately. SSH (port 22) is always allowed to prevent lockout. Be careful when
                        modifying rules as incorrect configuration may block access to your services.
                    </AlertDescription>
                </Alert>
            </PageHeader>

            {/* Add Rule Modal */}
            <CardFormModal
                open={showAddRuleDialog}
                onOpenChange={setShowAddRuleDialog}
                title="Add Firewall Rule"
                description="Configure port access and traffic rules for your server."
                onSubmit={handleSubmit}
                submitLabel="Add Rule"
                isSubmitting={processing}
                submittingLabel="Adding Rule..."
            >
                <div className="grid gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="name">Name</Label>
                        <Input
                            id="name"
                            placeholder="Application name"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            className={errors.name ? 'border-red-500' : ''}
                            disabled={processing}
                            required
                        />
                        {errors.name && <p className="text-xs text-red-500">{errors.name}</p>}
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="port">Port</Label>
                        <Input
                            id="port"
                            placeholder="80"
                            value={data.port}
                            onChange={(e) => setData('port', e.target.value)}
                            className={errors.port ? 'border-red-500' : ''}
                            disabled={processing}
                        />
                        <p className="text-xs text-muted-foreground">
                            You may provide a port range using a colon character (1:65535) or leave this field empty if the rule should apply to all
                            ports.
                        </p>
                        {errors.port && <p className="text-xs text-red-500">{errors.port}</p>}
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="from_ip_address">From IP Address (Optional)</Label>
                        <Input
                            id="from_ip_address"
                            placeholder=""
                            value={data.from_ip_address}
                            onChange={(e) => setData('from_ip_address', e.target.value)}
                            className={errors.from_ip_address ? 'border-red-500' : ''}
                            disabled={processing}
                        />
                        <p className="text-xs text-muted-foreground">You may also provide a subnet.</p>
                        {errors.from_ip_address && <p className="text-xs text-red-500">{errors.from_ip_address}</p>}
                    </div>

                    <div className="grid gap-2">
                        <Label>Rule Type</Label>
                        <div className="flex gap-4">
                            <label className="flex items-center gap-2">
                                <input
                                    type="radio"
                                    name="rule_type"
                                    value="allow"
                                    checked={data.rule_type === 'allow'}
                                    onChange={(e) => setData('rule_type', e.target.value)}
                                    className="text-primary"
                                    disabled={processing}
                                />
                                <span>Allow</span>
                            </label>
                            <label className="flex items-center gap-2">
                                <input
                                    type="radio"
                                    name="rule_type"
                                    value="deny"
                                    checked={data.rule_type === 'deny'}
                                    onChange={(e) => setData('rule_type', e.target.value)}
                                    className="text-primary"
                                    disabled={processing}
                                />
                                <span>Deny</span>
                            </label>
                        </div>
                        {errors.rule_type && <p className="text-xs text-red-500">{errors.rule_type}</p>}
                    </div>
                </div>
            </CardFormModal>
        </ServerLayout>
    );
}
