import { CardContainerAddButton } from '@/components/card-container-add-button';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { CardContainer } from '@/components/ui/card-container';
import { CardFormModal } from '@/components/ui/card-form-modal';
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
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import copyToClipboard from 'copy-to-clipboard';
import {
    Check,
    CheckCircle,
    CheckCircle2,
    ChevronRight,
    Clock,
    Copy,
    GitBranch,
    Globe,
    Loader2,
    Lock,
    Plus,
    XCircle,
} from 'lucide-react';
import { useEffect, useState } from 'react';

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
    const [wasProvisioning, setWasProvisioning] = useState(false);
    const [deployKey, setDeployKey] = useState<string>('');
    const [copiedDeployKey, setCopiedDeployKey] = useState(false);
    const { flash } = usePage<{ flash: { success?: string; error?: string } }>().props;
    const form = useForm({
        domain: '',
        php_version: '8.3',
        ssl: false,
        git_repository: '',
        git_branch: 'main',
    } as { domain: string; php_version: string; ssl: boolean; git_repository: string; git_branch: string });

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: dashboard.url() },
        { title: `Server #${server.id}`, href: showServer(server.id).url },
        { title: 'Sites', href: '#' },
    ];

    // Poll for updates when there are sites provisioning
    useEffect(() => {
        const hasProvisioningSites = sites.data.some((site) => site.status === 'provisioning');

        if (hasProvisioningSites) {
            setWasProvisioning(true);
            const interval = setInterval(() => {
                router.reload({ only: ['sites'], preserveScroll: true });
            }, 3000); // Poll every 3 seconds

            return () => clearInterval(interval);
        } else if (wasProvisioning) {
            // Sites finished provisioning, do one final reload to get fresh data
            setWasProvisioning(false);
            setTimeout(() => {
                router.reload({ only: ['sites'], preserveScroll: true });
            }, 500); // Small delay to ensure DB has been updated
        }
    }, [sites.data, wasProvisioning]);

    // Fetch deploy key when modal opens
    useEffect(() => {
        if (showAddSiteDialog && !deployKey) {
            fetch(`/servers/${server.id}/deploy-key`)
                .then((res) => res.json())
                .then((data) => setDeployKey(data.deploy_key))
                .catch((err) => console.error('Failed to fetch deploy key:', err));
        }
    }, [showAddSiteDialog, server.id, deployKey]);

    const handleCopyDeployKey = () => {
        const copiedOk = copyToClipboard(deployKey, { format: 'text/plain' });
        if (!copiedOk) return;

        setCopiedDeployKey(true);
        setTimeout(() => setCopiedDeployKey(false), 2000);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post(`/servers/${server.id}/sites`, {
            preserveScroll: true,
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
                    <Badge variant="outline" className="border-green-200 bg-green-50 px-2.5 py-0.5 text-xs font-medium text-green-700">
                        Active
                    </Badge>
                );
            case 'provisioning':
                return (
                    <Badge variant="outline" className="border-blue-200 bg-blue-50 px-2.5 py-0.5 text-xs font-medium text-blue-700">
                        Provisioning
                    </Badge>
                );
            case 'disabled':
                return (
                    <Badge variant="outline" className="border-gray-200 bg-gray-50 px-2.5 py-0.5 text-xs font-medium text-gray-700">
                        Disabled
                    </Badge>
                );
            case 'failed':
                return (
                    <Badge variant="outline" className="border-red-200 bg-red-50 px-2.5 py-0.5 text-xs font-medium text-red-700">
                        Failed
                    </Badge>
                );
            default:
                return (
                    <Badge variant="outline" className="px-2.5 py-0.5 text-xs font-medium">
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
                return (
                    <Badge variant="outline" className="border-orange-200 bg-orange-50 text-xs font-normal text-orange-700">
                        Laravel
                    </Badge>
                );
            case 'wordpress':
                return (
                    <Badge variant="outline" className="border-blue-200 bg-blue-50 text-xs font-normal text-blue-700">
                        WordPress
                    </Badge>
                );
            case 'static':
            case 'static-html':
                return (
                    <Badge variant="outline" className="border-gray-200 bg-gray-50 text-xs font-normal text-gray-700">
                        Static HTML
                    </Badge>
                );
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
            <PageHeader title="Sites Management" description="Manage websites, domains, and applications hosted on your server.">
                {/* Success Message */}
                {flash?.success && (
                    <Alert variant="default" className="border-green-200 bg-green-50 text-green-900">
                        <CheckCircle2 className="h-4 w-4 text-green-600" />
                        <AlertDescription>{flash.success}</AlertDescription>
                    </Alert>
                )}

                {/* Error Message */}
                {flash?.error && (
                    <Alert variant="destructive">
                        <XCircle className="h-4 w-4" />
                        <AlertDescription>{flash.error}</AlertDescription>
                    </Alert>
                )}

                {/* Sites List */}
                <CardContainer
                    title="Sites"
                    icon={
                        <svg width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <circle cx="6" cy="6" r="5" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" />
                            <path
                                d="M1.5 6h9M6 1.5c-1.5 1.5-1.5 4.5 0 9M6 1.5c1.5 1.5 1.5 4.5 0 9"
                                stroke="currentColor"
                                strokeLinecap="round"
                                strokeLinejoin="round"
                            />
                        </svg>
                    }
                    action={<CardContainerAddButton label="Add Site" onClick={() => setShowAddSiteDialog(true)} aria-label="Add Site" />}
                    parentBorder={false}
                >
                    {sites.data.length > 0 ? (
                        <div className="space-y-3">
                            {sites.data.map((site) => (
                                <div
                                    key={site.id}
                                    className="divide-y divide-neutral-200 rounded-lg border border-neutral-200 bg-white shadow-md shadow-black/5 dark:divide-white/8 dark:border-white/8 dark:bg-white/3"
                                >
                                    <Link href={showSite({ server: server.id, site: site.id }).url} className="group block">
                                        <div className="px-6 py-6 transition-colors hover:bg-muted/30">
                                            <div className="flex items-center gap-6">
                                                {/* Icon */}
                                                <div className="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-lg bg-primary/10">
                                                    <Globe className="h-5 w-5 text-primary" />
                                                </div>

                                                {/* Site Info */}
                                                <div className="min-w-0 flex-1">
                                                    <h3 className="mb-1 truncate text-base font-semibold text-foreground transition-colors group-hover:text-primary">
                                                        {site.domain}
                                                    </h3>
                                                    {site.configuration?.git_repository?.repository ? (
                                                        <div className="flex items-center gap-2">
                                                            <GitBranch className="h-3.5 w-3.5 flex-shrink-0 text-muted-foreground/60" />
                                                            <p className="truncate text-sm text-muted-foreground">
                                                                {site.configuration.git_repository.repository}
                                                                {site.configuration.git_repository.branch && (
                                                                    <span className="text-muted-foreground/60">
                                                                        {' '}
                                                                        • {site.configuration.git_repository.branch}
                                                                    </span>
                                                                )}
                                                            </p>
                                                        </div>
                                                    ) : (
                                                        <p className="text-sm text-muted-foreground/60">No repository configured</p>
                                                    )}
                                                </div>

                                                {/* Metadata */}
                                                <div className="flex flex-shrink-0 items-center gap-6">
                                                    {/* SSL */}
                                                    <div className="flex min-w-[80px] items-center gap-2">
                                                        <Lock
                                                            className={`h-4 w-4 flex-shrink-0 ${site.ssl_enabled ? 'text-green-600' : 'text-muted-foreground/30'}`}
                                                        />
                                                        <span className="text-sm text-muted-foreground">{site.ssl_enabled ? 'SSL' : 'No SSL'}</span>
                                                    </div>

                                                    {/* PHP Version */}
                                                    <div className="min-w-[70px] text-sm">
                                                        <span className="text-muted-foreground">PHP </span>
                                                        <span className="font-medium text-foreground">{site.php_version}</span>
                                                    </div>

                                                    {/* Deployed Time */}
                                                    <div className="min-w-[110px] text-right text-sm text-muted-foreground">
                                                        {site.provisioned_at_human || 'Not deployed'}
                                                    </div>

                                                    {/* Arrow */}
                                                    <ChevronRight className="h-5 w-5 flex-shrink-0 text-muted-foreground/40 transition-all group-hover:translate-x-0.5 group-hover:text-primary" />
                                                </div>
                                            </div>
                                        </div>
                                    </Link>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <div className="divide-y divide-neutral-200 rounded-lg border border-neutral-200 bg-white shadow-md shadow-black/5 dark:divide-white/8 dark:border-white/8 dark:bg-white/3">
                            <div className="px-6 py-6">
                                <div className="p-8 text-center">
                                    <Globe className="mx-auto h-12 w-12 text-muted-foreground/50" />
                                    <h3 className="mt-4 text-lg font-semibold">No sites configured</h3>
                                    <p className="mt-2 text-sm text-muted-foreground">Get started by adding your first site to this server.</p>
                                    <Button onClick={() => setShowAddSiteDialog(true)} className="mt-4" variant="outline">
                                        <Plus className="mr-2 h-4 w-4" />
                                        Add Your First Site
                                    </Button>
                                </div>
                            </div>
                        </div>
                    )}
                </CardContainer>
            </PageHeader>

            {/* Add Site Modal */}
            <CardFormModal
                open={showAddSiteDialog}
                onOpenChange={setShowAddSiteDialog}
                title="Add New Site"
                description="Configure a new site on your server. Your repository will be automatically cloned."
                onSubmit={handleSubmit}
                submitLabel="Create Site"
                isSubmitting={form.processing}
                submittingLabel="Creating..."
                className="sm:max-w-2xl"
            >
                <div className="flex w-full min-w-0 flex-col gap-4">
                    {/* Basic Configuration Section */}
                    <div className="w-full min-w-0 space-y-2">
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

                    <div className="grid w-full min-w-0 grid-cols-1 gap-4 sm:grid-cols-2">
                        <div className="min-w-0 space-y-2">
                            <Label htmlFor="php_version">PHP Version</Label>
                            <Select
                                value={form.data.php_version}
                                onValueChange={(value) => form.setData('php_version', value)}
                                disabled={form.processing}
                            >
                                <SelectTrigger id="php_version">
                                    <SelectValue placeholder="Select version" />
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

                        <div className="min-w-0 space-y-2">
                            <Label htmlFor="ssl">SSL Certificate</Label>
                            <div className="flex h-10 items-center">
                                <div className="flex items-center space-x-2">
                                    <Switch
                                        id="ssl"
                                        checked={form.data.ssl}
                                        onCheckedChange={(checked) => form.setData('ssl', checked)}
                                        disabled={form.processing}
                                    />
                                    <Label htmlFor="ssl" className="cursor-pointer text-sm font-normal">
                                        Enable HTTPS
                                    </Label>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Git Repository Section */}
                    <div className="grid w-full min-w-0 grid-cols-1 gap-4 sm:grid-cols-2">
                        <div className="min-w-0 space-y-2">
                            <Label htmlFor="git_repository">Git Repository</Label>
                            <Input
                                id="git_repository"
                                placeholder="owner/repo"
                                value={form.data.git_repository}
                                onChange={(e) => form.setData('git_repository', e.target.value)}
                                disabled={form.processing}
                            />
                            {form.errors.git_repository && <p className="text-sm text-red-500">{form.errors.git_repository}</p>}
                        </div>

                        <div className="min-w-0 space-y-2">
                            <Label htmlFor="git_branch">Branch</Label>
                            <Input
                                id="git_branch"
                                placeholder="main"
                                value={form.data.git_branch}
                                onChange={(e) => form.setData('git_branch', e.target.value)}
                                disabled={form.processing}
                            />
                            {form.errors.git_branch && <p className="text-sm text-red-500">{form.errors.git_branch}</p>}
                        </div>
                    </div>

                    {/* Deploy Key Section */}
                    <div className="w-full min-w-0 space-y-3 rounded-lg border border-amber-500/30 bg-amber-500/5 p-4">
                        <div className="flex items-start gap-3">
                            <div className="mt-0.5 rounded-md bg-amber-500/10 p-2">
                                <GitBranch className="h-4 w-4 text-amber-600 dark:text-amber-500" />
                            </div>
                            <div className="flex-1 space-y-1">
                                <h4 className="text-sm font-semibold leading-none">Deploy Key Required</h4>
                                <p className="text-xs text-muted-foreground">
                                    Add this SSH key to your GitHub repository to allow BrokeForge to clone it.
                                </p>
                            </div>
                        </div>

                        {deployKey ? (
                            <>
                                <div className="w-full min-w-0 overflow-hidden rounded-md border border-border bg-background">
                                    <pre className="max-h-24 w-full min-w-0 overflow-auto break-all p-3 font-mono text-[10px] leading-relaxed">{deployKey}</pre>
                                </div>

                                <div className="flex gap-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={handleCopyDeployKey}
                                        className="flex-1"
                                    >
                                        {copiedDeployKey ? (
                                            <>
                                                <Check className="mr-2 h-3 w-3" />
                                                Copied!
                                            </>
                                        ) : (
                                            <>
                                                <Copy className="mr-2 h-3 w-3" />
                                                Copy Key
                                            </>
                                        )}
                                    </Button>
                                </div>

                                <details className="group">
                                    <summary className="cursor-pointer text-xs font-medium text-muted-foreground hover:text-foreground">
                                        How to add this key to GitHub
                                    </summary>
                                    <ol className="mt-2 space-y-1.5 pl-4 text-xs text-muted-foreground">
                                        <li>1. Go to your repository on GitHub</li>
                                        <li>2. Navigate to Settings → Deploy keys</li>
                                        <li>3. Click "Add deploy key"</li>
                                        <li>4. Paste the key and save</li>
                                    </ol>
                                </details>
                            </>
                        ) : (
                            <div className="flex items-center gap-2 py-2 text-sm text-muted-foreground">
                                <Loader2 className="h-4 w-4 animate-spin" />
                                <span>Loading deploy key...</span>
                            </div>
                        )}
                    </div>
                </div>
            </CardFormModal>
        </ServerLayout>
    );
}
