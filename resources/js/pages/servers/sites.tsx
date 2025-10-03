import { CardContainerAddButton } from '@/components/card-container-add-button';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { CardContainer } from '@/components/ui/card-container';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { PageHeader } from '@/components/ui/page-header';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import ServerLayout from '@/layouts/server/layout';
import { dashboard } from '@/routes';
import { show as showServer } from '@/routes/servers';
import { show as showSite } from '@/routes/servers/sites';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { CheckCircle, ChevronRight, Clock, FileCode2, GitBranch, Globe, Loader2, Lock, Plus, XCircle } from 'lucide-react';
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
        { title: 'Dashboard', href: dashboard.url() },
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
                return (
                    <Badge variant="outline" className="border-green-200 bg-green-50 text-green-700 font-medium text-xs px-2.5 py-0.5">
                        Active
                    </Badge>
                );
            case 'provisioning':
                return (
                    <Badge variant="outline" className="border-blue-200 bg-blue-50 text-blue-700 font-medium text-xs px-2.5 py-0.5">
                        Provisioning
                    </Badge>
                );
            case 'disabled':
                return (
                    <Badge variant="outline" className="border-gray-200 bg-gray-50 text-gray-700 font-medium text-xs px-2.5 py-0.5">
                        Disabled
                    </Badge>
                );
            case 'failed':
                return (
                    <Badge variant="outline" className="border-red-200 bg-red-50 text-red-700 font-medium text-xs px-2.5 py-0.5">
                        Failed
                    </Badge>
                );
            default:
                return (
                    <Badge variant="outline" className="font-medium text-xs px-2.5 py-0.5">
                        {status}
                    </Badge>
                );
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

    const getApplicationTypeBadge = (type?: string) => {
        switch (type?.toLowerCase()) {
            case 'laravel':
                return <Badge variant="outline" className="text-xs font-normal border-orange-200 text-orange-700 bg-orange-50">Laravel</Badge>;
            case 'wordpress':
                return <Badge variant="outline" className="text-xs font-normal border-blue-200 text-blue-700 bg-blue-50">WordPress</Badge>;
            case 'static':
            case 'static-html':
                return <Badge variant="outline" className="text-xs font-normal border-gray-200 text-gray-700 bg-gray-50">Static HTML</Badge>;
            default:
                return null;
        }
    };

    const formatGitRepository = (git?: { provider?: string; repository?: string; branch?: string }) => {
        if (!git?.repository) return null;
        const parts = git.repository.split('/');
        const repoName = parts[parts.length - 1];
        return `${repoName}${git.branch ? ` (${git.branch})` : ''}`;
    };

    return (
        <ServerLayout server={server} breadcrumbs={breadcrumbs}>
            <Head title={`Sites — ${server.vanity_name}`} />
            <PageHeader
                title="Sites Management"
                description="Manage websites, domains, and applications hosted on your server."
                action={
                    <Button asChild variant="outline" size="sm">
                        <Link href={`/servers/${server.id}/explorer`}>Open File Explorer</Link>
                    </Button>
                }
            >
                {/* Sites List */}
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
                                    <div className="px-6 py-5 hover:bg-muted/30 transition-colors">
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
            </PageHeader>

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
