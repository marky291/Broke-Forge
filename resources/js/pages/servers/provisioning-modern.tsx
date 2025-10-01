import ProvisioningProgress from '@/components/provisioning/provisioning-progress';
import ProvisioningCommands from '@/components/provisioning/provisioning-commands';
import ServerLayout from '@/layouts/server/layout';
import { dashboard } from '@/routes';
import { show as showServer } from '@/routes/servers';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';

interface Server {
    id: number;
    vanity_name: string;
    public_ip: string;
    private_ip?: string | null;
    ssh_port: number;
    connection: string;
    provision_status: string;
    provision_status_label: string;
    provision_status_color: string;
    created_at: string;
    updated_at: string;
}

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

interface ProvisionData {
    command: string;
    root_password: string;
}

interface ProvisioningPageProps {
    server: Server;
    provision: ProvisionData | null;
    events: ServerEvent[];
    latestProgress: ServerEvent[];
}

export default function ProvisioningPage({
    server,
    provision,
    events,
    latestProgress,
}: ProvisioningPageProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: dashboard.url() },
        { title: `Server #${server.id}`, href: showServer(server.id).url },
        { title: 'Provisioning', href: '#' },
    ];

    return (
        <ServerLayout server={server} breadcrumbs={breadcrumbs}>
            <Head title={`Provisioning — ${server.vanity_name}`} />
            
            <div className="space-y-6">
                <div>
                    <h2 className="text-2xl font-semibold">Server Provisioning</h2>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Set up your server with essential services and configurations.
                    </p>
                </div>

                {/* Show provisioning commands if server needs setup */}
                {provision && (
                    <ProvisioningCommands
                        provisionData={provision}
                        serverName={server.vanity_name}
                        serverIp={server.public_ip}
                    />
                )}

                {/* Show progress if provisioning has started */}
                {events.length > 0 && (
                    <ProvisioningProgress
                        events={events}
                        latestProgress={latestProgress}
                        serverId={server.id}
                    />
                )}

                {/* Show completion message */}
                {server.provision_status === 'completed' && (
                    <div className="rounded-xl border border-green-200 bg-green-50 p-6 dark:border-green-900 dark:bg-green-900/10">
                        <div className="flex items-center gap-2">
                            <div className="h-2 w-2 rounded-full bg-green-600"></div>
                            <h3 className="font-semibold text-green-900 dark:text-green-100">
                                Provisioning Complete!
                            </h3>
                        </div>
                        <p className="text-sm text-green-700 dark:text-green-200 mt-1">
                            Your server is fully configured and ready to host applications.
                        </p>
                    </div>
                )}
            </div>
        </ServerLayout>
    );
}