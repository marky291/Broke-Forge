import { ServerProviderIcon, type ServerProvider } from '@/components/server-provider-icon';
import { type ServerMetric } from '@/types';
import { Cpu, HardDrive, MemoryStick } from 'lucide-react';

interface ServerDetailProps {
    server: {
        vanity_name: string;
        provider?: ServerProvider;
        public_ip?: string;
        private_ip?: string;
        monitoring_status?: 'installing' | 'active' | 'failed' | 'uninstalling' | 'uninstalled' | null;
    };
    metrics?: ServerMetric | null;
}

export function ServerDetail({ server, metrics }: ServerDetailProps) {
    return (
        <div id="server-detail-bar'server-detail-bar" className="w-full border-b bg-card py-4">
            <div className="container mx-auto max-w-7xl px-4">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:gap-8">
                        {/* Title */}
                        <div className="flex items-center gap-3">
                            <ServerProviderIcon provider={server.provider} size="lg" />
                            <h1 className="text-xl font-semibold text-foreground">{server.vanity_name}</h1>
                        </div>

                        {/* Server Info - Hide some items on mobile */}
                        <div className="flex flex-wrap items-center gap-4 text-sm lg:gap-8 lg:border-l lg:pl-8">
                            <div>
                                <div className="mb-0.5 text-[10px] tracking-wide text-muted-foreground uppercase">Public IP</div>
                                <div className="font-medium">{server.public_ip || 'N/A'}</div>
                            </div>
                            <div className="hidden md:block">
                                <div className="mb-0.5 text-[10px] tracking-wide text-muted-foreground uppercase">Private IP</div>
                                <div className="font-medium">{server.private_ip || 'N/A'}</div>
                            </div>
                            <div className="hidden md:block">
                                <div className="mb-0.5 text-[10px] tracking-wide text-muted-foreground uppercase">Region</div>
                                <div className="font-medium">Frankfurt</div>
                            </div>
                            <div className="hidden lg:block">
                                <div className="mb-0.5 text-[10px] tracking-wide text-muted-foreground uppercase">OS</div>
                                <div className="font-medium">Ubuntu 24.04</div>
                            </div>
                        </div>
                    </div>

                    {/* Monitoring Metrics - Responsive */}
                    {server.monitoring_status === 'active' && metrics && (
                        <div className="flex flex-wrap items-center gap-3 text-sm lg:gap-4 lg:border-l lg:pl-8">
                            <div className="flex items-center gap-2">
                                <Cpu className="h-3.5 w-3.5 text-blue-600" />
                                <div>
                                    <div className="mb-0.5 text-[10px] tracking-wide text-muted-foreground uppercase">CPU</div>
                                    <div className="font-medium">{Number(metrics.cpu_usage).toFixed(1)}%</div>
                                </div>
                            </div>
                            <div className="flex items-center gap-2">
                                <MemoryStick className="h-3.5 w-3.5 text-purple-600" />
                                <div>
                                    <div className="mb-0.5 text-[10px] tracking-wide text-muted-foreground uppercase">Memory</div>
                                    <div className="font-medium">{Number(metrics.memory_usage_percentage).toFixed(1)}%</div>
                                </div>
                            </div>
                            <div className="flex items-center gap-2">
                                <HardDrive className="h-3.5 w-3.5 text-orange-600" />
                                <div>
                                    <div className="mb-0.5 text-[10px] tracking-wide text-muted-foreground uppercase">Storage</div>
                                    <div className="font-medium">{Number(metrics.storage_usage_percentage).toFixed(1)}%</div>
                                </div>
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}
