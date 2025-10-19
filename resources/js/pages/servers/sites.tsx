import { CardList, type CardListAction } from '@/components/card-list';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { CardFormModal } from '@/components/ui/card-form-modal';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
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
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useEcho } from '@laravel/echo-react';
import { AlertCircle, CheckCircle, CheckCircle2, Clock, Eye, GitBranch, Globe, Loader2, Lock, RefreshCw, Trash2, XCircle } from 'lucide-react';
import { useEffect, useState } from 'react';

type ServerSite = {
    id: number;
    domain: string;
    document_root: string;
    php_version: string;
    ssl_enabled: boolean;
    status: string;
    provisioned_at: string | null;
    provisioned_at_human?: string | null;
    last_deployed_at: string | null;
    last_deployed_at_human?: string | null;
    configuration?: {
        git_repository?: {
            provider?: string;
            repository?: string;
            branch?: string;
        };
        application_type?: string;
    };
    git_status?: string;
    error_log?: string | null;
};

type ServerType = {
    id: number;
    vanity_name: string;
    public_ip: string;
    ssh_port: number;
    private_ip?: string | null;
    connection: string;
    created_at: string;
    updated_at: string;
    sites: ServerSite[];
    latestMetrics?: {
        cpu_usage: number;
        memory_total_mb: number;
        memory_used_mb: number;
        memory_usage_percentage: number;
        storage_total_gb: number;
        storage_used_gb: number;
        storage_usage_percentage: number;
        collected_at: string;
    } | null;
};

type SitesProps = {
    server: ServerType;
};

