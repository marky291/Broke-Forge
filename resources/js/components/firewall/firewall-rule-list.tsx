import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { Form } from '@inertiajs/react';
import { Loader2, Shield, Trash2 } from 'lucide-react';

interface FirewallRule {
    id: number;
    name: string;
    port: string;
    rule_type: 'allow' | 'deny';
    from_ip_address?: string | null;
    status: string;
    created_at: string;
}

interface FirewallRuleListProps {
    rules: FirewallRule[];
    serverId: number;
    firewallStatus: string;
}

export default function FirewallRuleList({ rules, serverId, firewallStatus }: FirewallRuleListProps) {
    const getStatusBadge = (status: string) => {
        switch (status) {
            case 'active':
                return <Badge variant="default">Active</Badge>;
            case 'pending':
                return (
                    <Badge variant="secondary">
                        <Loader2 className="mr-1 h-3 w-3 animate-spin" />
                        Pending
                    </Badge>
                );
            case 'failed':
                return <Badge variant="destructive">Failed</Badge>;
            default:
                return <Badge variant="outline">{status}</Badge>;
        }
    };

    const getRuleTypeBadge = (ruleType: string) => {
        return ruleType === 'allow' ? (
            <Badge variant="default" className="border-green-200 bg-green-100 text-green-800">
                Allow
            </Badge>
        ) : (
            <Badge variant="destructive">Deny</Badge>
        );
    };

    return (
        <div className="rounded-xl border bg-background">
            <div className="px-6 py-4">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-2">
                        <Shield className="h-5 w-5 text-blue-600" />
                        <h3 className="text-lg font-semibold">Firewall Rules</h3>
                        <Badge variant={firewallStatus === 'enabled' ? 'default' : 'secondary'}>{firewallStatus}</Badge>
                    </div>
                    <div className="text-sm text-muted-foreground">
                        {rules.length} rule{rules.length !== 1 ? 's' : ''}
                    </div>
                </div>
            </div>

            <Separator />

            {rules.length === 0 ? (
                <div className="px-6 py-8 text-center">
                    <Shield className="mx-auto mb-3 h-12 w-12 text-muted-foreground" />
                    <p className="text-muted-foreground">No firewall rules configured</p>
                    <p className="mt-1 text-sm text-muted-foreground">Add rules to control traffic to your server</p>
                </div>
            ) : (
                <div className="divide-y">
                    {rules.map((rule) => (
                        <div key={rule.id} className="px-6 py-4">
                            <div className="flex items-center justify-between">
                                <div className="space-y-1">
                                    <div className="flex items-center gap-2">
                                        <h4 className="font-medium">{rule.name}</h4>
                                        {getRuleTypeBadge(rule.rule_type)}
                                        {getStatusBadge(rule.status)}
                                    </div>
                                    <div className="text-sm text-muted-foreground">
                                        Port {rule.port}
                                        {rule.from_ip_address && <span> from {rule.from_ip_address}</span>}
                                    </div>
                                </div>

                                <Form
                                    method="delete"
                                    action={`/servers/${serverId}/firewall/${rule.id}`}
                                    onBefore={() => window.confirm('Are you sure you want to remove this firewall rule?')}
                                >
                                    {({ processing }) => (
                                        <Button type="submit" variant="outline" size="sm" disabled={processing}>
                                            {processing ? <Loader2 className="h-4 w-4 animate-spin" /> : <Trash2 className="h-4 w-4" />}
                                        </Button>
                                    )}
                                </Form>
                            </div>
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}
