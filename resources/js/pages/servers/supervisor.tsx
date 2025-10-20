import { CardList, type CardListAction } from '@/components/card-list';
import { InstallSkeleton } from '@/components/install-skeleton';
import { Button } from '@/components/ui/button';
import { CardBadge } from '@/components/ui/card-badge';
import { CardContainer } from '@/components/ui/card-container';
import { CardFormModal } from '@/components/ui/card-form-modal';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { PageHeader } from '@/components/ui/page-header';
import ServerLayout from '@/layouts/server/layout';
import { dashboard } from '@/routes';
import { show as showServer } from '@/routes/servers';
import { type BreadcrumbItem, type Server, type ServerSupervisorTask } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { useEcho } from '@laravel/echo-react';
import { Eye, Pause, Pencil, Play, RefreshCw, RotateCw, Trash2 } from 'lucide-react';
import { useState } from 'react';

export default function Supervisor({ server }: { server: Server }) {
    const tasks = server.supervisorTasks || [];
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: dashboard.url() },
        { title: server.vanity_name, href: showServer({ server: server.id }).url },
        { title: 'Supervisor', href: '#' },
    ];

    const { post, processing } = useForm({});

    const isActive = server.supervisor_status === 'active';
    const isInstalling = server.supervisor_status === 'installing';
    const isUninstalling = server.supervisor_status === 'uninstalling';

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
        working_directory: '/home/brokeforge',
        processes: 1,
        user: 'brokeforge',
        auto_restart: true,
        autorestart_unexpected: true,
    });

    // Edit task dialog state
    const [editDialogOpen, setEditDialogOpen] = useState(false);
    const [editingTask, setEditingTask] = useState<any>(null);
    const {
        data: editData,
        setData: setEditData,
        put: updateTask,
        processing: updatingTask,
        errors: editErrors,
        reset: resetEdit,
    } = useForm({
        name: '',
        command: '',
        working_directory: '/home/brokeforge',
        processes: 1,
        user: 'brokeforge',
        auto_restart: true,
        autorestart_unexpected: true,
    });

    // Real-time updates via Reverb WebSocket - listens for supervisor changes
    useEcho(`servers.${server.id}`, 'ServerUpdated', () => {
        router.reload({
            only: ['server'],
            preserveScroll: true,
            preserveState: true,
        });
    });

    const handleInstall = () => {
        post(`/servers/${server.id}/supervisor/install`, {
            onSuccess: () => router.reload(),
        });
    };

    const handleUninstall = () => {
        if (!confirm('Are you sure you want to uninstall Supervisor? All tasks will be stopped.')) {
            return;
        }
        post(`/servers/${server.id}/supervisor/uninstall`, {
            onSuccess: () => router.reload(),
        });
    };

    const handleToggleTask = (task: any) => {
        post(`/servers/${server.id}/supervisor/tasks/${task.id}/toggle`, {
            onSuccess: () => router.reload(),
        });
    };

    const handleDeleteTask = (task: any) => {
        if (!confirm(`Are you sure you want to delete the task "${task.name}"?`)) {
            return;
        }
        router.delete(`/servers/${server.id}/supervisor/tasks/${task.id}`, {
            onSuccess: () => router.reload(),
        });
    };

    const handleRestartTask = (task: any) => {
        if (!confirm(`Restart "${task.name}"?`)) {
            return;
        }
        post(`/servers/${server.id}/supervisor/tasks/${task.id}/restart`, {
            onSuccess: () => router.reload(),
        });
    };

    const handleRetryTask = (task: any) => {
        if (!confirm(`Retry installation of "${task.name}"?`)) {
            return;
        }
        post(`/servers/${server.id}/supervisor/tasks/${task.id}/retry`, {
            onSuccess: () => router.reload(),
        });
    };

    const handleCreateTask = (e: React.FormEvent) => {
        e.preventDefault();
        createTask(`/servers/${server.id}/supervisor/tasks`, {
            onSuccess: () => {
                setCreateDialogOpen(false);
                reset();
                router.reload();
            },
        });
    };

    const handleOpenEditDialog = (task: any) => {
        setEditingTask(task);
        setEditData({
            name: task.name,
            command: task.command,
            working_directory: task.working_directory,
            processes: task.processes,
            user: task.user,
            auto_restart: task.auto_restart,
            autorestart_unexpected: task.autorestart_unexpected,
        });
        setEditDialogOpen(true);
    };

    const handleUpdateTask = (e: React.FormEvent) => {
        e.preventDefault();
        if (!editingTask) return;

        updateTask(`/servers/${server.id}/supervisor/tasks/${editingTask.id}`, {
            onSuccess: () => {
                setEditDialogOpen(false);
                setEditingTask(null);
                resetEdit();
                router.reload();
            },
        });
    };

    return (
        <ServerLayout server={server} breadcrumbs={breadcrumbs}>
            <Head title={`${server.vanity_name} - Supervisor`} />

            <div className="space-y-6">
                <PageHeader title="Process Supervisor" description="Manage long-running processes with automatic restart on failure" icon={Eye} />

                {/* Supervisor Tasks */}
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
                            onInstall={handleInstall}
                            isInstalling={processing}
                        />
                    </CardContainer>
                ) : isInstalling || isUninstalling ? (
                    <CardContainer>
                        <div className="p-12 text-center">
                            <Loader2 className="mx-auto mb-4 h-12 w-12 animate-spin text-blue-500" />
                            <p className="text-muted-foreground">
                                {isInstalling && 'Please wait while supervisor is being installed...'}
                                {isUninstalling && 'Please wait while supervisor is being removed...'}
                            </p>
                        </div>
                    </CardContainer>
                ) : (
                    <>
                        <CardList<ServerSupervisorTask>
                            title="Supervisor Tasks"
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
                            renderItem={(task) => (
                                <div className="flex items-center justify-between gap-3">
                                    {/* Left: Task info */}
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

                                    {/* Right: Status badge */}
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
                                        onClick: () => handleRetryTask(task),
                                        icon: <RotateCw className="h-4 w-4" />,
                                        disabled: processing,
                                    });
                                }

                                actions.push({
                                    label: 'Edit Task',
                                    onClick: () => handleOpenEditDialog(task),
                                    icon: <Pencil className="h-4 w-4" />,
                                    disabled: processing || isInTransition,
                                });

                                actions.push({
                                    label: 'Restart Task',
                                    onClick: () => handleRestartTask(task),
                                    icon: <RefreshCw className="h-4 w-4" />,
                                    disabled: processing || task.status !== 'active',
                                });

                                actions.push({
                                    label: task.status === 'active' ? 'Stop Task' : 'Start Task',
                                    onClick: () => handleToggleTask(task),
                                    icon: task.status === 'active' ? <Pause className="h-4 w-4" /> : <Play className="h-4 w-4" />,
                                    disabled: processing || task.status === 'pending' || task.status === 'installing' || task.status === 'failed' || task.status === 'removing',
                                });

                                actions.push({
                                    label: 'Delete Task',
                                    onClick: () => handleDeleteTask(task),
                                    variant: 'destructive',
                                    icon: <Trash2 className="h-4 w-4" />,
                                    disabled: processing || isInTransition,
                                });

                                return actions;
                            }}
                            emptyStateMessage="No supervisor tasks yet"
                            emptyStateIcon={<Eye className="h-6 w-6 text-muted-foreground" />}
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
                    title="Create Supervisor Task"
                    description="Create a long-running process managed by supervisor"
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
                                placeholder="e.g., queue-worker"
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
                                placeholder="e.g., php artisan queue:work"
                                required
                            />
                            {errors.command && <p className="text-sm text-red-600">{errors.command}</p>}
                        </div>

                        {/* Working Directory */}
                        <div className="space-y-2">
                            <Label htmlFor="working_directory">Working Directory</Label>
                            <Input
                                id="working_directory"
                                value={data.working_directory}
                                onChange={(e) => setData('working_directory', e.target.value)}
                                placeholder="/home/brokeforge"
                                required
                            />
                            {errors.working_directory && <p className="text-sm text-red-600">{errors.working_directory}</p>}
                        </div>

                        {/* Number of Processes */}
                        <div className="space-y-2">
                            <Label htmlFor="processes">Number of Processes</Label>
                            <Input
                                id="processes"
                                type="number"
                                value={data.processes}
                                onChange={(e) => setData('processes', parseInt(e.target.value))}
                                min="1"
                                max="20"
                                required
                            />
                            <p className="text-xs text-muted-foreground">How many instances of this process to run</p>
                            {errors.processes && <p className="text-sm text-red-600">{errors.processes}</p>}
                        </div>

                        {/* User */}
                        <div className="space-y-2">
                            <Label htmlFor="user">Run as User</Label>
                            <Input id="user" value={data.user} onChange={(e) => setData('user', e.target.value)} placeholder="brokeforge" required />
                            {errors.user && <p className="text-sm text-red-600">{errors.user}</p>}
                        </div>

                        {/* Auto Restart */}
                        <div className="flex items-center space-x-2">
                            <Checkbox
                                id="auto_restart"
                                checked={data.auto_restart}
                                onCheckedChange={(checked) => setData('auto_restart', checked === true)}
                            />
                            <Label htmlFor="auto_restart" className="cursor-pointer">
                                Auto restart on exit
                            </Label>
                        </div>

                        {/* Auto Restart on Unexpected Exit */}
                        <div className="flex items-center space-x-2">
                            <Checkbox
                                id="autorestart_unexpected"
                                checked={data.autorestart_unexpected}
                                onCheckedChange={(checked) => setData('autorestart_unexpected', checked === true)}
                            />
                            <Label htmlFor="autorestart_unexpected" className="cursor-pointer">
                                Auto restart only on unexpected exit
                            </Label>
                        </div>
                    </div>
                </CardFormModal>

                {/* Edit Task Modal */}
                <CardFormModal
                    open={editDialogOpen}
                    onOpenChange={(open) => {
                        setEditDialogOpen(open);
                        if (!open) {
                            setEditingTask(null);
                            resetEdit();
                        }
                    }}
                    title="Edit Supervisor Task"
                    description="Update the configuration for this supervisor task"
                    onSubmit={handleUpdateTask}
                    submitLabel="Update Task"
                    isSubmitting={updatingTask}
                    submittingLabel="Updating..."
                >
                    <div className="space-y-4">
                        {/* Task Name */}
                        <div className="space-y-2">
                            <Label htmlFor="edit-name">Task Name</Label>
                            <Input
                                id="edit-name"
                                value={editData.name}
                                onChange={(e) => setEditData('name', e.target.value)}
                                placeholder="e.g., queue-worker"
                                required
                            />
                            {editErrors.name && <p className="text-sm text-red-600">{editErrors.name}</p>}
                        </div>

                        {/* Command */}
                        <div className="space-y-2">
                            <Label htmlFor="edit-command">Command</Label>
                            <Input
                                id="edit-command"
                                value={editData.command}
                                onChange={(e) => setEditData('command', e.target.value)}
                                placeholder="e.g., php artisan queue:work"
                                required
                            />
                            {editErrors.command && <p className="text-sm text-red-600">{editErrors.command}</p>}
                        </div>

                        {/* Working Directory */}
                        <div className="space-y-2">
                            <Label htmlFor="edit-working_directory">Working Directory</Label>
                            <Input
                                id="edit-working_directory"
                                value={editData.working_directory}
                                onChange={(e) => setEditData('working_directory', e.target.value)}
                                placeholder="/home/brokeforge"
                                required
                            />
                            {editErrors.working_directory && <p className="text-sm text-red-600">{editErrors.working_directory}</p>}
                        </div>

                        {/* Number of Processes */}
                        <div className="space-y-2">
                            <Label htmlFor="edit-processes">Number of Processes</Label>
                            <Input
                                id="edit-processes"
                                type="number"
                                value={editData.processes}
                                onChange={(e) => setEditData('processes', parseInt(e.target.value))}
                                min="1"
                                max="20"
                                required
                            />
                            <p className="text-xs text-muted-foreground">How many instances of this process to run</p>
                            {editErrors.processes && <p className="text-sm text-red-600">{editErrors.processes}</p>}
                        </div>

                        {/* User */}
                        <div className="space-y-2">
                            <Label htmlFor="edit-user">Run as User</Label>
                            <Input
                                id="edit-user"
                                value={editData.user}
                                onChange={(e) => setEditData('user', e.target.value)}
                                placeholder="brokeforge"
                                required
                            />
                            {editErrors.user && <p className="text-sm text-red-600">{editErrors.user}</p>}
                        </div>

                        {/* Auto Restart */}
                        <div className="flex items-center space-x-2">
                            <Checkbox
                                id="edit-auto_restart"
                                checked={editData.auto_restart}
                                onCheckedChange={(checked) => setEditData('auto_restart', checked === true)}
                            />
                            <Label htmlFor="edit-auto_restart" className="cursor-pointer">
                                Auto restart on exit
                            </Label>
                        </div>

                        {/* Auto Restart on Unexpected Exit */}
                        <div className="flex items-center space-x-2">
                            <Checkbox
                                id="edit-autorestart_unexpected"
                                checked={editData.autorestart_unexpected}
                                onCheckedChange={(checked) => setEditData('autorestart_unexpected', checked === true)}
                            />
                            <Label htmlFor="edit-autorestart_unexpected" className="cursor-pointer">
                                Auto restart only on unexpected exit
                            </Label>
                        </div>
                    </div>
                </CardFormModal>
            </div>
        </ServerLayout>
    );
}
