import { CardList, type CardListAction } from '@/components/card-list';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { CardBadge } from '@/components/ui/card-badge';
import { CardContainer } from '@/components/ui/card-container';
import { CardFormModal } from '@/components/ui/card-form-modal';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { PageHeader } from '@/components/ui/page-header';
import ServerLayout from '@/layouts/server/layout';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { useEcho } from '@laravel/echo-react';
import { AlertCircle, CheckCircle2, Clock, RotateCw, Shield, Trash2 } from 'lucide-react';
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
    firewall: {
        isInstalled: boolean;
        status: string;
        is_enabled: boolean;
        rules: FirewallRule[];
        recentEvents: FirewallEvent[];
    };
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

    const handleDeleteRule = (ruleId: number) => {
        if (window.confirm('Are you sure you want to delete this firewall rule?')) {
            router.delete(`/servers/${server.id}/firewall/${ruleId}`);
        }
    };

    const handleRetryFirewallRule = (rule: FirewallRule) => {
        if (!confirm(`Retry installing firewall rule "${rule.name}"?`)) {
            return;
        }
        router.post(`/servers/${server.id}/firewall/${rule.id}/retry`, {}, {
            preserveScroll: true,
        });
    };


    if (!server.firewall.isInstalled) {
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
                <CardList<FirewallRule>
                    title="Rules"
                    description="Manage firewall rules that control the incoming and outgoing traffic to and from your server."
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
                    onAddClick={() => setShowAddRuleDialog(true)}
                    addButtonLabel="Add Rule"
                    items={server.firewall.rules}
                    keyExtractor={(rule) => rule.id || Math.random()}
                    renderItem={(rule) => (
                        <div className="flex items-center justify-between gap-3">
                            {/* Left: Rule info */}
                            <div className="min-w-0 flex-1">
                                <div className="truncate text-sm font-medium text-foreground">{rule.name}</div>
                                <div className="mt-1 flex items-center gap-3 text-xs text-muted-foreground">
                                    <span className={rule.rule_type === 'allow' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'}>
                                        {rule.rule_type === 'allow' ? 'Allow' : 'Deny'}
                                    </span>
                                    <span>•</span>
                                    <span>
                                        Port: <span className="font-mono">{rule.port || 'All'}</span>
                                    </span>
                                    <span>•</span>
                                    <span>Protocol: TCP/UDP</span>
                                    <span>•</span>
                                    <span>
                                        Source: <span className="font-mono">{rule.from_ip_address || 'Any'}</span>
                                    </span>
                                </div>
                            </div>

                            {/* Right: Status badge */}
                            <div className="flex-shrink-0">
                                <CardBadge variant={rule.status as any} />
                            </div>
                        </div>
                    )}
                    actions={(rule) => {
                        const actions: CardListAction[] = [];
                        const isInTransition = rule.status === 'pending' || rule.status === 'installing' || rule.status === 'removing';

                        // Add retry action for failed firewall rules
                        if (rule.status === 'failed') {
                            actions.push({
                                label: 'Retry Installation',
                                onClick: () => handleRetryFirewallRule(rule),
                                icon: <RotateCw className="h-4 w-4" />,
                                disabled: processing,
                            });
                        }

                        if (rule.id && (!rule.status || rule.status === 'active' || rule.status === 'failed')) {
                            actions.push({
                                label: 'Delete Rule',
                                onClick: () => handleDeleteRule(rule.id!),
                                variant: 'destructive',
                                icon: <Trash2 className="h-4 w-4" />,
                                disabled: isInTransition,
                            });
                        }

                        return actions;
                    }}
                    emptyStateMessage="No firewall rules configured"
                    emptyStateIcon={<Shield className="h-6 w-6 text-muted-foreground" />}
                />

                {/* Recent Events */}
                {server.firewall.recentEvents.length > 0 && (
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
                            {server.firewall.recentEvents.map((event) => (
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
                {/*<Alert>*/}
                {/*    <Shield className="size-4" />*/}
                {/*    <AlertDescription>*/}
                {/*        Changes to firewall rules are applied immediately. SSH (port 22) is always allowed to prevent lockout. Be careful when*/}
                {/*        modifying rules as incorrect configuration may block access to your services.*/}
                {/*    </AlertDescription>*/}
                {/*</Alert>*/}
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
