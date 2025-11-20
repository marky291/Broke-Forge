import { CardList, type CardListAction } from '@/components/card-list';
import { FrameworkIcon } from '@/components/framework-icon';
import { SiteAvatar } from '@/components/site-avatar';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { CardBadge } from '@/components/ui/card-badge';
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
import { AlertCircle, ArrowUpDown, CheckCircle2, Eye, FileEdit, Globe, Loader2, RefreshCw, Trash2, X, XCircle } from 'lucide-react';
import { useEffect, useState } from 'react';

type ServerSite = {
    id: number;
    domain: string;
    document_root: string;
    php_version: string;
    ssl_enabled: boolean;
    is_default: boolean;
    default_site_status?: string | null;
    status: string;
    installed_at: string | null;
    installed_at_human?: string | null;
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
    site_framework: {
        id: number;
        name: string;
        slug: string;
        env: {
            file_path: string | null;
            supports: boolean;
        };
        requirements: {
            database: boolean;
            redis: boolean;
            nodejs: boolean;
            composer: boolean;
        };
    };
};

type AvailableFramework = {
    id: number;
    name: string;
    slug: string;
    requirements: {
        database: boolean;
        redis: boolean;
        nodejs: boolean;
        composer: boolean;
    };
};

type Database = {
    id: number;
    name: string;
    type: string;
    version: string;
    port: number;
    status: string;
};

