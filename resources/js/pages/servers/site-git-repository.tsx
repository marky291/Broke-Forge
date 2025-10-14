import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { CardContainer } from '@/components/ui/card-container';
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
import SiteLayout from '@/layouts/server/site-layout';
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
            post(`/servers/${server.id}/sites/${site.id}/application/git/setup`, {
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
            <SiteLayout server={server} site={site} breadcrumbs={breadcrumbs}>
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
            </SiteLayout>
        );
    }

    // Render failed state with retry option
    if (site.git_status === GitStatus.Failed && gitRepository?.repository) {
        return (
            <SiteLayout server={server} site={site} breadcrumbs={breadcrumbs}>
                <Head title={`Git Repository — ${site.domain}`} />

                <div className="space-y-6">
                    <div className="space-y-2">
                        <h1 className="text-2xl font-semibold">Repository Installation Failed</h1>
                        <p className="text-sm text-muted-foreground">Update your configuration below and retry the installation.</p>
                    </div>

                    <Alert variant="destructive">
                        <XCircle className="h-4 w-4" />
                        <AlertDescription>
                            <div className="space-y-3">
                                <p className="font-medium">Repository installation failed</p>
                                {gitRepository?.latestEvent?.error_log ? (
                                    <details className="mt-2">
                                        <summary className="cursor-pointer text-sm hover:underline">View error details</summary>
                                        <pre className="mt-2 max-h-48 overflow-y-auto rounded bg-destructive/10 p-3 font-mono text-xs whitespace-pre-wrap">
                                            {gitRepository.latestEvent.error_log}
                                        </pre>
                                    </details>
                                ) : (
                                    <p className="text-sm">Please check your repository settings and SSH deploy key configuration, then try again.</p>
                                )}
                            </div>
                        </AlertDescription>
                    </Alert>

                    {/* Editable Repository Configuration */}
                    <Card>
                        <CardHeader className="space-y-1">
                            <CardTitle>Update Configuration</CardTitle>
                            <CardDescription>Modify the settings below and retry the installation</CardDescription>
                        </CardHeader>
                        <Separator />
                        <CardContent className="py-6">
                            <form onSubmit={handleSubmit} className="flex flex-col gap-6">
                                <div className="space-y-3">
                                    <Label htmlFor="failed-provider" className="text-sm text-muted-foreground">
                                        Provider
                                    </Label>
                                    <Select value={data.provider} onValueChange={handleProviderChange}>
                                        <SelectTrigger id="failed-provider" className="w-full md:w-1/2">
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
                                    <Label htmlFor="failed-repository" className="text-sm text-muted-foreground">
                                        Repository
                                    </Label>
                                    <Input
                                        id="failed-repository"
                                        value={data.repository}
                                        onChange={(event) => setData('repository', event.target.value)}
                                        placeholder="e.g. organisation/project"
                                        className="w-full"
                                        required
                                    />
                                    {(errors.repository || serverErrors?.repository) && (
                                        <p className="text-xs text-destructive">{errors.repository || serverErrors?.repository}</p>
                                    )}
                                </div>

                                <div className="space-y-3">
                                    <Label htmlFor="failed-branch" className="text-sm text-muted-foreground">
                                        Branch
                                    </Label>
                                    <Input
                                        id="failed-branch"
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
                                        Common branch names: <span className="font-medium">main</span>, <span className="font-medium">master</span>
                                    </p>
                                </div>

                                <div className="flex justify-end">
                                    <Button type="submit" disabled={processing || !data.repository || !data.branch} variant="destructive">
                                        {processing ? (
                                            <>
                                                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                                Retrying...
                                            </>
                                        ) : (
                                            'Retry Installation'
                                        )}
                                    </Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>

                    {/* SSH Deploy Key Card */}
                    <CardContainer
                        title="SSH Deploy Key"
                        description="Ensure this key is added to your repository with read access"
                        icon={
                            <svg width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path
                                    d="M8.5 5.5H10.5M7 1.5L4.5 4L7 6.5M4.5 4H1.5M4.5 4C4.5 5.933 6.067 7.5 8 7.5H8.5V9.5L11 7L8.5 4.5V6.5H8C6.895 6.5 6 5.605 6 4.5"
                                    stroke="currentColor"
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                />
                            </svg>
                        }
                    >
                        <div className="space-y-4">
                            <div className="rounded-md border border-blue-200 bg-blue-50 p-3 dark:border-blue-900 dark:bg-blue-950/20">
                                <p className="text-xs text-blue-800 dark:text-blue-200">
                                    <strong>Tip:</strong> Make sure this SSH key is properly configured in your repository settings before retrying.
                                </p>
                            </div>
                            <div className="overflow-x-auto rounded-md border border-border bg-muted/60 p-4">
                                <pre className="max-h-56 overflow-y-auto font-mono text-xs leading-relaxed break-all whitespace-pre-wrap">
                                    {deployKey}
                                </pre>
                            </div>
                            <Button type="button" variant="secondary" onClick={handleCopyDeployKey} className="inline-flex items-center gap-2">
                                {copied ? <Check className="h-4 w-4" /> : <Copy className="h-4 w-4" />}
                                {copied ? 'Copied' : 'Copy Deploy Key'}
                            </Button>
                        </div>
                    </CardContainer>
                </div>
            </SiteLayout>
        );
    }

    // Render installed state with application info
    if (site.git_status === GitStatus.Installed && gitRepository?.repository) {
        return (
            <SiteLayout server={server} site={site} breadcrumbs={breadcrumbs}>
                <Head title={`Git Repository — ${site.domain}`} />

                <div className="space-y-6">
                    {flash?.success && (
                        <Alert>
                            <CheckCircle className="h-4 w-4" />
                            <AlertDescription>{flash.success}</AlertDescription>
                        </Alert>
                    )}

                    {/* Application Section */}
                    <CardContainer title="Application">
                        <div className="mb-6 flex items-center gap-2.5">
                            <GitBranch className="h-4 w-4 text-muted-foreground" />
                            <span className="text-sm font-medium">{gitRepository.repository}</span>
                        </div>

                        <div className="grid grid-cols-2 gap-6">
                            <div>
                                <div className="mb-1.5 text-xs text-muted-foreground">HTTPS</div>
                                <div className="text-sm">{site.status === 'active' ? 'Disabled' : 'Disabled'}</div>
                            </div>
                            <div>
                                <div className="mb-1.5 text-xs text-muted-foreground">PHP Version</div>
                                <div className="text-sm">PHP 8.3</div>
                            </div>
                            <div>
                                <div className="mb-1.5 text-xs text-muted-foreground">Quick Deploy</div>
                                <div className="text-sm">Enabled</div>
                            </div>
                            <div>
                                <div className="mb-1.5 text-xs text-muted-foreground">Web Directory</div>
                                <div className="font-mono text-sm text-xs">{getWebDirectory(gitRepository.repository)}</div>
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
                    </CardContainer>

                    {/* Update Git Remote Section */}
                    <CardContainer title="Update Git Remote">
                        <Alert className="mb-6 border-muted-foreground/20 bg-muted/30">
                            <AlertCircle className="h-4 w-4" />
                            <AlertDescription className="text-sm leading-relaxed">
                                This setting determines the Git remote URL on your server; however, the site will not be removed or become unavailable
                                during the process. The updated Git remote must contain the same repository / Git history as the currently installed
                                repository. <strong>You should not use this function to install an entirely different project onto this site.</strong>{' '}
                                If you would like to install an entirely different project, you should completely uninstall the existing repository
                                using the "Uninstall Repository" button below.
                            </AlertDescription>
                        </Alert>

                        <div className="space-y-4">
                            <div>
                                <Label className="mb-2 block text-sm">Provider</Label>
                                <div className="grid grid-cols-2 gap-3">
                                    <button
                                        type="button"
                                        className="flex items-center justify-center gap-2 rounded-md border-2 border-primary bg-transparent px-4 py-2.5 text-sm font-medium transition-colors hover:bg-accent"
                                    >
                                        <GitBranch className="h-4 w-4" />
                                        <span>GitHub</span>
                                    </button>
                                    <button
                                        type="button"
                                        className="flex items-center justify-center gap-2 rounded-md border-2 border-muted bg-transparent px-4 py-2.5 text-sm font-medium transition-colors hover:bg-accent"
                                    >
                                        <GitBranch className="h-4 w-4" />
                                        <span>Custom</span>
                                    </button>
                                </div>
                            </div>

                            <div>
                                <Label htmlFor="update-repository" className="mb-2 block text-sm">
                                    Repository
                                </Label>
                                <Input id="update-repository" type="text" placeholder="e.g. organisation/project" className="w-full" />
                            </div>

                            <div className="flex justify-end pt-2">
                                <Button>Update Git Remote</Button>
                            </div>
                        </div>
                    </CardContainer>
                </div>
            </SiteLayout>
        );
    }

    // Render installation form (default state or failed state)
    return (
        <SiteLayout server={server} site={site} breadcrumbs={breadcrumbs}>
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
                    <CardContainer
                        title="Repository Details"
                        icon={
                            <svg width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path
                                    d="M6 1L10.5 3.5V8.5L6 11L1.5 8.5V3.5L6 1Z"
                                    stroke="currentColor"
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                />
                                <path d="M6 6V11" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" />
                                <path d="M1.5 3.5L6 6L10.5 3.5" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" />
                            </svg>
                        }
                    >
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
                    </CardContainer>

                    <CardContainer
                        title="SSH Deploy Key"
                        icon={
                            <svg width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path
                                    d="M8.5 5.5H10.5M7 1.5L4.5 4L7 6.5M4.5 4H1.5M4.5 4C4.5 5.933 6.067 7.5 8 7.5H8.5V9.5L11 7L8.5 4.5V6.5H8C6.895 6.5 6 5.605 6 4.5"
                                    stroke="currentColor"
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                />
                            </svg>
                        }
                    >
                        <div className="space-y-4">
                            <div className="mb-4 rounded-md border border-blue-200 bg-blue-50 p-3 dark:border-blue-900 dark:bg-blue-950/20">
                                <p className="text-xs text-blue-800 dark:text-blue-200">
                                    <strong>Security:</strong> This SSH key is unique to this server. If this server is compromised, only this key
                                    needs to be revoked—your other servers remain secure.
                                </p>
                            </div>
                            <ol className="space-y-2 text-sm text-muted-foreground">
                                <li>1. Go to your GitHub repository&apos;s deploy key settings.</li>
                                <li>
                                    2. Add a new key with a descriptive title, e.g.{' '}
                                    <span className="font-medium">BrokeForge Server #{server.id}</span>.
                                </li>
                                <li>3. Paste the key below and grant read-only access.</li>
                            </ol>
                            <div className="overflow-x-auto rounded-md border border-border bg-muted/60 p-4">
                                <pre className="max-h-56 overflow-y-auto font-mono text-xs leading-relaxed break-all whitespace-pre-wrap">
                                    {deployKey}
                                </pre>
                            </div>
                            <Button type="button" variant="secondary" onClick={handleCopyDeployKey} className="inline-flex items-center gap-2">
                                {copied ? <Check className="h-4 w-4" /> : <Copy className="h-4 w-4" />}
                                {copied ? 'Copied' : 'Copy Deploy Key'}
                            </Button>
                        </div>
                    </CardContainer>
                </div>
            </div>
        </SiteLayout>
    );
}
