import { Button } from '@/components/ui/button';
import { CardContainer } from '@/components/ui/card-container';
import { PageHeader } from '@/components/ui/page-header';
import ServerLayout from '@/layouts/server/layout';
import { dashboard } from '@/routes';
import { show as showServer } from '@/routes/servers';
import { type BreadcrumbItem, type Server, type ServerMetric } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { Activity, AlertCircle, CheckCircle, Cpu, HardDrive, Loader2, MemoryStick } from 'lucide-react';
import { useEffect, useState } from 'react';
import { CartesianGrid, Legend, Line, LineChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';

export default function Monitoring({
    server,
    latestMetrics,
    recentMetrics,
}: {
    server: Server;
    latestMetrics: ServerMetric | null;
    recentMetrics: ServerMetric[];
}) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: dashboard.url() },
        { title: server.vanity_name, href: showServer({ server: server.id }).url },
        { title: 'Monitor', href: '#' },
    ];

    const { post, processing } = useForm({});
    const [localMetrics, setLocalMetrics] = useState<ServerMetric | null>(latestMetrics);
    const [localRecentMetrics, setLocalRecentMetrics] = useState<ServerMetric[]>(recentMetrics);

    const isActive = server.monitoring_status === 'active';

    // Poll for new metrics when monitoring is active
    useEffect(() => {
        if (!isActive) return;

        const interval = setInterval(async () => {
            try {
                const res = await fetch(`/servers/${server.id}/monitoring/metrics?hours=24`, {
                    headers: { Accept: 'application/json' },
                });
                if (!res.ok) return;
                const json = await res.json();
                if (json.success && json.data && json.data.length > 0) {
                    setLocalMetrics(json.data[json.data.length - 1]);
                    setLocalRecentMetrics(json.data);
                }
            } catch (error) {
                console.error('Failed to fetch metrics:', error);
            }
        }, 30000); // Polling interval - configurable in config/monitoring.php

        return () => clearInterval(interval);
    }, [isActive, server.id]);

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

    const formatBytes = (mb: number) => {
        if (mb >= 1024) {
            return `${(mb / 1024).toFixed(1)} GB`;
        }
        return `${mb} MB`;
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
                <CardContainer title="Monitoring Status">
                    {server.monitoring_status === 'failed' ? (
                        <div className="p-8 text-center">
                            <div className="flex items-center justify-center w-12 h-12 rounded-lg bg-red-500/10 mx-auto">
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
                            <div className="flex items-center justify-center w-12 h-12 rounded-lg bg-blue-500/10 mx-auto">
                                <Loader2 className="h-6 w-6 text-blue-600 animate-spin" />
                            </div>
                            <h3 className="mt-4 text-lg font-semibold">Installing Monitoring</h3>
                            <p className="mt-2 text-sm text-muted-foreground">
                                Please wait while monitoring is being installed on your server...
                            </p>
                        </div>
                    ) : server.monitoring_status === 'uninstalling' ? (
                        <div className="p-8 text-center">
                            <div className="flex items-center justify-center w-12 h-12 rounded-lg bg-orange-500/10 mx-auto">
                                <Loader2 className="h-6 w-6 text-orange-600 animate-spin" />
                            </div>
                            <h3 className="mt-4 text-lg font-semibold">Uninstalling Monitoring</h3>
                            <p className="mt-2 text-sm text-muted-foreground">
                                Please wait while monitoring is being removed from your server...
                            </p>
                        </div>
                    ) : !isActive ? (
                        <div className="p-8 text-center">
                            <Activity className="mx-auto h-12 w-12 text-muted-foreground/50" />
                            <h3 className="mt-4 text-lg font-semibold">Monitoring Not Installed</h3>
                            <p className="mt-2 text-sm text-muted-foreground">
                                Install monitoring to track CPU, memory, and storage usage on your server.
                            </p>
                            <Button onClick={handleInstall} disabled={processing} className="mt-4">
                                {processing ? (
                                    <>
                                        <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                        Installing...
                                    </>
                                ) : (
                                    'Install Monitoring'
                                )}
                            </Button>
                        </div>
                    ) : (
                        <div className="p-6">
                            <div className="flex items-center justify-between">
                                <div className="flex items-center gap-3">
                                    <div className="flex items-center justify-center w-10 h-10 rounded-lg bg-green-500/10">
                                        <CheckCircle className="h-5 w-5 text-green-600" />
                                    </div>
                                    <div>
                                        <h3 className="text-base font-semibold">Monitoring Active</h3>
                                        <p className="text-sm text-muted-foreground">
                                            Collecting metrics every {(server.monitoring_collection_interval || 300) / 60} minutes
                                        </p>
                                    </div>
                                </div>
                                <Button onClick={handleUninstall} disabled={processing} variant="destructive">
                                    {processing ? (
                                        <>
                                            <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                            Uninstalling...
                                        </>
                                    ) : (
                                        'Uninstall Monitoring'
                                    )}
                                </Button>
                            </div>
                        </div>
                    )}
                </CardContainer>

                {/* Current Metrics */}
                {isActive && localMetrics && (
                    <>
                        <CardContainer title="Current Metrics">
                            <div className="grid grid-cols-1 md:grid-cols-3 gap-6 p-6">
                                {/* CPU Usage */}
                                <div className="space-y-3">
                                    <div className="flex items-center gap-3">
                                        <div className="flex items-center justify-center w-10 h-10 rounded-lg bg-blue-500/10">
                                            <Cpu className="h-5 w-5 text-blue-600" />
                                        </div>
                                        <div>
                                            <p className="text-sm text-muted-foreground">CPU Usage</p>
                                            <p className="text-2xl font-bold">{Number(localMetrics.cpu_usage).toFixed(1)}%</p>
                                        </div>
                                    </div>
                                    <div className="w-full bg-muted rounded-full h-2">
                                        <div
                                            className="bg-blue-600 h-2 rounded-full transition-all"
                                            style={{ width: `${Math.min(localMetrics.cpu_usage, 100)}%` }}
                                        />
                                    </div>
                                </div>

                                {/* Memory Usage */}
                                <div className="space-y-3">
                                    <div className="flex items-center gap-3">
                                        <div className="flex items-center justify-center w-10 h-10 rounded-lg bg-purple-500/10">
                                            <MemoryStick className="h-5 w-5 text-purple-600" />
                                        </div>
                                        <div>
                                            <p className="text-sm text-muted-foreground">Memory Usage</p>
                                            <p className="text-2xl font-bold">{Number(localMetrics.memory_usage_percentage).toFixed(1)}%</p>
                                        </div>
                                    </div>
                                    <div className="w-full bg-muted rounded-full h-2">
                                        <div
                                            className="bg-purple-600 h-2 rounded-full transition-all"
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
                                        <div className="flex items-center justify-center w-10 h-10 rounded-lg bg-orange-500/10">
                                            <HardDrive className="h-5 w-5 text-orange-600" />
                                        </div>
                                        <div>
                                            <p className="text-sm text-muted-foreground">Storage Usage</p>
                                            <p className="text-2xl font-bold">{Number(localMetrics.storage_usage_percentage).toFixed(1)}%</p>
                                        </div>
                                    </div>
                                    <div className="w-full bg-muted rounded-full h-2">
                                        <div
                                            className="bg-orange-600 h-2 rounded-full transition-all"
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
                            <CardContainer title="Usage Over Time (Last 24 Hours)">
                                <div className="p-6">
                                    <ResponsiveContainer width="100%" height={300}>
                                        <LineChart data={localRecentMetrics.slice().reverse()}>
                                            <CartesianGrid strokeDasharray="3 3" className="stroke-muted" />
                                            <XAxis
                                                dataKey="collected_at"
                                                tickFormatter={(value) => new Date(value).toLocaleTimeString()}
                                                className="text-xs"
                                            />
                                            <YAxis
                                                domain={[0, 100]}
                                                tickFormatter={(value) => `${value}%`}
                                                className="text-xs"
                                            />
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
                                            />
                                            <Line
                                                type="monotone"
                                                dataKey="memory_usage_percentage"
                                                stroke="rgb(168, 85, 247)"
                                                strokeWidth={2}
                                                dot={false}
                                                name="Memory Usage"
                                            />
                                            <Line
                                                type="monotone"
                                                dataKey="storage_usage_percentage"
                                                stroke="rgb(234, 88, 12)"
                                                strokeWidth={2}
                                                dot={false}
                                                name="Storage Usage"
                                            />
                                        </LineChart>
                                    </ResponsiveContainer>
                                </div>
                            </CardContainer>
                        )}

                        {/* Recent Metrics Table */}
                        {localRecentMetrics.length > 0 && (
                            <CardContainer title="Recent Metrics (Last 24 Hours)">
                                <div className="overflow-x-auto">
                                    <table className="w-full">
                                        <thead className="bg-muted/50">
                                            <tr>
                                                <th className="px-4 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">
                                                    Time
                                                </th>
                                                <th className="px-4 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">
                                                    CPU
                                                </th>
                                                <th className="px-4 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">
                                                    Memory
                                                </th>
                                                <th className="px-4 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">
                                                    Storage
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-border/50">
                                            {localRecentMetrics.slice().reverse().map((metric) => (
                                                <tr key={metric.id} className="hover:bg-muted/30">
                                                    <td className="px-4 py-3 text-sm">
                                                        {new Date(metric.collected_at).toLocaleString()}
                                                    </td>
                                                    <td className="px-4 py-3 text-sm">{Number(metric.cpu_usage).toFixed(1)}%</td>
                                                    <td className="px-4 py-3 text-sm">{Number(metric.memory_usage_percentage).toFixed(1)}%</td>
                                                    <td className="px-4 py-3 text-sm">{Number(metric.storage_usage_percentage).toFixed(1)}%</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </CardContainer>
                        )}
                    </>
                )}
            </div>
        </ServerLayout>
    );
}
