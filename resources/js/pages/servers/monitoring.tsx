import { CardList } from '@/components/card-list';
import { InstallSkeleton } from '@/components/install-skeleton';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { CardContainer } from '@/components/ui/card-container';
import { CardFormModal } from '@/components/ui/card-form-modal';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { PageHeader } from '@/components/ui/page-header';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import ServerLayout from '@/layouts/server/layout';
import { dashboard } from '@/routes';
import { show as showServer } from '@/routes/servers';
import { type BreadcrumbItem, type Server, type ServerMonitor } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { useEcho } from '@laravel/echo-react';
import {
    Activity,
    AlertCircle,
    AlertTriangle,
    Bell,
    Cpu,
    Edit,
    HardDrive,
    Loader2,
    MemoryStick,
    Power,
    PowerOff,
    RotateCw,
    Trash2,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { CartesianGrid, Line, LineChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';

export default function Monitoring({ server, selectedTimeframe = 24 }: { server: Server; selectedTimeframe?: number }) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: dashboard.url() },
        { title: server.vanity_name, href: showServer({ server: server.id }).url },
        { title: 'Monitor', href: '#' },
    ];

    const { post, processing } = useForm({});
    const latestMetrics = server.latestMetrics;
    const recentMetrics = server.recentMetrics || [];
    const monitors = server.monitors || [];

    // Monitor modal state
    const [showMonitorModal, setShowMonitorModal] = useState(false);
    const [editingMonitor, setEditingMonitor] = useState<ServerMonitor | null>(null);

    // Monitor form
    const {
        data: monitorData,
        setData: setMonitorData,
        post: postMonitor,
        put: putMonitor,
        processing: monitorProcessing,
        errors: monitorErrors,
        reset: resetMonitor,
    } = useForm({
        name: '',
        metric_type: 'cpu' as 'cpu' | 'memory' | 'storage',
        operator: '>=' as '>' | '<' | '>=' | '<=' | '==',
        threshold: '',
        duration_minutes: '5',
        notification_emails: '',
        cooldown_minutes: '30',
    });

    const [collectionInterval, setCollectionInterval] = useState<string>((server.monitoring_collection_interval || 60).toString());
    const [nextCollectionCountdown, setNextCollectionCountdown] = useState<string>('Calculating...');

    const isActive = server.monitoring_status === 'active';

    // Real-time updates via Reverb WebSocket - listens for new metrics
    useEcho(`servers.${server.id}`, 'ServerUpdated', () => {
        router.reload({
            only: ['server'],
            preserveScroll: true,
            preserveState: true,
        });
    });

    // Calculate countdown to next metric collection
    useEffect(() => {
        if (!isActive || !latestMetrics) {
            setNextCollectionCountdown('No metrics yet');
            return;
        }

        const updateCountdown = () => {
            const lastCollectedAt = new Date(latestMetrics.collected_at);
            const intervalSeconds = parseInt(collectionInterval);
            const nextCollectionTime = new Date(lastCollectedAt.getTime() + intervalSeconds * 1000);
            const now = new Date();
            const secondsUntilNext = Math.floor((nextCollectionTime.getTime() - now.getTime()) / 1000);

            if (secondsUntilNext <= 0) {
                setNextCollectionCountdown('Collecting now...');
            } else if (secondsUntilNext < 60) {
                setNextCollectionCountdown(`${secondsUntilNext}s`);
            } else {
                const minutes = Math.floor(secondsUntilNext / 60);
                const seconds = secondsUntilNext % 60;
                setNextCollectionCountdown(`${minutes}m ${seconds}s`);
            }
        };

        updateCountdown();
        const interval = setInterval(updateCountdown, 1000);

        return () => clearInterval(interval);
    }, [isActive, latestMetrics?.collected_at, collectionInterval]);

    // Auto-reload when monitoring status is installing or uninstalling
    useEffect(() => {
        if (server.monitoring_status === 'installing' || server.monitoring_status === 'uninstalling') {
            const interval = setInterval(() => {
                router.reload({ only: ['server'] });
            }, 5000); // Check every 5 seconds

            return () => clearInterval(interval);
        }
    }, [server.monitoring_status]);

    const handleInstall = () => {
        post(`/servers/${server.id}/monitoring/install`, {
            onSuccess: () => router.reload(),
        });
    };

    const handleUninstall = () => {
        if (!confirm('Are you sure you want to uninstall monitoring? All historical metrics will be preserved.')) {
            return;
        }
        post(`/servers/${server.id}/monitoring/uninstall`, {
            onSuccess: () => router.reload(),
        });
    };

    const handleRetry = () => {
        if (!confirm('Retry installing monitoring?')) {
            return;
        }
        router.post(
            `/servers/${server.id}/monitoring/retry`,
            {},
            {
                preserveScroll: true,
            },
        );
    };

    const handleIntervalChange = (value: string) => {
        setCollectionInterval(value);
        router.post(
            `/servers/${server.id}/monitoring/update-interval`,
            {
                interval: parseInt(value),
            },
            {
                preserveScroll: true,
            },
        );
    };

    const formatBytes = (mb: number) => {
        if (mb >= 1024) {
            return `${(mb / 1024).toFixed(1)} GB`;
        }
        return `${mb} MB`;
    };

    // Monitor handlers
    const handleAddMonitor = () => {
        setEditingMonitor(null);
        resetMonitor();
        setShowMonitorModal(true);
    };

    const handleEditMonitor = (monitor: ServerMonitor) => {
        setEditingMonitor(monitor);
        setMonitorData({
            name: monitor.name,
            metric_type: monitor.metric_type,
            operator: monitor.operator,
            threshold: monitor.threshold.toString(),
            duration_minutes: monitor.duration_minutes.toString(),
            notification_emails: monitor.notification_emails.join(', '),
            cooldown_minutes: monitor.cooldown_minutes.toString(),
        });
        setShowMonitorModal(true);
    };

    const handleDeleteMonitor = (monitorId: number) => {
        if (window.confirm('Are you sure you want to delete this monitor?')) {
            router.delete(`/servers/${server.id}/monitors/${monitorId}`, {
                preserveScroll: true,
            });
        }
    };

    const handleToggleMonitor = (monitorId: number, currentlyEnabled: boolean) => {
        router.put(
            `/servers/${server.id}/monitors/${monitorId}/toggle`,
            { enabled: !currentlyEnabled },
            {
                preserveScroll: true,
            },
        );
    };

    const handleSubmitMonitor = (e: React.FormEvent) => {
        e.preventDefault();

        const payload = {
            name: monitorData.name,
            metric_type: monitorData.metric_type,
            operator: monitorData.operator,
            threshold: parseFloat(monitorData.threshold),
            duration_minutes: parseInt(monitorData.duration_minutes),
            notification_emails: monitorData.notification_emails
                .split(',')
                .map((email) => email.trim())
                .filter((email) => email),
            cooldown_minutes: parseInt(monitorData.cooldown_minutes),
        };

        if (editingMonitor) {
            putMonitor(`/servers/${server.id}/monitors/${editingMonitor.id}`, {
                data: payload,
                preserveScroll: true,
                onSuccess: () => {
                    resetMonitor();
                    setShowMonitorModal(false);
                    setEditingMonitor(null);
                },
            });
        } else {
            postMonitor(`/servers/${server.id}/monitors`, {
                data: payload,
                preserveScroll: true,
                onSuccess: () => {
                    resetMonitor();
                    setShowMonitorModal(false);
                },
            });
        }
    };

    const getMetricIcon = (metricType: string) => {
        switch (metricType) {
            case 'cpu':
                return <Cpu className="h-5 w-5 text-blue-600" />;
            case 'memory':
                return <MemoryStick className="h-5 w-5 text-purple-600" />;
            case 'storage':
                return <HardDrive className="h-5 w-5 text-orange-600" />;
            default:
                return <Activity className="h-5 w-5" />;
        }
    };

    const getMetricLabel = (metricType: string) => {
        switch (metricType) {
            case 'cpu':
                return 'CPU';
            case 'memory':
                return 'Memory';
            case 'storage':
                return 'Storage';
            default:
                return metricType;
        }
    };

    const getOperatorLabel = (operator: string) => {
        switch (operator) {
            case '>':
                return 'greater than';
            case '<':
                return 'less than';
            case '>=':
                return 'greater than or equal to';
            case '<=':
                return 'less than or equal to';
            case '==':
                return 'equal to';
            default:
                return operator;
        }
    };

    // Custom tooltip for charts
    const CustomTooltip = ({ active, payload }: any) => {
        if (!active || !payload || !payload.length) {
            return null;
        }

        const data = payload[0].payload;

        return (
            <div className="rounded-lg border border-neutral-200 bg-white p-3 shadow-lg dark:border-white/8 dark:bg-[#141514]">
                <p className="mb-2 text-xs text-muted-foreground">
                    {new Date(data.collected_at).toLocaleString('en-US', {
                        month: 'short',
                        day: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit',
                    })}
                </p>
                <div className="space-y-1">
                    {payload.map((entry: any, index: number) => (
                        <div key={index} className="flex items-center justify-between gap-4">
                            <div className="flex items-center gap-2">
                                <div className="h-2 w-2 rounded-full" style={{ backgroundColor: entry.stroke }} />
                                <span className="text-xs text-muted-foreground">{entry.name}</span>
                            </div>
                            <span className="text-sm font-semibold text-foreground">{Number(entry.value).toFixed(1)}%</span>
                        </div>
                    ))}
                </div>
            </div>
        );
    };

    return (
        <ServerLayout server={server} breadcrumbs={breadcrumbs}>
            <Head title={`${server.vanity_name} - Monitor`} />

            <div className="space-y-6">
                <PageHeader
                    title="Server Monitoring"
                    description="Monitor CPU, memory, and storage usage on your server in real-time"
                    icon={Activity}
                />

                {/* Monitoring Status - Only show if not active */}
                {!isActive && (
                    <CardContainer
                        title="Monitoring Status"
                        icon={
                            <svg width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <circle cx="6" cy="6" r="5" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" />
                                <path d="M6 8V6M6 4h.01" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" />
                            </svg>
                        }
                    >
                        {server.monitoring_status === 'failed' ? (
                            <div className="p-8 text-center">
                                <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-lg bg-red-500/10">
                                    <AlertCircle className="h-6 w-6 text-red-600" />
                                </div>
                                <h3 className="mt-4 text-lg font-semibold text-red-600">Installation Failed</h3>
                                <p className="mt-2 text-sm text-muted-foreground">
                                    The monitoring installation failed. Please check the server logs for details.
                                </p>
                                <Button onClick={handleRetry} disabled={processing} className="mt-4">
                                    {processing ? (
                                        <>
                                            <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                            Retrying...
                                        </>
                                    ) : (
                                        <>
                                            <RotateCw className="mr-2 h-4 w-4" />
                                            Retry Installation
                                        </>
                                    )}
                                </Button>
                            </div>
                        ) : server.monitoring_status === 'installing' ? (
                            <div className="p-8 text-center">
                                <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-lg bg-blue-500/10">
                                    <Loader2 className="h-6 w-6 animate-spin text-blue-600" />
                                </div>
                                <h3 className="mt-4 text-lg font-semibold">Installing Monitoring</h3>
                                <p className="mt-2 text-sm text-muted-foreground">
                                    Please wait while monitoring is being installed on your server...
                                </p>
                            </div>
                        ) : server.monitoring_status === 'uninstalling' ? (
                            <div className="p-8 text-center">
                                <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-lg bg-orange-500/10">
                                    <Loader2 className="h-6 w-6 animate-spin text-orange-600" />
                                </div>
                                <h3 className="mt-4 text-lg font-semibold">Uninstalling Monitoring</h3>
                                <p className="mt-2 text-sm text-muted-foreground">
                                    Please wait while monitoring is being removed from your server...
                                </p>
                            </div>
                        ) : (
                            <InstallSkeleton
                                icon={Activity}
                                title="Monitoring Not Installed"
                                description="Install monitoring to track CPU, memory, and storage usage on your server."
                                buttonLabel="Install Monitoring"
                                onInstall={handleInstall}
                                isInstalling={processing}
                            />
                        )}
                    </CardContainer>
                )}

                {/* Server Monitors */}
                {isActive && (
                    <CardList<ServerMonitor>
                        title="Server Monitors"
                        description="Configure alert monitors for CPU, memory, and storage usage thresholds."
                        icon={
                            <svg width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path
                                    d="M6 1C5 1 4 1.5 4 3C4 4 4 4 3 5C2 6 1.5 6.5 1.5 7.5C1.5 9 2.5 10 4 10.5"
                                    stroke="currentColor"
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                />
                                <path
                                    d="M6 1C7 1 8 1.5 8 3C8 4 8 4 9 5C10 6 10.5 6.5 10.5 7.5C10.5 9 9.5 10 8 10.5"
                                    stroke="currentColor"
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                />
                                <path d="M6 10.5V11" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" />
                            </svg>
                        }
                        onAddClick={handleAddMonitor}
                        addButtonLabel="Add Monitor"
                        items={monitors}
                        keyExtractor={(monitor) => monitor.id}
                        renderItem={(monitor) => (
                            <div className="flex items-center gap-4">
                                {/* Left: Metric icon */}
                                <div className="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg bg-muted">
                                    {getMetricIcon(monitor.metric_type)}
                                </div>

                                {/* Middle: Monitor details */}
                                <div className="min-w-0 flex-1">
                                    <div className="flex items-center gap-2">
                                        <h4 className="truncate text-sm font-medium text-foreground">{monitor.name}</h4>
                                        {monitor.status === 'triggered' ? (
                                            <Badge className="bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                                                <AlertTriangle className="mr-1 h-3 w-3" />
                                                Triggered
                                            </Badge>
                                        ) : (
                                            <Badge className="bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">Normal</Badge>
                                        )}
                                        {!monitor.enabled && (
                                            <Badge className="bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200">Disabled</Badge>
                                        )}
                                    </div>
                                    <div className="mt-1 text-xs text-muted-foreground">
                                        <span>
                                            {getMetricLabel(monitor.metric_type)} {getOperatorLabel(monitor.operator)} {monitor.threshold}% for{' '}
                                            {monitor.duration_minutes} min
                                        </span>
                                        {monitor.last_triggered_at && (
                                            <span className="ml-2">• Last triggered: {new Date(monitor.last_triggered_at).toLocaleString()}</span>
                                        )}
                                    </div>
                                </div>
                            </div>
                        )}
                        actions={(monitor) => [
                            {
                                label: monitor.enabled ? 'Disable' : 'Enable',
                                onClick: () => handleToggleMonitor(monitor.id, monitor.enabled),
                                icon: monitor.enabled ? <PowerOff className="h-4 w-4" /> : <Power className="h-4 w-4" />,
                            },
                            {
                                label: 'Edit',
                                onClick: () => handleEditMonitor(monitor),
                                icon: <Edit className="h-4 w-4" />,
                            },
                            {
                                label: 'Delete',
                                onClick: () => handleDeleteMonitor(monitor.id),
                                variant: 'destructive',
                                icon: <Trash2 className="h-4 w-4" />,
                            },
                        ]}
                        emptyStateMessage="No monitors configured yet"
                        emptyStateIcon={<Bell className="h-6 w-6 text-muted-foreground" />}
                    />
                )}

                {/* Combined Usage Chart */}
                {isActive && latestMetrics && recentMetrics.length > 1 && (
                    <CardContainer title="Usage" icon={<Activity className="h-3 w-3" />}>
                        <ResponsiveContainer width="100%" height={250} className="pt-2 pr-4">
                            <LineChart data={recentMetrics.slice().reverse()}>
                                <CartesianGrid strokeDasharray="3 3" stroke="rgb(229, 231, 235)" strokeOpacity={0.1} vertical={false} />
                                <XAxis
                                    dataKey="collected_at"
                                    tickFormatter={(value) =>
                                        new Date(value).toLocaleTimeString('en-US', {
                                            hour: '2-digit',
                                            minute: '2-digit',
                                        })
                                    }
                                    tick={{ fill: 'rgb(64, 64, 64)', fontSize: 12 }}
                                    tickLine={false}
                                    axisLine={{ stroke: 'rgb(229, 231, 235)' }}
                                />
                                <YAxis
                                    domain={[0, 100]}
                                    tickFormatter={(value) => `${value}%`}
                                    tick={{ fill: 'rgb(64, 64, 64)', fontSize: 12 }}
                                    tickLine={false}
                                    axisLine={{ stroke: 'rgb(229, 231, 235)' }}
                                />
                                <Tooltip content={<CustomTooltip />} />
                                <Line
                                    type="monotone"
                                    dataKey="cpu_usage"
                                    stroke="rgb(120, 160, 200)"
                                    strokeWidth={2}
                                    dot={false}
                                    isAnimationActive={false}
                                    name="CPU"
                                />
                                <Line
                                    type="monotone"
                                    dataKey="memory_usage_percentage"
                                    stroke="rgb(170, 140, 200)"
                                    strokeWidth={2}
                                    dot={false}
                                    isAnimationActive={false}
                                    name="Memory"
                                />
                                <Line
                                    type="monotone"
                                    dataKey="storage_usage_percentage"
                                    stroke="rgb(120, 180, 150)"
                                    strokeWidth={2}
                                    dot={false}
                                    isAnimationActive={false}
                                    name="Storage"
                                />
                            </LineChart>
                        </ResponsiveContainer>
                    </CardContainer>
                )}

                {/* Monitor Modal */}
                <CardFormModal
                    open={showMonitorModal}
                    onOpenChange={setShowMonitorModal}
                    title={editingMonitor ? 'Edit Monitor' : 'Add Monitor'}
                    description="Configure alert thresholds for your server metrics."
                    onSubmit={handleSubmitMonitor}
                    submitLabel={editingMonitor ? 'Update Monitor' : 'Create Monitor'}
                    isSubmitting={monitorProcessing}
                    submittingLabel={editingMonitor ? 'Updating...' : 'Creating...'}
                >
                    <div className="grid gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor="monitor-name">Monitor Name</Label>
                            <Input
                                id="monitor-name"
                                placeholder="High CPU Usage"
                                value={monitorData.name}
                                onChange={(e) => setMonitorData('name', e.target.value)}
                                className={monitorErrors.name ? 'border-red-500' : ''}
                                disabled={monitorProcessing}
                                required
                            />
                            {monitorErrors.name && <p className="text-xs text-red-500">{monitorErrors.name}</p>}
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="metric-type">Metric Type</Label>
                            <Select
                                value={monitorData.metric_type}
                                onValueChange={(value: 'cpu' | 'memory' | 'storage') => setMonitorData('metric_type', value)}
                                disabled={monitorProcessing}
                            >
                                <SelectTrigger id="metric-type">
                                    <SelectValue placeholder="Select metric" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="cpu">CPU Usage</SelectItem>
                                    <SelectItem value="memory">Memory Usage</SelectItem>
                                    <SelectItem value="storage">Storage Usage</SelectItem>
                                </SelectContent>
                            </Select>
                            {monitorErrors.metric_type && <p className="text-xs text-red-500">{monitorErrors.metric_type}</p>}
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <div className="grid gap-2">
                                <Label htmlFor="operator">Operator</Label>
                                <Select
                                    value={monitorData.operator}
                                    onValueChange={(value: '>' | '<' | '>=' | '<=' | '==') => setMonitorData('operator', value)}
                                    disabled={monitorProcessing}
                                >
                                    <SelectTrigger id="operator">
                                        <SelectValue placeholder="Select operator" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value=">">Greater than (&gt;)</SelectItem>
                                        <SelectItem value="<">Less than (&lt;)</SelectItem>
                                        <SelectItem value=">=">Greater than or equal (&gt;=)</SelectItem>
                                        <SelectItem value="<=">Less than or equal (&lt;=)</SelectItem>
                                        <SelectItem value="==">Equal to (==)</SelectItem>
                                    </SelectContent>
                                </Select>
                                {monitorErrors.operator && <p className="text-xs text-red-500">{monitorErrors.operator}</p>}
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="threshold">Threshold (%)</Label>
                                <Input
                                    id="threshold"
                                    type="number"
                                    placeholder="80"
                                    min="0"
                                    max="100"
                                    step="0.1"
                                    value={monitorData.threshold}
                                    onChange={(e) => setMonitorData('threshold', e.target.value)}
                                    className={monitorErrors.threshold ? 'border-red-500' : ''}
                                    disabled={monitorProcessing}
                                    required
                                />
                                {monitorErrors.threshold && <p className="text-xs text-red-500">{monitorErrors.threshold}</p>}
                            </div>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="duration">Duration (minutes)</Label>
                            <Input
                                id="duration"
                                type="number"
                                placeholder="5"
                                min="1"
                                value={monitorData.duration_minutes}
                                onChange={(e) => setMonitorData('duration_minutes', e.target.value)}
                                className={monitorErrors.duration_minutes ? 'border-red-500' : ''}
                                disabled={monitorProcessing}
                                required
                            />
                            <p className="text-xs text-muted-foreground">Trigger alert only if threshold is breached for this duration</p>
                            {monitorErrors.duration_minutes && <p className="text-xs text-red-500">{monitorErrors.duration_minutes}</p>}
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="notification-emails">Notification Emails</Label>
                            <Textarea
                                id="notification-emails"
                                placeholder="admin@example.com, alerts@example.com"
                                value={monitorData.notification_emails}
                                onChange={(e) => setMonitorData('notification_emails', e.target.value)}
                                className={monitorErrors.notification_emails ? 'border-red-500' : ''}
                                disabled={monitorProcessing}
                                rows={2}
                                required
                            />
                            <p className="text-xs text-muted-foreground">Comma-separated list of email addresses</p>
                            {monitorErrors.notification_emails && <p className="text-xs text-red-500">{monitorErrors.notification_emails}</p>}
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="cooldown">Cooldown Period (minutes)</Label>
                            <Input
                                id="cooldown"
                                type="number"
                                placeholder="30"
                                min="1"
                                value={monitorData.cooldown_minutes}
                                onChange={(e) => setMonitorData('cooldown_minutes', e.target.value)}
                                className={monitorErrors.cooldown_minutes ? 'border-red-500' : ''}
                                disabled={monitorProcessing}
                                required
                            />
                            <p className="text-xs text-muted-foreground">Minimum time between alert notifications</p>
                            {monitorErrors.cooldown_minutes && <p className="text-xs text-red-500">{monitorErrors.cooldown_minutes}</p>}
                        </div>
                    </div>
                </CardFormModal>

                {/* Uninstall Monitoring Link */}
                {isActive && (
                    <div className="mt-8 text-right">
                        <button
                            onClick={handleUninstall}
                            disabled={processing}
                            className="text-sm text-red-600 hover:text-red-700 hover:underline disabled:cursor-not-allowed disabled:opacity-50 dark:text-red-500 dark:hover:text-red-400"
                        >
                            {processing ? 'Uninstalling monitoring...' : 'Uninstall Monitoring'}
                        </button>
                    </div>
                )}
            </div>
        </ServerLayout>
    );
}
