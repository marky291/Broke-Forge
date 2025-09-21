import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Switch } from '@/components/ui/switch';
import ServerLayout from '@/layouts/server/layout';
import { dashboard } from '@/routes';
import { show as showServer } from '@/routes/servers';
import { show as showSite } from '@/routes/servers/sites';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { CheckCircle, Clock, Globe, Loader2, Lock, Plus, XCircle } from 'lucide-react';
import { useState } from 'react';

type ServerType = {
    id: number;
    vanity_name: string;
    public_ip: string;
    ssh_port: number;
    private_ip?: string | null;
    connection: string;
    created_at: string;
    updated_at: string;
};

/**
 * Represents a site hosted on a server.
 * Maps to the ServerSite model on the backend.
 */
type ServerSite = {
    id: number;
    domain: string;
    document_root: string;
    php_version: string;
    ssl_enabled: boolean;
    status: string;
    provisioned_at: string | null;
};

type SitesProps = {
    server: ServerType;
    sites: {
        data: ServerSite[];
        links: Record<string, string | null>;
        meta: {
            current_page: number;
            from: number | null;
            last_page: number;
            links: Array<{
                url: string | null;
                label: string;
                active: boolean;
            }>;
            path: string;
            per_page: number;
            to: number | null;
            total: number;
        };
    };
};

