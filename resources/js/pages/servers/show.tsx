import { CardContainerAddButton } from '@/components/card-container-add-button';
import { Button } from '@/components/ui/button';
import { CardContainer } from '@/components/ui/card-container';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import ServerLayout from '@/layouts/server/layout';
import { dashboard } from '@/routes';
import { show as showSite } from '@/routes/servers/sites';
import { type BreadcrumbItem, type ServerMetric } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import {
    ChevronRight,
    GitBranch,
    Globe,
    Loader2,
    Lock,
    Plus
} from 'lucide-react';
import { useState } from 'react';

type Server = {
    id: number;
    vanity_name: string;
    public_ip: string;
    private_ip?: string | null;
    connection: 'pending' | 'connecting' | 'connected' | 'failed' | 'disconnected' | string;
    ssh_port: number;
    created_at: string;
    updated_at: string;
};

type ServerSite = {
    id: number;
    domain: string;
    document_root: string;
    php_version: string;
    ssl_enabled: boolean;
    status: string;
    provisioned_at: string | null;
    provisioned_at_human?: string | null;
    configuration?: {
        git_repository?: {
            provider?: string;
            repository?: string;
            branch?: string;
        };
        application_type?: string;
    };
    git_status?: string;
};

export default function Show({ server, sites, latestMetrics }: { server: Server; sites: { data: ServerSite[] }; latestMetrics?: ServerMetric | null }) {
    const [showAddSiteDialog, setShowAddSiteDialog] = useState(false);

    const form = useForm({
        domain: '',
        php_version: '8.3',
        ssl: false,
    } as { domain: string; php_version: string; ssl: boolean });

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: dashboard.url() },
        { title: `Server #${server.id}`, href: '#' },
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

    return (
        <ServerLayout server={server} breadcrumbs={breadcrumbs} latestMetrics={latestMetrics}>
            <Head title={`${server.vanity_name} - Server Overview`} />

            <div className="space-y-6">
                {/* Sites Management */}
                <CardContainer
                    title="Configured Sites"
                    action={
                        <CardContainerAddButton
                            label="Add Site"
                            onClick={() => setShowAddSiteDialog(true)}
                            aria-label="Add Site"
                        />
                    }
                >
                    {sites.data.length > 0 ? (
                        <div className="divide-y divide-border/50">
                            {sites.data.map((site) => (
                                <Link
                                    key={site.id}
                                    href={showSite({ server: server.id, site: site.id }).url}
                                    className="block group"
                                >
                                    <div className="hover:bg-muted/30 transition-colors">
                                        <div className="flex items-center gap-6">
                                            {/* Icon */}
                                            <div className="flex items-center justify-center w-11 h-11 rounded-lg bg-primary/10 flex-shrink-0">
                                                <Globe className="h-5 w-5 text-primary" />
                                            </div>

                                            {/* Site Info */}
                                            <div className="flex-1 min-w-0">
                                                <h3 className="text-base font-semibold text-foreground truncate group-hover:text-primary transition-colors mb-1">
                                                    {site.domain}
                                                </h3>
                                                {site.configuration?.git_repository?.repository ? (
                                                    <div className="flex items-center gap-2">
                                                        <GitBranch className="h-3.5 w-3.5 text-muted-foreground/60 flex-shrink-0" />
                                                        <p className="text-sm text-muted-foreground truncate">
                                                            {site.configuration.git_repository.repository}
                                                            {site.configuration.git_repository.branch && (
                                                                <span className="text-muted-foreground/60"> • {site.configuration.git_repository.branch}</span>
                                                            )}
                                                        </p>
                                                    </div>
                                                ) : (
                                                    <p className="text-sm text-muted-foreground/60">No repository configured</p>
                                                )}
                                            </div>

                                            {/* Metadata */}
                                            <div className="flex items-center gap-6 flex-shrink-0">
                                                {/* SSL */}
                                                <div className="flex items-center gap-2 min-w-[80px]">
                                                    <Lock className={`h-4 w-4 flex-shrink-0 ${site.ssl_enabled ? 'text-green-600' : 'text-muted-foreground/30'}`} />
                                                    <span className="text-sm text-muted-foreground">
                                                        {site.ssl_enabled ? 'SSL' : 'No SSL'}
                                                    </span>
                                                </div>

                                                {/* PHP Version */}
                                                <div className="text-sm min-w-[70px]">
                                                    <span className="text-muted-foreground">PHP </span>
                                                    <span className="font-medium text-foreground">{site.php_version}</span>
                                                </div>

                                                {/* Deployed Time */}
                                                <div className="text-sm text-muted-foreground min-w-[110px] text-right">
                                                    {site.provisioned_at_human || 'Not deployed'}
                                                </div>

                                                {/* Arrow */}
                                                <ChevronRight className="h-5 w-5 text-muted-foreground/40 group-hover:text-primary group-hover:translate-x-0.5 transition-all flex-shrink-0" />
                                            </div>
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
                </CardContainer>
            </div>

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
        </ServerLayout>
    );
}
