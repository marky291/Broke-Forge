import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { CardContainer } from '@/components/ui/card-container';
import { PageHeader } from '@/components/ui/page-header';
import { Separator } from '@/components/ui/separator';
import { Textarea } from '@/components/ui/textarea';
import SiteLayout from '@/layouts/server/site-layout';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import { show as showServer } from '@/routes/servers';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { AlertCircle, CheckCircle2, Clock, GitBranch, Loader2, Rocket, XCircle } from 'lucide-react';
import { useEffect, useState } from 'react';

type Server = {
    id: number;
    vanity_name: string;
    connection: string;
};

type ServerSite = {
    id: number;
    domain: string;
    document_root: string | null;
    status: string;
    git_status: string;
    last_deployment_sha?: string | null;
    last_deployed_at?: string | null;
};

type GitConfig = {
    provider: string | null;
    repository: string | null;
    branch: string | null;
    deploy_key: string | null;
};

type Deployment = {
    id: number;
    status: 'pending' | 'running' | 'success' | 'failed';
    deployment_script: string;
    output: string | null;
    error_output: string | null;
    exit_code: number | null;
    commit_sha: string | null;
    branch: string | null;
    duration_ms: number | null;
    duration_seconds: number | null;
    started_at: string | null;
    completed_at: string | null;
    created_at: string;
};

