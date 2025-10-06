import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Progress } from '@/components/ui/progress';
import { Form } from '@inertiajs/react';
import { CheckIcon, Loader2, XCircle } from 'lucide-react';

interface ServerEvent {
    id: number;
    service_type: string;
    milestone: string;
    current_step: number;
    total_steps: number;
    progress_percentage: number;
    status: string;
    details?: {
        label?: string;
    };
}

interface ProvisioningProgressProps {
    events: ServerEvent[];
    latestProgress: ServerEvent[];
    serverId: number;
}

export default function ProvisioningProgress({ events, latestProgress, serverId }: ProvisioningProgressProps) {
    const getStatusConfig = (status: string) => {
        switch (status) {
            case 'success':
                return {
                    icon: CheckIcon,
                    variant: 'default' as const,
                    color: 'text-green-600',
                    bgColor: 'bg-green-100 dark:bg-green-900/20',
                };
            case 'failed':
                return {
                    icon: XCircle,
                    variant: 'destructive' as const,
                    color: 'text-red-600',
                    bgColor: 'bg-red-100 dark:bg-red-900/20',
                };
            default:
                return {
                    icon: Loader2,
                    variant: 'secondary' as const,
                    color: 'text-blue-600',
                    bgColor: 'bg-blue-100 dark:bg-blue-900/20',
                };
        }
    };

    return (
        <div
            className="space-y-4"
            poll={events.some((e) => e.status === 'pending') ? { interval: 2000, only: ['events', 'latestProgress'] } : undefined}
        >
            {/* Overall Progress */}
            <div className="rounded-xl border bg-background p-6">
                <h3 className="mb-4 text-lg font-semibold">Provisioning Progress</h3>

                <div className="space-y-4">
                    {latestProgress.map((event) => {
                        const statusConfig = getStatusConfig(event.status);
                        const StatusIcon = statusConfig.icon;
                        const isActive = event.status === 'pending';

                        return (
                            <div key={event.service_type} className={`rounded-lg border p-4 transition-colors ${statusConfig.bgColor}`}>
                                <div className="mb-2 flex items-center justify-between">
                                    <div className="flex items-center gap-3">
                                        <StatusIcon className={`h-5 w-5 ${statusConfig.color} ${isActive ? 'animate-spin' : ''}`} />
                                        <div>
                                            <h4 className="font-medium capitalize">{event.service_type} Setup</h4>
                                            <p className="text-sm text-muted-foreground">{event.details?.label || event.milestone}</p>
                                        </div>
                                    </div>
                                    <Badge variant={statusConfig.variant} className="gap-1">
                                        {event.current_step}/{event.total_steps}
                                    </Badge>
                                </div>

                                {isActive && (
                                    <div className="mt-3">
                                        <Progress value={event.progress_percentage} className="h-2" />
                                        <div className="mt-1 flex justify-between text-xs text-muted-foreground">
                                            <span>
                                                Step {event.current_step} of {event.total_steps}
                                            </span>
                                            <span>{event.progress_percentage}%</span>
                                        </div>
                                    </div>
                                )}
                            </div>
                        );
                    })}
                </div>
            </div>

            {/* Retry Option for Failed Provisioning */}
            {events.some((e) => e.status === 'failed') && (
                <div className="rounded-xl border border-red-200 bg-red-50 p-6 dark:border-red-900 dark:bg-red-900/10">
                    <div className="mb-3 flex items-center gap-2">
                        <XCircle className="h-5 w-5 text-red-600" />
                        <h3 className="font-semibold text-red-900 dark:text-red-100">Provisioning Failed</h3>
                    </div>
                    <p className="mb-4 text-sm text-red-700 dark:text-red-200">
                        Server provisioning encountered errors. You can retry the provisioning process.
                    </p>
                    <Form method="post" action={`/servers/${serverId}/provision/retry`}>
                        {({ processing }) => (
                            <Button type="submit" variant="destructive" size="sm" disabled={processing}>
                                {processing ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : 'Retry Provisioning'}
                            </Button>
                        )}
                    </Form>
                </div>
            )}
        </div>
    );
}
