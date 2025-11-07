import { CardList, type CardListAction } from '@/components/card-list';
import { InstallSkeleton } from '@/components/install-skeleton';
import { CardBadge } from '@/components/ui/card-badge';
import { CardContainer } from '@/components/ui/card-container';
import { CardFormModal } from '@/components/ui/card-form-modal';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { PageHeader } from '@/components/ui/page-header';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import ServerLayout from '@/layouts/server/layout';
import { dashboard } from '@/routes';
import { show as showServer } from '@/routes/servers';
import { type BreadcrumbItem, type Server, type ServerScheduledTask, type ServerSupervisorTask } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { useEcho } from '@laravel/echo-react';
import { Activity, Clock, Eye, FileText, Loader2, Pause, Pencil, Play, RefreshCw, RotateCw, Trash2 } from 'lucide-react';
import { useState } from 'react';

type InstallDialogType = 'scheduled' | 'worker' | null;

interface TaskLogs {
    task_id: number;
    task_name: string;
    logs: Array<{ source: string; content: string }>;
    error: string | null;
}

interface TaskStatus {
    task_id: number;
    task_name: string;
    status: {
        raw_output: string;
        parsed: {
            name: string;
            state: string;
            pid: string | null;
            uptime: string | null;
        } | null;
    } | null;
    error: string | null;
}