type NodeVersion = {
    id: number;
    version: string;
    status: string;
    is_default: boolean;
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
    databases: Database[];
    nodes: NodeVersion[];
    availableFrameworks: AvailableFramework[];
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
    const [selectedFramework, setSelectedFramework] = useState<AvailableFramework | null>(null);
    const { flash } = usePage<{ flash: { success?: string; error?: string; open_add_site_modal?: boolean } }>().props;

    // Set default framework to Laravel if available
    useEffect(() => {
        if (server.availableFrameworks.length > 0 && !selectedFramework) {
            const laravel = server.availableFrameworks.find(f => f.slug === 'laravel') || server.availableFrameworks[0];
            setSelectedFramework(laravel);
        }
    }, [server.availableFrameworks]);

    const form = useForm({
        domain: '',
        available_framework_id: selectedFramework?.id || '',
        php_version: '8.3',
        ssl: false,
        git_repository: '',
        git_branch: 'main',
        database_id: '',
        node_id: '',
    } as {
        domain: string;
        available_framework_id: number | string;
        php_version: string;
        ssl: boolean;
        git_repository: string;
        git_branch: string;
        database_id: number | string;
        node_id: number | string;
    });

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: dashboard.url() },
        { title: `Server #${server.id}`, href: showServer(server.id).url },
        { title: 'Sites', href: '#' },
    ];

    // Sync framework ID with form when selectedFramework changes
    useEffect(() => {
        if (selectedFramework) {
            form.setData('available_framework_id', selectedFramework.id);
        }
    }, [selectedFramework]);

    // Handle framework change
    const handleFrameworkChange = (frameworkId: string) => {
        const framework = server.availableFrameworks.find(f => f.id === parseInt(frameworkId));
        if (framework) {
            setSelectedFramework(framework);
            // Clear requirement fields when switching frameworks
            form.setData({
                ...form.data,
                available_framework_id: framework.id,
                database_id: '',
                node_id: '',
            });
        }
    };

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

    // Fetch GitHub repositories when modal opens (skip for WordPress)
    useEffect(() => {
        if (showAddSiteDialog && repositories.length === 0 && selectedFramework?.slug !== 'wordpress') {
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
    }, [showAddSiteDialog, server.id, repositories, selectedFramework]);

    // Auto-disable SSL for non-domain sites
    useEffect(() => {
        if (form.data.domain && !form.data.domain.includes('.') && form.data.ssl) {
            form.setData('ssl', false);
        }
    }, [form.data.domain]);

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

    const handleSetDefault = (site: ServerSite) => {
        if (site.is_default) return;
        router.patch(`/servers/${server.id}/sites/${site.id}/set-default`, {}, {
            preserveScroll: true,
        });
    };

    const handleUnsetDefault = (site: ServerSite) => {
        if (!site.is_default) return;
        router.patch(`/servers/${server.id}/sites/${site.id}/unset-default`, {}, {
            preserveScroll: true,
        });
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
                    renderItem={(site) => {
                        // Show deployment timestamp for operational sites (active/success), badge for transitional states
                        const showStatusBadge = !['active', 'success'].includes(site.status);

                        // Map site status to CardBadge variants
                        const getBadgeVariant = (status: string): string => {
                            const statusMap: Record<string, string> = {
                                provisioning: 'installing',
                                paused: 'inactive',
                            };
                            return statusMap[status] || status;
                        };

                        return (
                            <div className="flex items-center justify-between gap-3">
                                {/* Left: Site Avatar + Info */}
                                <div className="flex min-w-0 flex-1 items-center gap-3">
                                    <SiteAvatar domain={site.domain} />
                                    <div className="min-w-0 flex-1">
                                        <div className="flex items-center gap-2">
                                            <div className="truncate text-sm font-medium">{site.domain}</div>
                                            {site.is_default && !site.default_site_status && (
                                                <span className="rounded-full bg-primary/10 px-2 py-0.5 text-xs font-medium text-primary">
                                                    Default
                                                </span>
                                            )}
                                            {site.default_site_status && site.default_site_status !== 'active' && (
                                                <span
                                                    className={
                                                        site.default_site_status === 'installing'
                                                            ? 'inline-flex items-center gap-1 rounded-full bg-blue-500/10 px-2 py-0.5 text-xs font-medium text-blue-600'
                                                            : site.default_site_status === 'removing'
                                                              ? 'inline-flex items-center gap-1 rounded-full bg-amber-500/10 px-2 py-0.5 text-xs font-medium text-amber-600'
                                                              : site.default_site_status === 'failed'
                                                                ? 'inline-flex items-center gap-1 rounded-full bg-red-500/10 px-2 py-0.5 text-xs font-medium text-red-600'
                                                                : 'inline-flex items-center gap-1 rounded-full bg-gray-500/10 px-2 py-0.5 text-xs font-medium text-gray-600'
                                                    }
                                                >
                                                    {site.default_site_status === 'installing' && <Loader2 className="h-3 w-3 animate-spin" />}
                                                    {site.default_site_status === 'removing' && <Loader2 className="h-3 w-3 animate-spin" />}
                                                    {site.default_site_status === 'failed' && <AlertCircle className="h-3 w-3" />}
                                                    {site.default_site_status === 'installing' && 'Setting Default...'}
                                                    {site.default_site_status === 'removing' && 'Removing Default...'}
                                                    {site.default_site_status === 'failed' && 'Default Operation Failed'}
                                                </span>
                                            )}
                                            {site.is_default && site.default_site_status === 'active' && (
                                                <span className="rounded-full bg-primary/10 px-2 py-0.5 text-xs font-medium text-primary">
                                                    Default
                                                </span>
                                            )}
                                        </div>
                                        <div className="mt-1 flex items-center gap-3 text-xs text-muted-foreground">
                                            {site.configuration?.git_repository?.repository ? (
                                                <>
                                                    <span>{site.configuration.git_repository.repository}</span>
                                                    {site.configuration.git_repository.branch && (
                                                        <>
                                                            <span>•</span>
                                                            <span>{site.configuration.git_repository.branch}</span>
                                                        </>
                                                    )}
                                                </>
                                            ) : (
                                                <span>No repository configured</span>
                                            )}
                                            <span>•</span>
                                            <span>PHP {site.php_version}</span>
                                            {site.ssl_enabled && (
                                                <>
                                                    <span>•</span>
                                                    <span>SSL</span>
                                                </>
                                            )}
                                        </div>
                                    </div>
                                </div>

                                {/* Right: Status Badge or Deployment Timestamp */}
                                {showStatusBadge ? (
                                    <CardBadge variant={getBadgeVariant(site.status) as any} />
                                ) : (
                                    <div className="flex-shrink-0 text-xs text-muted-foreground">
                                        {site.last_deployed_at_human ? `Deployed ${site.last_deployed_at_human}` : 'Not deployed'}
                                    </div>
                                )}
                            </div>
                        );
                    }}
                    actions={(site) => {
                        const actions: CardListAction[] = [];
                        const isInTransition = ['provisioning', 'installing', 'updating', 'removing'].includes(site.status);
                        const isDefaultTransitioning =
                            site.default_site_status && ['installing', 'removing'].includes(site.default_site_status);

                        // Active sites get "Set as Default" or "Unset as Default" and "Delete Site"
                        if (site.status === 'active') {
                            // Add Edit Environment action if framework supports it
                            if (site.site_framework.env.supports) {
                                actions.push({
                                    label: 'Edit Environment',
                                    onClick: () => {
                                        router.visit(route('servers.sites.environment.edit', { server: server.id, site: site.id }));
                                    },
                                    disabled: isInTransition,
                                    icon: <FileEdit className="h-4 w-4" />,
                                });
                            }

                            if (!site.is_default) {
                                actions.push({
                                    label: 'Set as Default',
                                    onClick: () => handleSetDefault(site),
                                    disabled: isInTransition || !!isDefaultTransitioning,
                                    icon: <ArrowUpDown className="h-4 w-4" />,
                                });
                            } else {
                                actions.push({
                                    label: 'Unset as Default',
                                    onClick: () => handleUnsetDefault(site),
                                    disabled: isInTransition || !!isDefaultTransitioning,
                                    icon: <X className="h-4 w-4" />,
                                });
                            }
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
                        <Label htmlFor="domain">Name or Domain</Label>
                        <Input
                            id="domain"
                            placeholder="example.com or my-project"
                            value={form.data.domain}
                            onChange={(e) => form.setData('domain', e.target.value)}
                            disabled={form.processing}
                        />
                        {form.errors.domain && <p className="text-sm text-red-500">{form.errors.domain}</p>}
                    </div>

                    {/* Framework Selection */}
                    <div className="w-full min-w-0 space-y-2">
                        <Label htmlFor="framework">Framework</Label>
                        <Select
                            value={selectedFramework?.id.toString() || ''}
                            onValueChange={handleFrameworkChange}
                            disabled={form.processing}
                        >
                            <SelectTrigger id="framework">
                                <SelectValue placeholder="Select framework" />
                            </SelectTrigger>
                            <SelectContent>
                                {server.availableFrameworks.map((framework) => (
                                    <SelectItem key={framework.id} value={framework.id.toString()}>
                                        <div className="flex items-center gap-2">
                                            <FrameworkIcon framework={framework.slug as any} size="sm" />
                                            <span>{framework.name}</span>
                                        </div>
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {form.errors.available_framework_id && <p className="text-sm text-red-500">{form.errors.available_framework_id}</p>}
                    </div>

                    <div className="grid w-full min-w-0 grid-cols-1 gap-4 sm:grid-cols-2">
                        {/* Show PHP Version only if framework is PHP-based (not Static HTML) */}
                        {selectedFramework && selectedFramework.slug !== 'static-html' && (
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
                        )}

                        <div className="min-w-0 space-y-2">
                            <Label htmlFor="ssl">SSL Certificate</Label>
                            <div className="flex h-10 items-center">
                                <div className="flex items-center space-x-2">
                                    <Switch
                                        id="ssl"
                                        checked={form.data.ssl}
                                        onCheckedChange={(checked) => form.setData('ssl', checked)}
                                        disabled={form.processing || !form.data.domain.includes('.')}
                                    />
                                    <Label htmlFor="ssl" className="cursor-pointer text-sm font-normal">
                                        Enable HTTPS
                                    </Label>
                                </div>
                            </div>
                            {form.data.domain && !form.data.domain.includes('.') && (
                                <p className="text-xs text-muted-foreground">SSL requires a domain name (e.g., example.com)</p>
                            )}
                        </div>
                    </div>

                    {/* Framework Requirements Section */}
                    {selectedFramework && (selectedFramework.requirements.database || selectedFramework.requirements.nodejs) && (
                        <div className="grid w-full min-w-0 grid-cols-1 gap-4 sm:grid-cols-2">
                            {/* Database Selection */}
                            {selectedFramework.requirements.database && (
                                <div className="min-w-0 space-y-2">
                                    <Label htmlFor="database_id">
                                        Database <span className="text-red-500">*</span>
                                    </Label>
                                    <Select
                                        value={form.data.database_id.toString()}
                                        onValueChange={(value) => form.setData('database_id', parseInt(value))}
                                        disabled={form.processing}
                                    >
                                        <SelectTrigger id="database_id">
                                            <SelectValue placeholder="Select database" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {server.databases.filter(db => db.status === 'active').map((database) => (
                                                <SelectItem key={database.id} value={database.id.toString()}>
                                                    {database.name} ({database.type} {database.version})
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {form.errors.database_id && <p className="text-sm text-red-500">{form.errors.database_id}</p>}
                                    {server.databases.filter(db => db.status === 'active').length === 0 && (
                                        <p className="text-xs text-amber-600">No active databases found. Create one first.</p>
                                    )}
                                </div>
                            )}

                            {/* Node.js Selection */}
                            {selectedFramework.requirements.nodejs && (
                                <div className="min-w-0 space-y-2">
                                    <Label htmlFor="node_id">
                                        Node.js Version <span className="text-red-500">*</span>
                                    </Label>
                                    <Select
                                        value={form.data.node_id.toString()}
                                        onValueChange={(value) => form.setData('node_id', parseInt(value))}
                                        disabled={form.processing}
                                    >
                                        <SelectTrigger id="node_id">
                                            <SelectValue placeholder="Select Node.js version" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {server.nodes.filter(node => node.status === 'active').map((node) => (
                                                <SelectItem key={node.id} value={node.id.toString()}>
                                                    Node.js {node.version} {node.is_default ? '(Default)' : ''}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {form.errors.node_id && <p className="text-sm text-red-500">{form.errors.node_id}</p>}
                                    {server.nodes.filter(node => node.status === 'active').length === 0 && (
                                        <p className="text-xs text-amber-600">No active Node.js versions found. Install one first.</p>
                                    )}
                                </div>
                            )}
                        </div>
                    )}

                    {/* GitHub Connection Alert */}
                    {selectedFramework && selectedFramework.slug !== 'wordpress' && !githubConnected && !loadingRepositories && (
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
                    {selectedFramework && selectedFramework.slug !== 'wordpress' && (
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
                                    placeholder={
                                        loadingRepositories ? 'Loading repositories...' : githubConnected ? 'No repositories found' : 'owner/repo'
                                    }
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
                    )}
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
                                <pre className="font-mono text-xs break-words whitespace-pre-wrap text-red-900 dark:text-red-300">
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
                                    Are you sure you want to delete <span className="font-semibold">{selectedSite.domain}</span>? This will clean up
                                    any partial installation files from the server.
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
