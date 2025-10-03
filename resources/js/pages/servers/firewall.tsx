import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { CardContainer } from '@/components/ui/card-container';
import { CardContainerAddButton } from '@/components/card-container-add-button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { PageHeader } from '@/components/ui/page-header';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useStatusPolling } from '@/hooks/useStatusPolling';
import ServerLayout from '@/layouts/server/layout';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm, router } from '@inertiajs/react';
import { AlertCircle, CheckCircle2, Clock, Loader2, Plus, Shield, Trash2 } from 'lucide-react';
import { useState } from 'react';

type Server = {
    id: number;
    vanity_name: string;
    public_ip: string;
    ssh_port: number;
    private_ip?: string | null;
    connection: string;
    created_at: string;
    updated_at: string;
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
    rules: FirewallRule[];
    isFirewallInstalled: boolean;
    firewallStatus: string;
    recentEvents: FirewallEvent[];
}

export default function Firewall({
    server,
    rules: initialRules,
    isFirewallInstalled,
    firewallStatus: initialFirewallStatus,
    recentEvents: initialRecentEvents
}: FirewallProps) {
    const [showAddRuleDialog, setShowAddRuleDialog] = useState(false);
    const [isDeletingRule, setIsDeletingRule] = useState<number | null>(null);
    const [rules, setRules] = useState<FirewallRule[]>(initialRules);
    const [firewallStatus, setFirewallStatus] = useState(initialFirewallStatus);
    const [recentEvents, setRecentEvents] = useState<FirewallEvent[]>(initialRecentEvents);

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

    // Poll for status updates when there are pending or installing rules
    const hasPendingRules = rules.some(r => r.status === 'pending' || r.status === 'installing' || r.status === 'removing');

    useStatusPolling({
        url: `/servers/${server.id}/firewall/status`,
        interval: 2000,
        enabled: hasPendingRules,
        onSuccess: (data) => {
            setRules(data.rules);
            setFirewallStatus(data.firewallStatus);

            // Update recent events if provided
            if (data.latestEvent) {
                setRecentEvents(prev => {
                    const exists = prev.some(e => e.id === data.latestEvent.id);
                    if (!exists && data.latestEvent.id) {
                        return [data.latestEvent, ...prev].slice(0, 5);
                    }
                    return prev;
                });
            }
        },
        stopCondition: (data) => {
            // Stop polling if no more pending/installing rules
            return !data.rules.some((r: FirewallRule) =>
                r.status === 'pending' || r.status === 'installing'
            );
        }
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
                onSuccess: () => {
                    // Remove from local state immediately
                    setRules(prev => prev.filter(r => r.id !== ruleId));
                }
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
                    <Clock className="size-3 mr-1" />
                    Pending
                </Badge>
            );
        }
        if (status === 'installing') {
            return (
                <Badge className="bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200">
                    <Loader2 className="size-3 mr-1 animate-spin" />
                    Installing
                </Badge>
            );
        }
        if (status === 'active') {
            return (
                <Badge className="bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                    <CheckCircle2 className="size-3 mr-1" />
                    Active
                </Badge>
            );
        }
        if (status === 'failed') {
            return (
                <Badge className="bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                    <AlertCircle className="size-3 mr-1" />
                    Failed
                </Badge>
            );
        }
        if (status === 'removing') {
            return (
                <Badge className="bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200">
                    <Loader2 className="size-3 mr-1 animate-spin" />
                    Removing
                </Badge>
            );
        }
        return null;
    };

    if (!isFirewallInstalled) {
        return (
            <ServerLayout server={server} breadcrumbs={breadcrumbs}>
                <Head title={`${server.vanity_name} - Firewall`} />

                <div className="space-y-6">
                    <div>
                        <h1 className="text-2xl font-semibold">Firewall</h1>
                        <p className="text-sm text-muted-foreground mt-1">
                            Manage firewall rules for your server
                        </p>
                    </div>

                    <Alert className="border-amber-200 bg-amber-50 dark:bg-amber-950 dark:border-amber-800">
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

            <PageHeader
                title="Firewall"
                description="Configure and manage firewall rules to control network traffic to your server."
            >
                {/* Firewall Rules */}
                <CardContainer
                    title="Firewall Rules"
                    action={
                        <CardContainerAddButton
                            label="Add Rule"
                            onClick={() => setShowAddRuleDialog(true)}
                            aria-label="Add Firewall Rule"
                        />
                    }
                >
                    {rules.length === 0 ? (
                        <div className="text-center py-8 text-muted-foreground">
                            <Shield className="size-12 mx-auto mb-3 opacity-20" />
                            <p>No firewall rules configured</p>
                            <p className="text-sm mt-1">Add your first rule above</p>
                        </div>
                    ) : (
                        <div className="space-y-2">
                            {/* Table Header */}
                            <div className="grid grid-cols-12 gap-2 px-4 py-2 text-sm font-medium text-muted-foreground border-b">
                                <div className="col-span-2">Name</div>
                                <div className="col-span-2">Port</div>
                                <div className="col-span-1">Protocol</div>
                                <div className="col-span-2">Source</div>
                                <div className="col-span-2">Action</div>
                                <div className="col-span-2">Status</div>
                                <div className="col-span-1 text-right">Actions</div>
                            </div>

                            {/* Table Rows */}
                            <div className="divide-y">
                                {rules.map((rule, index) => (
                                    <div
                                        key={rule.id || index}
                                        className={cn(
                                            "grid grid-cols-12 gap-2 px-4 py-3 items-center transition-all",
                                            rule.status === 'pending'
                                                ? "bg-gray-50/50 dark:bg-gray-950/20"
                                                : rule.status === 'installing'
                                                ? "bg-amber-50/50 dark:bg-amber-950/20 animate-pulse"
                                                : rule.status === 'removing'
                                                ? "bg-orange-50/50 dark:bg-orange-950/20 animate-pulse"
                                                : rule.status === 'failed'
                                                ? "bg-red-50/50 dark:bg-red-950/20"
                                                : "hover:bg-muted/50"
                                        )}
                                    >
                                        <div className="col-span-2 text-sm">{rule.name}</div>
                                        <div className="col-span-2 font-mono text-sm">{rule.port || 'All'}</div>
                                        <div className="col-span-1 text-sm">TCP/UDP</div>
                                        <div className="col-span-2 font-mono text-sm">
                                            {rule.from_ip_address || 'Any'}
                                        </div>
                                        <div className="col-span-2">{getActionBadge(rule.rule_type)}</div>
                                        <div className="col-span-2">{getStatusBadge(rule.status)}</div>
                                        <div className="col-span-1 flex justify-end">
                                            {(!rule.status || rule.status === 'active' || rule.status === 'failed') && rule.id && (
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() => handleDeleteRule(rule.id!, index)}
                                                    disabled={isDeletingRule === index}
                                                >
                                                    {isDeletingRule === index ? (
                                                        <Loader2 className="size-4 animate-spin" />
                                                    ) : (
                                                        <Trash2 className="size-4 text-red-500" />
                                                    )}
                                                </Button>
                                            )}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}
                </CardContainer>

                {/* Recent Events */}
                {recentEvents.length > 0 && (
                    <CardContainer
                        title="Recent Activity"
                    >
                        <div className="space-y-2">
                            {recentEvents.map((event) => (
                                <div key={event.id} className="flex items-center justify-between py-2 border-b last:border-0">
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
                                                <p className="text-xs text-muted-foreground">
                                                    {event.details.server_name}
                                                </p>
                                            )}
                                        </div>
                                    </div>
                                    <div className="text-xs text-muted-foreground">
                                        {new Date(event.created_at).toLocaleString()}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </CardContainer>
                )}

                {/* Info Alert */}
                <Alert>
                    <Shield className="size-4" />
                    <AlertDescription>
                        Changes to firewall rules are applied immediately. SSH (port 22) is always allowed to prevent lockout.
                        Be careful when modifying rules as incorrect configuration may block access to your services.
                    </AlertDescription>
                </Alert>
            </PageHeader>

            {/* Add Rule Dialog */}
            <Dialog open={showAddRuleDialog} onOpenChange={setShowAddRuleDialog}>
                    <DialogContent className="sm:max-w-[500px]">
                        <form onSubmit={handleSubmit}>
                            <DialogHeader>
                                <DialogTitle>Add Firewall Rule</DialogTitle>
                                <DialogDescription>
                                    Configure port access and traffic rules for your server.
                                </DialogDescription>
                            </DialogHeader>
                            <div className="grid gap-4 py-4">
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
                                    {errors.name && (
                                        <p className="text-xs text-red-500">{errors.name}</p>
                                    )}
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
                                        You may provide a port range using a colon character (1:65535) or leave this field empty if the rule should apply to all ports.
                                    </p>
                                    {errors.port && (
                                        <p className="text-xs text-red-500">{errors.port}</p>
                                    )}
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
                                    <p className="text-xs text-muted-foreground">
                                        You may also provide a subnet.
                                    </p>
                                    {errors.from_ip_address && (
                                        <p className="text-xs text-red-500">{errors.from_ip_address}</p>
                                    )}
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
                                    {errors.rule_type && (
                                        <p className="text-xs text-red-500">{errors.rule_type}</p>
                                    )}
                                </div>
                            </div>
                            <DialogFooter>
                                <Button type="button" variant="outline" onClick={() => setShowAddRuleDialog(false)} disabled={processing}>
                                    Cancel
                                </Button>
                                <Button type="submit" disabled={processing}>
                                    {processing ? (
                                        <>
                                            <Loader2 className="mr-2 size-4 animate-spin" />
                                            Adding Rule...
                                        </>
                                    ) : (
                                        'Add Rule'
                                    )}
                                </Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>
        </ServerLayout>
    );
}