export default function SiteDeployments({
    server,
    site,
    deploymentScript,
    gitConfig,
    deployments,
    latestDeployment,
}: {
    server: Server;
    site: ServerSite;
    deploymentScript: string;
    gitConfig: GitConfig;
    deployments: { data: Deployment[] };
    latestDeployment: Deployment | null;
}) {
    const { data, setData, put, processing: updating } = useForm({
        deployment_script: deploymentScript,
    });

    const [deploying, setDeploying] = useState(false);
    const [liveDeployment, setLiveDeployment] = useState<Deployment | null>(
        latestDeployment?.status === 'running' ? latestDeployment : null
    );

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: dashboard.url() },
        { title: `Server #${server.id}`, href: showServer(server.id).url },
        { title: 'Sites', href: `/servers/${server.id}/sites` },
        { title: site.domain, href: `/servers/${server.id}/sites/${site.id}` },
        { title: 'Deployments', href: '#' },
    ];

    const handleUpdateScript = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        put(`/servers/${server.id}/sites/${site.id}/deployments`, {
            preserveScroll: true,
        });
    };

    const handleDeploy = () => {
        setDeploying(true);
        router.post(
            `/servers/${server.id}/sites/${site.id}/deployments`,
            {},
            {
                onSuccess: () => {
                    router.reload({ only: ['latestDeployment', 'deployments'] });
                },
                onFinish: () => setDeploying(false),
            }
        );
    };

    // Poll for deployment status when running
    useEffect(() => {
        if (liveDeployment?.status === 'running') {
            const interval = setInterval(() => {
                fetch(`/servers/${server.id}/sites/${site.id}/deployments/${liveDeployment.id}/status`)
                    .then((res) => res.json())
                    .then((deployment) => {
                        setLiveDeployment(deployment);
                        if (deployment.status !== 'running') {
                            router.reload({ only: ['latestDeployment', 'deployments'] });
                        }
                    });
            }, 2000);

            return () => clearInterval(interval);
        }
    }, [liveDeployment, server.id, site.id]);

    const getStatusBadge = (status: string) => {
        switch (status) {
            case 'success':
                return (
                    <Badge variant="default" className="flex items-center gap-1">
                        <CheckCircle2 className="h-3.5 w-3.5" />
                        Success
                    </Badge>
                );
            case 'failed':
                return (
                    <Badge variant="destructive" className="flex items-center gap-1">
                        <XCircle className="h-3.5 w-3.5" />
                        Failed
                    </Badge>
                );
            case 'running':
                return (
                    <Badge variant="secondary" className="flex items-center gap-1">
                        <Loader2 className="h-3.5 w-3.5 animate-spin" />
                        Running
                    </Badge>
                );
            default:
                return (
                    <Badge variant="secondary" className="flex items-center gap-1">
                        <Clock className="h-3.5 w-3.5" />
                        Pending
                    </Badge>
                );
        }
    };

    return (
        <SiteLayout server={server} site={site} breadcrumbs={breadcrumbs}>
            <Head title={`Deployments — ${site.domain}`} />
            <PageHeader
                title="Deployments"
                description={`Deploy and manage application updates for ${site.domain}`}
                action={
                    <Button
                        onClick={handleDeploy}
                        disabled={deploying || liveDeployment?.status === 'running'}
                        size="sm"
                    >
                        {deploying || liveDeployment?.status === 'running' ? (
                            <>
                                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                Deploying...
                            </>
                        ) : (
                            <>
                                <Rocket className="mr-2 h-4 w-4" />
                                Deploy Now
                            </>
                        )}
                    </Button>
                }
            >
                {/* Git Repository Info */}
                <div className="rounded-lg border bg-card">
                    <div className="flex items-center gap-3 p-4">
                        <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
                            <GitBranch className="h-5 w-5 text-primary" />
                        </div>
                        <div className="flex-1 min-w-0">
                            <div className="flex items-center gap-2 flex-wrap">
                                <span className="text-sm font-medium">Repository:</span>
                                <code className="rounded bg-muted px-2 py-0.5 text-xs font-mono">{gitConfig.repository}</code>
                                <span className="text-muted-foreground">•</span>
                                <span className="text-sm font-medium">Branch:</span>
                                <code className="rounded bg-muted px-2 py-0.5 text-xs font-mono">{gitConfig.branch}</code>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Latest Deployment Status - Prominent Card */}
                {latestDeployment && (
                    <Card className="border-2">
                        <CardContent className="p-6">
                            <div className="flex items-start justify-between gap-4">
                                <div className="space-y-1">
                                    <p className="text-sm font-medium text-muted-foreground">Latest Deployment</p>
                                    <div className="flex items-center gap-3">
                                        {getStatusBadge(latestDeployment.status)}
                                        {latestDeployment.commit_sha && (
                                            <span className="font-mono text-sm text-muted-foreground">
                                                {latestDeployment.commit_sha.substring(0, 7)}
                                            </span>
                                        )}
                                    </div>
                                </div>
                                <div className="flex gap-4 text-sm">
                                    {latestDeployment.duration_seconds !== null && (
                                        <div className="text-right">
                                            <div className="text-muted-foreground">Duration</div>
                                            <div className="font-medium">{latestDeployment.duration_seconds}s</div>
                                        </div>
                                    )}
                                    {latestDeployment.completed_at && (
                                        <div className="text-right">
                                            <div className="text-muted-foreground">Completed</div>
                                            <div className="font-medium">{new Date(latestDeployment.completed_at).toLocaleTimeString()}</div>
                                        </div>
                                    )}
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Deployment Script Section */}
                <CardContainer title="Deployment Script" description="Commands executed on the remote server">
                    <form onSubmit={handleUpdateScript} className="space-y-4">
                            {/* Code Editor */}
                            <div className="relative rounded-lg overflow-hidden border border-neutral-200 bg-neutral-50 dark:border-white/8 dark:bg-neutral-900">
                                <div className="flex">
                                    {/* Line Numbers */}
                                    <div className="flex-shrink-0 py-4 px-4 bg-neutral-100 text-neutral-400 dark:bg-neutral-950 dark:text-neutral-600 text-sm font-mono leading-relaxed select-none border-r border-neutral-200 dark:border-white/8">
                                        {data.deployment_script.split('\n').map((_, index) => (
                                            <div key={index} className="text-right">
                                                {index + 1}
                                            </div>
                                        ))}
                                    </div>
                                    {/* Text Area */}
                                    <Textarea
                                        id="deployment_script"
                                        value={data.deployment_script}
                                        onChange={(event: React.ChangeEvent<HTMLTextAreaElement>) => setData('deployment_script', event.target.value)}
                                        placeholder="git pull origin main"
                                        className="flex-1 bg-transparent border-0 text-foreground placeholder:text-muted-foreground font-mono text-sm leading-relaxed resize-none focus-visible:ring-0 focus-visible:ring-offset-0 rounded-none p-4"
                                        rows={12}
                                        spellCheck={false}
                                    />
                                </div>
                            </div>

                            {/* Checkbox for .env variables */}
                            <div className="flex items-center space-x-2">
                                <input
                                    type="checkbox"
                                    id="env_variables"
                                    className="h-4 w-4 rounded border-neutral-300 dark:border-neutral-700 text-primary focus:ring-primary cursor-pointer"
                                />
                                <label htmlFor="env_variables" className="text-sm text-muted-foreground cursor-pointer">
                                    Make <code className="rounded bg-orange-100 dark:bg-orange-950/30 px-1.5 py-0.5 text-orange-600 dark:text-orange-500 font-mono text-xs">.env</code> variables available to deploy script
                                </label>
                            </div>

                            <p className="text-xs text-muted-foreground">
                                Commands run in <code className="rounded bg-neutral-100 dark:bg-neutral-800 px-1.5 py-0.5 font-mono">/home/brokeforge/{site.domain}</code> as the brokeforge user
                            </p>

                            {/* Update Button */}
                            <div className="flex justify-end">
                                <Button
                                    type="submit"
                                    disabled={updating}
                                    size="sm"
                                >
                                    {updating ? 'Updating...' : 'Update'}
                                </Button>
                            </div>
                    </form>
                </CardContainer>

                {/* Live Deployment Output */}
                {liveDeployment && liveDeployment.status === 'running' && (
                    <Card className="border-primary/50">
                        <CardHeader>
                            <div className="flex items-center justify-between">
                                <div className="flex items-center gap-3">
                                    <div className="h-2 w-2 rounded-full bg-primary animate-pulse" />
                                    <CardTitle>Live Deployment</CardTitle>
                                </div>
                                <Badge variant="secondary" className="flex items-center gap-1.5">
                                    <Loader2 className="h-3.5 w-3.5 animate-spin" />
                                    Running
                                </Badge>
                            </div>
                        </CardHeader>
                        <Separator />
                        <CardContent className="p-0">
                            <div className="relative">
                                <pre className="overflow-auto bg-slate-950 text-slate-50 px-4 py-4 font-mono text-xs leading-relaxed whitespace-pre-wrap max-h-[400px] min-h-[200px]">
                                    {liveDeployment.output || 'Waiting for output...'}
                                </pre>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Last Deployment Output */}
                {latestDeployment && latestDeployment.status !== 'running' && latestDeployment.output && (
                    <CardContainer title="Deployment Output">
                        <div className="flex justify-end mb-4">
                            {getStatusBadge(latestDeployment.status)}
                        </div>
                        <div className="space-y-0">
                                {latestDeployment.output && (
                                    <div>
                                        <div className="px-4 py-2 bg-muted/50 border-b">
                                            <span className="text-xs font-medium text-muted-foreground">Standard Output</span>
                                        </div>
                                        <pre className="overflow-auto bg-slate-950 text-slate-50 px-4 py-4 font-mono text-xs leading-relaxed whitespace-pre-wrap max-h-[300px]">
                                            {latestDeployment.output}
                                        </pre>
                                    </div>
                                )}
                                {latestDeployment.error_output && (
                                    <div>
                                        <div className="px-4 py-2 bg-destructive/10 border-b border-destructive/20">
                                            <span className="text-xs font-medium text-destructive">Error Output</span>
                                        </div>
                                        <pre className="overflow-auto bg-destructive/5 text-destructive px-4 py-4 font-mono text-xs leading-relaxed whitespace-pre-wrap max-h-[300px] border-l-2 border-destructive">
                                            {latestDeployment.error_output}
                                        </pre>
                                    </div>
                                )}
                        </div>
                    </CardContainer>
                )}

                {/* Deployment History */}
                <CardContainer title="Deployment History">
                    {deployments.data.length > 0 && (
                        <div className="flex justify-end mb-4">
                            <span className="text-sm text-muted-foreground">
                                {deployments.data.length} deployment{deployments.data.length !== 1 ? 's' : ''}
                            </span>
                        </div>
                    )}
                    <div>
                        {deployments.data.length > 0 ? (
                            <div className="divide-y">
                                {deployments.data.map((deployment, index) => (
                                    <div
                                        key={deployment.id}
                                        className={cn(
                                            "p-4 transition-colors",
                                            index === 0 ? "bg-muted/30" : "hover:bg-muted/50"
                                        )}
                                    >
                                        <div className="flex items-center justify-between gap-4">
                                            <div className="flex items-center gap-3 flex-1 min-w-0">
                                                {getStatusBadge(deployment.status)}
                                                {deployment.commit_sha && (
                                                    <code className="font-mono text-xs text-muted-foreground">
                                                        {deployment.commit_sha.substring(0, 7)}
                                                    </code>
                                                )}
                                            </div>
                                            <div className="flex items-center gap-4 text-sm text-muted-foreground">
                                                {deployment.duration_seconds !== null && (
                                                    <div className="flex items-center gap-1">
                                                        <Clock className="h-3.5 w-3.5" />
                                                        <span>{deployment.duration_seconds}s</span>
                                                    </div>
                                                )}
                                                <span className="whitespace-nowrap">
                                                    {new Date(deployment.created_at).toLocaleString(undefined, {
                                                        month: 'short',
                                                        day: 'numeric',
                                                        hour: '2-digit',
                                                        minute: '2-digit'
                                                    })}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <div className="flex flex-col items-center justify-center p-12 text-center">
                                <div className="rounded-full bg-muted p-3 mb-4">
                                    <Rocket className="h-6 w-6 text-muted-foreground" />
                                </div>
                                <p className="text-sm font-medium mb-1">No deployments yet</p>
                                <p className="text-sm text-muted-foreground">
                                    Click "Deploy Now" to start your first deployment
                                </p>
                            </div>
                        )}
                    </div>
                </CardContainer>
            </PageHeader>
        </SiteLayout>
    );
}
