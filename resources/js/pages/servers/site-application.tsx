import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { CardContainer } from '@/components/ui/card-container';
import { Separator } from '@/components/ui/separator';
import SiteLayout from '@/layouts/server/site-layout';
import { dashboard } from '@/routes';
import { show as showServer } from '@/routes/servers';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { CheckCircle, GitBranch, Loader2, Lock, XCircle } from 'lucide-react';
import { type ReactNode } from 'react';

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
    configuration: Record<string, unknown> | null;
    provisioned_at: string | null;
    git_status?: string;
    last_deployed_at?: string;
    created_at: string;
    updated_at: string;
};

type GitRepository = {
    provider: string;
    repository: string;
    branch: string;
    deployKey: string;
    lastDeployedSha: string | null;
    lastDeployedAt: string | null;
};

type SiteApplicationProps = {
    server: ServerType;
    site: ServerSite;
    applicationType: string | null;
    gitRepository?: GitRepository | null;
};

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
export default function SiteApplication({ server, site, applicationType, gitRepository }: SiteApplicationProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: dashboard.url() },
        { title: `Server #${server.id}`, href: showServer(server.id).url },
        { title: 'Sites', href: `/servers/${server.id}/sites` },
        { title: 'Application', href: '#' },
    ];

    const activeStatus = statusMeta[site.status] ?? statusMeta.default;

    // Show provisioning progress if site or Git is installing
    if (site.status === 'provisioning' || site.git_status === 'installing') {
        return (
            <SiteLayout server={server} site={site} breadcrumbs={breadcrumbs}>
                <Head title={`Application — ${site.domain}`} />
                <div className="space-y-8">
                    <div className="space-y-2">
                        <h1 className="text-2xl font-semibold">Installing Application</h1>
                        <p className="text-sm text-muted-foreground">
                            Setting up nginx and cloning your repository...
                        </p>
                    </div>
                    <Card>
                        <CardContent className="flex flex-col items-center justify-center py-16">
                            <Loader2 className="mb-4 h-12 w-12 animate-spin text-primary" />
                            <h2 className="mb-2 text-lg font-semibold">Installing...</h2>
                            <p className="max-w-md text-center text-sm text-muted-foreground">
                                Configuring nginx and cloning repository. Usually takes 1-2 minutes.
                            </p>
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
                    <p className="text-sm text-muted-foreground">
                        Your site is being configured. This page will update automatically.
                    </p>
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