export default function Tasks({
    server,
    viewingSupervisorLogs,
    supervisorTaskLogs,
    viewingSupervisorStatus,
    supervisorTaskStatus,
}: {
    server: Server;
    viewingSupervisorLogs?: boolean;
    supervisorTaskLogs?: TaskLogs;
    viewingSupervisorStatus?: boolean;
    supervisorTaskStatus?: TaskStatus;
}) {
    const scheduledTasks = server.scheduledTasks || [];
    const supervisorTasks = server.supervisorTasks || [];

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: dashboard.url() },
        { title: server.vanity_name, href: showServer({ server: server.id }).url },
        { title: 'Tasks', href: '#' },
    ];

    const { post, processing } = useForm({});

    // Scheduler status
    const schedulerActive = server.scheduler_status === 'active';
    const schedulerInstalling = server.scheduler_status === 'installing';
    const schedulerUninstalling = server.scheduler_status === 'uninstalling';

    // Supervisor status
    const supervisorActive = server.supervisor_status === 'active';
    const supervisorInstalling = server.supervisor_status === 'installing';
    const supervisorUninstalling = server.supervisor_status === 'uninstalling';

    // Install dialog state
    const [installDialogType, setInstallDialogType] = useState<InstallDialogType>(null);

    // Scheduled Task: Create dialog state
    const [createScheduledDialogOpen, setCreateScheduledDialogOpen] = useState(false);
    const {
        data: scheduledData,
        setData: setScheduledData,
        post: createScheduledTask,
        processing: creatingScheduledTask,
        errors: scheduledErrors,
        reset: resetScheduled,
    } = useForm({
        name: '',
        command: '',
        frequency: 'daily' as 'minutely' | 'hourly' | 'daily' | 'weekly' | 'monthly' | 'custom',
        cron_expression: '',
        send_notifications: false,
        timeout: 300,
    });

    // Supervisor Task: Create dialog state
    const [createWorkerDialogOpen, setCreateWorkerDialogOpen] = useState(false);
    const {
        data: workerData,
        setData: setWorkerData,
        post: createWorkerTask,
        processing: creatingWorkerTask,
        errors: workerErrors,
        reset: resetWorker,
    } = useForm({
        name: '',
        command: '',
        working_directory: '/home/brokeforge',
        processes: 1,
        user: 'brokeforge',
        auto_restart: true,
        autorestart_unexpected: true,
    });

    // Supervisor Task: Edit dialog state
    const [editWorkerDialogOpen, setEditWorkerDialogOpen] = useState(false);
    const [editingWorker, setEditingWorker] = useState<ServerSupervisorTask | null>(null);
    const {
        data: editWorkerData,
        setData: setEditWorkerData,
        put: updateWorkerTask,
        processing: updatingWorkerTask,
        errors: editWorkerErrors,
        reset: resetEditWorker,
    } = useForm({
        name: '',
        command: '',
        working_directory: '/home/brokeforge',
        processes: 1,
        user: 'brokeforge',
        auto_restart: true,
        autorestart_unexpected: true,
    });

    // Real-time updates via Reverb WebSocket
    useEcho(`servers.${server.id}`, 'ServerUpdated', () => {
        router.reload({
            only: ['server'],
            preserveScroll: true,
            preserveState: true,
        });
    });

    useEcho(`servers.${server.id}`, 'ScheduledTaskCreated', () => {
        router.reload({
            only: ['server'],
            preserveScroll: true,
            preserveState: true,
        });
    });

    useEcho(`servers.${server.id}`, 'ScheduledTaskUpdated', () => {
        router.reload({
            only: ['server'],
            preserveScroll: true,
            preserveState: true,
        });
    });

    useEcho(`servers.${server.id}`, 'ScheduledTaskDeleted', () => {
        router.reload({
            only: ['server'],
            preserveScroll: true,
            preserveState: true,
        });
    });

    useEcho(`servers.${server.id}`, 'SupervisorTaskCreated', () => {
        router.reload({
            only: ['server'],
            preserveScroll: true,
            preserveState: true,
        });
    });

    useEcho(`servers.${server.id}`, 'SupervisorTaskUpdated', () => {
        router.reload({
            only: ['server'],
            preserveScroll: true,
            preserveState: true,
        });
    });

    useEcho(`servers.${server.id}`, 'SupervisorTaskDeleted', () => {
        router.reload({
            only: ['server'],
            preserveScroll: true,
            preserveState: true,
        });
    });

    // Scheduler handlers
    const handleSchedulerInstall = () => {
        post(`/servers/${server.id}/scheduler/install`, {
            onSuccess: () => router.reload(),
        });
    };

    const handleSchedulerUninstall = () => {
        if (!confirm('Are you sure you want to uninstall the scheduler? All scheduled tasks will be removed from cron.')) {
            return;
        }
        post(`/servers/${server.id}/scheduler/uninstall`, {
            onSuccess: () => router.reload(),
        });
    };

    const handleToggleScheduledTask = (task: ServerScheduledTask) => {
        post(`/servers/${server.id}/scheduler/tasks/${task.id}/toggle`, {
            onSuccess: () => router.reload(),
        });
    };

    const handleDeleteScheduledTask = (task: ServerScheduledTask) => {
        if (!confirm(`Are you sure you want to delete the task "${task.name}"?`)) {
            return;
        }
        router.delete(`/servers/${server.id}/scheduler/tasks/${task.id}`, {
            onSuccess: () => router.reload(),
        });
    };

    const handleRunScheduledTask = (task: ServerScheduledTask) => {
        if (!confirm(`Run "${task.name}" now?`)) {
            return;
        }
        post(`/servers/${server.id}/scheduler/tasks/${task.id}/run`, {
            onSuccess: () => router.reload(),
        });
    };

    const handleRetryScheduledTask = (task: ServerScheduledTask) => {
        if (!confirm(`Retry installing "${task.name}"?`)) {
            return;
        }
        post(`/servers/${server.id}/scheduler/tasks/${task.id}/retry`, {
            onSuccess: () => router.reload(),
        });
    };

    const handleCreateScheduledTask = (e: React.FormEvent) => {
        e.preventDefault();
        createScheduledTask(`/servers/${server.id}/scheduler/tasks`, {
            onSuccess: () => {
                setCreateScheduledDialogOpen(false);
                resetScheduled();
                router.reload();
            },
        });
    };

    // Supervisor handlers
    const handleSupervisorInstall = () => {
        post(`/servers/${server.id}/supervisor/install`, {
            onSuccess: () => router.reload(),
        });
    };

    const handleSupervisorUninstall = () => {
        if (!confirm('Are you sure you want to uninstall Supervisor? All tasks will be stopped.')) {
            return;
        }
        post(`/servers/${server.id}/supervisor/uninstall`, {
            onSuccess: () => router.reload(),
        });
    };

    const handleToggleWorkerTask = (task: ServerSupervisorTask) => {
        post(`/servers/${server.id}/supervisor/tasks/${task.id}/toggle`, {
            onSuccess: () => router.reload(),
        });
    };

    const handleDeleteWorkerTask = (task: ServerSupervisorTask) => {
        if (!confirm(`Are you sure you want to delete the task "${task.name}"?`)) {
            return;
        }
        router.delete(`/servers/${server.id}/supervisor/tasks/${task.id}`, {
            onSuccess: () => router.reload(),
        });
    };

    const handleRestartWorkerTask = (task: ServerSupervisorTask) => {
        if (!confirm(`Restart "${task.name}"?`)) {
            return;
        }
        post(`/servers/${server.id}/supervisor/tasks/${task.id}/restart`, {
            onSuccess: () => router.reload(),
        });
    };

    const handleRetryWorkerTask = (task: ServerSupervisorTask) => {
        if (!confirm(`Retry installation of "${task.name}"?`)) {
            return;
        }
        post(`/servers/${server.id}/supervisor/tasks/${task.id}/retry`, {
            onSuccess: () => router.reload(),
        });
    };

    const handleCreateWorkerTask = (e: React.FormEvent) => {
        e.preventDefault();
        createWorkerTask(`/servers/${server.id}/supervisor/tasks`, {
            onSuccess: () => {
                setCreateWorkerDialogOpen(false);
                resetWorker();
                router.reload();
            },
        });
    };

    const handleOpenEditWorkerDialog = (task: ServerSupervisorTask) => {
        setEditingWorker(task);
        setEditWorkerData({
            name: task.name,
            command: task.command,
            working_directory: task.working_directory,
            processes: task.processes,
            user: task.user,
            auto_restart: task.auto_restart,
            autorestart_unexpected: task.autorestart_unexpected,
        });
        setEditWorkerDialogOpen(true);
    };

    const handleUpdateWorkerTask = (e: React.FormEvent) => {
        e.preventDefault();
        if (!editingWorker) return;

        updateWorkerTask(`/servers/${server.id}/supervisor/tasks/${editingWorker.id}`, {
            onSuccess: () => {
                setEditWorkerDialogOpen(false);
                setEditingWorker(null);
                resetEditWorker();
                router.reload();
            },
        });
    };

    const handleViewSupervisorLogs = (task: ServerSupervisorTask) => {
        router.visit(`/servers/${server.id}/supervisor/tasks/${task.id}/logs`);
    };

    const handleViewSupervisorStatus = (task: ServerSupervisorTask) => {
        router.visit(`/servers/${server.id}/supervisor/tasks/${task.id}/status`);
    };

    const handleCloseSupervisorLogsModal = () => {
        router.visit(`/servers/${server.id}/tasks`, {
            preserveScroll: true,
        });
    };

    const handleCloseSupervisorStatusModal = () => {
        router.visit(`/servers/${server.id}/tasks`, {
            preserveScroll: true,
        });
    };

    return (
        <ServerLayout server={server} breadcrumbs={breadcrumbs}>
            <Head title={`${server.vanity_name} - Tasks`} />

            <div className="space-y-6">
                <PageHeader title="Task Management" description="Manage scheduled tasks and background workers for your server" />

                {/* Scheduled Tasks Section */}
                {!server.scheduler_status ? (
                    <CardContainer
                        title="Task Scheduler"
                        icon={
                            <svg width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <circle cx="6" cy="6" r="5" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" />
                                <path d="M6 3v3l2 1" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" />
                            </svg>
                        }
                    >
                        <InstallSkeleton
                            icon={Clock}
                            title="Scheduler Not Installed"
                            description="Install the task scheduler to run commands automatically using cron."
                            buttonLabel="Install Scheduler"
                            onInstall={handleSchedulerInstall}
                            isInstalling={processing}
                        />
                    </CardContainer>
                ) : schedulerInstalling || schedulerUninstalling ? (
                    <CardContainer>
                        <div className="p-12 text-center">
                            <Loader2 className="mx-auto mb-4 h-12 w-12 animate-spin text-blue-500" />
                            <p className="text-muted-foreground">
                                {schedulerInstalling && 'Please wait while the scheduler is being installed...'}
                                {schedulerUninstalling && 'Please wait while the scheduler is being removed...'}
                            </p>
                        </div>
                    </CardContainer>
                ) : (
                    <CardList<ServerScheduledTask>
                        title="Scheduled Tasks"
                        icon={
                            <svg width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <circle cx="6" cy="6" r="5" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" />
                                <path d="M6 3v3l2 1" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" />
                            </svg>
                        }
                        onAddClick={() => setCreateScheduledDialogOpen(true)}
                        addButtonLabel="Create Task"
                        items={scheduledTasks}
                        keyExtractor={(task) => task.id}
                        renderItem={(task) => (
                            <div className="flex items-center justify-between gap-3">
                                <div className="min-w-0 flex-1">
                                    <h4 className="truncate text-sm font-medium text-foreground">{task.name}</h4>
                                    <p className="mt-0.5 truncate text-xs text-muted-foreground">
                                        {task.user && <span>{task.user} · </span>}
                                        {task.command}
                                    </p>
                                </div>
                                <div className="flex-shrink-0">
                                    <CardBadge variant={task.status === 'paused' ? 'inactive' : (task.status as any)} />
                                </div>
                            </div>
                        )}
                        actions={(task) => {
                            const actions: CardListAction[] = [];
                            const isInTransition = task.status === 'pending' || task.status === 'installing' || task.status === 'removing';

                            actions.push({
                                label: 'View Activity',
                                onClick: () => router.visit(`/servers/${server.id}/scheduler/tasks/${task.id}/activity`),
                                icon: <Clock className="h-4 w-4" />,
                                disabled: false,
                            });

                            if (task.status === 'failed') {
                                actions.push({
                                    label: 'Retry Installation',
                                    onClick: () => handleRetryScheduledTask(task),
                                    icon: <RotateCw className="h-4 w-4" />,
                                    disabled: processing,
                                });
                            } else {
                                actions.push({
                                    label: task.status === 'active' ? 'Pause Task' : 'Resume Task',
                                    onClick: () => handleToggleScheduledTask(task),
                                    icon: task.status === 'active' ? <Pause className="h-4 w-4" /> : <Play className="h-4 w-4" />,
                                    disabled: processing || isInTransition,
                                });
                            }

                            actions.push({
                                label: 'Delete Task',
                                onClick: () => handleDeleteScheduledTask(task),
                                variant: 'destructive',
                                icon: <Trash2 className="h-4 w-4" />,
                                disabled: processing || isInTransition,
                            });

                            return actions;
                        }}
                        emptyStateMessage="No scheduled tasks yet"
                        emptyStateIcon={<Clock className="h-6 w-6 text-muted-foreground" />}
                    />
                )}

                {/* Background Workers Section */}
                <div className="mt-8">
                    {!server.supervisor_status ? (
                        <CardContainer
                            title="Process Supervisor"
                            icon={
                                <svg width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <circle cx="6" cy="6" r="5" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" />
                                    <path d="M6 3v3l2 1" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" />
                                </svg>
                            }
                        >
                            <InstallSkeleton
                                icon={Eye}
                                title="Supervisor Not Installed"
                                description="Install Supervisor to manage long-running processes with automatic restart on failure."
                                buttonLabel="Install Supervisor"
                                onInstall={handleSupervisorInstall}
                                isInstalling={processing}
                            />
                        </CardContainer>
                    ) : supervisorInstalling || supervisorUninstalling ? (
                        <CardContainer>
                            <div className="p-12 text-center">
                                <Loader2 className="mx-auto mb-4 h-12 w-12 animate-spin text-blue-500" />
                                <p className="text-muted-foreground">
                                    {supervisorInstalling && 'Please wait while supervisor is being installed...'}
                                    {supervisorUninstalling && 'Please wait while supervisor is being removed...'}
                                </p>
                            </div>
                        </CardContainer>
                    ) : (
                        <CardList<ServerSupervisorTask>
                            title="Background Workers"
                            icon={
                                <svg width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <circle cx="6" cy="6" r="5" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" />
                                    <path d="M6 3v3l2 1" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" />
                                </svg>
                            }
                            onAddClick={() => setCreateWorkerDialogOpen(true)}
                            addButtonLabel="Create Worker"
                            items={supervisorTasks}
                            keyExtractor={(task) => task.id}
                            renderItem={(task) => (
                                <div className="flex items-center justify-between gap-3">
                                    <div className="min-w-0 flex-1">
                                        <h4 className="truncate text-sm font-medium text-foreground">{task.name}</h4>
                                        <p className="mt-1 truncate font-mono text-xs text-muted-foreground">{task.command}</p>
                                        <div className="mt-1.5 flex items-center gap-3 text-xs text-muted-foreground">
                                            <span>{task.working_directory}</span>
                                            <span>•</span>
                                            <span>
                                                {task.processes} {task.processes === 1 ? 'process' : 'processes'}
                                            </span>
                                            <span>•</span>
                                            <span>User: {task.user}</span>
                                        </div>
                                    </div>
                                    <div className="flex-shrink-0">
                                        <CardBadge variant={task.status as any} />
                                    </div>
                                </div>
                            )}
                            actions={(task) => {
                                const actions: CardListAction[] = [];
                                const isInTransition = task.status === 'pending' || task.status === 'installing' || task.status === 'removing';

                                if (task.status === 'failed') {
                                    actions.push({
                                        label: 'Retry Installation',
                                        onClick: () => handleRetryWorkerTask(task),
                                        icon: <RotateCw className="h-4 w-4" />,
                                        disabled: processing,
                                    });
                                }

                                actions.push({
                                    label: 'View Logs',
                                    onClick: () => handleViewSupervisorLogs(task),
                                    icon: <FileText className="h-4 w-4" />,
                                    disabled: processing,
                                });

                                actions.push({
                                    label: 'View Status',
                                    onClick: () => handleViewSupervisorStatus(task),
                                    icon: <Activity className="h-4 w-4" />,
                                    disabled: processing,
                                });

                                actions.push({
                                    label: 'Edit Task',
                                    onClick: () => handleOpenEditWorkerDialog(task),
                                    icon: <Pencil className="h-4 w-4" />,
                                    disabled: processing || isInTransition,
                                });

                                actions.push({
                                    label: 'Restart Task',
                                    onClick: () => handleRestartWorkerTask(task),
                                    icon: <RefreshCw className="h-4 w-4" />,
                                    disabled: processing || task.status !== 'active',
                                });

                                actions.push({
                                    label: task.status === 'active' ? 'Stop Task' : 'Start Task',
                                    onClick: () => handleToggleWorkerTask(task),
                                    icon: task.status === 'active' ? <Pause className="h-4 w-4" /> : <Play className="h-4 w-4" />,
                                    disabled: processing || task.status === 'pending' || task.status === 'installing' || task.status === 'failed' || task.status === 'removing',
                                });

                                actions.push({
                                    label: 'Delete Task',
                                    onClick: () => handleDeleteWorkerTask(task),
                                    variant: 'destructive',
                                    icon: <Trash2 className="h-4 w-4" />,
                                    disabled: processing || isInTransition,
                                });

                                return actions;
                            }}
                            emptyStateMessage="No supervisor tasks yet"
                            emptyStateIcon={<Eye className="h-6 w-6 text-muted-foreground" />}
                        />
                    )}
                </div>

                {/* Create Scheduled Task Modal */}
                <CardFormModal
                    open={createScheduledDialogOpen}
                    onOpenChange={(open) => {
                        setCreateScheduledDialogOpen(open);
                        if (!open) resetScheduled();
                    }}
                    title="Create Scheduled Task"
                    description="Schedule a command to run automatically on your server"
                    onSubmit={handleCreateScheduledTask}
                    submitLabel="Create Task"
                    isSubmitting={creatingScheduledTask}
                    submittingLabel="Creating..."
                >
                    <div className="space-y-4">
                        <div className="space-y-2">
                            <Label htmlFor="scheduled-name">Task Name</Label>
                            <Input
                                id="scheduled-name"
                                value={scheduledData.name}
                                onChange={(e) => setScheduledData('name', e.target.value)}
                                placeholder="e.g., Daily Backup"
                                required
                            />
                            {scheduledErrors.name && <p className="text-sm text-red-600">{scheduledErrors.name}</p>}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="scheduled-command">Command</Label>
                            <Input
                                id="scheduled-command"
                                value={scheduledData.command}
                                onChange={(e) => setScheduledData('command', e.target.value)}
                                placeholder="e.g., php artisan backup:run"
                                required
                            />
                            {scheduledErrors.command && <p className="text-sm text-red-600">{scheduledErrors.command}</p>}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="scheduled-frequency">Frequency</Label>
                            <Select value={scheduledData.frequency} onValueChange={(value) => setScheduledData('frequency', value as typeof scheduledData.frequency)}>
                                <SelectTrigger id="scheduled-frequency">
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
                            {scheduledErrors.frequency && <p className="text-sm text-red-600">{scheduledErrors.frequency}</p>}
                        </div>

                        {scheduledData.frequency === 'custom' && (
                            <div className="space-y-2">
                                <Label htmlFor="scheduled-cron">Cron Expression</Label>
                                <Input
                                    id="scheduled-cron"
                                    value={scheduledData.cron_expression}
                                    onChange={(e) => setScheduledData('cron_expression', e.target.value)}
                                    placeholder="e.g., 0 */6 * * *"
                                />
                                <p className="text-xs text-muted-foreground">Format: minute hour day month weekday</p>
                                {scheduledErrors.cron_expression && <p className="text-sm text-red-600">{scheduledErrors.cron_expression}</p>}
                            </div>
                        )}

                        <div className="space-y-2">
                            <Label htmlFor="scheduled-timeout">Timeout (seconds)</Label>
                            <Input
                                id="scheduled-timeout"
                                type="number"
                                value={scheduledData.timeout}
                                onChange={(e) => setScheduledData('timeout', parseInt(e.target.value))}
                                min="1"
                                max="3600"
                            />
                            {scheduledErrors.timeout && <p className="text-sm text-red-600">{scheduledErrors.timeout}</p>}
                        </div>

                        <div className="flex items-center space-x-2">
                            <Checkbox
                                id="scheduled-notifications"
                                checked={scheduledData.send_notifications}
                                onCheckedChange={(checked) => setScheduledData('send_notifications', checked === true)}
                            />
                            <Label htmlFor="scheduled-notifications" className="cursor-pointer">
                                Send notifications on task failure
                            </Label>
                        </div>
                    </div>
                </CardFormModal>

                {/* Create Worker Task Modal */}
                <CardFormModal
                    open={createWorkerDialogOpen}
                    onOpenChange={(open) => {
                        setCreateWorkerDialogOpen(open);
                        if (!open) resetWorker();
                    }}
                    title="Create Supervisor Task"
                    description="Create a long-running process managed by supervisor"
                    onSubmit={handleCreateWorkerTask}
                    submitLabel="Create Task"
                    isSubmitting={creatingWorkerTask}
                    submittingLabel="Creating..."
                >
                    <div className="space-y-4">
                        <div className="space-y-2">
                            <Label htmlFor="worker-name">Task Name</Label>
                            <Input
                                id="worker-name"
                                value={workerData.name}
                                onChange={(e) => setWorkerData('name', e.target.value)}
                                placeholder="e.g., queue-worker"
                                required
                            />
                            {workerErrors.name && <p className="text-sm text-red-600">{workerErrors.name}</p>}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="worker-command">Command</Label>
                            <Input
                                id="worker-command"
                                value={workerData.command}
                                onChange={(e) => setWorkerData('command', e.target.value)}
                                placeholder="e.g., php artisan queue:work"
                                required
                            />
                            {workerErrors.command && <p className="text-sm text-red-600">{workerErrors.command}</p>}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="worker-directory">Working Directory</Label>
                            <Input
                                id="worker-directory"
                                value={workerData.working_directory}
                                onChange={(e) => setWorkerData('working_directory', e.target.value)}
                                placeholder="/home/brokeforge"
                                required
                            />
                            {workerErrors.working_directory && <p className="text-sm text-red-600">{workerErrors.working_directory}</p>}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="worker-processes">Number of Processes</Label>
                            <Input
                                id="worker-processes"
                                type="number"
                                value={workerData.processes}
                                onChange={(e) => setWorkerData('processes', parseInt(e.target.value))}
                                min="1"
                                max="20"
                                required
                            />
                            <p className="text-xs text-muted-foreground">How many instances of this process to run</p>
                            {workerErrors.processes && <p className="text-sm text-red-600">{workerErrors.processes}</p>}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="worker-user">Run as User</Label>
                            <Input id="worker-user" value={workerData.user} onChange={(e) => setWorkerData('user', e.target.value)} placeholder="brokeforge" required />
                            {workerErrors.user && <p className="text-sm text-red-600">{workerErrors.user}</p>}
                        </div>

                        <div className="flex items-center space-x-2">
                            <Checkbox
                                id="worker-auto-restart"
                                checked={workerData.auto_restart}
                                onCheckedChange={(checked) => setWorkerData('auto_restart', checked === true)}
                            />
                            <Label htmlFor="worker-auto-restart" className="cursor-pointer">
                                Auto restart on exit
                            </Label>
                        </div>

                        <div className="flex items-center space-x-2">
                            <Checkbox
                                id="worker-auto-restart-unexpected"
                                checked={workerData.autorestart_unexpected}
                                onCheckedChange={(checked) => setWorkerData('autorestart_unexpected', checked === true)}
                            />
                            <Label htmlFor="worker-auto-restart-unexpected" className="cursor-pointer">
                                Auto restart only on unexpected exit
                            </Label>
                        </div>
                    </div>
                </CardFormModal>

                {/* Edit Worker Task Modal */}
                <CardFormModal
                    open={editWorkerDialogOpen}
                    onOpenChange={(open) => {
                        setEditWorkerDialogOpen(open);
                        if (!open) {
                            setEditingWorker(null);
                            resetEditWorker();
                        }
                    }}
                    title="Edit Supervisor Task"
                    description="Update the configuration for this supervisor task"
                    onSubmit={handleUpdateWorkerTask}
                    submitLabel="Update Task"
                    isSubmitting={updatingWorkerTask}
                    submittingLabel="Updating..."
                >
                    <div className="space-y-4">
                        <div className="space-y-2">
                            <Label htmlFor="edit-worker-name">Task Name</Label>
                            <Input
                                id="edit-worker-name"
                                value={editWorkerData.name}
                                onChange={(e) => setEditWorkerData('name', e.target.value)}
                                placeholder="e.g., queue-worker"
                                required
                            />
                            {editWorkerErrors.name && <p className="text-sm text-red-600">{editWorkerErrors.name}</p>}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="edit-worker-command">Command</Label>
                            <Input
                                id="edit-worker-command"
                                value={editWorkerData.command}
                                onChange={(e) => setEditWorkerData('command', e.target.value)}
                                placeholder="e.g., php artisan queue:work"
                                required
                            />
                            {editWorkerErrors.command && <p className="text-sm text-red-600">{editWorkerErrors.command}</p>}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="edit-worker-directory">Working Directory</Label>
                            <Input
                                id="edit-worker-directory"
                                value={editWorkerData.working_directory}
                                onChange={(e) => setEditWorkerData('working_directory', e.target.value)}
                                placeholder="/home/brokeforge"
                                required
                            />
                            {editWorkerErrors.working_directory && <p className="text-sm text-red-600">{editWorkerErrors.working_directory}</p>}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="edit-worker-processes">Number of Processes</Label>
                            <Input
                                id="edit-worker-processes"
                                type="number"
                                value={editWorkerData.processes}
                                onChange={(e) => setEditWorkerData('processes', parseInt(e.target.value))}
                                min="1"
                                max="20"
                                required
                            />
                            <p className="text-xs text-muted-foreground">How many instances of this process to run</p>
                            {editWorkerErrors.processes && <p className="text-sm text-red-600">{editWorkerErrors.processes}</p>}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="edit-worker-user">Run as User</Label>
                            <Input
                                id="edit-worker-user"
                                value={editWorkerData.user}
                                onChange={(e) => setEditWorkerData('user', e.target.value)}
                                placeholder="brokeforge"
                                required
                            />
                            {editWorkerErrors.user && <p className="text-sm text-red-600">{editWorkerErrors.user}</p>}
                        </div>

                        <div className="flex items-center space-x-2">
                            <Checkbox
                                id="edit-worker-auto-restart"
                                checked={editWorkerData.auto_restart}
                                onCheckedChange={(checked) => setEditWorkerData('auto_restart', checked === true)}
                            />
                            <Label htmlFor="edit-worker-auto-restart" className="cursor-pointer">
                                Auto restart on exit
                            </Label>
                        </div>

                        <div className="flex items-center space-x-2">
                            <Checkbox
                                id="edit-worker-auto-restart-unexpected"
                                checked={editWorkerData.autorestart_unexpected}
                                onCheckedChange={(checked) => setEditWorkerData('autorestart_unexpected', checked === true)}
                            />
                            <Label htmlFor="edit-worker-auto-restart-unexpected" className="cursor-pointer">
                                Auto restart only on unexpected exit
                            </Label>
                        </div>
                    </div>
                </CardFormModal>

                {/* Supervisor Task Logs Modal */}
                <Dialog open={viewingSupervisorLogs === true} onOpenChange={handleCloseSupervisorLogsModal}>
                    <DialogContent className="max-h-[80vh] max-w-4xl border-0 bg-transparent p-0 shadow-none [&>button]:hidden">
                        <div className="rounded-2xl border border-neutral-200/50 bg-white/50 p-3 dark:border-neutral-700/50 dark:bg-black/50">
                            <div className="relative flex max-h-[75vh] flex-col rounded-xl border border-neutral-200 bg-white shadow-lg dark:border-neutral-800 dark:bg-neutral-950">
                                {/* Close button */}
                                <button
                                    onClick={handleCloseSupervisorLogsModal}
                                    className="ring-offset-background focus:ring-ring absolute right-4 top-4 z-10 rounded-sm opacity-70 transition-opacity hover:opacity-100 focus:ring-2 focus:ring-offset-2 focus:outline-hidden"
                                    type="button"
                                >
                                    <svg className="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                                        <path d="M18 6L6 18M6 6l12 12" />
                                    </svg>
                                    <span className="sr-only">Close</span>
                                </button>

                                {/* Header */}
                                <div className="border-b border-neutral-200 p-6 dark:border-neutral-800">
                                    <DialogHeader>
                                        <DialogTitle>Task Logs - {supervisorTaskLogs?.task_name}</DialogTitle>
                                        <DialogDescription>Last 500 lines of stdout and stderr output</DialogDescription>
                                    </DialogHeader>
                                </div>

                                {/* Logs content */}
                                <div className="min-h-0 flex-1 overflow-y-auto p-6">
                                    {supervisorTaskLogs?.error ? (
                                        <div className="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-800 dark:bg-red-950 dark:text-red-200">
                                            {supervisorTaskLogs.error}
                                        </div>
                                    ) : supervisorTaskLogs?.logs && supervisorTaskLogs.logs.length > 0 ? (
                                        <div className="space-y-1 font-mono text-xs">
                                            {supervisorTaskLogs.logs.map((log, index) => (
                                                <div
                                                    key={index}
                                                    className={`rounded px-2 py-1 ${
                                                        log.source === 'stderr'
                                                            ? 'bg-red-50 text-red-900 dark:bg-red-950/30 dark:text-red-300'
                                                            : 'bg-neutral-50 text-neutral-900 dark:bg-neutral-900 dark:text-neutral-100'
                                                    }`}
                                                >
                                                    <span
                                                        className={`mr-2 text-[10px] font-semibold ${
                                                            log.source === 'stderr' ? 'text-red-600 dark:text-red-400' : 'text-blue-600 dark:text-blue-400'
                                                        }`}
                                                    >
                                                        [{log.source}]
                                                    </span>
                                                    <span className="whitespace-pre-wrap break-all">{log.content}</span>
                                                </div>
                                            ))}
                                        </div>
                                    ) : (
                                        <div className="text-center text-sm text-muted-foreground">No logs available</div>
                                    )}
                                </div>
                            </div>
                        </div>
                    </DialogContent>
                </Dialog>

                {/* Supervisor Task Status Modal */}
                <Dialog open={viewingSupervisorStatus === true} onOpenChange={handleCloseSupervisorStatusModal}>
                    <DialogContent className="border-0 bg-transparent p-0 shadow-none [&>button]:hidden">
                        <div className="rounded-2xl border border-neutral-200/50 bg-white/50 p-3 dark:border-neutral-700/50 dark:bg-black/50">
                            <div className="relative rounded-xl border border-neutral-200 bg-white shadow-lg dark:border-neutral-800 dark:bg-neutral-950">
                                {/* Close button */}
                                <button
                                    onClick={handleCloseSupervisorStatusModal}
                                    className="ring-offset-background focus:ring-ring absolute right-4 top-4 z-10 rounded-sm opacity-70 transition-opacity hover:opacity-100 focus:ring-2 focus:ring-offset-2 focus:outline-hidden"
                                    type="button"
                                >
                                    <svg className="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                                        <path d="M18 6L6 18M6 6l12 12" />
                                    </svg>
                                    <span className="sr-only">Close</span>
                                </button>

                                {/* Content */}
                                <div className="p-6">
                                    <DialogHeader>
                                        <DialogTitle>Remote Status - {supervisorTaskStatus?.task_name}</DialogTitle>
                                        <DialogDescription>Current supervisor daemon status</DialogDescription>
                                    </DialogHeader>

                                    <div className="mt-6">
                                        {supervisorTaskStatus?.error ? (
                                            <div className="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-800 dark:bg-red-950 dark:text-red-200">
                                                {supervisorTaskStatus.error}
                                            </div>
                                        ) : supervisorTaskStatus?.status ? (
                                            <div className="space-y-4">
                                                {/* Parsed Status */}
                                                {supervisorTaskStatus.status.parsed ? (
                                                    <div className="space-y-3">
                                                        <div className="flex items-center gap-2">
                                                            <span className="text-sm font-medium text-muted-foreground">State:</span>
                                                            <CardBadge
                                                                variant={
                                                                    supervisorTaskStatus.status.parsed.state === 'RUNNING'
                                                                        ? 'active'
                                                                        : supervisorTaskStatus.status.parsed.state === 'STOPPED'
                                                                          ? 'paused'
                                                                          : 'failed'
                                                                }
                                                            />
                                                        </div>
                                                        {supervisorTaskStatus.status.parsed.pid && (
                                                            <div className="flex items-center gap-2">
                                                                <span className="text-sm font-medium text-muted-foreground">PID:</span>
                                                                <span className="font-mono text-sm">{supervisorTaskStatus.status.parsed.pid}</span>
                                                            </div>
                                                        )}
                                                        {supervisorTaskStatus.status.parsed.uptime && (
                                                            <div className="flex items-center gap-2">
                                                                <span className="text-sm font-medium text-muted-foreground">Uptime:</span>
                                                                <span className="font-mono text-sm">{supervisorTaskStatus.status.parsed.uptime}</span>
                                                            </div>
                                                        )}
                                                    </div>
                                                ) : null}

                                                {/* Raw Output */}
                                                <div className="space-y-2">
                                                    <span className="text-sm font-medium text-muted-foreground">Raw Output:</span>
                                                    <div className="rounded-lg border border-neutral-200 bg-neutral-50 p-4 font-mono text-xs dark:border-neutral-800 dark:bg-neutral-900">
                                                        <pre className="whitespace-pre-wrap break-all">{supervisorTaskStatus.status.raw_output}</pre>
                                                    </div>
                                                </div>
                                            </div>
                                        ) : (
                                            <div className="text-center text-sm text-muted-foreground">No status available</div>
                                        )}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </DialogContent>
                </Dialog>
            </div>
        </ServerLayout>
    );
}
