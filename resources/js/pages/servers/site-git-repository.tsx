import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import {
    COPY_FEEDBACK_DURATION,
    DEFAULT_BRANCH,
    DEFAULT_PROVIDER,
    DEPLOY_KEY_PLACEHOLDER,
    GIT_PROVIDERS,
    GitStatus,
    POLLING_INTERVAL,
    type GitProvider,
} from '@/constants/git';
import ServerLayout from '@/layouts/server/layout';
import { dashboard } from '@/routes';
import { show as showServer } from '@/routes/servers';
import { type BreadcrumbItem } from '@/types';
import type { GitFormData, SiteGitRepositoryProps } from '@/types/git';
import { Head, router, useForm } from '@inertiajs/react';
import copyToClipboard from 'copy-to-clipboard';
import { AlertCircle, Check, CheckCircle, Copy, GitBranch, Loader2, XCircle } from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';

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
 * Get the web directory path from repository name.
 */
const getWebDirectory = (repository: string | null | undefined): string => {
    if (!repository) return '/var/www/site/public';
    const projectName = repository.split('/')[1] || 'site';
    return `/home/forge/${projectName}/public`;
};

/**
 * Git Repository Installation Component
 *
 * Manages the Git repository setup workflow for server sites with:
 * - Installation form with provider, repository, and branch selection
 * - Real-time installation progress tracking
 * - Application information display after successful installation
 * - Error handling and retry capabilities
 */
