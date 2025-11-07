import { CardList, type CardListAction } from '@/components/card-list';
import { InstallSkeleton } from '@/components/install-skeleton';
import { CardBadge } from '@/components/ui/card-badge';
import { CardContainer } from '@/components/ui/card-container';
import { CardFormModal } from '@/components/ui/card-form-modal';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { PageHeader } from '@/components/ui/page-header';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import ServerLayout from '@/layouts/server/layout';
import { dashboard } from '@/routes';
import { show as showServer } from '@/routes/servers';
import { type BreadcrumbItem, type Server, type ServerScheduledTask } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { useEcho } from '@laravel/echo-react';
import { Clock, Pause, Play, RotateCw, Trash2 } from 'lucide-react';
import { useState } from 'react';

export default function Scheduler({ server }: { server: Server }) {
    const tasks = server.scheduledTasks || [];
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
    const {
        data,
        setData,
        post: createTask,
        processing: creatingTask,
        errors,
        reset,
    } = useForm({
        name: '',
        command: '',
        frequency: 'daily' as 'minutely' | 'hourly' | 'daily' | 'weekly' | 'monthly' | 'custom',
        cron_expression: '',
        send_notifications: false,
        timeout: 300,
    });

    // Real-time updates via Reverb WebSocket - listens for scheduler changes
    useEcho(`servers.${server.id}`, 'ServerUpdated', () => {
        router.reload({
            only: ['server'],
            preserveScroll: true,
            preserveState: true,
        });
    });

    const handleInstall = () => {
        post(`/servers/${server.id}/scheduler/install`, {
            onSuccess: () => router.reload(),
        });
    };

    const handleUninstall = () => {
        if (!confirm('Are you sure you want to uninstall the scheduler? All scheduled tasks will be removed from cron.')) {
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

    const handleRetryTask = (task: ServerScheduledTask) => {
        if (!confirm(`Retry installing "${task.name}"?`)) {
            return;
        }
        post(`/servers/${server.id}/scheduler/tasks/${task.id}/retry`, {
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

    const formatFrequency = (frequency: string) => {
        return frequency.charAt(0).toUpperCase() + frequency.slice(1);
    };

    return (
        <ServerLayout server={server} breadcrumbs={breadcrumbs}>
            <Head title={`${server.vanity_name} - Scheduler`} />

            <div className="space-y-6">
                <PageHeader title="Task Scheduler" description="Schedule commands to run automatically using cron" icon={Clock} />

                {/* Scheduled Tasks */}
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
                            onInstall={handleInstall}
                            isInstalling={processing}
                        />
                    </CardContainer>
                ) : isInstalling || isUninstalling ? (
                    <CardContainer>
                        <div className="p-12 text-center">
                            <Loader2 className="mx-auto mb-4 h-12 w-12 animate-spin text-blue-500" />
                            <p className="text-muted-foreground">
                                {isInstalling && 'Please wait while the scheduler is being installed...'}
                                {isUninstalling && 'Please wait while the scheduler is being removed...'}
                            </p>
                        </div>
                    </CardContainer>
                ) : (
                    <>
                        <CardList<ServerScheduledTask>
                            title="Scheduled Tasks"
                            icon={
                                <svg width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <circle cx="6" cy="6" r="5" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" />
                                    <path d="M6 3v3l2 1" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" />
                                </svg>
                            }
                            onAddClick={() => setCreateDialogOpen(true)}
                            addButtonLabel="Create Task"
                            items={tasks}
                            keyExtractor={(task) => task.id}
                            renderItem={(task) => {
                                return (
                                    <div className="flex items-center justify-between gap-3">
                                        {/* Left: Task name and command */}
                                        <div className="min-w-0 flex-1">
                                            <h4 className="truncate text-sm font-medium text-foreground">{task.name}</h4>
                                            <p className="mt-0.5 truncate text-xs text-muted-foreground">
                                                {task.user && <span>{task.user} · </span>}
                                                {task.command}
                                            </p>
                                        </div>

                                        {/* Right: Status Badge */}
                                        <div className="flex-shrink-0">
                                            <CardBadge variant={task.status === 'paused' ? 'inactive' : (task.status as any)} />
                                        </div>
                                    </div>
                                );
                            }}
                            actions={(task) => {
                                const actions: CardListAction[] = [];
                                const isInTransition = task.status === 'pending' || task.status === 'installing' || task.status === 'removing';

                                // View Activity - always available
                                actions.push({
                                    label: 'View Activity',
                                    onClick: () => router.visit(`/servers/${server.id}/scheduler/tasks/${task.id}/activity`),
                                    icon: <Clock className="h-4 w-4" />,
                                    disabled: false,
                                });

                                if (task.status === 'failed') {
                                    actions.push({
                                        label: 'Retry Installation',
                                        onClick: () => handleRetryTask(task),
                                        icon: <RotateCw className="h-4 w-4" />,
                                        disabled: processing,
                                    });
                                } else {
                                    actions.push({
                                        label: task.status === 'active' ? 'Pause Task' : 'Resume Task',
                                        onClick: () => handleToggleTask(task),
                                        icon: task.status === 'active' ? <Pause className="h-4 w-4" /> : <Play className="h-4 w-4" />,
                                        disabled: processing || isInTransition,
                                    });
                                }

                                actions.push({
                                    label: 'Delete Task',
                                    onClick: () => handleDeleteTask(task),
                                    variant: 'destructive',
                                    icon: <Trash2 className="h-4 w-4" />,
                                    disabled: processing || isInTransition,
                                });

                                return actions;
                            }}
                            emptyStateMessage="No scheduled tasks yet"
                            emptyStateIcon={<Clock className="h-6 w-6 text-muted-foreground" />}
                        />
                    </>
                )}

                {/* Create Task Modal */}
                <CardFormModal
                    open={createDialogOpen}
                    onOpenChange={(open) => {
                        setCreateDialogOpen(open);
                        if (!open) reset();
                    }}
                    title="Create Scheduled Task"
                    description="Schedule a command to run automatically on your server"
                    onSubmit={handleCreateTask}
                    submitLabel="Create Task"
                    isSubmitting={creatingTask}
                    submittingLabel="Creating..."
                >
                    <div className="space-y-4">
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
                            <Select value={data.frequency} onValueChange={(value) => setData('frequency', value as typeof data.frequency)}>
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
                                <p className="text-xs text-muted-foreground">Format: minute hour day month weekday</p>
                                {errors.cron_expression && <p className="text-sm text-red-600">{errors.cron_expression}</p>}
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
                                onCheckedChange={(checked) => setData('send_notifications', checked === true)}
                            />
                            <Label htmlFor="send_notifications" className="cursor-pointer">
                                Send notifications on task failure
                            </Label>
                        </div>
                    </div>
                </CardFormModal>
            </div>
        </ServerLayout>
    );
}
