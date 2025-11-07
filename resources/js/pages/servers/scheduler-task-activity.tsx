import { Button } from '@/components/ui/button';
import { CardContainer } from '@/components/ui/card-container';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { PageHeader } from '@/components/ui/page-header';
import { TablePagination } from '@/components/ui/table-pagination';
import ServerLayout from '@/layouts/server/layout';
import { dashboard } from '@/routes';
import { show as showServer } from '@/routes/servers';
import { type BreadcrumbItem, type Server, type ServerScheduledTask, type ServerScheduledTaskRun } from '@/types';
import { Head, router } from '@inertiajs/react';
import { useEcho } from '@laravel/echo-react';
import { AlertCircle, ArrowLeft, CheckCircle, Clock, Eye } from 'lucide-react';
import { useState } from 'react';

interface Props {
    server: Server;
    task: ServerScheduledTask;
    runs: {
        data: ServerScheduledTaskRun[];
        links: any;
        meta: any;
    };
}

export default function SchedulerTaskActivity({ server, task, runs }: Props) {
    const [outputDialogOpen, setOutputDialogOpen] = useState(false);
    const [selectedRun, setSelectedRun] = useState<ServerScheduledTaskRun | null>(null);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: dashboard.url() },
        { title: server.vanity_name, href: showServer({ server: server.id }).url },
        { title: 'Tasks', href: `/servers/${server.id}/tasks` },
        { title: task.name, href: '#' },
    ];

    // Real-time updates via Reverb WebSocket
    useEcho(`servers.${server.id}`, 'ServerUpdated', () => {
        router.reload({
            only: ['runs'],
            preserveScroll: true,
            preserveState: true,
        });
    });

    const handleViewOutput = (run: ServerScheduledTaskRun) => {
        setSelectedRun(run);
        setOutputDialogOpen(true);
    };

    const formatDuration = (ms: number | null) => {
        if (ms === null) return 'N/A';
        if (ms < 1000) return `${ms}ms`;
        const seconds = Math.floor(ms / 1000);
        if (seconds < 60) return `${seconds}s`;
        const minutes = Math.floor(seconds / 60);
        return `${minutes}m ${seconds % 60}s`;
    };

    const formatDateTime = (dateString: string) => {
        return new Date(dateString).toLocaleString();
    };

    return (
        <ServerLayout server={server} breadcrumbs={breadcrumbs}>
            <Head title={`${task.name} - Activity`} />

            <div className="space-y-6">
                <PageHeader
                    title={
                        <div className="flex items-center gap-3">
                            <Button variant="ghost" size="sm" className="h-8 w-8 p-0" onClick={() => router.visit(`/servers/${server.id}/tasks`)}>
                                <ArrowLeft className="h-4 w-4" />
                            </Button>
                            <span>{task.name} - Activity</span>
                        </div>
                    }
                    description={`Command: ${task.command}`}
                    icon={Clock}
                />

                <CardContainer
                    title="Task Run History"
                    icon={
                        <svg width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <circle cx="6" cy="6" r="5" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" />
                            <path d="M6 3v3l2 1" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" />
                        </svg>
                    }
                >
                    {runs.data.length > 0 ? (
                        <>
                            <div className="overflow-x-auto">
                                <table className="w-full">
                                    <thead className="border-b border-neutral-200 dark:border-white/8">
                                        <tr>
                                            <th className="pb-3 text-left text-sm font-medium text-muted-foreground">Started</th>
                                            <th className="pb-3 text-left text-sm font-medium text-muted-foreground">Duration</th>
                                            <th className="pb-3 text-left text-sm font-medium text-muted-foreground">Exit Code</th>
                                            <th className="pb-3 text-left text-sm font-medium text-muted-foreground">Status</th>
                                            <th className="pb-3 text-right text-sm font-medium text-muted-foreground">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-neutral-200 dark:divide-white/8">
                                        {runs.data.map((run) => (
                                            <tr key={run.id} className="group hover:bg-muted/30">
                                                <td className="py-3 text-sm text-foreground">{formatDateTime(run.started_at)}</td>
                                                <td className="py-3 text-sm text-muted-foreground">{formatDuration(run.duration_ms)}</td>
                                                <td className="py-3 text-sm text-muted-foreground">
                                                    {run.exit_code !== null ? run.exit_code : 'N/A'}
                                                </td>
                                                <td className="py-3">
                                                    {run.was_successful ? (
                                                        <span className="inline-flex items-center gap-1 rounded bg-emerald-500/10 px-2 py-1 text-xs font-medium text-emerald-600 dark:text-emerald-400">
                                                            <CheckCircle className="h-3 w-3" />
                                                            Success
                                                        </span>
                                                    ) : (
                                                        <span className="inline-flex items-center gap-1 rounded bg-red-500/10 px-2 py-1 text-xs font-medium text-red-600 dark:text-red-400">
                                                            <AlertCircle className="h-3 w-3" />
                                                            Failed
                                                        </span>
                                                    )}
                                                </td>
                                                <td className="py-3 text-right">
                                                    <Button variant="ghost" size="sm" onClick={() => handleViewOutput(run)} className="h-8 gap-2">
                                                        <Eye className="h-3 w-3" />
                                                        View Output
                                                    </Button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>

                            {runs.meta && <TablePagination links={runs.links} meta={runs.meta} />}
                        </>
                    ) : (
                        <div className="py-12 text-center">
                            <Clock className="mx-auto mb-3 h-12 w-12 text-muted-foreground/30" />
                            <p className="text-muted-foreground">No task runs yet</p>
                            <p className="mt-1 text-sm text-muted-foreground/70">Task runs will appear here after execution</p>
                        </div>
                    )}
                </CardContainer>
            </div>

            {/* Task Run Output Dialog */}
            <Dialog open={outputDialogOpen} onOpenChange={setOutputDialogOpen}>
                <DialogContent className="max-w-3xl">
                    <DialogHeader>
                        <DialogTitle>Task Run Output</DialogTitle>
                        <DialogDescription>Run started at {selectedRun && formatDateTime(selectedRun.started_at)}</DialogDescription>
                    </DialogHeader>

                    {selectedRun && (
                        <div className="space-y-4">
                            {/* Run Details */}
                            <div className="grid grid-cols-3 gap-4 rounded-lg border border-neutral-200 bg-muted/30 p-4 dark:border-white/8">
                                <div>
                                    <p className="text-xs text-muted-foreground">Duration</p>
                                    <p className="mt-1 font-medium">{formatDuration(selectedRun.duration_ms)}</p>
                                </div>
                                <div>
                                    <p className="text-xs text-muted-foreground">Exit Code</p>
                                    <p className="mt-1 font-medium">{selectedRun.exit_code !== null ? selectedRun.exit_code : 'N/A'}</p>
                                </div>
                                <div>
                                    <p className="text-xs text-muted-foreground">Status</p>
                                    <p className="mt-1 font-medium">
                                        {selectedRun.was_successful ? (
                                            <span className="text-emerald-600 dark:text-emerald-400">Success</span>
                                        ) : (
                                            <span className="text-red-600 dark:text-red-400">Failed</span>
                                        )}
                                    </p>
                                </div>
                            </div>

                            {/* Standard Output */}
                            {selectedRun.output && (
                                <div>
                                    <h4 className="mb-2 text-sm font-medium">Standard Output</h4>
                                    <pre className="max-h-64 overflow-auto rounded-lg border border-neutral-200 bg-muted/30 p-4 text-xs dark:border-white/8">
                                        {selectedRun.output}
                                    </pre>
                                </div>
                            )}

                            {/* Error Output */}
                            {selectedRun.error_output && (
                                <div>
                                    <h4 className="mb-2 text-sm font-medium text-red-600 dark:text-red-400">Error Output</h4>
                                    <pre className="max-h-64 overflow-auto rounded-lg border border-red-200 bg-red-50 p-4 text-xs text-red-800 dark:border-red-900/50 dark:bg-red-950/20 dark:text-red-300">
                                        {selectedRun.error_output}
                                    </pre>
                                </div>
                            )}

                            {!selectedRun.output && !selectedRun.error_output && (
                                <div className="py-8 text-center text-sm text-muted-foreground">No output recorded for this run</div>
                            )}
                        </div>
                    )}
                </DialogContent>
            </Dialog>
        </ServerLayout>
    );
}