export default function SiteGitRepository({ server, site, gitRepository, flash, errors: serverErrors }: SiteGitRepositoryProps) {
    const { data, setData, post, processing, errors } = useForm<GitFormData>({
        provider: (gitRepository?.provider as GitProvider) ?? DEFAULT_PROVIDER,
        repository: gitRepository?.repository ?? '',
        branch: gitRepository?.branch ?? DEFAULT_BRANCH,
    });

    const [copied, setCopied] = useState(false);

    // Poll for status updates when installing
    useEffect(() => {
        if (site.git_status === GitStatus.Installing) {
            const interval = setInterval(() => {
                router.reload({ only: ['site'] });
            }, POLLING_INTERVAL);

            return () => clearInterval(interval);
        }
    }, [site.git_status]);

    // Memoized values
    const breadcrumbs: BreadcrumbItem[] = useMemo(
        () => [
            { title: 'Dashboard', href: dashboard.url() },
            { title: `Server #${server.id}`, href: showServer(server.id).url },
            { title: 'Sites', href: `/servers/${server.id}/sites` },
            { title: site.domain, href: `/servers/${server.id}/sites/${site.id}` },
            { title: 'Git Repository', href: '#' },
        ],
        [server.id, site.domain, site.id],
    );

    const deployKey = useMemo(() => {
        const key = gitRepository?.deployKey?.trim();
        return key || DEPLOY_KEY_PLACEHOLDER;
    }, [gitRepository?.deployKey]);

    // Event handlers
    const handleCopyDeployKey = useCallback(() => {
        const copiedOk = copyToClipboard(deployKey, { format: 'text/plain' });
        if (!copiedOk) return;

        setCopied(true);
        window.setTimeout(() => setCopied(false), COPY_FEEDBACK_DURATION);
    }, [deployKey]);

    const handleSubmit = useCallback(
        (event: React.FormEvent<HTMLFormElement>) => {
            event.preventDefault();
            post(`/servers/${server.id}/sites/${site.id}/application/git-repository`, {
                onSuccess: () => {
                    // Force reload to get updated status
                    router.reload();
                },
            });
        },
        [post, server.id, site.id],
    );

    const handleProviderChange = useCallback(
        (value: string) => {
            setData('provider', value as GitProvider);
        },
        [setData],
    );

    // Render installation in progress state
    if (site.git_status === GitStatus.Installing) {
        return (
            <ServerLayout server={server} site={site} breadcrumbs={breadcrumbs}>
                <Head title={`Git Repository — ${site.domain}`} />

                <div className="space-y-8">
                    <div className="space-y-2">
                        <h1 className="text-2xl font-semibold">Installing Git Repository</h1>
                        <p className="text-sm text-muted-foreground">Setting up your repository connection...</p>
                    </div>

                    <Card>
                        <CardContent className="flex flex-col items-center justify-center py-16">
                            <Loader2 className="mb-4 h-12 w-12 animate-spin text-primary" />
                            <h2 className="mb-2 text-lg font-semibold">Installing...</h2>
                            <p className="max-w-md text-center text-sm text-muted-foreground">
                                We're cloning your repository and setting up the deployment configuration. This usually takes 1-2 minutes depending on
                                the repository size.
                            </p>
                        </CardContent>
                    </Card>
                </div>
            </ServerLayout>
        );
    }

    // Render installed or failed state with application info
    if ((site.git_status === GitStatus.Installed || site.git_status === GitStatus.Failed) && gitRepository?.repository) {
        return (
            <ServerLayout server={server} site={site} breadcrumbs={breadcrumbs}>
                <Head title={`Git Repository — ${site.domain}`} />

                <div className="space-y-8">
                    <div className="space-y-2">
                        <h1 className="text-2xl font-semibold">Git Repository</h1>
                        <p className="text-sm text-muted-foreground">
                            {site.git_status === GitStatus.Installed
                                ? 'Your repository is connected and ready for deployments.'
                                : 'Repository configuration details. Installation failed - please retry.'}
                        </p>
                    </div>

                    {flash?.success && (
                        <Alert>
                            <CheckCircle className="h-4 w-4" />
                            <AlertDescription>{flash.success}</AlertDescription>
                        </Alert>
                    )}

                    {site.git_status === GitStatus.Failed && (
                        <Alert variant="destructive">
                            <XCircle className="h-4 w-4" />
                            <AlertDescription>
                                Repository installation failed. Please check your repository settings and SSH deploy key configuration, then try again.
                            </AlertDescription>
                        </Alert>
                    )}

                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <GitBranch className="h-5 w-5" />
                                Application Information
                            </CardTitle>
                            <CardDescription>Repository details and deployment status</CardDescription>
                        </CardHeader>
                        <Separator />
                        <CardContent className="py-6">
                            <dl className="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2">
                                <div>
                                    <dt className="text-sm font-medium text-muted-foreground">Application</dt>
                                    <dd className="mt-1 text-sm">{gitRepository.repository}</dd>
                                </div>
                                <div>
                                    <dt className="text-sm font-medium text-muted-foreground">Branch</dt>
                                    <dd className="mt-1 text-sm">{gitRepository.branch}</dd>
                                </div>
                                <div>
                                    <dt className="text-sm font-medium text-muted-foreground">HTTPS</dt>
                                    <dd className="mt-1 text-sm">{site.status === 'active' ? 'Enabled' : 'Disabled'}</dd>
                                </div>
                                <div>
                                    <dt className="text-sm font-medium text-muted-foreground">PHP Version</dt>
                                    <dd className="mt-1 text-sm">PHP 8.3</dd>
                                </div>
                                <div>
                                    <dt className="text-sm font-medium text-muted-foreground">Quick Deploy</dt>
                                    <dd className="mt-1 text-sm">Enabled</dd>
                                </div>
                                <div>
                                    <dt className="text-sm font-medium text-muted-foreground">Envoyer Integration</dt>
                                    <dd className="mt-1 text-sm">Disabled</dd>
                                </div>
                                <div>
                                    <dt className="text-sm font-medium text-muted-foreground">Web Directory</dt>
                                    <dd className="mt-1 font-mono text-sm text-xs">{getWebDirectory(gitRepository.repository)}</dd>
                                </div>
                                <div>
                                    <dt className="text-sm font-medium text-muted-foreground">Last Deployed</dt>
                                    <dd className="mt-1 text-sm">
                                        {formatDate(gitRepository.lastDeployedAt)}
                                        {gitRepository.lastDeployedSha && (
                                            <>
                                                {' '}
                                                • <span className="font-mono">{gitRepository.lastDeployedSha.substring(0, 7)}</span>
                                            </>
                                        )}
                                    </dd>
                                </div>
                            </dl>
                            <div className="mt-6 flex gap-3">
                                {site.git_status === GitStatus.Installed ? (
                                    <>
                                        <Button variant="default">Deploy Now</Button>
                                        <Button variant="outline">View Deployment History</Button>
                                        <Button variant="outline">Update Repository</Button>
                                    </>
                                ) : (
                                    <>
                                        <Button
                                            variant="default"
                                            onClick={() => router.visit(`/servers/${server.id}/sites/${site.id}/application/git-repository`)}
                                        >
                                            Retry Installation
                                        </Button>
                                        <Button variant="outline">Edit Configuration</Button>
                                    </>
                                )}
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </ServerLayout>
        );
    }

    // Render installation form (default state or failed state)
    return (
        <ServerLayout server={server} site={site} breadcrumbs={breadcrumbs}>
            <Head title={`Git Repository — ${site.domain}`} />

            <div className="space-y-8">
                <div className="space-y-2">
                    <h1 className="text-2xl font-semibold">Connect a Git Repository</h1>
                    <p className="text-sm text-muted-foreground">
                        Configure how BrokeForge should fetch your GitHub repository. Once connected we can deploy the tracked branch on demand.
                    </p>
                </div>

                {site.git_status === GitStatus.Failed && (
                    <Alert variant="destructive">
                        <XCircle className="h-4 w-4" />
                        <AlertDescription>Repository installation failed. Please check your repository settings and try again.</AlertDescription>
                    </Alert>
                )}

                {(errors.repository || serverErrors?.repository) && (
                    <Alert variant="destructive">
                        <AlertCircle className="h-4 w-4" />
                        <AlertDescription>{errors.repository || serverErrors?.repository}</AlertDescription>
                    </Alert>
                )}

                {flash?.info && (
                    <Alert>
                        <AlertCircle className="h-4 w-4" />
                        <AlertDescription>{flash.info}</AlertDescription>
                    </Alert>
                )}

                <div className="flex flex-col gap-6">
                    <Card>
                        <CardHeader className="space-y-1">
                            <CardTitle>Repository Details</CardTitle>
                            <CardDescription>Select the GitHub repository and branch you would like to deploy.</CardDescription>
                        </CardHeader>
                        <Separator />
                        <CardContent className="py-6">
                            <form onSubmit={handleSubmit} className="flex flex-col gap-6">
                                <div className="space-y-3">
                                    <Label htmlFor="provider" className="text-sm text-muted-foreground">
                                        Provider
                                    </Label>
                                    <Select value={data.provider} onValueChange={handleProviderChange}>
                                        <SelectTrigger id="provider" className="w-full md:w-1/2">
                                            <SelectValue placeholder="Choose provider" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectGroup>
                                                {GIT_PROVIDERS.map((provider) => (
                                                    <SelectItem key={provider.value} value={provider.value}>
                                                        {provider.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectGroup>
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div className="space-y-3">
                                    <Label htmlFor="repository" className="text-sm text-muted-foreground">
                                        Repository
                                    </Label>
                                    <Input
                                        id="repository"
                                        value={data.repository}
                                        onChange={(event) => setData('repository', event.target.value)}
                                        placeholder="e.g. organisation/project"
                                        className="w-full"
                                        required
                                    />
                                    {(errors.repository || serverErrors?.repository) && (
                                        <p className="text-xs text-destructive">{errors.repository || serverErrors?.repository}</p>
                                    )}
                                    <p className="text-xs text-muted-foreground">
                                        Use the <span className="font-medium">owner/name</span> format. BrokeForge clones via SSH using the deploy key
                                        below.
                                    </p>
                                </div>

                                <div className="space-y-3">
                                    <Label htmlFor="branch" className="text-sm text-muted-foreground">
                                        Branch
                                    </Label>
                                    <Input
                                        id="branch"
                                        value={data.branch}
                                        onChange={(event) => setData('branch', event.target.value)}
                                        placeholder="main"
                                        className="w-full md:w-1/2"
                                        required
                                    />
                                    {(errors.branch || serverErrors?.branch) && (
                                        <p className="text-xs text-destructive">{errors.branch || serverErrors?.branch}</p>
                                    )}
                                    <p className="text-xs text-muted-foreground">
                                        We will deploy this branch on new releases. Change it later if your workflow evolves.
                                    </p>
                                </div>

                                <div className="flex justify-end">
                                    <Button type="submit" disabled={processing || !data.repository || !data.branch}>
                                        {processing ? 'Installing...' : 'Install Repository'}
                                    </Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="space-y-1">
                            <CardTitle>SSH Deploy Key</CardTitle>
                            <CardDescription>
                                Add this key to your repository with read access. BrokeForge uses it to fetch your code securely.
                            </CardDescription>
                        </CardHeader>
                        <Separator />
                        <CardContent className="space-y-4 py-6">
                            <div className="rounded-md bg-blue-50 dark:bg-blue-950/20 border border-blue-200 dark:border-blue-900 p-3 mb-4">
                                <p className="text-xs text-blue-800 dark:text-blue-200">
                                    <strong>Security:</strong> This SSH key is unique to this server. If this server is compromised, only this key needs to be revoked—your other servers remain secure.
                                </p>
                            </div>
                            <ol className="space-y-2 text-sm text-muted-foreground">
                                <li>1. Go to your GitHub repository&apos;s deploy key settings.</li>
                                <li>
                                    2. Add a new key with a descriptive title, e.g. <span className="font-medium">BrokeForge Server #{server.id}</span>.
                                </li>
                                <li>3. Paste the key below and grant read-only access.</li>
                            </ol>
                            <div className="rounded-md border border-border bg-muted/60 p-4">
                                <pre className="max-h-56 overflow-y-auto font-mono text-xs leading-relaxed break-words whitespace-pre-wrap">
                                    {deployKey}
                                </pre>
                            </div>
                            <Button type="button" variant="secondary" onClick={handleCopyDeployKey} className="inline-flex items-center gap-2">
                                {copied ? <Check className="h-4 w-4" /> : <Copy className="h-4 w-4" />}
                                {copied ? 'Copied' : 'Copy Deploy Key'}
                            </Button>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </ServerLayout>
    );
}