export default function Sites({ server }: SitesProps) {
    const [showAddSiteDialog, setShowAddSiteDialog] = useState(false);
    const [showErrorDialog, setShowErrorDialog] = useState(false);
    const [showDeleteDialog, setShowDeleteDialog] = useState(false);
    const [selectedSite, setSelectedSite] = useState<ServerSite | null>(null);
    const [isDeleting, setIsDeleting] = useState(false);
    const [repositories, setRepositories] = useState<string[]>([]);
    const [loadingRepositories, setLoadingRepositories] = useState(false);
    const [branches, setBranches] = useState<string[]>([]);
    const [loadingBranches, setLoadingBranches] = useState(false);
    const [githubConnected, setGithubConnected] = useState(false);
    const [clearingCache, setClearingCache] = useState(false);
    const { flash } = usePage<{ flash: { success?: string; error?: string; open_add_site_modal?: boolean } }>().props;
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

    // Listen for real-time site updates via Reverb WebSocket
    useEcho('sites', 'ServerSiteUpdated', () => {
        router.reload({ only: ['server'], preserveScroll: true });
    });

    // Reopen modal after GitHub OAuth redirect
    useEffect(() => {
        if (flash?.open_add_site_modal) {
            setShowAddSiteDialog(true);
        }
    }, [flash?.open_add_site_modal]);

    // Fetch GitHub repositories when modal opens
    useEffect(() => {
        if (showAddSiteDialog && repositories.length === 0) {
            setLoadingRepositories(true);
            fetch(`/servers/${server.id}/github/repositories`)
                .then((res) => res.json())
                .then((data) => {
                    setRepositories(data.repositories || []);
                    setGithubConnected(data.connected || false);
                })
                .catch((err) => {
                    console.error('Failed to fetch GitHub repositories:', err);
                    setGithubConnected(false);
                })
                .finally(() => setLoadingRepositories(false));
        }
    }, [showAddSiteDialog, server.id, repositories]);

    // Fetch branches when repository is selected
    const fetchBranches = (repository: string) => {
        if (!repository) {
            setBranches([]);
            return;
        }

        // Parse owner and repo from "owner/repo" format
        const [owner, repo] = repository.split('/');
        if (!owner || !repo) {
            console.error('Invalid repository format:', repository);
            return;
        }

        setLoadingBranches(true);
        fetch(`/servers/${server.id}/github/repositories/${owner}/${repo}/branches`)
            .then((res) => res.json())
            .then((data) => {
                setBranches(data.branches || []);
                // Auto-select first branch or default branch
                if (data.branches && data.branches.length > 0) {
                    const defaultBranch = data.branches.find((b: string) => b === 'main' || b === 'master') || data.branches[0];
                    form.setData('git_branch', defaultBranch);
                }
            })
            .catch((err) => {
                console.error('Failed to fetch branches:', err);
                setBranches([]);
            })
            .finally(() => setLoadingBranches(false));
    };

    // Clear cache and refresh repositories
    const handleClearCache = () => {
        setClearingCache(true);
        // Clear local state
        setRepositories([]);
        setBranches([]);
        form.setData('git_repository', '');
        form.setData('git_branch', 'main');

        // Fetch fresh data with cache-busting timestamp
        fetch(`/servers/${server.id}/github/repositories?_=${Date.now()}`)
            .then((res) => res.json())
            .then((data) => {
                setRepositories(data.repositories || []);
                setGithubConnected(data.connected || false);
            })
            .catch((err) => {
                console.error('Failed to fetch GitHub repositories:', err);
                setGithubConnected(false);
            })
            .finally(() => setClearingCache(false));
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

    const handleViewError = (site: ServerSite) => {
        setSelectedSite(site);
        setShowErrorDialog(true);
    };

    const handleDeleteClick = (site: ServerSite) => {
        setSelectedSite(site);
        setShowDeleteDialog(true);
    };

    const handleDeleteConfirm = () => {
        if (!selectedSite) return;

        setIsDeleting(true);
        router.delete(`/servers/${server.id}/sites/${selectedSite.id}`, {
            preserveScroll: true,
            onFinish: () => {
                setIsDeleting(false);
                setShowDeleteDialog(false);
                setSelectedSite(null);
            },
        });
    };

    const getStatusBadge = (site: ServerSite) => {
        const status = site.status;

        switch (status) {
            case 'active':
                return null;
            case 'pending':
                return (
                    <Badge variant="outline" className="inline-flex items-center gap-1.5 border-amber-200 bg-amber-50 px-2.5 py-0.5 text-xs font-medium text-amber-700">
                        <Clock className="h-3 w-3" />
                        Pending
                    </Badge>
                );
            case 'provisioning':
                return (
                    <Badge variant="outline" className="inline-flex items-center gap-1.5 border-blue-200 bg-blue-50 px-2.5 py-0.5 text-xs font-medium text-blue-700">
                        <Loader2 className="h-3 w-3 animate-spin" />
                        Provisioning
                    </Badge>
                );
            case 'failed':
                return (
                    <div className="inline-flex items-center gap-2">
                        <Badge variant="outline" className="inline-flex items-center gap-1.5 border-red-200 bg-red-50 px-2.5 py-0.5 text-xs font-medium text-red-700">
                            <XCircle className="h-3 w-3" />
                            Failed
                        </Badge>
                        <div className="inline-flex items-center gap-1.5">
                            {site.error_log && (
                                <button
                                    onClick={(e) => {
                                        e.preventDefault();
                                        e.stopPropagation();
                                        handleViewError(site);
                                    }}
                                    className="inline-flex items-center gap-1 text-xs text-red-600 hover:text-red-700 hover:underline"
                                    title="View error details"
                                >
                                    <Eye className="h-3.5 w-3.5" />
                                    View Error
                                </button>
                            )}
                            <button
                                onClick={(e) => {
                                    e.preventDefault();
                                    e.stopPropagation();
                                    handleDeleteClick(site);
                                }}
                                className="inline-flex items-center gap-1 text-xs text-red-600 hover:text-red-700 hover:underline"
                                title="Delete site"
                            >
                                <Trash2 className="h-3.5 w-3.5" />
                                Delete
                            </button>
                        </div>
                    </div>
                );
            case 'disabled':
                return (
                    <Badge variant="outline" className="border-gray-200 bg-gray-50 px-2.5 py-0.5 text-xs font-medium text-gray-700">
                        Disabled
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
                <CardList<ServerSite>
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
                    onAddClick={() => setShowAddSiteDialog(true)}
                    addButtonLabel="Add Site"
                    items={server.sites}
                    keyExtractor={(site) => site.id}
                    onItemClick={(site) => {
                        if (site.status === 'active') {
                            router.visit(showSite({ server: server.id, site: site.id }).url);
                        }
                    }}
                    renderItem={(site) => (
                        <div className="flex items-center justify-between gap-3">
                            {/* Left: Icon + Site Info */}
                            <div className="flex min-w-0 flex-1 items-center gap-3">
                                <div className="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg bg-primary/10">
                                    <Globe className="h-5 w-5 text-primary" />
                                </div>
                                <div className="min-w-0 flex-1">
                                    <div className="text-sm font-medium text-foreground">{site.domain}</div>
                                    {site.configuration?.git_repository?.repository ? (
                                        <div className="flex items-center gap-1.5">
                                            <GitBranch className="h-3.5 w-3.5 flex-shrink-0 text-muted-foreground/60" />
                                            <p className="truncate text-xs text-muted-foreground">
                                                {site.configuration.git_repository.repository}
                                                {site.configuration.git_repository.branch && (
                                                    <span className="text-muted-foreground/60"> • {site.configuration.git_repository.branch}</span>
                                                )}
                                            </p>
                                        </div>
                                    ) : (
                                        <p className="text-xs text-muted-foreground/60">No repository configured</p>
                                    )}
                                </div>
                            </div>

                            {/* Right: Status Badge + Metadata */}
                            <div className="flex flex-shrink-0 items-center gap-3">
                                {getStatusBadge(site)}
                                <div className="hidden items-center gap-2 text-xs text-muted-foreground md:flex">
                                    <Lock className={`h-4 w-4 ${site.ssl_enabled ? 'text-green-600' : 'text-muted-foreground/30'}`} />
                                    <span>PHP {site.php_version}</span>
                                </div>
                            </div>
                        </div>
                    )}
                    actions={(site) => {
                        const actions: CardListAction[] = [];

                        // Active sites get "Delete Site" only (clicking item navigates to details)
                        if (site.status === 'active') {
                            actions.push({
                                label: 'Delete Site',
                                onClick: () => handleDeleteClick(site),
                                variant: 'destructive',
                                icon: <Trash2 className="h-4 w-4" />,
                            });
                        }

                        // Failed sites get "View Error" and "Delete Site"
                        if (site.status === 'failed') {
                            if (site.error_log) {
                                actions.push({
                                    label: 'View Error',
                                    onClick: () => handleViewError(site),
                                    icon: <Eye className="h-4 w-4" />,
                                });
                            }
                            actions.push({
                                label: 'Delete Site',
                                onClick: () => handleDeleteClick(site),
                                variant: 'destructive',
                                icon: <Trash2 className="h-4 w-4" />,
                            });
                        }

                        return actions;
                    }}
                    emptyStateMessage="No sites configured on this server yet."
                    emptyStateIcon={<Globe className="h-6 w-6 text-muted-foreground" />}
                />
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

                    {/* GitHub Connection Alert */}
                    {!githubConnected && !loadingRepositories && (
                        <Alert variant="default" className="border-blue-200 bg-blue-50 dark:border-blue-900/50 dark:bg-blue-950/20">
                            <AlertCircle className="h-4 w-4 text-blue-600 dark:text-blue-500" />
                            <AlertDescription>
                                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                    <div>
                                        <p className="font-medium text-blue-900 dark:text-blue-100">Connect GitHub to continue</p>
                                        <p className="mt-1 text-xs text-blue-700 dark:text-blue-300">
                                            GitHub authentication is required to create sites with repositories.
                                        </p>
                                    </div>
                                    <Button
                                        type="button"
                                        onClick={() => {
                                            window.location.href = `/servers/${server.id}/source-providers/github/connect`;
                                        }}
                                        className="shrink-0"
                                    >
                                        Connect GitHub
                                    </Button>
                                </div>
                            </AlertDescription>
                        </Alert>
                    )}

                    {/* Git Repository Section */}
                    <div className="grid w-full min-w-0 grid-cols-1 gap-4 sm:grid-cols-2">
                        <div className="min-w-0 space-y-2">
                            <div className="flex items-center gap-2">
                                <Label htmlFor="git_repository">Git Repository</Label>
                                {loadingRepositories && <Loader2 className="h-3 w-3 animate-spin text-muted-foreground" />}
                                {githubConnected && !loadingRepositories && (
                                    <button
                                        type="button"
                                        onClick={handleClearCache}
                                        disabled={clearingCache}
                                        className="inline-flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground disabled:opacity-50"
                                        title="Refresh repositories"
                                    >
                                        <RefreshCw className={`h-3 w-3 ${clearingCache ? 'animate-spin' : ''}`} />
                                    </button>
                                )}
                            </div>
                            {githubConnected && repositories.length > 0 ? (
                                <Select
                                    value={form.data.git_repository}
                                    onValueChange={(value) => {
                                        form.setData('git_repository', value);
                                        // Fetch branches for the selected repository
                                        fetchBranches(value);
                                    }}
                                    disabled={form.processing || loadingRepositories || !githubConnected}
                                >
                                    <SelectTrigger id="git_repository">
                                        <SelectValue placeholder="Select repository" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {repositories.map((repo) => (
                                            <SelectItem key={repo} value={repo}>
                                                {repo}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            ) : (
                                <Input
                                    id="git_repository"
                                    placeholder={loadingRepositories ? 'Loading repositories...' : githubConnected ? 'No repositories found' : 'owner/repo'}
                                    value={form.data.git_repository}
                                    onChange={(e) => form.setData('git_repository', e.target.value)}
                                    disabled={form.processing || loadingRepositories || !githubConnected}
                                />
                            )}
                            {form.errors.git_repository && <p className="text-sm text-red-500">{form.errors.git_repository}</p>}
                        </div>

                        <div className="min-w-0 space-y-2">
                            <div className="flex items-center gap-2">
                                <Label htmlFor="git_branch">Branch</Label>
                                {loadingBranches && <Loader2 className="h-3 w-3 animate-spin text-muted-foreground" />}
                            </div>
                            {form.data.git_repository && branches.length > 0 ? (
                                <Select
                                    value={form.data.git_branch}
                                    onValueChange={(value) => form.setData('git_branch', value)}
                                    disabled={form.processing || loadingBranches || !githubConnected}
                                >
                                    <SelectTrigger id="git_branch">
                                        <SelectValue placeholder="Select branch" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {branches.map((branch) => (
                                            <SelectItem key={branch} value={branch}>
                                                {branch}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            ) : (
                                <Input
                                    id="git_branch"
                                    placeholder={loadingBranches ? 'Loading branches...' : 'main'}
                                    value={form.data.git_branch}
                                    onChange={(e) => form.setData('git_branch', e.target.value)}
                                    disabled={form.processing || loadingBranches || !githubConnected}
                                />
                            )}
                            {form.errors.git_branch && <p className="text-sm text-red-500">{form.errors.git_branch}</p>}
                        </div>
                    </div>
                </div>
            </CardFormModal>

            {/* Error Viewing Modal */}
            <Dialog open={showErrorDialog} onOpenChange={setShowErrorDialog}>
                <DialogContent className="max-w-3xl">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            <AlertCircle className="h-5 w-5 text-red-600" />
                            Site Installation Error
                        </DialogTitle>
                        <DialogDescription>
                            {selectedSite && (
                                <span>
                                    Error details for <span className="font-semibold">{selectedSite.domain}</span>
                                </span>
                            )}
                        </DialogDescription>
                    </DialogHeader>
                    <div className="mt-4">
                        {selectedSite?.error_log ? (
                            <div className="max-h-96 overflow-auto rounded-md border border-red-200 bg-red-50 p-4 dark:border-red-900/50 dark:bg-red-950/20">
                                <pre className="whitespace-pre-wrap break-words font-mono text-xs text-red-900 dark:text-red-300">
                                    {selectedSite.error_log}
                                </pre>
                            </div>
                        ) : (
                            <div className="rounded-md border border-neutral-200 bg-neutral-50 p-4 text-sm text-muted-foreground dark:border-neutral-800 dark:bg-neutral-900/50">
                                No error details available.
                            </div>
                        )}
                    </div>
                    <div className="mt-6 flex justify-end">
                        <Button variant="outline" onClick={() => setShowErrorDialog(false)}>
                            Close
                        </Button>
                    </div>
                </DialogContent>
            </Dialog>

            {/* Delete Confirmation Modal */}
            <Dialog open={showDeleteDialog} onOpenChange={setShowDeleteDialog}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            <AlertCircle className="h-5 w-5 text-red-600" />
                            Delete Failed Site
                        </DialogTitle>
                        <DialogDescription>
                            {selectedSite && (
                                <span>
                                    Are you sure you want to delete <span className="font-semibold">{selectedSite.domain}</span>? This will clean up any
                                    partial installation files from the server.
                                </span>
                            )}
                        </DialogDescription>
                    </DialogHeader>
                    <div className="mt-6 flex justify-end gap-3">
                        <Button variant="outline" onClick={() => setShowDeleteDialog(false)} disabled={isDeleting}>
                            Cancel
                        </Button>
                        <Button variant="destructive" onClick={handleDeleteConfirm} disabled={isDeleting}>
                            {isDeleting ? (
                                <>
                                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                    Deleting...
                                </>
                            ) : (
                                <>
                                    <Trash2 className="mr-2 h-4 w-4" />
                                    Delete Site
                                </>
                            )}
                        </Button>
                    </div>
                </DialogContent>
            </Dialog>
        </ServerLayout>
    );
}
