import { CardList, type CardListAction } from '@/components/card-list';
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
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import ServerLayout from '@/layouts/server/layout';
import { dashboard } from '@/routes';
import { show as showServer } from '@/routes/servers';
import { type BreadcrumbItem, type Server, type ServerMonitor } from '@/types';
import { useEcho } from '@laravel/echo-react';
import { Head, router, useForm } from '@inertiajs/react';
import { Activity, AlertCircle, AlertTriangle, Bell, CheckCircle, Cpu, Edit, HardDrive, Loader2, MemoryStick, Power, PowerOff, Trash2 } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { Area, AreaChart, CartesianGrid, Legend, Line, LineChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import { ArrowDown, ArrowRight, ArrowUp, TrendingDown, TrendingUp } from 'lucide-react';

// Helper function to calculate statistics for a metric
const calculateStats = (metrics: any[], key: string) => {
    if (!metrics || metrics.length === 0) {
        return { min: 0, max: 0, avg: 0 };
    }

    const values = metrics.map((m) => Number(m[key])).filter((v) => !isNaN(v));
    if (values.length === 0) {
        return { min: 0, max: 0, avg: 0 };
    }

    const min = Math.min(...values);
    const max = Math.max(...values);
    const avg = values.reduce((sum, val) => sum + val, 0) / values.length;

    return { min, max, avg };
};

// Helper function to calculate trend
const calculateTrend = (current: number, metrics: any[], key: string) => {
    if (!metrics || metrics.length < 2) {
        return { direction: 'stable' as const, change: 0 };
    }

    const recentValues = metrics.slice(-10).map((m) => Number(m[key])).filter((v) => !isNaN(v));
    if (recentValues.length === 0) {
        return { direction: 'stable' as const, change: 0 };
    }

    const avg = recentValues.reduce((sum, val) => sum + val, 0) / recentValues.length;
    const change = current - avg;
    const changePercent = avg > 0 ? (change / avg) * 100 : 0;

    // Consider changes > 5% as trending
    if (Math.abs(changePercent) < 5) {
        return { direction: 'stable' as const, change: changePercent };
    }

    return {
        direction: change > 0 ? ('up' as const) : ('down' as const),
        change: changePercent,
    };
};

// Helper function to get status color based on usage - CONSISTENT STATUS COLORS
const getStatusColor = (usage: number) => {
    if (usage >= 80) {
        return {
            bg: 'bg-red-500/10 dark:bg-red-500/20',
            bgSolid: 'bg-red-50 dark:bg-red-950/30',
            text: 'text-red-600 dark:text-red-500',
            textDark: 'text-red-700 dark:text-red-400',
            textLight: 'text-red-900 dark:text-red-100',
            bar: 'bg-red-600',
            border: 'border-red-300 dark:border-red-700',
            borderLight: 'border-red-200 dark:border-red-800',
            stroke: 'rgb(220, 38, 38)', // red-600
            fill: 'rgba(220, 38, 38, 0.1)',
            status: 'critical' as const,
        };
    } else if (usage >= 60) {
        return {
            bg: 'bg-amber-500/10 dark:bg-amber-500/20',
            bgSolid: 'bg-amber-50 dark:bg-amber-950/30',
            text: 'text-amber-600 dark:text-amber-500',
            textDark: 'text-amber-700 dark:text-amber-400',
            textLight: 'text-amber-900 dark:text-amber-100',
            bar: 'bg-amber-600',
            border: 'border-amber-300 dark:border-amber-700',
            borderLight: 'border-amber-200 dark:border-amber-800',
            stroke: 'rgb(217, 119, 6)', // amber-600
            fill: 'rgba(217, 119, 6, 0.1)',
            status: 'warning' as const,
        };
    }
    return {
        bg: 'bg-green-500/10 dark:bg-green-500/20',
        bgSolid: 'bg-green-50 dark:bg-green-950/30',
        text: 'text-green-600 dark:text-green-500',
        textDark: 'text-green-700 dark:text-green-400',
        textLight: 'text-green-900 dark:text-green-100',
        bar: 'bg-green-600',
        border: 'border-green-300 dark:border-green-700',
        borderLight: 'border-green-200 dark:border-green-800',
        stroke: 'rgb(22, 163, 74)', // green-600
        fill: 'rgba(22, 163, 74, 0.1)',
        status: 'healthy' as const,
    };
};

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
    const { data: monitorData, setData: setMonitorData, post: postMonitor, put: putMonitor, processing: monitorProcessing, errors: monitorErrors, reset: resetMonitor } = useForm({
        name: '',
        metric_type: 'cpu' as 'cpu' | 'memory' | 'storage',
        operator: '>=' as '>' | '<' | '>=' | '<=' | '==',
        threshold: '',
        duration_minutes: '5',
        notification_emails: '',
        cooldown_minutes: '30',
    });

    // Ensure timeframe is always valid (24, 72, or 168)
    const validTimeframe = [24, 72, 168].includes(selectedTimeframe) ? selectedTimeframe : 24;
    const [timeframe, setTimeframe] = useState<string>(validTimeframe.toString());
    const [collectionInterval, setCollectionInterval] = useState<string>((server.monitoring_collection_interval || 300).toString());
    const [nextCollectionCountdown, setNextCollectionCountdown] = useState<string>('Calculating...');
    const [justUpdated, setJustUpdated] = useState(false);

    const isActive = server.monitoring_status === 'active';

    // Timeframe options
    const timeframeOptions = [
        { value: '24', label: '24 Hours' },
        { value: '72', label: '3 Days' },
        { value: '168', label: '7 Days' },
    ];

    // Handle timeframe change
    const handleTimeframeChange = (value: string) => {
        if (value) {
            setTimeframe(value);
            router.get(`/servers/${server.id}/monitoring`, { hours: value }, { preserveState: true, preserveScroll: true });
        }
    };

    // Real-time updates via Reverb WebSocket - listens for new metrics
    useEcho(`servers.${server.id}`, 'ServerUpdated', () => {
        // Show update indicator
        setJustUpdated(true);

        router.reload({
            only: ['server'],
            preserveScroll: true,
            preserveState: true,
        });

        // Hide update indicator after 3 seconds
        setTimeout(() => {
            setJustUpdated(false);
        }, 3000);
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

    const handleIntervalChange = (value: string) => {
        setCollectionInterval(value);
        router.post(
            `/servers/${server.id}/monitoring/update-interval`,
            {
                interval: parseInt(value),
                hours: parseInt(timeframe), // Preserve current viewing timeframe
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
            notification_emails: monitorData.notification_emails.split(',').map((email) => email.trim()).filter((email) => email),
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

    // Memoize chart data to prevent re-renders when countdown updates
    const chartData = useMemo(() => {
        return recentMetrics.slice().reverse();
    }, [recentMetrics]);

    // Sparkline component for mini trend graphs
    const Sparkline = ({ data, dataKey, color }: { data: any[]; dataKey: string; color: string }) => {
        if (!data || data.length < 2) {
            return null;
        }

        // Get last 15 points for sparkline
        const sparklineData = data.slice(-15);
        const values = sparklineData.map((d) => Number(d[dataKey]));
        const max = Math.max(...values, 100);
        const min = Math.min(...values, 0);
        const range = max - min || 1;

        // Create SVG path
        const width = 80;
        const height = 24;
        const points = sparklineData.map((d, i) => {
            const x = (i / (sparklineData.length - 1)) * width;
            const value = Number(d[dataKey]);
            const y = height - ((value - min) / range) * height;
            return `${x},${y}`;
        });

        return (
            <svg width={width} height={height} className="opacity-60">
                <polyline
                    fill="none"
                    stroke={color}
                    strokeWidth="1.5"
                    points={points.join(' ')}
                />
            </svg>
        );
    };

    // Custom tooltip component
    const CustomTooltip = ({ active, payload, label }: any) => {
        if (!active || !payload || !payload.length) {
            return null;
        }

        return (
            <div className="rounded-lg border border-border bg-background p-3 shadow-lg">
                <p className="mb-2 text-xs font-medium text-foreground">
                    {new Date(label).toLocaleString('en-US', {
                        month: 'short',
                        day: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit',
                    })}
                </p>
                <div className="space-y-1">
                    {payload.map((entry: any, index: number) => (
                        <div key={index} className="flex items-center justify-between gap-4 text-xs">
                            <div className="flex items-center gap-2">
                                <div className="h-2 w-2 rounded-full" style={{ backgroundColor: entry.color }} />
                                <span className="text-muted-foreground">{entry.name}</span>
                            </div>
                            <span className="font-semibold text-foreground">{Number(entry.value).toFixed(1)}%</span>
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

                {/* Monitoring Status */}
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
                            <Button onClick={handleInstall} disabled={processing} className="mt-4">
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
                    ) : server.monitoring_status === 'installing' ? (
                        <div className="p-8 text-center">
                            <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-lg bg-blue-500/10">
                                <Loader2 className="h-6 w-6 animate-spin text-blue-600" />
                            </div>
                            <h3 className="mt-4 text-lg font-semibold">Installing Monitoring</h3>
                            <p className="mt-2 text-sm text-muted-foreground">Please wait while monitoring is being installed on your server...</p>
                        </div>
                    ) : server.monitoring_status === 'uninstalling' ? (
                        <div className="p-8 text-center">
                            <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-lg bg-orange-500/10">
                                <Loader2 className="h-6 w-6 animate-spin text-orange-600" />
                            </div>
                            <h3 className="mt-4 text-lg font-semibold">Uninstalling Monitoring</h3>
                            <p className="mt-2 text-sm text-muted-foreground">Please wait while monitoring is being removed from your server...</p>
                        </div>
                    ) : !isActive ? (
                        <InstallSkeleton
                            icon={Activity}
                            title="Monitoring Not Installed"
                            description="Install monitoring to track CPU, memory, and storage usage on your server."
                            buttonLabel="Install Monitoring"
                            onInstall={handleInstall}
                            isInstalling={processing}
                        />
                    ) : (
                        <div className="space-y-6 p-6">
                            <div className="flex items-center justify-between">
                                <div className="flex items-center gap-3">
                                    <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-green-500/10">
                                        <CheckCircle className="h-5 w-5 text-green-600" />
                                    </div>
                                    <div>
                                        <h3 className="text-base font-semibold">Monitoring Active</h3>
                                        <p className="text-sm text-muted-foreground">Configure metrics collection interval below</p>
                                    </div>
                                </div>
                                <div className="text-right">
                                    <p className="text-xs text-muted-foreground">Next collection in</p>
                                    <p className="text-lg font-semibold text-green-600">{nextCollectionCountdown}</p>
                                </div>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="collection-interval">Collection Interval</Label>
                                <Select value={collectionInterval} onValueChange={handleIntervalChange} disabled={processing}>
                                    <SelectTrigger id="collection-interval">
                                        <SelectValue placeholder="Select interval" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="60">1 minute</SelectItem>
                                        <SelectItem value="300">5 minutes</SelectItem>
                                        <SelectItem value="600">10 minutes</SelectItem>
                                        <SelectItem value="1200">20 minutes</SelectItem>
                                        <SelectItem value="1800">30 minutes</SelectItem>
                                        <SelectItem value="3600">Every Hourly</SelectItem>
                                    </SelectContent>
                                </Select>
                                <p className="text-xs text-muted-foreground">Choose how often metrics are collected from your server</p>
                            </div>
                        </div>
                    )}
                </CardContainer>

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
                                        {!monitor.enabled && <Badge className="bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200">Disabled</Badge>}
                                    </div>
                                    <div className="mt-1 text-xs text-muted-foreground">
                                        <span>
                                            {getMetricLabel(monitor.metric_type)} {getOperatorLabel(monitor.operator)} {monitor.threshold}% for {monitor.duration_minutes} min
                                        </span>
                                        {monitor.last_triggered_at && (
                                            <span className="ml-2">
                                                • Last triggered: {new Date(monitor.last_triggered_at).toLocaleString()}
                                            </span>
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

                {/* Current Metrics */}
                {isActive && latestMetrics && (
                    <>
                        <CardContainer
                            title="Current Metrics"
                            icon={
                                <svg width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M1.5 9.5L4 7L6 9L10.5 4.5" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" />
                                    <path d="M7.5 4.5H10.5V7.5" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" />
                                </svg>
                            }
                            action={
                                justUpdated && (
                                    <div className="animate-in fade-in slide-in-from-right-2 duration-300">
                                        <Badge className="bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                            <div className="mr-1 h-1.5 w-1.5 animate-pulse rounded-full bg-blue-600" />
                                            Updated
                                        </Badge>
                                    </div>
                                )
                            }
                        >
                            <div className="grid grid-cols-1 gap-4 p-4 sm:gap-6 sm:p-6 lg:grid-cols-3">
                                {/* CPU Usage */}
                                {(() => {
                                    const cpuValue = Number(latestMetrics.cpu_usage);
                                    const cpuStatus = getStatusColor(cpuValue);
                                    const cpuStats = calculateStats(recentMetrics, 'cpu_usage');
                                    const cpuTrend = calculateTrend(cpuValue, recentMetrics, 'cpu_usage');

                                    return (
                                        <div className={`relative space-y-3 rounded-lg border-2 p-4 transition-all ${cpuStatus.border} ${justUpdated ? 'animate-pulse' : ''}`}>
                                            <div className="flex items-start justify-between">
                                                <div className="flex items-center gap-3">
                                                    <div className={`flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-lg ${cpuStatus.bg}`}>
                                                        <Cpu className={`h-6 w-6 ${cpuStatus.text}`} />
                                                    </div>
                                                    <div className="min-w-0 flex-1">
                                                        <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">CPU Usage</p>
                                                        <div className="flex items-baseline gap-2">
                                                            <p className={`text-3xl font-bold tabular-nums sm:text-4xl ${cpuStatus.text}`}>{cpuValue.toFixed(1)}%</p>
                                                            {cpuTrend.direction !== 'stable' && (
                                                                <div className="flex items-center gap-1 text-xs">
                                                                    {cpuTrend.direction === 'up' ? (
                                                                        <ArrowUp className={`h-3 w-3 ${cpuStatus.text}`} />
                                                                    ) : (
                                                                        <ArrowDown className={`h-3 w-3 ${cpuStatus.text}`} />
                                                                    )}
                                                                    <span className={cpuStatus.text}>{Math.abs(cpuTrend.change).toFixed(1)}%</span>
                                                                </div>
                                                            )}
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            {recentMetrics.length > 1 && (
                                                <div className="flex justify-end">
                                                    <Sparkline data={recentMetrics.slice().reverse()} dataKey="cpu_usage" color={cpuStatus.stroke} />
                                                </div>
                                            )}
                                            <div className="h-2.5 w-full rounded-full bg-muted">
                                                <div
                                                    className={`h-2.5 rounded-full transition-all duration-500 ${cpuStatus.bar}`}
                                                    style={{ width: `${Math.min(cpuValue, 100)}%` }}
                                                />
                                            </div>
                                            <div className="grid grid-cols-3 gap-2 text-center text-xs">
                                                <div>
                                                    <p className="text-muted-foreground">Min</p>
                                                    <p className={`font-semibold ${cpuStatus.textDark}`}>{cpuStats.min.toFixed(1)}%</p>
                                                </div>
                                                <div>
                                                    <p className="text-muted-foreground">Avg</p>
                                                    <p className={`font-semibold ${cpuStatus.textDark}`}>{cpuStats.avg.toFixed(1)}%</p>
                                                </div>
                                                <div>
                                                    <p className="text-muted-foreground">Max</p>
                                                    <p className={`font-semibold ${cpuStatus.textDark}`}>{cpuStats.max.toFixed(1)}%</p>
                                                </div>
                                            </div>
                                        </div>
                                    );
                                })()}

                                {/* Memory Usage */}
                                {(() => {
                                    const memoryValue = Number(latestMetrics.memory_usage_percentage);
                                    const memoryStatus = getStatusColor(memoryValue);
                                    const memoryStats = calculateStats(recentMetrics, 'memory_usage_percentage');
                                    const memoryTrend = calculateTrend(memoryValue, recentMetrics, 'memory_usage_percentage');

                                    return (
                                        <div className={`relative space-y-3 rounded-lg border-2 p-4 transition-all ${memoryStatus.border} ${justUpdated ? 'animate-pulse' : ''}`}>
                                            <div className="flex items-start justify-between">
                                                <div className="flex items-center gap-3">
                                                    <div className={`flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-lg ${memoryStatus.bg}`}>
                                                        <MemoryStick className={`h-6 w-6 ${memoryStatus.text}`} />
                                                    </div>
                                                    <div className="min-w-0 flex-1">
                                                        <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">Memory Usage</p>
                                                        <div className="flex items-baseline gap-2">
                                                            <p className={`text-3xl font-bold tabular-nums sm:text-4xl ${memoryStatus.text}`}>{memoryValue.toFixed(1)}%</p>
                                                            {memoryTrend.direction !== 'stable' && (
                                                                <div className="flex items-center gap-1 text-xs">
                                                                    {memoryTrend.direction === 'up' ? (
                                                                        <ArrowUp className={`h-3 w-3 ${memoryStatus.text}`} />
                                                                    ) : (
                                                                        <ArrowDown className={`h-3 w-3 ${memoryStatus.text}`} />
                                                                    )}
                                                                    <span className={memoryStatus.text}>{Math.abs(memoryTrend.change).toFixed(1)}%</span>
                                                                </div>
                                                            )}
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            {recentMetrics.length > 1 && (
                                                <div className="flex justify-end">
                                                    <Sparkline data={recentMetrics.slice().reverse()} dataKey="memory_usage_percentage" color={memoryStatus.stroke} />
                                                </div>
                                            )}
                                            <div className="h-2.5 w-full rounded-full bg-muted">
                                                <div
                                                    className={`h-2.5 rounded-full transition-all duration-500 ${memoryStatus.bar}`}
                                                    style={{ width: `${Math.min(memoryValue, 100)}%` }}
                                                />
                                            </div>
                                            <div className="space-y-2">
                                                <div className="grid grid-cols-3 gap-2 text-center text-xs">
                                                    <div>
                                                        <p className="text-muted-foreground">Min</p>
                                                        <p className={`font-semibold ${memoryStatus.textDark}`}>{memoryStats.min.toFixed(1)}%</p>
                                                    </div>
                                                    <div>
                                                        <p className="text-muted-foreground">Avg</p>
                                                        <p className={`font-semibold ${memoryStatus.textDark}`}>{memoryStats.avg.toFixed(1)}%</p>
                                                    </div>
                                                    <div>
                                                        <p className="text-muted-foreground">Max</p>
                                                        <p className={`font-semibold ${memoryStatus.textDark}`}>{memoryStats.max.toFixed(1)}%</p>
                                                    </div>
                                                </div>
                                                <p className="text-center text-xs text-muted-foreground">
                                                    {formatBytes(latestMetrics.memory_used_mb)} / {formatBytes(latestMetrics.memory_total_mb)}
                                                </p>
                                            </div>
                                        </div>
                                    );
                                })()}

                                {/* Storage Usage */}
                                {(() => {
                                    const storageValue = Number(latestMetrics.storage_usage_percentage);
                                    const storageStatus = getStatusColor(storageValue);
                                    const storageStats = calculateStats(recentMetrics, 'storage_usage_percentage');
                                    const storageTrend = calculateTrend(storageValue, recentMetrics, 'storage_usage_percentage');

                                    return (
                                        <div className={`relative space-y-3 rounded-lg border-2 p-4 transition-all ${storageStatus.border} ${justUpdated ? 'animate-pulse' : ''}`}>
                                            <div className="flex items-start justify-between">
                                                <div className="flex items-center gap-3">
                                                    <div className={`flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-lg ${storageStatus.bg}`}>
                                                        <HardDrive className={`h-6 w-6 ${storageStatus.text}`} />
                                                    </div>
                                                    <div className="min-w-0 flex-1">
                                                        <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">Storage Usage</p>
                                                        <div className="flex items-baseline gap-2">
                                                            <p className={`text-3xl font-bold tabular-nums sm:text-4xl ${storageStatus.text}`}>{storageValue.toFixed(1)}%</p>
                                                            {storageTrend.direction !== 'stable' && (
                                                                <div className="flex items-center gap-1 text-xs">
                                                                    {storageTrend.direction === 'up' ? (
                                                                        <ArrowUp className={`h-3 w-3 ${storageStatus.text}`} />
                                                                    ) : (
                                                                        <ArrowDown className={`h-3 w-3 ${storageStatus.text}`} />
                                                                    )}
                                                                    <span className={storageStatus.text}>{Math.abs(storageTrend.change).toFixed(1)}%</span>
                                                                </div>
                                                            )}
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            {recentMetrics.length > 1 && (
                                                <div className="flex justify-end">
                                                    <Sparkline data={recentMetrics.slice().reverse()} dataKey="storage_usage_percentage" color={storageStatus.stroke} />
                                                </div>
                                            )}
                                            <div className="h-2.5 w-full rounded-full bg-muted">
                                                <div
                                                    className={`h-2.5 rounded-full transition-all duration-500 ${storageStatus.bar}`}
                                                    style={{ width: `${Math.min(storageValue, 100)}%` }}
                                                />
                                            </div>
                                            <div className="space-y-2">
                                                <div className="grid grid-cols-3 gap-2 text-center text-xs">
                                                    <div>
                                                        <p className="text-muted-foreground">Min</p>
                                                        <p className={`font-semibold ${storageStatus.textDark}`}>{storageStats.min.toFixed(1)}%</p>
                                                    </div>
                                                    <div>
                                                        <p className="text-muted-foreground">Avg</p>
                                                        <p className={`font-semibold ${storageStatus.textDark}`}>{storageStats.avg.toFixed(1)}%</p>
                                                    </div>
                                                    <div>
                                                        <p className="text-muted-foreground">Max</p>
                                                        <p className={`font-semibold ${storageStatus.textDark}`}>{storageStats.max.toFixed(1)}%</p>
                                                    </div>
                                                </div>
                                                <p className="text-center text-xs text-muted-foreground">
                                                    {latestMetrics.storage_used_gb} GB / {latestMetrics.storage_total_gb} GB
                                                </p>
                                            </div>
                                        </div>
                                    );
                                })()}
                            </div>
                        </CardContainer>

                        {/* Summary Statistics */}
                        {recentMetrics.length > 1 && (
                            <CardContainer
                                title="Summary Statistics"
                                icon={
                                    <svg width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <rect x="2" y="3" width="3" height="7" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" />
                                        <rect x="7" y="2" width="3" height="8" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" />
                                        <path d="M1.5 10.5h9" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" />
                                    </svg>
                                }
                                action={
                                    <div className="flex flex-wrap items-center gap-2">
                                        <span className="hidden text-xs text-muted-foreground sm:inline">Timeframe:</span>
                                        <ToggleGroup type="single" value={timeframe} onValueChange={handleTimeframeChange} variant="outline" className="flex-wrap">
                                            {timeframeOptions.map((option) => (
                                                <ToggleGroupItem key={option.value} value={option.value} className="text-xs sm:text-sm">
                                                    {option.label}
                                                </ToggleGroupItem>
                                            ))}
                                        </ToggleGroup>
                                    </div>
                                }
                            >
                                <div className="grid grid-cols-1 gap-4 p-4 sm:gap-6 sm:p-6 lg:grid-cols-3">
                                    {/* CPU Statistics */}
                                    {(() => {
                                        const cpuStats = calculateStats(recentMetrics, 'cpu_usage');
                                        const cpuStatus = getStatusColor(cpuStats.avg);
                                        return (
                                            <div className={`space-y-3 rounded-lg border-2 p-4 ${cpuStatus.borderLight} ${cpuStatus.bgSolid}`}>
                                                <div className="flex items-center gap-2">
                                                    <div className={`rounded-md p-1.5 ${cpuStatus.bg}`}>
                                                        <Cpu className={`h-4 w-4 ${cpuStatus.text}`} />
                                                    </div>
                                                    <h4 className={`text-sm font-semibold ${cpuStatus.textLight}`}>CPU Usage</h4>
                                                </div>
                                                <div className="grid grid-cols-3 gap-3 text-xs">
                                                    <div className="text-center">
                                                        <p className="text-muted-foreground">Minimum</p>
                                                        <p className={`text-lg font-bold tabular-nums ${cpuStatus.textDark}`}>{cpuStats.min.toFixed(1)}%</p>
                                                    </div>
                                                    <div className="text-center">
                                                        <p className="text-muted-foreground">Average</p>
                                                        <p className={`text-lg font-bold tabular-nums ${cpuStatus.textDark}`}>{cpuStats.avg.toFixed(1)}%</p>
                                                    </div>
                                                    <div className="text-center">
                                                        <p className="text-muted-foreground">Maximum</p>
                                                        <p className={`text-lg font-bold tabular-nums ${cpuStatus.textDark}`}>{cpuStats.max.toFixed(1)}%</p>
                                                    </div>
                                                </div>
                                            </div>
                                        );
                                    })()}

                                    {/* Memory Statistics */}
                                    {(() => {
                                        const memoryStats = calculateStats(recentMetrics, 'memory_usage_percentage');
                                        const memoryStatus = getStatusColor(memoryStats.avg);
                                        return (
                                            <div className={`space-y-3 rounded-lg border-2 p-4 ${memoryStatus.borderLight} ${memoryStatus.bgSolid}`}>
                                                <div className="flex items-center gap-2">
                                                    <div className={`rounded-md p-1.5 ${memoryStatus.bg}`}>
                                                        <MemoryStick className={`h-4 w-4 ${memoryStatus.text}`} />
                                                    </div>
                                                    <h4 className={`text-sm font-semibold ${memoryStatus.textLight}`}>Memory Usage</h4>
                                                </div>
                                                <div className="grid grid-cols-3 gap-3 text-xs">
                                                    <div className="text-center">
                                                        <p className="text-muted-foreground">Minimum</p>
                                                        <p className={`text-lg font-bold tabular-nums ${memoryStatus.textDark}`}>{memoryStats.min.toFixed(1)}%</p>
                                                    </div>
                                                    <div className="text-center">
                                                        <p className="text-muted-foreground">Average</p>
                                                        <p className={`text-lg font-bold tabular-nums ${memoryStatus.textDark}`}>{memoryStats.avg.toFixed(1)}%</p>
                                                    </div>
                                                    <div className="text-center">
                                                        <p className="text-muted-foreground">Maximum</p>
                                                        <p className={`text-lg font-bold tabular-nums ${memoryStatus.textDark}`}>{memoryStats.max.toFixed(1)}%</p>
                                                    </div>
                                                </div>
                                            </div>
                                        );
                                    })()}

                                    {/* Storage Statistics */}
                                    {(() => {
                                        const storageStats = calculateStats(recentMetrics, 'storage_usage_percentage');
                                        const storageStatus = getStatusColor(storageStats.avg);
                                        return (
                                            <div className={`space-y-3 rounded-lg border-2 p-4 ${storageStatus.borderLight} ${storageStatus.bgSolid}`}>
                                                <div className="flex items-center gap-2">
                                                    <div className={`rounded-md p-1.5 ${storageStatus.bg}`}>
                                                        <HardDrive className={`h-4 w-4 ${storageStatus.text}`} />
                                                    </div>
                                                    <h4 className={`text-sm font-semibold ${storageStatus.textLight}`}>Storage Usage</h4>
                                                </div>
                                                <div className="grid grid-cols-3 gap-3 text-xs">
                                                    <div className="text-center">
                                                        <p className="text-muted-foreground">Minimum</p>
                                                        <p className={`text-lg font-bold tabular-nums ${storageStatus.textDark}`}>{storageStats.min.toFixed(1)}%</p>
                                                    </div>
                                                    <div className="text-center">
                                                        <p className="text-muted-foreground">Average</p>
                                                        <p className={`text-lg font-bold tabular-nums ${storageStatus.textDark}`}>{storageStats.avg.toFixed(1)}%</p>
                                                    </div>
                                                    <div className="text-center">
                                                        <p className="text-muted-foreground">Maximum</p>
                                                        <p className={`text-lg font-bold tabular-nums ${storageStatus.textDark}`}>{storageStats.max.toFixed(1)}%</p>
                                                    </div>
                                                </div>
                                            </div>
                                        );
                                    })()}
                                </div>
                            </CardContainer>
                        )}

                        {/* Usage Chart */}
                        {recentMetrics.length > 1 && (
                            <CardContainer
                                title={`Usage Over Time`}
                                icon={
                                    <svg width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M1.5 1.5V10.5H10.5" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" />
                                        <path d="M3 7.5L5 5.5L7 7L10.5 3.5" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" />
                                    </svg>
                                }
                            >
                                <div className="p-4 sm:p-6">
                                    <ResponsiveContainer width="100%" height={window.innerWidth < 640 ? 250 : 350}>
                                        <AreaChart data={chartData}>
                                            <defs>
                                                <linearGradient id="cpuGradient" x1="0" y1="0" x2="0" y2="1">
                                                    <stop offset="5%" stopColor="rgb(59, 130, 246)" stopOpacity={0.3} />
                                                    <stop offset="95%" stopColor="rgb(59, 130, 246)" stopOpacity={0} />
                                                </linearGradient>
                                                <linearGradient id="memoryGradient" x1="0" y1="0" x2="0" y2="1">
                                                    <stop offset="5%" stopColor="rgb(168, 85, 247)" stopOpacity={0.3} />
                                                    <stop offset="95%" stopColor="rgb(168, 85, 247)" stopOpacity={0} />
                                                </linearGradient>
                                                <linearGradient id="storageGradient" x1="0" y1="0" x2="0" y2="1">
                                                    <stop offset="5%" stopColor="rgb(249, 115, 22)" stopOpacity={0.3} />
                                                    <stop offset="95%" stopColor="rgb(249, 115, 22)" stopOpacity={0} />
                                                </linearGradient>
                                            </defs>
                                            <CartesianGrid strokeDasharray="3 3" className="stroke-muted/50" />
                                            <XAxis
                                                dataKey="collected_at"
                                                tickFormatter={(value) =>
                                                    new Date(value).toLocaleTimeString('en-US', {
                                                        hour: '2-digit',
                                                        minute: '2-digit',
                                                    })
                                                }
                                                className="text-xs"
                                                stroke="hsl(var(--muted-foreground))"
                                            />
                                            <YAxis
                                                domain={[0, 100]}
                                                tickFormatter={(value) => `${value}%`}
                                                className="text-xs"
                                                stroke="hsl(var(--muted-foreground))"
                                            />
                                            <Tooltip content={<CustomTooltip />} />
                                            <Legend wrapperStyle={{ paddingTop: '20px' }} />
                                            <Area
                                                type="monotone"
                                                dataKey="cpu_usage"
                                                stroke="rgb(59, 130, 246)"
                                                strokeWidth={2.5}
                                                fill="url(#cpuGradient)"
                                                name="CPU Usage"
                                                isAnimationActive={false}
                                            />
                                            <Area
                                                type="monotone"
                                                dataKey="memory_usage_percentage"
                                                stroke="rgb(168, 85, 247)"
                                                strokeWidth={2.5}
                                                fill="url(#memoryGradient)"
                                                name="Memory Usage"
                                                isAnimationActive={false}
                                            />
                                            <Area
                                                type="monotone"
                                                dataKey="storage_usage_percentage"
                                                stroke="rgb(249, 115, 22)"
                                                strokeWidth={2.5}
                                                fill="url(#storageGradient)"
                                                name="Storage Usage"
                                                isAnimationActive={false}
                                            />
                                        </AreaChart>
                                    </ResponsiveContainer>
                                </div>
                            </CardContainer>
                        )}
                    </>
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
                            <Select value={monitorData.metric_type} onValueChange={(value: 'cpu' | 'memory' | 'storage') => setMonitorData('metric_type', value)} disabled={monitorProcessing}>
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
                                <Select value={monitorData.operator} onValueChange={(value: '>' | '<' | '>=' | '<=' | '==') => setMonitorData('operator', value)} disabled={monitorProcessing}>
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
