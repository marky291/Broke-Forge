import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Separator } from '@/components/ui/separator';
import SiteLayout from '@/layouts/server/site-layout';
import { dashboard } from '@/routes';
import { show as showServer } from '@/routes/servers';
import { type BreadcrumbItem, type ServerSite } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { useEcho } from '@laravel/echo-react';
import { CheckCircle, Clock, Copy, ExternalLink, GitBranch, Key, Loader2, Lock, XCircle } from 'lucide-react';
import { type ReactNode, useState } from 'react';

const statusMeta: Record<string, { badgeClass: string; label: string; icon: ReactNode; description: string }> = {
    active: {
        badgeClass: 'bg-green-500/10 text-green-500 border-green-500/20',
        label: 'Active',
        icon: <CheckCircle className="h-4 w-4 text-green-500" />,
        description: 'The application is serving traffic. Keep an eye on deployments and queued jobs.',
    },
    provisioning: {
        badgeClass: 'bg-blue-500/10 text-blue-500 border-blue-500/20',
        label: 'Provisioning',
        icon: <Loader2 className="h-4 w-4 animate-spin text-blue-500" />,
        description: 'We are preparing the runtime. You will be notified as soon as provisioning finishes.',
    },
    disabled: {
        badgeClass: 'bg-gray-500/10 text-gray-500 border-gray-500/20',
        label: 'Disabled',
        icon: <XCircle className="h-4 w-4 text-gray-500" />,
        description: 'This site is paused. Resume or redeploy when you are ready to serve traffic again.',
    },
    failed: {
        badgeClass: 'bg-red-500/10 text-red-500 border-red-500/20',
        label: 'Failed',
        icon: <XCircle className="h-4 w-4 text-red-500" />,
        description: 'Provisioning failed. Review the logs and retry the deployment when the issue is resolved.',
    },
    default: {
        badgeClass: 'border border-border bg-muted text-muted-foreground',
        label: 'Pending',
        icon: <Clock className="h-4 w-4 text-muted-foreground" />,
        description: 'We have not started provisioning yet. Configure how you want to launch the application.',
    },
};

/**
 * Format date for display.
 */
const formatDate = (dateString: string | null | undefined): string => {
    if (!dateString) return 'Not deployed yet';

    const date = new Date(dateString);
    const dateOptions: Intl.DateTimeFormatOptions = { day: 'numeric', month: 'short', year: 'numeric' };
    const timeOptions: Intl.DateTimeFormatOptions = { hour: '2-digit', minute: '2-digit' };

    return `${date.toLocaleDateString('en-US', dateOptions)} at ${date.toLocaleTimeString('en-US', timeOptions)}`;
};

/**
 * Render the BrokeForge site settings view.
 */