export default function Sites({ server, sites }: SitesProps) {
    const [showAddSiteDialog, setShowAddSiteDialog] = useState(false);
    const form = useForm({
        domain: '',
        php_version: '8.3',
        ssl: false,
    } as { domain: string; php_version: string; ssl: boolean });

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: dashboard().url },
        { title: `Server #${server.id}`, href: showServer(server.id).url },
        { title: 'Sites', href: '#' },
    ];

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post(`/servers/${server.id}/sites`, {
            onSuccess: () => {
                setShowAddSiteDialog(false);
                form.reset();
            },
        });
    };

    const getStatusBadge = (status: string) => {
        switch (status) {
            case 'active':
                return <Badge className="border-green-500/20 bg-green-500/10 text-green-500">Active</Badge>;
            case 'provisioning':
                return <Badge className="border-blue-500/20 bg-blue-500/10 text-blue-500">Provisioning</Badge>;
            case 'disabled':
                return <Badge className="border-gray-500/20 bg-gray-500/10 text-gray-500">Disabled</Badge>;
            case 'failed':
                return <Badge className="border-red-500/20 bg-red-500/10 text-red-500">Failed</Badge>;
            default:
                return <Badge variant="outline">{status}</Badge>;
        }
    };

    const getStatusIcon = (status: string) => {
        switch (status) {
            case 'active':
                return <CheckCircle className="h-4 w-4 text-green-500" />;
            case 'provisioning':
                return <Loader2 className="h-4 w-4 animate-spin text-blue-500" />;
            case 'disabled':
                return <XCircle className="h-4 w-4 text-gray-500" />;
            case 'failed':
                return <XCircle className="h-4 w-4 text-red-500" />;
            default:
                return <Clock className="h-4 w-4 text-gray-400" />;
        }
    };

    return (
        <ServerLayout server={server} breadcrumbs={breadcrumbs}>
            <Head title={`Sites — ${server.vanity_name}`} />
            <div className="space-y-6">
                <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h2 className="text-2xl font-semibold">Sites Management</h2>
                        <p className="mt-1 text-sm text-muted-foreground">Manage websites, domains, and applications hosted on your server.</p>
                    </div>
                    <Button asChild variant="outline">
                        <Link href={`/servers/${server.id}/explorer`}>Open File Explorer</Link>
                    </Button>
                </div>

                {/* Sites List */}
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between">
                        <CardTitle className="text-base font-medium">Configured Sites</CardTitle>
                        <Button onClick={() => setShowAddSiteDialog(true)} size="sm">
                            <Plus className="mr-2 h-4 w-4" />
                            Add Site
                        </Button>
                    </CardHeader>
                    <Separator />
                    <CardContent className="p-0">
                        {sites.data.length > 0 ? (
                            <div className="divide-y">
                                {sites.data.map((site) => (
                                    <Link
                                        key={site.id}
                                        href={showSite({ server: server.id, site: site.id }).url}
                                        className="block transition-colors hover:bg-muted/50 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                    >
                                        <div className="p-4">
                                            <div className="flex items-start justify-between">
                                                <div className="space-y-1">
                                                    <div className="flex items-center gap-2">
                                                        {getStatusIcon(site.status)}
                                                        <h3 className="font-medium">{site.domain}</h3>
                                                        {site.ssl_enabled && <Lock className="h-3.5 w-3.5 text-green-500" />}
                                                    </div>
                                                    <div className="flex items-center gap-4 text-sm text-muted-foreground">
                                                        <span>PHP {site.php_version}</span>
                                                        <span>•</span>
                                                        <span>{site.document_root}</span>
                                                    </div>
                                                </div>
                                                <div className="flex items-center gap-2">{getStatusBadge(site.status)}</div>
                                            </div>
                                        </div>
                                    </Link>
                                ))}
                            </div>
                        ) : (
                            <div className="p-8 text-center">
                                <Globe className="mx-auto h-12 w-12 text-muted-foreground/50" />
                                <h3 className="mt-4 text-lg font-semibold">No sites configured</h3>
                                <p className="mt-2 text-sm text-muted-foreground">Get started by adding your first site to this server.</p>
                                <Button onClick={() => setShowAddSiteDialog(true)} className="mt-4" variant="outline">
                                    <Plus className="mr-2 h-4 w-4" />
                                    Add Your First Site
                                </Button>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Add Site Dialog */}
                <Dialog open={showAddSiteDialog} onOpenChange={setShowAddSiteDialog}>
                    <DialogContent className="sm:max-w-[425px]">
                        <form onSubmit={handleSubmit}>
                            <DialogHeader>
                                <DialogTitle>Add New Site</DialogTitle>
                                <DialogDescription>
                                    Configure a new site on your server. The site will be provisioned with nginx and PHP-FPM.
                                </DialogDescription>
                            </DialogHeader>
                            <div className="grid gap-4 py-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="domain">Domain Name</Label>
                                    <Input
                                        id="domain"
                                        placeholder="example.com"
                                        value={form.data.domain}
                                        onChange={(e) => form.setData('domain', e.target.value)}
                                        disabled={form.processing}
                                    />
                                    {form.errors.domain && <p className="text-sm text-red-500">{form.errors.domain}</p>}
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="php_version">PHP Version</Label>
                                    <Select
                                        value={form.data.php_version}
                                        onValueChange={(value) => form.setData('php_version', value)}
                                        disabled={form.processing}
                                    >
                                        <SelectTrigger id="php_version">
                                            <SelectValue placeholder="Select PHP version" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="8.3">PHP 8.3</SelectItem>
                                            <SelectItem value="8.2">PHP 8.2</SelectItem>
                                            <SelectItem value="8.1">PHP 8.1</SelectItem>
                                            <SelectItem value="8.0">PHP 8.0</SelectItem>
                                            <SelectItem value="7.4">PHP 7.4</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="flex items-center space-x-2">
                                    <Switch
                                        id="ssl"
                                        checked={form.data.ssl}
                                        onCheckedChange={(checked) => form.setData('ssl', checked)}
                                        disabled={form.processing}
                                    />
                                    <Label htmlFor="ssl" className="flex cursor-pointer items-center gap-2">
                                        <Lock className="h-4 w-4" />
                                        Enable SSL (HTTPS)
                                    </Label>
                                </div>
                            </div>
                            <DialogFooter>
                                <Button type="button" variant="outline" onClick={() => setShowAddSiteDialog(false)} disabled={form.processing}>
                                    Cancel
                                </Button>
                                <Button type="submit" disabled={form.processing}>
                                    {form.processing ? (
                                        <>
                                            <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                            Provisioning...
                                        </>
                                    ) : (
                                        'Add Site'
                                    )}
                                </Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>
            </div>
        </ServerLayout>
    );
}
