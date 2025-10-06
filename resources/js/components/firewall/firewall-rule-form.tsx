import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Form } from '@inertiajs/react';
import { Plus, Shield } from 'lucide-react';

interface FirewallRule {
    id: number;
    name: string;
    port: string;
    rule_type: 'allow' | 'deny';
    from_ip_address?: string | null;
    status: string;
}

interface FirewallRuleFormProps {
    serverId: number;
    commonPorts: Record<string, string>;
    onSuccess?: () => void;
}

export default function FirewallRuleForm({ serverId, commonPorts, onSuccess }: FirewallRuleFormProps) {
    return (
        <div className="rounded-xl border bg-background p-6">
            <div className="mb-4 flex items-center gap-2">
                <Shield className="h-5 w-5 text-blue-600" />
                <h3 className="text-lg font-semibold">Add Firewall Rule</h3>
            </div>

            <Form method="post" action={`/servers/${serverId}/firewall`} resetOnSuccess onSuccess={onSuccess}>
                {({ processing, errors, data, setData }) => (
                    <div className="space-y-4">
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div className="space-y-2">
                                <Label htmlFor="name">Rule Name</Label>
                                <Input
                                    id="name"
                                    name="name"
                                    placeholder="e.g., Allow HTTPS"
                                    value={data.name || ''}
                                    onChange={(e) => setData('name', e.target.value)}
                                    disabled={processing}
                                    required
                                />
                                {errors.name && <div className="text-sm text-destructive">{errors.name}</div>}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="rule_type">Action</Label>
                                <Select
                                    value={data.rule_type || 'allow'}
                                    onValueChange={(value) => setData('rule_type', value)}
                                    disabled={processing}
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="allow">Allow</SelectItem>
                                        <SelectItem value="deny">Deny</SelectItem>
                                    </SelectContent>
                                </Select>
                                {errors.rule_type && <div className="text-sm text-destructive">{errors.rule_type}</div>}
                            </div>
                        </div>

                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div className="space-y-2">
                                <Label htmlFor="port">Port(s)</Label>
                                <div className="flex gap-2">
                                    <Input
                                        id="port"
                                        name="port"
                                        placeholder="80 or 8000:8100"
                                        value={data.port || ''}
                                        onChange={(e) => setData('port', e.target.value)}
                                        disabled={processing}
                                        required
                                    />
                                    <Select value="" onValueChange={(value) => setData('port', value)} disabled={processing}>
                                        <SelectTrigger className="w-32">
                                            <SelectValue placeholder="Common" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {Object.entries(commonPorts).map(([port, service]) => (
                                                <SelectItem key={port} value={port}>
                                                    {port} ({service})
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                {errors.port && <div className="text-sm text-destructive">{errors.port}</div>}
                                <div className="text-xs text-muted-foreground">Single port (80) or range (8000:8100)</div>
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="from_ip_address">Source IP (Optional)</Label>
                                <Input
                                    id="from_ip_address"
                                    name="from_ip_address"
                                    placeholder="0.0.0.0/0 (any) or specific IP"
                                    value={data.from_ip_address || ''}
                                    onChange={(e) => setData('from_ip_address', e.target.value)}
                                    disabled={processing}
                                />
                                {errors.from_ip_address && <div className="text-sm text-destructive">{errors.from_ip_address}</div>}
                                <div className="text-xs text-muted-foreground">Leave empty to allow from anywhere</div>
                            </div>
                        </div>

                        <Separator />

                        <div className="flex justify-end">
                            <Button type="submit" disabled={processing}>
                                <Plus className="mr-2 h-4 w-4" />
                                {processing ? 'Adding Rule...' : 'Add Rule'}
                            </Button>
                        </div>
                    </div>
                )}
            </Form>
        </div>
    );
}