export default function SiteSettings({ site }: { site: ServerSite }) {
    const { post, processing } = useForm();
    const server = site.server!;
    const applicationType = site.applicationType;
    const gitRepository = site.gitRepository;
    const [showDeployKeyDialog, setShowDeployKeyDialog] = useState(false);
    const [generatedDeployKey, setGeneratedDeployKey] = useState<string | null>(null);
    const [generatingDeployKey, setGeneratingDeployKey] = useState(false);
    const [deployKeyError, setDeployKeyError] = useState<string | null>(null);
    const [copiedDeployKey, setCopiedDeployKey] = useState(false);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: dashboard.url() },
        { title: `Server #${server.id}`, href: showServer(server.id).url },
        { title: 'Sites', href: `/servers/${server.id}/sites` },
        { title: 'Settings', href: '#' },
    ];

    const activeStatus = statusMeta[site.status] ?? statusMeta.default;

    const handleUninstall = () => {
        if (!confirm(`Are you sure you want to uninstall ${site.domain}? This will remove the site configuration from the server.`)) {
            return;
        }
        post(`/servers/${server.id}/sites/${site.id}/uninstall`, {
            preserveScroll: true,
        });
    };

    // Listen for real-time site updates via Reverb WebSocket
    useEcho(`sites.${site.id}`, 'ServerSiteUpdated', () => {
        router.reload({ only: ['site'], preserveScroll: true, preserveState: true });
    });

    const handleCancelInstallation = () => {
        router.post(
            `/servers/${server.id}/sites/${site.id}/git/cancel`,
            {},
            {
                onSuccess: () => {
                    router.reload();
                },
            },
        );
    };

    const handleGenerateDeployKey = async () => {
        setGeneratingDeployKey(true);
        setDeployKeyError(null);

        try {
            const response = await fetch(`/servers/${server.id}/sites/${site.id}/deploy-key`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
            });

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.error || 'Failed to generate deploy key');
            }

            setGeneratedDeployKey(data.public_key);
            setShowDeployKeyDialog(true);

            // Reload the page to update the site data
            router.reload({ only: ['site'], preserveScroll: true, preserveState: true });
        } catch (error) {
            setDeployKeyError(error instanceof Error ? error.message : 'An unknown error occurred');
        } finally {
            setGeneratingDeployKey(false);
        }
    };

    const handleCopyDeployKey = () => {
        if (generatedDeployKey) {
            navigator.clipboard.writeText(generatedDeployKey);
            setCopiedDeployKey(true);
            setTimeout(() => setCopiedDeployKey(false), 2000);
        }
    };

    const getRepositoryDeployKeysUrl = (): string | null => {
        if (!gitRepository?.repository) return null;

        // Assumes GitHub format: owner/repo
        const [owner, repo] = gitRepository.repository.split('/');
        if (!owner || !repo) return null;

        return `https://github.com/${owner}/${repo}/settings/keys`;
    };

    // Show provisioning progress if site or Git is installing
    if (site.status === 'provisioning' || site.git_status === 'installing') {
        return (
            <SiteLayout server={server} site={site} breadcrumbs={breadcrumbs}>
                <Head title={`Settings — ${site.domain}`} />
                <div className="space-y-8">
                    <div className="space-y-2">
                        <h1 className="text-2xl font-semibold">Installing Site</h1>
                        <p className="text-sm text-muted-foreground">Setting up nginx and cloning your repository...</p>
                    </div>
                    <Card>
                        <CardContent className="flex flex-col items-center justify-center py-16">
                            <Loader2 className="mb-4 h-12 w-12 animate-spin text-primary" />
                            <h2 className="mb-2 text-lg font-semibold">Installing...</h2>
                            <p className="max-w-md text-center text-sm text-muted-foreground">
                                Configuring nginx and cloning repository. Usually takes 1-2 minutes.
                            </p>

                            <div className="mt-6">
                                <button
                                    onClick={handleCancelInstallation}
                                    className="rounded-md border border-red-500 px-4 py-2 text-sm font-medium text-red-500 transition-colors hover:bg-red-500 hover:text-white"
                                >
                                    Cancel Installation
                                </button>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </SiteLayout>
        );
    }

    // Show installed application dashboard
    if (applicationType === 'application' && gitRepository) {
        return (
            <SiteLayout server={server} site={site} breadcrumbs={breadcrumbs}>
                <Head title={`Settings — ${site.domain}`} />
                <div className="space-y-8">
                    <div className="space-y-2">
                        <div className="flex flex-wrap items-center gap-3">
                            <h1 className="text-2xl font-semibold">{site.domain}</h1>
                            <div className="flex items-center gap-2 text-xs text-muted-foreground">
                                {activeStatus.icon}
                                <Badge className={activeStatus.badgeClass}>{activeStatus.label}</Badge>
                            </div>
                        </div>
                        <p className="text-sm text-muted-foreground">
                            Your application is configured and ready. Manage deployments and settings below.
                        </p>
                    </div>

                    {gitRepository && (
                        <Card>
                            <CardHeader>
                                <div className="flex items-center justify-between">
                                    <div className="flex items-center gap-2.5">
                                        <GitBranch className="h-5 w-5 text-muted-foreground" />
                                        <CardTitle>Git Repository</CardTitle>
                                    </div>
                                    {!site.has_dedicated_deploy_key && (
                                        <Button variant="outline" size="sm" onClick={handleGenerateDeployKey} disabled={generatingDeployKey}>
                                            {generatingDeployKey ? (
                                                <>
                                                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                                    Generating...
                                                </>
                                            ) : (
                                                <>
                                                    <Key className="mr-2 h-4 w-4" />
                                                    Generate Deploy Key
                                                </>
                                            )}
                                        </Button>
                                    )}
                                </div>
                            </CardHeader>
                            <Separator />
                            <CardContent className="space-y-6">
                                {deployKeyError && (
                                    <div className="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700 dark:border-red-900/50 dark:bg-red-950/20 dark:text-red-300">
                                        {deployKeyError}
                                    </div>
                                )}
                                <div className="grid grid-cols-2 gap-6">
                                    <div>
                                        <div className="mb-1.5 text-xs text-muted-foreground">Repository</div>
                                        <div className="text-sm font-medium">{gitRepository.repository}</div>
                                    </div>
                                    <div>
                                        <div className="mb-1.5 text-xs text-muted-foreground">Branch</div>
                                        <div className="text-sm font-medium">{gitRepository.branch}</div>
                                    </div>
                                    <div>
                                        <div className="mb-1.5 text-xs text-muted-foreground">Provider</div>
                                        <div className="text-sm">{gitRepository.provider}</div>
                                    </div>
                                    <div>
                                        <div className="mb-1.5 text-xs text-muted-foreground">PHP Version</div>
                                        <div className="text-sm">PHP {site.php_version}</div>
                                    </div>
                                    <div>
                                        <div className="mb-1.5 text-xs text-muted-foreground">SSL Enabled</div>
                                        <div className="flex items-center gap-1.5 text-sm">
                                            {site.ssl_enabled ? (
                                                <>
                                                    <Lock className="h-3.5 w-3.5 text-green-500" />
                                                    <span>Enabled</span>
                                                </>
                                            ) : (
                                                <span>Disabled</span>
                                            )}
                                        </div>
                                    </div>
                                    <div>
                                        <div className="mb-1.5 text-xs text-muted-foreground">Last Deployed</div>
                                        <div className="text-sm">
                                            {formatDate(gitRepository.lastDeployedAt)}
                                            {gitRepository.lastDeployedSha && (
                                                <>
                                                    {' '}
                                                    •{' '}
                                                    <span className="font-mono text-xs text-muted-foreground">
                                                        {gitRepository.lastDeployedSha.substring(0, 7)}
                                                    </span>
                                                </>
                                            )}
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    )}

                    {/* Uninstall Site Link */}
                    <div className="mt-8 text-right">
                        <button
                            onClick={handleUninstall}
                            disabled={processing}
                            className="text-sm text-red-600 hover:text-red-700 hover:underline disabled:cursor-not-allowed disabled:opacity-50 dark:text-red-500 dark:hover:text-red-400"
                        >
                            {processing ? 'Uninstalling site...' : 'Uninstall Site'}
                        </button>
                    </div>

                    {/* Deploy Key Modal */}
                    <Dialog open={showDeployKeyDialog} onOpenChange={setShowDeployKeyDialog}>
                        <DialogContent className="max-w-2xl">
                            <DialogHeader>
                                <DialogTitle className="flex items-center gap-2">
                                    <Key className="h-5 w-5 text-primary" />
                                    Deploy Key Generated
                                </DialogTitle>
                                <DialogDescription>Add this SSH key to your repository to enable deployments for this site.</DialogDescription>
                            </DialogHeader>
                            <div className="mt-4 space-y-4">
                                {generatedDeployKey && (
                                    <>
                                        <div>
                                            <label className="mb-2 block text-sm font-medium">SSH Public Key</label>
                                            <div className="relative">
                                                <pre className="overflow-x-auto rounded-md border bg-muted p-4 font-mono text-xs">
                                                    {generatedDeployKey}
                                                </pre>
                                                <Button size="sm" variant="outline" className="absolute top-2 right-2" onClick={handleCopyDeployKey}>
                                                    {copiedDeployKey ? (
                                                        <>
                                                            <CheckCircle className="mr-2 h-4 w-4" />
                                                            Copied!
                                                        </>
                                                    ) : (
                                                        <>
                                                            <Copy className="mr-2 h-4 w-4" />
                                                            Copy
                                                        </>
                                                    )}
                                                </Button>
                                            </div>
                                        </div>

                                        <div className="rounded-md border border-blue-200 bg-blue-50 p-4 dark:border-blue-900/50 dark:bg-blue-950/20">
                                            <h4 className="mb-2 font-semibold text-blue-900 dark:text-blue-100">Next Steps</h4>
                                            <ol className="list-inside list-decimal space-y-1 text-sm text-blue-700 dark:text-blue-300">
                                                <li>Copy the SSH key above</li>
                                                <li>Go to your repository's deploy keys settings</li>
                                                <li>Add a new deploy key and paste the SSH key</li>
                                                <li>
                                                    Give it a title like "{site.dedicated_deploy_key_title || `BrokeForge Site - ${site.domain}`}"
                                                </li>
                                                <li>Make sure "Allow write access" is unchecked (read-only)</li>
                                                <li>Save the deploy key</li>
                                            </ol>
                                        </div>

                                        {getRepositoryDeployKeysUrl() && (
                                            <div className="flex justify-center">
                                                <Button variant="outline" asChild>
                                                    <a href={getRepositoryDeployKeysUrl()!} target="_blank" rel="noopener noreferrer">
                                                        <ExternalLink className="mr-2 h-4 w-4" />
                                                        Open Repository Deploy Keys
                                                    </a>
                                                </Button>
                                            </div>
                                        )}
                                    </>
                                )}
                            </div>
                            <div className="mt-6 flex justify-end">
                                <Button variant="outline" onClick={() => setShowDeployKeyDialog(false)}>
                                    Close
                                </Button>
                            </div>
                        </DialogContent>
                    </Dialog>
                </div>
            </SiteLayout>
        );
    }

    // Show static site or site without Git repository
    if (applicationType === 'static' || site.status === 'active') {
        return (
            <SiteLayout server={server} site={site} breadcrumbs={breadcrumbs}>
                <Head title={`Settings — ${site.domain}`} />
                <div className="space-y-8">
                    <div className="space-y-2">
                        <div className="flex flex-wrap items-center gap-3">
                            <h1 className="text-2xl font-semibold">{site.domain}</h1>
                            <div className="flex items-center gap-2 text-xs text-muted-foreground">
                                {activeStatus.icon}
                                <Badge className={activeStatus.badgeClass}>{activeStatus.label}</Badge>
                            </div>
                        </div>
                        <p className="text-sm text-muted-foreground">Your site is active and serving content from {site.document_root}.</p>
                    </div>

                    <Card>
                        <CardHeader>
                            <CardTitle>Site Details</CardTitle>
                        </CardHeader>
                        <Separator />
                        <CardContent className="space-y-6">
                            <div className="grid grid-cols-2 gap-6">
                                <div>
                                    <div className="mb-1.5 text-xs text-muted-foreground">Document Root</div>
                                    <div className="font-mono text-sm">{site.document_root}</div>
                                </div>
                                <div>
                                    <div className="mb-1.5 text-xs text-muted-foreground">PHP Version</div>
                                    <div className="text-sm">PHP {site.php_version}</div>
                                </div>
                                <div>
                                    <div className="mb-1.5 text-xs text-muted-foreground">SSL Enabled</div>
                                    <div className="flex items-center gap-1.5 text-sm">
                                        {site.ssl_enabled ? (
                                            <>
                                                <Lock className="h-3.5 w-3.5 text-green-500" />
                                                <span>Enabled</span>
                                            </>
                                        ) : (
                                            <span>Disabled</span>
                                        )}
                                    </div>
                                </div>
                                <div>
                                    <div className="mb-1.5 text-xs text-muted-foreground">Provisioned</div>
                                    <div className="text-sm">{formatDate(site.provisioned_at)}</div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Uninstall Site Link */}
                    <div className="mt-8 text-right">
                        <button
                            onClick={handleUninstall}
                            disabled={processing}
                            className="text-sm text-red-600 hover:text-red-700 hover:underline disabled:cursor-not-allowed disabled:opacity-50 dark:text-red-500 dark:hover:text-red-400"
                        >
                            {processing ? 'Uninstalling site...' : 'Uninstall Site'}
                        </button>
                    </div>
                </div>
            </SiteLayout>
        );
    }

    // Fallback: still provisioning or unknown state
    return (
        <SiteLayout server={server} site={site} breadcrumbs={breadcrumbs}>
            <Head title={`Settings — ${site.domain}`} />
            <div className="space-y-8">
                <div className="space-y-2">
                    <h1 className="text-2xl font-semibold">Provisioning...</h1>
                    <p className="text-sm text-muted-foreground">Your site is being configured. This page will update automatically.</p>
                </div>
                <Card>
                    <CardContent className="flex flex-col items-center justify-center py-16">
                        <Loader2 className="mb-4 h-12 w-12 animate-spin text-primary" />
                        <p className="text-sm text-muted-foreground">Please wait...</p>
                    </CardContent>
                </Card>
            </div>
        </SiteLayout>
    );
}
