import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { CardContainer } from '@/components/ui/card-container';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { PageHeader } from '@/components/ui/page-header';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import SiteLayout from '@/layouts/server/site-layout';
import { dashboard } from '@/routes';
import { show as showServer } from '@/routes/servers';
import { type BreadcrumbItem, type ServerSite } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { useEcho } from '@laravel/echo-react';
import { CheckCircle2, Clock, Eye, GitCommitHorizontal, Loader2, Rocket, XCircle } from 'lucide-react';
import { useState } from 'react';

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
    created_at_human: string;
};

export default function SiteDeployments({ site }: { site: ServerSite }) {
    const server = site.server!;
    const deploymentScript = site.deploymentScript!;
    const gitConfig = site.gitConfig!;
    const deployments = site.deployments || { data: [] };
    const latestDeployment = site.latestDeployment;
    const {
        data,
        setData,
        put,
        processing: updating,
    } = useForm({
        deployment_script: deploymentScript,
    });

    const [deploying, setDeploying] = useState(false);
    const [liveDeployment, setLiveDeployment] = useState<Deployment | null>(
        latestDeployment?.status === 'pending' || latestDeployment?.status === 'running' ? latestDeployment : null,
    );

    // Output dialog state
    const [outputDialogOpen, setOutputDialogOpen] = useState(false);
    const [selectedDeployment, setSelectedDeployment] = useState<Deployment | null>(null);

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
                onSuccess: (page) => {
                    // Immediately set the new deployment to show live output
                    const updatedSite = (page.props as any).site as ServerSite;
                    const newDeployment = updatedSite.latestDeployment;
                    if (newDeployment) {
                        setLiveDeployment(newDeployment as any);
                    }
                    router.reload({ only: ['site'] });
                },
                onFinish: () => setDeploying(false),
            },
        );
    };

    const handleToggleAutoDeploy = (enabled: boolean) => {
        router.post(
            `/servers/${server.id}/sites/${site.id}/deployments/auto-deploy`,
            { enabled },
            {
                preserveScroll: true,
            },
        );
    };

    const handleViewOutput = (deployment: Deployment) => {
        setSelectedDeployment(deployment);
        setOutputDialogOpen(true);
    };

    // Listen for real-time deployment updates via Reverb WebSocket
    useEcho(`sites.${site.id}`, 'ServerSiteUpdated', () => {
        router.reload({
            only: ['site'],
            preserveScroll: true,
            preserveState: true,
            onSuccess: (page) => {
                // Update live deployment if it's still active
                const updatedSite = (page.props as any).site as ServerSite;
                const updatedLatest = updatedSite.latestDeployment;
                if (updatedLatest) {
                    setLiveDeployment(updatedLatest as any);
                }
            },
        });
    });

    const getStatusBadge = (status: string) => {
        switch (status) {
            case 'success':
                return (
                    <Badge
                        variant="outline"
                        className="flex items-center gap-1 border-emerald-500/20 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400"
                    >
                        <CheckCircle2 className="h-3.5 w-3.5" />
                        Success
                    </Badge>
                );
            case 'failed':
                return (
                    <Badge variant="outline" className="flex items-center gap-1 border-red-500/20 bg-red-500/10 text-red-600 dark:text-red-400">
                        <XCircle className="h-3.5 w-3.5" />
                        Failed
                    </Badge>
                );
            case 'running':
                return (
                    <Badge variant="outline" className="flex items-center gap-1 border-blue-500/20 bg-blue-500/10 text-blue-600 dark:text-blue-400">
                        <Loader2 className="h-3.5 w-3.5 animate-spin" />
                        Running
                    </Badge>
                );
            default:
                return (
                    <Badge
                        variant="outline"
                        className="flex items-center gap-1 border-amber-500/20 bg-amber-500/10 text-amber-600 dark:text-amber-400"
                    >
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
                        disabled={deploying || liveDeployment?.status === 'pending' || liveDeployment?.status === 'running'}
                        size="sm"
                    >
                        {deploying || liveDeployment?.status === 'pending' || liveDeployment?.status === 'running' ? (
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
                {/* Deployment Output */}
                {liveDeployment && (
                    <CardContainer
                        title="Deployment Output"
                        action={
                            <div className="flex items-center gap-3">
                                <div className="h-2 w-2 animate-pulse rounded-full bg-primary" />
                                {getStatusBadge(liveDeployment.status)}
                            </div>
                        }
                    >
                        <div className="-mx-6 -my-6">
                            {liveDeployment.status === 'pending' || liveDeployment.status === 'running' ? (
                                <div className="relative">
                                    <pre className="max-h-[400px] min-h-[200px] overflow-auto bg-slate-950 px-4 py-4 font-mono text-xs leading-relaxed whitespace-pre-wrap text-slate-50">
                                        {liveDeployment.output || 'Waiting for output...'}
                                    </pre>
                                </div>
                            ) : (
                                <div className="space-y-0">
                                    {liveDeployment.output && (
                                        <div>
                                            <div className="border-b bg-muted/50 px-4 py-2">
                                                <span className="text-xs font-medium text-muted-foreground">Standard Output</span>
                                            </div>
                                            <pre className="max-h-[300px] overflow-auto bg-slate-950 px-4 py-4 font-mono text-xs leading-relaxed whitespace-pre-wrap text-slate-50">
                                                {liveDeployment.output}
                                            </pre>
                                        </div>
                                    )}
                                    {liveDeployment.error_output && (
                                        <div>
                                            <div className="border-b border-destructive/20 bg-destructive/10 px-4 py-2">
                                                <span className="text-xs font-medium text-destructive">Error Output</span>
                                            </div>
                                            <pre className="max-h-[300px] overflow-auto border-l-2 border-destructive bg-destructive/5 px-4 py-4 font-mono text-xs leading-relaxed whitespace-pre-wrap text-destructive">
                                                {liveDeployment.error_output}
                                            </pre>
                                        </div>
                                    )}
                                </div>
                            )}
                        </div>
                    </CardContainer>
                )}

                {/* Auto-Deploy Settings */}
                <CardContainer title="Auto-Deploy" description="Automatically deploy when code is pushed to your repository">
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-3">
                            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
                                <GitCommitHorizontal className="h-5 w-5 text-primary" />
                            </div>
                            <div>
                                <div className="font-medium">Auto-Deploy on Push</div>
                                <div className="text-sm text-muted-foreground">
                                    {site.auto_deploy_enabled
                                        ? 'Deployments triggered automatically on git push'
                                        : 'Connect GitHub in server settings to enable auto-deploy'}
                                </div>
                            </div>
                        </div>
                        <Switch checked={site.auto_deploy_enabled ?? false} onCheckedChange={handleToggleAutoDeploy} />
                    </div>
                </CardContainer>

                {/* Deployment Script Section */}
                <CardContainer title="Deployment Script" description="Commands executed on the remote server">
                    <form onSubmit={handleUpdateScript} className="space-y-4">
                        {/* Code Editor */}
                        <div className="relative overflow-hidden rounded-lg border border-neutral-200 bg-neutral-50 dark:border-white/8 dark:bg-neutral-900">
                            <div className="flex">
                                {/* Line Numbers */}
                                <div className="flex-shrink-0 border-r border-neutral-200 bg-neutral-100 px-4 py-4 font-mono text-sm leading-relaxed text-neutral-400 select-none dark:border-white/8 dark:bg-neutral-950 dark:text-neutral-600">
                                    {Array.from({ length: Math.max(1, data.deployment_script.split('\n').length) }).map((_, index) => (
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
                                    className="flex-1 resize-none rounded-none border-0 bg-transparent p-4 font-mono text-sm leading-relaxed text-foreground placeholder:text-muted-foreground focus-visible:ring-0 focus-visible:ring-offset-0"
                                    rows={Math.max(1, data.deployment_script.split('\n').length)}
                                    spellCheck={false}
                                />
                            </div>
                        </div>

                        <p className="text-xs text-muted-foreground">
                            Commands run in{' '}
                            <code className="rounded bg-neutral-100 px-1.5 py-0.5 font-mono dark:bg-neutral-800">/home/brokeforge/{site.domain}</code>{' '}
                            as the brokeforge user
                        </p>

                        {/* Update Button */}
                        <div className="flex justify-end">
                            <Button type="submit" disabled={updating} size="sm">
                                {updating ? 'Updating...' : 'Update'}
                            </Button>
                        </div>
                    </form>
                </CardContainer>

                {/* Deployment History */}
                <CardContainer title="Deployment History">
                    {deployments.data.length > 0 ? (
                        <div className="-mx-6 -my-6">
                            <div className="divide-y">
                                {deployments.data.map((deployment) => (
                                    <div key={deployment.id} className="px-6 py-4">
                                        <div className="flex items-center justify-between">
                                            <div className="flex items-center gap-3">
                                                {deployment.status === 'success' && (
                                                    <CheckCircle2 className="h-5 w-5 shrink-0 text-emerald-500" />
                                                )}
                                                {deployment.status === 'failed' && (
                                                    <XCircle className="h-5 w-5 shrink-0 text-red-500" />
                                                )}
                                                {deployment.commit_sha && (
                                                    <div className="flex items-center gap-2">
                                                        <GitCommitHorizontal className="h-4 w-4 text-muted-foreground" />
                                                        <code className="font-mono text-sm text-muted-foreground">
                                                            {deployment.commit_sha.substring(0, 7)}
                                                        </code>
                                                    </div>
                                                )}
                                                <span className={`text-sm ${deployment.status === 'failed' ? 'text-red-600 dark:text-red-400' : 'text-muted-foreground'}`}>
                                                    {deployment.status === 'failed'
                                                        ? (deployment.error_output?.split('\n')[0] || 'Deployment failed')
                                                        : (deployment.branch ? `Deployed from ${deployment.branch}` : 'Deployment successful')}
                                                </span>
                                            </div>
                                            <div className="flex items-center gap-3">
                                                <span className="text-sm text-muted-foreground">{deployment.created_at_human}</span>
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    onClick={() => handleViewOutput(deployment)}
                                                    className="h-8 w-8 p-0"
                                                    title="View deployment output"
                                                >
                                                    <Eye className="h-4 w-4" />
                                                </Button>
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    ) : (
                        <div className="flex flex-col items-center justify-center py-12 text-center">
                            <div className="mb-4 rounded-full bg-muted p-3">
                                <Rocket className="h-6 w-6 text-muted-foreground" />
                            </div>
                            <p className="mb-1 text-sm font-medium">No deployments yet</p>
                            <p className="text-sm text-muted-foreground">Click "Deploy Now" to start your first deployment</p>
                        </div>
                    )}
                </CardContainer>

                {/* Deployment Output Dialog */}
                <Dialog open={outputDialogOpen} onOpenChange={setOutputDialogOpen}>
                    <DialogContent className="flex max-h-[80vh] max-w-4xl flex-col overflow-hidden">
                        <DialogHeader>
                            <DialogTitle>Deployment Output</DialogTitle>
                            <DialogDescription>
                                {selectedDeployment && (
                                    <div className="mt-2 flex items-center gap-4">
                                        {selectedDeployment.commit_sha && (
                                            <div className="flex items-center gap-2">
                                                <GitCommitHorizontal className="h-4 w-4 text-muted-foreground" />
                                                <code className="font-mono text-sm text-muted-foreground">
                                                    {selectedDeployment.commit_sha.substring(0, 7)}
                                                </code>
                                            </div>
                                        )}
                                        <span className="text-xs text-muted-foreground">{selectedDeployment.created_at_human}</span>
                                        <span
                                            className={
                                                selectedDeployment.status === 'success'
                                                    ? 'text-emerald-600 dark:text-emerald-400'
                                                    : 'text-red-600 dark:text-red-400'
                                            }
                                        >
                                            {selectedDeployment.status === 'success' ? '✓ Success' : `✗ Failed (exit ${selectedDeployment.exit_code})`}
                                        </span>
                                    </div>
                                )}
                            </DialogDescription>
                        </DialogHeader>

                        <div className="flex-1 space-y-4 overflow-y-auto">
                            {selectedDeployment && (
                                <>
                                    {/* Standard Output */}
                                    {selectedDeployment.output && (
                                        <div>
                                            <div className="mb-2 border-b bg-muted/50 px-4 py-2">
                                                <span className="text-xs font-medium text-muted-foreground">Standard Output</span>
                                            </div>
                                            <pre className="max-h-[300px] overflow-auto bg-slate-950 px-4 py-4 font-mono text-xs leading-relaxed whitespace-pre-wrap text-slate-50">
                                                {selectedDeployment.output}
                                            </pre>
                                        </div>
                                    )}

                                    {/* Error Output */}
                                    {selectedDeployment.error_output && (
                                        <div>
                                            <div className="border-b border-destructive/20 bg-destructive/10 px-4 py-2">
                                                <span className="text-xs font-medium text-destructive">Error Output</span>
                                            </div>
                                            <pre className="max-h-[300px] overflow-auto border-l-2 border-destructive bg-destructive/5 px-4 py-4 font-mono text-xs leading-relaxed whitespace-pre-wrap text-destructive">
                                                {selectedDeployment.error_output}
                                            </pre>
                                        </div>
                                    )}

                                    {/* Deployment Details */}
                                    <div>
                                        <h4 className="mb-2 text-sm font-medium text-foreground">Deployment Details</h4>
                                        <div className="space-y-1 rounded-md bg-muted p-4 text-sm">
                                            {selectedDeployment.branch && (
                                                <div className="flex justify-between">
                                                    <span className="text-muted-foreground">Branch:</span>
                                                    <span>{selectedDeployment.branch}</span>
                                                </div>
                                            )}
                                            {selectedDeployment.started_at && (
                                                <div className="flex justify-between">
                                                    <span className="text-muted-foreground">Started:</span>
                                                    <span>{new Date(selectedDeployment.started_at).toLocaleString()}</span>
                                                </div>
                                            )}
                                            {selectedDeployment.completed_at && (
                                                <div className="flex justify-between">
                                                    <span className="text-muted-foreground">Completed:</span>
                                                    <span>{new Date(selectedDeployment.completed_at).toLocaleString()}</span>
                                                </div>
                                            )}
                                            {selectedDeployment.duration_seconds && (
                                                <div className="flex justify-between">
                                                    <span className="text-muted-foreground">Duration:</span>
                                                    <span>{selectedDeployment.duration_seconds}s</span>
                                                </div>
                                            )}
                                            <div className="flex justify-between">
                                                <span className="text-muted-foreground">Exit Code:</span>
                                                <span>{selectedDeployment.exit_code ?? 'N/A'}</span>
                                            </div>
                                        </div>
                                    </div>
                                </>
                            )}
                        </div>

                        <DialogFooter>
                            <Button variant="outline" onClick={() => setOutputDialogOpen(false)}>
                                Close
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </PageHeader>
        </SiteLayout>
    );
}
