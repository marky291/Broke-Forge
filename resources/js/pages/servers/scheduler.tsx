import { CardContainerAddButton } from '@/components/card-container-add-button';
import { Button } from '@/components/ui/button';
import { CardContainer } from '@/components/ui/card-container';
import { PageHeader } from '@/components/ui/page-header';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Checkbox } from '@/components/ui/checkbox';
import ServerLayout from '@/layouts/server/layout';
import { dashboard } from '@/routes';
import { show as showServer } from '@/routes/servers';
import {
    type BreadcrumbItem,
    type Server,
    type ServerScheduledTask,
    type ServerScheduledTaskRun,
} from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { AlertCircle, CheckCircle, ChevronLeft, ChevronRight, Clock, Eye, Loader2, Pause, Play, PlayCircle, Plus, Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';

export default function Scheduler({
    server,
    tasks,
    recentRuns,
}: {
    server: Server;
    tasks: ServerScheduledTask[];
    recentRuns: {
        data: ServerScheduledTaskRun[];
        links: {
            first: string | null;
            last: string | null;
            prev: string | null;
            next: string | null;
        };
        meta: {
            current_page: number;
            from: number | null;
            last_page: number;
            per_page: number;
            to: number | null;
            total: number;
        };
    };
}) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: dashboard.url() },
        { title: server.vanity_name, href: showServer({ server: server.id }).url },
        { title: 'Scheduler', href: '#' },
    ];

    const { post, processing } = useForm({});

    const isActive = server.scheduler_status === 'active';
    const isInstalling = server.scheduler_status === 'installing';
    const isUninstalling = server.scheduler_status === 'uninstalling';

    // Create task dialog state
    const [createDialogOpen, setCreateDialogOpen] = useState(false);
    const { data, setData, post: createTask, processing: creatingTask, errors, reset } = useForm({
        name: '',
        command: '',
        frequency: 'daily' as 'minutely' | 'hourly' | 'daily' | 'weekly' | 'monthly' | 'custom',
        cron_expression: '',
        send_notifications: false,
        timeout: 300,
    });

    // Output dialog state
    const [outputDialogOpen, setOutputDialogOpen] = useState(false);
    const [selectedRun, setSelectedRun] = useState<ServerScheduledTaskRun | null>(null);

    const handleViewOutput = (run: ServerScheduledTaskRun) => {
        setSelectedRun(run);
        setOutputDialogOpen(true);
    };

    // Auto-reload when scheduler status is installing or uninstalling
    useEffect(() => {
        if (isInstalling || isUninstalling) {
            const interval = setInterval(() => {
                router.reload({ only: ['server'] });
            }, 5000); // Check every 5 seconds

            return () => clearInterval(interval);
        }
    }, [server.scheduler_status]);

    const handleInstall = () => {
        post(`/servers/${server.id}/scheduler/install`, {
            onSuccess: () => router.reload(),
        });
    };

    const handleUninstall = () => {
        if (
            !confirm(
                'Are you sure you want to uninstall the scheduler? All scheduled tasks will be removed from cron.',
            )
        ) {
            return;
        }
        post(`/servers/${server.id}/scheduler/uninstall`, {
            onSuccess: () => router.reload(),
        });
    };

    const handleToggleTask = (task: ServerScheduledTask) => {
        post(`/servers/${server.id}/scheduler/tasks/${task.id}/toggle`, {
            onSuccess: () => router.reload(),
        });
    };

    const handleDeleteTask = (task: ServerScheduledTask) => {
        if (!confirm(`Are you sure you want to delete the task "${task.name}"?`)) {
            return;
        }
        router.delete(`/servers/${server.id}/scheduler/tasks/${task.id}`, {
            onSuccess: () => router.reload(),
        });
    };

    const handleRunTask = (task: ServerScheduledTask) => {
        if (!confirm(`Run "${task.name}" now?`)) {
            return;
        }
        post(`/servers/${server.id}/scheduler/tasks/${task.id}/run`, {
            onSuccess: () => router.reload(),
        });
    };

    const handleCreateTask = (e: React.FormEvent) => {
        e.preventDefault();
        createTask(`/servers/${server.id}/scheduler/tasks`, {
            onSuccess: () => {
                setCreateDialogOpen(false);
                reset();
                router.reload();
            },
        });
    };

    const formatDuration = (ms: number | null) => {
        if (ms === null) return 'N/A';
        if (ms < 1000) return `${ms}ms`;
        const seconds = Math.floor(ms / 1000);
        if (seconds < 60) return `${seconds}s`;
        const minutes = Math.floor(seconds / 60);
        return `${minutes}m ${seconds % 60}s`;
    };

    const formatFrequency = (frequency: string) => {
        return frequency.charAt(0).toUpperCase() + frequency.slice(1);
    };

    return (
        <ServerLayout server={server} breadcrumbs={breadcrumbs}>
            <Head title={`${server.vanity_name} - Scheduler`} />

            <div className="space-y-6">
                <PageHeader
                    title="Task Scheduler"
                    description="Schedule commands to run automatically using cron"
                    icon={Clock}
                />

                {/* Scheduled Tasks */}
                {!server.scheduler_status ? (
                    <CardContainer>
                        <div className="p-12 text-center">
                            <Clock className="h-12 w-12 text-muted-foreground/30 mx-auto mb-4" />
                            <p className="text-muted-foreground mb-4">Scheduler Not Installed</p>
                            <Button onClick={handleInstall} disabled={processing}>
                                {processing ? (
                                    <>
                                        <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                        Installing...
                                    </>
                                ) : (
                                    <>Install Scheduler</>
                                )}
                            </Button>
                        </div>
                    </CardContainer>
                ) : isInstalling || isUninstalling ? (
                    <CardContainer>
                        <div className="p-12 text-center">
                            <Loader2 className="h-12 w-12 text-blue-500 animate-spin mx-auto mb-4" />
                            <p className="text-muted-foreground">
                                {isInstalling && 'Please wait while the scheduler is being installed...'}
                                {isUninstalling && 'Please wait while the scheduler is being removed...'}
                            </p>
                        </div>
                    </CardContainer>
                ) : (
                    <>
                        <CardContainer
                            title="Scheduled Tasks"
                            description="Manage automated tasks on your server"
                            action={
                                <CardContainerAddButton
                                    label="Create Task"
                                    onClick={() => setCreateDialogOpen(true)}
                                />
                            }
                        >
                            {tasks.length === 0 ? (
                                <div className="p-12 text-center">
                                    <Clock className="h-12 w-12 text-muted-foreground/30 mx-auto mb-4" />
                                    <p className="text-muted-foreground">No scheduled tasks yet</p>
                                    <p className="text-sm text-muted-foreground/70 mt-1">
                                        Create your first task to get started
                                    </p>
                                </div>
                            ) : (
                                <div className="divide-y">
                                    {tasks.map((task) => {
                                        const latestRun = recentRuns?.data?.find((run) => run.server_scheduled_task_id === task.id);
                                        return (
                                            <div key={task.id} className="p-4">
                                                <div className="flex items-start justify-between gap-3">
                                                    <div className="flex-1 min-w-0">
                                                        <div className="flex items-center gap-2">
                                                            <h4 className="font-medium text-sm text-foreground truncate">{task.name}</h4>
                                                            {task.status === 'active' && (
                                                                <span className="inline-flex items-center gap-1 text-xs bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 px-1.5 py-0.5 rounded">
                                                                    <CheckCircle className="h-3 w-3" />
                                                                    Active
                                                                </span>
                                                            )}
                                                            {task.status === 'paused' && (
                                                                <span className="inline-flex items-center gap-1 text-xs bg-amber-500/10 text-amber-600 dark:text-amber-400 px-1.5 py-0.5 rounded">
                                                                    <Pause className="h-3 w-3" />
                                                                    Paused
                                                                </span>
                                                            )}
                                                            <span className="text-xs text-muted-foreground">
                                                                {formatFrequency(task.frequency)}
                                                            </span>
                                                        </div>
                                                        <p className="text-xs font-mono text-muted-foreground mt-1 truncate">
                                                            {task.command}
                                                        </p>
                                                        {latestRun && (
                                                            <div className="mt-1.5 flex items-center gap-3 text-xs text-muted-foreground">
                                                                <span>{new Date(latestRun.started_at).toLocaleString()}</span>
                                                                {latestRun.was_successful ? (
                                                                    <span className="text-emerald-600 dark:text-emerald-400">
                                                                        ✓ {formatDuration(latestRun.duration_ms)}
                                                                    </span>
                                                                ) : (
                                                                    <span className="text-red-600 dark:text-red-400">
                                                                        ✗ Exit {latestRun.exit_code}
                                                                    </span>
                                                                )}
                                                            </div>
                                                        )}
                                                    </div>
                                                    <div className="flex items-center gap-1.5 flex-shrink-0">
                                                        <Button
                                                            size="sm"
                                                            variant="ghost"
                                                            onClick={() => handleToggleTask(task)}
                                                            disabled={processing}
                                                            className="h-8 w-8 p-0"
                                                            title={task.status === 'active' ? 'Pause task' : 'Resume task'}
                                                        >
                                                            {task.status === 'active' ? (
                                                                <Pause className="h-4 w-4" />
                                                            ) : (
                                                                <Play className="h-4 w-4" />
                                                            )}
                                                        </Button>
                                                        <Button
                                                            size="sm"
                                                            variant="ghost"
                                                            onClick={() => handleDeleteTask(task)}
                                                            disabled={processing}
                                                            className="h-8 w-8 p-0 text-destructive hover:text-destructive"
                                                            title="Delete task"
                                                        >
                                                            <Trash2 className="h-4 w-4" />
                                                        </Button>
                                                    </div>
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>
                            )}
                        </CardContainer>

                        {/* Task Activity */}
                        <CardContainer
                            title="Task Activity"
                            description="Task execution history with captured data"
                        >
                            {!recentRuns?.data || recentRuns.data.length === 0 ? (
                                <div className="p-12 text-center">
                                    <Clock className="h-12 w-12 text-muted-foreground/30 mx-auto mb-4" />
                                    <p className="text-muted-foreground">No tasks have run yet</p>
                                    <p className="text-sm text-muted-foreground/70 mt-1">
                                        Task executions will appear here once they run
                                    </p>
                                </div>
                            ) : (
                                <>
                                    <div className="divide-y">
                                        {recentRuns.data.map((run) => {
                                        const task = tasks.find((t) => t.id === run.server_scheduled_task_id);
                                        return (
                                            <div key={run.id} className="p-4">
                                                <div className="flex items-start justify-between gap-3 mb-2">
                                                    <div className="flex items-center gap-2 min-w-0 flex-1">
                                                        {run.was_successful ? (
                                                            <CheckCircle className="h-4 w-4 text-emerald-500 flex-shrink-0" />
                                                        ) : (
                                                            <AlertCircle className="h-4 w-4 text-red-500 flex-shrink-0" />
                                                        )}
                                                        <div className="min-w-0 flex-1">
                                                            <h4 className="font-medium text-sm text-foreground truncate">{task?.name || 'Unknown Task'}</h4>
                                                            <p className="text-xs text-muted-foreground">
                                                                {new Date(run.started_at).toLocaleString()} · {formatDuration(run.duration_ms)}
                                                            </p>
                                                        </div>
                                                    </div>
                                                    {!run.was_successful && (
                                                        <span className="text-xs text-red-600 dark:text-red-400 flex-shrink-0">Exit {run.exit_code}</span>
                                                    )}
                                                </div>

                                                <div className="flex items-center justify-between gap-2">
                                                    <p className="text-xs font-mono text-muted-foreground truncate flex-1">
                                                        {task?.command || 'N/A'}
                                                    </p>
                                                    <Button
                                                        size="sm"
                                                        variant="ghost"
                                                        onClick={() => handleViewOutput(run)}
                                                        className="h-6 w-6 p-0 flex-shrink-0"
                                                        title="View output"
                                                    >
                                                        <Eye className="h-3.5 w-3.5" />
                                                    </Button>
                                                </div>
                                            </div>
                                        );
                                        })}
                                    </div>

                                    {/* Pagination Controls */}
                                    {recentRuns?.meta?.last_page > 1 && (
                                        <div className="flex items-center justify-between px-6 py-4 border-t">
                                            <div className="text-sm text-muted-foreground">
                                                Showing {recentRuns.meta.from ?? 0} to {recentRuns.meta.to ?? 0} of {recentRuns.meta.total} runs
                                            </div>
                                            <div className="flex items-center gap-2">
                                                {recentRuns.links?.prev ? (
                                                    <Link
                                                        href={recentRuns.links.prev}
                                                        preserveScroll
                                                        className="inline-flex items-center gap-1 px-3 py-1.5 text-sm border rounded-md hover:bg-muted transition-colors"
                                                    >
                                                        <ChevronLeft className="h-4 w-4" />
                                                        Previous
                                                    </Link>
                                                ) : (
                                                    <span className="inline-flex items-center gap-1 px-3 py-1.5 text-sm border rounded-md opacity-50 cursor-not-allowed">
                                                        <ChevronLeft className="h-4 w-4" />
                                                        Previous
                                                    </span>
                                                )}
                                                <div className="text-sm text-muted-foreground">
                                                    Page {recentRuns.meta.current_page} of {recentRuns.meta.last_page}
                                                </div>
                                                {recentRuns.links?.next ? (
                                                    <Link
                                                        href={recentRuns.links.next}
                                                        preserveScroll
                                                        className="inline-flex items-center gap-1 px-3 py-1.5 text-sm border rounded-md hover:bg-muted transition-colors"
                                                    >
                                                        Next
                                                        <ChevronRight className="h-4 w-4" />
                                                    </Link>
                                                ) : (
                                                    <span className="inline-flex items-center gap-1 px-3 py-1.5 text-sm border rounded-md opacity-50 cursor-not-allowed">
                                                        Next
                                                        <ChevronRight className="h-4 w-4" />
                                                    </span>
                                                )}
                                            </div>
                                        </div>
                                    )}
                                </>
                            )}
                        </CardContainer>
                    </>
                )}

                {/* Create Task Dialog */}
                <Dialog open={createDialogOpen} onOpenChange={setCreateDialogOpen}>
                    <DialogContent>
                        <form onSubmit={handleCreateTask}>
                            <DialogHeader>
                                <DialogTitle>Create Scheduled Task</DialogTitle>
                                <DialogDescription>
                                    Schedule a command to run automatically on your server
                                </DialogDescription>
                            </DialogHeader>

                            <div className="space-y-4 py-4">
                                {/* Task Name */}
                                <div className="space-y-2">
                                    <Label htmlFor="name">Task Name</Label>
                                    <Input
                                        id="name"
                                        value={data.name}
                                        onChange={(e) => setData('name', e.target.value)}
                                        placeholder="e.g., Daily Backup"
                                        required
                                    />
                                    {errors.name && <p className="text-sm text-red-600">{errors.name}</p>}
                                </div>

                                {/* Command */}
                                <div className="space-y-2">
                                    <Label htmlFor="command">Command</Label>
                                    <Input
                                        id="command"
                                        value={data.command}
                                        onChange={(e) => setData('command', e.target.value)}
                                        placeholder="e.g., php artisan backup:run"
                                        required
                                    />
                                    {errors.command && <p className="text-sm text-red-600">{errors.command}</p>}
                                </div>

                                {/* Frequency */}
                                <div className="space-y-2">
                                    <Label htmlFor="frequency">Frequency</Label>
                                    <Select
                                        value={data.frequency}
                                        onValueChange={(value) =>
                                            setData('frequency', value as typeof data.frequency)
                                        }
                                    >
                                        <SelectTrigger id="frequency">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="minutely">Every Minute</SelectItem>
                                            <SelectItem value="hourly">Every Hour</SelectItem>
                                            <SelectItem value="daily">Daily at Midnight</SelectItem>
                                            <SelectItem value="weekly">Weekly (Sunday at Midnight)</SelectItem>
                                            <SelectItem value="monthly">Monthly (1st at Midnight)</SelectItem>
                                            <SelectItem value="custom">Custom Cron Expression</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    {errors.frequency && <p className="text-sm text-red-600">{errors.frequency}</p>}
                                </div>

                                {/* Custom Cron Expression (conditional) */}
                                {data.frequency === 'custom' && (
                                    <div className="space-y-2">
                                        <Label htmlFor="cron_expression">Cron Expression</Label>
                                        <Input
                                            id="cron_expression"
                                            value={data.cron_expression}
                                            onChange={(e) => setData('cron_expression', e.target.value)}
                                            placeholder="e.g., 0 */6 * * *"
                                        />
                                        <p className="text-xs text-muted-foreground">
                                            Format: minute hour day month weekday
                                        </p>
                                        {errors.cron_expression && (
                                            <p className="text-sm text-red-600">{errors.cron_expression}</p>
                                        )}
                                    </div>
                                )}

                                {/* Timeout */}
                                <div className="space-y-2">
                                    <Label htmlFor="timeout">Timeout (seconds)</Label>
                                    <Input
                                        id="timeout"
                                        type="number"
                                        value={data.timeout}
                                        onChange={(e) => setData('timeout', parseInt(e.target.value))}
                                        min="1"
                                        max="3600"
                                    />
                                    {errors.timeout && <p className="text-sm text-red-600">{errors.timeout}</p>}
                                </div>

                                {/* Send Notifications */}
                                <div className="flex items-center space-x-2">
                                    <Checkbox
                                        id="send_notifications"
                                        checked={data.send_notifications}
                                        onCheckedChange={(checked) =>
                                            setData('send_notifications', checked === true)
                                        }
                                    />
                                    <Label htmlFor="send_notifications" className="cursor-pointer">
                                        Send notifications on task failure
                                    </Label>
                                </div>
                            </div>

                            <DialogFooter>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => {
                                        setCreateDialogOpen(false);
                                        reset();
                                    }}
                                    disabled={creatingTask}
                                >
                                    Cancel
                                </Button>
                                <Button type="submit" disabled={creatingTask}>
                                    {creatingTask ? (
                                        <>
                                            <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                            Creating...
                                        </>
                                    ) : (
                                        'Create Task'
                                    )}
                                </Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>

                {/* Task Run Output Dialog */}
                <Dialog open={outputDialogOpen} onOpenChange={setOutputDialogOpen}>
                    <DialogContent className="max-w-4xl max-h-[80vh] overflow-hidden flex flex-col">
                        <DialogHeader>
                            <DialogTitle>Task Run Output</DialogTitle>
                            <DialogDescription>
                                {selectedRun && (
                                    <div className="flex items-center gap-4 mt-2">
                                        <span>
                                            {tasks.find((t) => t.id === selectedRun.server_scheduled_task_id)?.name || 'Unknown Task'}
                                        </span>
                                        <span className="text-xs text-muted-foreground">
                                            {new Date(selectedRun.started_at).toLocaleString()}
                                        </span>
                                        <span className={selectedRun.was_successful ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'}>
                                            {selectedRun.was_successful ? '✓ Success' : `✗ Failed (exit ${selectedRun.exit_code})`}
                                        </span>
                                    </div>
                                )}
                            </DialogDescription>
                        </DialogHeader>

                        <div className="flex-1 overflow-y-auto space-y-4">
                            {selectedRun && (
                                <>
                                    {/* Standard Output */}
                                    <div>
                                        <h4 className="text-sm font-medium mb-2 text-foreground">Standard Output</h4>
                                        <pre className="bg-muted p-4 rounded-md text-xs overflow-x-auto">
                                            {selectedRun.output || '(no output)'}
                                        </pre>
                                    </div>

                                    {/* Error Output */}
                                    {selectedRun.error_output && (
                                        <div>
                                            <h4 className="text-sm font-medium mb-2 text-red-600 dark:text-red-400">Error Output</h4>
                                            <pre className="bg-red-50 dark:bg-red-950/20 p-4 rounded-md text-xs overflow-x-auto text-red-600 dark:text-red-400">
                                                {selectedRun.error_output}
                                            </pre>
                                        </div>
                                    )}

                                    {/* Run Details */}
                                    <div>
                                        <h4 className="text-sm font-medium mb-2 text-foreground">Run Details</h4>
                                        <div className="bg-muted p-4 rounded-md text-sm space-y-1">
                                            <div className="flex justify-between">
                                                <span className="text-muted-foreground">Started:</span>
                                                <span>{new Date(selectedRun.started_at).toLocaleString()}</span>
                                            </div>
                                            <div className="flex justify-between">
                                                <span className="text-muted-foreground">Completed:</span>
                                                <span>{selectedRun.completed_at ? new Date(selectedRun.completed_at).toLocaleString() : 'N/A'}</span>
                                            </div>
                                            <div className="flex justify-between">
                                                <span className="text-muted-foreground">Duration:</span>
                                                <span>{formatDuration(selectedRun.duration_ms)}</span>
                                            </div>
                                            <div className="flex justify-between">
                                                <span className="text-muted-foreground">Exit Code:</span>
                                                <span>{selectedRun.exit_code ?? 'N/A'}</span>
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
            </div>
        </ServerLayout>
    );
}
