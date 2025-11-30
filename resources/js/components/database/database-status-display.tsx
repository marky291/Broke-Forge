import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { CheckIcon, DatabaseIcon, Loader2, XCircle } from 'lucide-react';

interface ServerDatabase {
    id: number;
    name: string;
    engine: string;
    version: string;
    port: number;
    status: string;
    created_at: string;
    updated_at: string;
}

interface DatabaseStatusDisplayProps {
    database: ServerDatabase;
    serverId: number;
    availableEngines: Record<string, any>;
    onUninstall: () => void;
}

export default function DatabaseStatusDisplay({ database, serverId, availableEngines, onUninstall }: DatabaseStatusDisplayProps) {
    const getStatusConfig = (status: string) => {
        switch (status) {
            case 'active':
                return {
                    icon: CheckIcon,
                    variant: 'default' as const,
                    color: 'text-green-600',
                    label: 'Active',
                };
            case 'installing':
                return {
                    icon: Loader2,
                    variant: 'secondary' as const,
                    color: 'text-blue-600',
                    label: 'Installing',
                };
            case 'uninstalling':
                return {
                    icon: Loader2,
                    variant: 'secondary' as const,
                    color: 'text-orange-600',
                    label: 'Uninstalling',
                };
            case 'updating':
                return {
                    icon: Loader2,
                    variant: 'secondary' as const,
                    color: 'text-blue-600',
                    label: 'Updating',
                };
            case 'failed':
                return {
                    icon: XCircle,
                    variant: 'destructive' as const,
                    color: 'text-red-600',
                    label: 'Failed',
                };
            default:
                return {
                    icon: DatabaseIcon,
                    variant: 'outline' as const,
                    color: 'text-muted-foreground',
                    label: status,
                };
        }
    };

    const statusConfig = getStatusConfig(database.status);
    const StatusIcon = statusConfig.icon;
    const isProcessing = ['installing', 'uninstalling', 'updating'].includes(database.status);
    const engineName = availableEngines[database.engine]?.name || database.engine;

    return (
        <div className="rounded-xl border border-sidebar-border/70 bg-background shadow-sm dark:border-sidebar-border">
            <div className="px-4 py-3">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-2">
                        <DatabaseIcon className="h-5 w-5 text-green-600" />
                        <div className="text-sm font-medium tracking-wide text-neutral-600 uppercase dark:text-neutral-300">Database Service</div>
                    </div>
                    <Badge variant={statusConfig.variant} className="gap-1.5">
                        <StatusIcon className={`h-3 w-3 ${isProcessing ? 'animate-spin' : ''}`} />
                        {statusConfig.label}
                    </Badge>
                </div>
            </div>
            <Separator />

            <div className="space-y-4 px-4 py-4">
                {/* Database Information */}
                <div className="grid grid-cols-2 gap-4 md:grid-cols-4">
                    <div>
                        <div className="text-sm text-muted-foreground">Engine</div>
                        <div className="font-medium">{engineName}</div>
                    </div>
                    <div>
                        <div className="text-sm text-muted-foreground">Version</div>
                        <div className="font-medium">{database.version}</div>
                    </div>
                    <div>
                        <div className="text-sm text-muted-foreground">Port</div>
                        <div className="font-medium">{database.port}</div>
                    </div>
                    <div>
                        <div className="text-sm text-muted-foreground">Name</div>
                        <div className="font-medium">{database.name}</div>
                    </div>
                </div>

                {/* Actions */}
                {!isProcessing && (
                    <div className="flex justify-end gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => {
                                // Add manage/configure functionality here
                                console.log('Manage database');
                            }}
                        >
                            Manage
                        </Button>
                        <Button variant="destructive" size="sm" onClick={onUninstall}>
                            Uninstall
                        </Button>
                    </div>
                )}
            </div>
        </div>
    );
}
