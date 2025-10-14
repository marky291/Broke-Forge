import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import SiteLayout from '@/layouts/server/site-layout';
import { dashboard } from '@/routes';
import { show as showServer } from '@/routes/servers';
import { type BreadcrumbItem, type ServerSite } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { useEcho } from '@laravel/echo-react';
import { CheckCircle, Clock, GitBranch, Loader2, Lock, Trash2, XCircle } from 'lucide-react';
import { type ReactNode } from 'react';

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
 * Render the BrokeForge site application view.
 */
export default function SiteApplication({ site }: { site: ServerSite }) {
    const { post, processing } = useForm();
    const server = site.server!;
    const applicationType = site.applicationType;
    const gitRepository = site.gitRepository;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: dashboard.url() },
        { title: `Server #${server.id}`, href: showServer(server.id).url },
        { title: 'Sites', href: `/servers/${server.id}/sites` },
        { title: 'Application', href: '#' },
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

    // Show provisioning progress if site or Git is installing
    if (site.status === 'provisioning' || site.git_status === 'installing') {
        return (
            <SiteLayout server={server} site={site} breadcrumbs={breadcrumbs}>
                <Head title={`Application — ${site.domain}`} />
                <div className="space-y-8">
                    <div className="space-y-2">
                        <h1 className="text-2xl font-semibold">Installing Application</h1>
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
                <Head title={`Application — ${site.domain}`} />
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
                                <div className="flex items-center gap-2.5">
                                    <GitBranch className="h-5 w-5 text-muted-foreground" />
                                    <CardTitle>Git Repository</CardTitle>
                                </div>
                            </CardHeader>
                            <Separator />
                            <CardContent className="space-y-6">
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
                </div>
            </SiteLayout>
        );
    }

    // Show static site or site without Git repository
    if (applicationType === 'static' || site.status === 'active') {
        return (
            <SiteLayout server={server} site={site} breadcrumbs={breadcrumbs}>
                <Head title={`Application — ${site.domain}`} />
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
            <Head title={`Application — ${site.domain}`} />
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
