import { InstallSkeleton } from '@/components/install-skeleton';
import { Button } from '@/components/ui/button';
import { CardContainer } from '@/components/ui/card-container';
import { CardTable, type CardTableColumn } from '@/components/ui/card-table';
import { Label } from '@/components/ui/label';
import { PageHeader } from '@/components/ui/page-header';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { usePagination } from '@/components/ui/table-pagination';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import ServerLayout from '@/layouts/server/layout';
import { dashboard } from '@/routes';
import { show as showServer } from '@/routes/servers';
import { type BreadcrumbItem, type Server, type ServerMetric } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { Activity, AlertCircle, CheckCircle, Cpu, HardDrive, Loader2, MemoryStick } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { CartesianGrid, Legend, Line, LineChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';

export default function Monitoring({
    server,
    latestMetrics,
    recentMetrics,
    selectedTimeframe = 24,
}: {
    server: Server;
    latestMetrics: ServerMetric | null;
    recentMetrics: ServerMetric[];
    selectedTimeframe?: number;
}) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: dashboard.url() },
        { title: server.vanity_name, href: showServer({ server: server.id }).url },
        { title: 'Monitor', href: '#' },
    ];

    const { post, processing } = useForm({});
    const [localMetrics, setLocalMetrics] = useState<ServerMetric | null>(latestMetrics);
    const [localRecentMetrics, setLocalRecentMetrics] = useState<ServerMetric[]>(recentMetrics);

    // Ensure timeframe is always valid (24, 72, or 168)
    const validTimeframe = [24, 72, 168].includes(selectedTimeframe) ? selectedTimeframe : 24;
    const [timeframe, setTimeframe] = useState<string>(validTimeframe.toString());
    const [collectionInterval, setCollectionInterval] = useState<string>((server.monitoring_collection_interval || 300).toString());
    const [nextCollectionCountdown, setNextCollectionCountdown] = useState<string>('Calculating...');

    // Pagination for Recent Metrics table (latest first - already sorted by backend)
    const { paginatedData: paginatedMetrics, paginationProps } = usePagination(localRecentMetrics, 5);

    const isActive = server.monitoring_status === 'active';

    // Table columns for Recent Metrics
    const metricsColumns: CardTableColumn<ServerMetric>[] = [
        {
            header: 'Time',
            accessor: (metric) => <span className="text-sm">{new Date(metric.collected_at).toLocaleString()}</span>,
        },
        {
            header: 'CPU',
            accessor: (metric) => <span className="text-sm">{Number(metric.cpu_usage).toFixed(1)}%</span>,
        },
        {
            header: 'Memory',
            accessor: (metric) => <span className="text-sm">{Number(metric.memory_usage_percentage).toFixed(1)}%</span>,
        },
        {
            header: 'Storage',
            accessor: (metric) => <span className="text-sm">{Number(metric.storage_usage_percentage).toFixed(1)}%</span>,
        },
    ];

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

    // Calculate countdown to next metric collection
    useEffect(() => {
        if (!isActive || !localMetrics) {
            setNextCollectionCountdown('No metrics yet');
            return;
        }

        const updateCountdown = () => {
            const lastCollectedAt = new Date(localMetrics.collected_at);
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
    }, [isActive, localMetrics?.collected_at, collectionInterval]);

    // Poll for new metrics when monitoring is active
    useEffect(() => {
        if (!isActive) return;

        // Poll every 10 seconds to check for new metrics (regardless of collection interval)
        const pollingInterval = 10000; // 10 seconds
        // Capture current timeframe value to avoid closure issues
        const currentTimeframe = timeframe;

        const fetchMetrics = async () => {
            try {
                const res = await fetch(`/servers/${server.id}/monitoring/metrics?hours=${currentTimeframe}`, {
                    headers: { Accept: 'application/json' },
                });
                if (!res.ok) return;
                const json = await res.json();
                if (json.success && json.data && json.data.length > 0) {
                    // Force new array reference to trigger React re-render
                    const metrics = [...json.data];
                    setLocalMetrics(metrics[0]); // First item is latest (desc order)
                    setLocalRecentMetrics(metrics);
                }
            } catch (error) {
                console.error('Failed to fetch metrics:', error);
            }
        };

        // Fetch immediately on mount
        fetchMetrics();

        // Then poll at regular intervals
        const interval = setInterval(fetchMetrics, pollingInterval);

        return () => {
            clearInterval(interval);
        };
    }, [isActive, server.id, timeframe]);

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

    // Memoize chart data to prevent re-renders when countdown updates
    const chartData = useMemo(() => {
        return localRecentMetrics.slice().reverse();
    }, [localRecentMetrics]);

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

                {/* Current Metrics */}
                {isActive && localMetrics && (
                    <>
                        <CardContainer
                            title="Current Metrics"
                            icon={
                                <svg width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M1.5 9.5L4 7L6 9L10.5 4.5" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" />
                                    <path d="M7.5 4.5H10.5V7.5" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" />
                                </svg>
                            }
                        >
                            <div className="grid grid-cols-1 gap-6 p-6 md:grid-cols-3">
                                {/* CPU Usage */}
                                <div className="space-y-3">
                                    <div className="flex items-center gap-3">
                                        <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-500/10">
                                            <Cpu className="h-5 w-5 text-blue-600" />
                                        </div>
                                        <div>
                                            <p className="text-sm text-muted-foreground">CPU Usage</p>
                                            <p className="text-2xl font-bold">{Number(localMetrics.cpu_usage).toFixed(1)}%</p>
                                        </div>
                                    </div>
                                    <div className="h-2 w-full rounded-full bg-muted">
                                        <div
                                            className="h-2 rounded-full bg-blue-600 transition-all"
                                            style={{ width: `${Math.min(localMetrics.cpu_usage, 100)}%` }}
                                        />
                                    </div>
                                </div>

                                {/* Memory Usage */}
                                <div className="space-y-3">
                                    <div className="flex items-center gap-3">
                                        <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-purple-500/10">
                                            <MemoryStick className="h-5 w-5 text-purple-600" />
                                        </div>
                                        <div>
                                            <p className="text-sm text-muted-foreground">Memory Usage</p>
                                            <p className="text-2xl font-bold">{Number(localMetrics.memory_usage_percentage).toFixed(1)}%</p>
                                        </div>
                                    </div>
                                    <div className="h-2 w-full rounded-full bg-muted">
                                        <div
                                            className="h-2 rounded-full bg-purple-600 transition-all"
                                            style={{ width: `${Math.min(localMetrics.memory_usage_percentage, 100)}%` }}
                                        />
                                    </div>
                                    <p className="text-xs text-muted-foreground">
                                        {formatBytes(localMetrics.memory_used_mb)} / {formatBytes(localMetrics.memory_total_mb)}
                                    </p>
                                </div>

                                {/* Storage Usage */}
                                <div className="space-y-3">
                                    <div className="flex items-center gap-3">
                                        <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-orange-500/10">
                                            <HardDrive className="h-5 w-5 text-orange-600" />
                                        </div>
                                        <div>
                                            <p className="text-sm text-muted-foreground">Storage Usage</p>
                                            <p className="text-2xl font-bold">{Number(localMetrics.storage_usage_percentage).toFixed(1)}%</p>
                                        </div>
                                    </div>
                                    <div className="h-2 w-full rounded-full bg-muted">
                                        <div
                                            className="h-2 rounded-full bg-orange-600 transition-all"
                                            style={{ width: `${Math.min(localMetrics.storage_usage_percentage, 100)}%` }}
                                        />
                                    </div>
                                    <p className="text-xs text-muted-foreground">
                                        {localMetrics.storage_used_gb} GB / {localMetrics.storage_total_gb} GB
                                    </p>
                                </div>
                            </div>
                        </CardContainer>

                        {/* Usage Chart */}
                        {localRecentMetrics.length > 1 && (
                            <CardContainer
                                title={`Usage Over Time (${timeframeOptions.find((opt) => opt.value === timeframe)?.label || 'Last 24 Hours'})`}
                                icon={
                                    <svg width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M1.5 1.5V10.5H10.5" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" />
                                        <path d="M3 7.5L5 5.5L7 7L10.5 3.5" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" />
                                    </svg>
                                }
                                action={
                                    <ToggleGroup type="single" value={timeframe} onValueChange={handleTimeframeChange} variant="outline">
                                        {timeframeOptions.map((option) => (
                                            <ToggleGroupItem key={option.value} value={option.value}>
                                                {option.label}
                                            </ToggleGroupItem>
                                        ))}
                                    </ToggleGroup>
                                }
                            >
                                <div className="p-6">
                                    <ResponsiveContainer width="100%" height={300}>
                                        <LineChart data={chartData}>
                                            <CartesianGrid strokeDasharray="3 3" className="stroke-muted" />
                                            <XAxis
                                                dataKey="collected_at"
                                                tickFormatter={(value) => new Date(value).toLocaleTimeString()}
                                                className="text-xs"
                                            />
                                            <YAxis domain={[0, 100]} tickFormatter={(value) => `${value}%`} className="text-xs" />
                                            <Tooltip
                                                labelFormatter={(value) => new Date(value).toLocaleString()}
                                                formatter={(value: number) => `${Number(value).toFixed(1)}%`}
                                                contentStyle={{
                                                    backgroundColor: 'hsl(var(--background))',
                                                    border: '1px solid hsl(var(--border))',
                                                    borderRadius: '0.5rem',
                                                }}
                                            />
                                            <Legend />
                                            <Line
                                                type="monotone"
                                                dataKey="cpu_usage"
                                                stroke="rgb(37, 99, 235)"
                                                strokeWidth={2}
                                                dot={false}
                                                name="CPU Usage"
                                                isAnimationActive={false}
                                            />
                                            <Line
                                                type="monotone"
                                                dataKey="memory_usage_percentage"
                                                stroke="rgb(168, 85, 247)"
                                                strokeWidth={2}
                                                dot={false}
                                                name="Memory Usage"
                                                isAnimationActive={false}
                                            />
                                            <Line
                                                type="monotone"
                                                dataKey="storage_usage_percentage"
                                                stroke="rgb(234, 88, 12)"
                                                strokeWidth={2}
                                                dot={false}
                                                name="Storage Usage"
                                                isAnimationActive={false}
                                            />
                                        </LineChart>
                                    </ResponsiveContainer>
                                </div>
                            </CardContainer>
                        )}

                        {/* Recent Metrics Table */}
                        {localRecentMetrics.length > 0 && (
                            <CardContainer
                                title={`Recent Metrics (${timeframeOptions.find((opt) => opt.value === timeframe)?.label || 'Last 24 Hours'})`}
                                icon={
                                    <svg width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <rect
                                            x="1.5"
                                            y="1.5"
                                            width="9"
                                            height="9"
                                            rx="1"
                                            stroke="currentColor"
                                            strokeLinecap="round"
                                            strokeLinejoin="round"
                                        />
                                        <path d="M1.5 4.5H10.5M4.5 1.5V10.5" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" />
                                    </svg>
                                }
                            >
                                <CardTable
                                    columns={metricsColumns}
                                    data={paginatedMetrics}
                                    getRowKey={(metric) => metric.id}
                                    pagination={paginationProps}
                                />
                            </CardContainer>
                        )}
                    </>
                )}

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
