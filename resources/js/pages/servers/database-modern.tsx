import DatabaseInstallationForm from '@/components/database/database-installation-form';
import DatabaseStatusDisplay from '@/components/database/database-status-display';
import ServerLayout from '@/layouts/server/layout';
import { dashboard } from '@/routes';
import { show as showServer } from '@/routes/servers';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';

interface Server {
    id: number;
    vanity_name: string;
    public_ip: string;
    private_ip?: string | null;
    ssh_port: number;
    connection: string;
    provision_status: string;
    created_at: string;
    updated_at: string;
}

interface DatabaseEngineConfig {
    name: string;
    description: string;
    versions: Record<string, string>;
    default_version: string;
    default_port: number;
}

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

interface DatabasePageProps {
    server: Server;
    availableEngines?: Record<string, DatabaseEngineConfig>;
    database?: ServerDatabase | null;
}

export default function DatabasePage({ server, availableEngines = {}, database = null }: DatabasePageProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: dashboard.url() },
        { title: `Server #${server.id}`, href: showServer(server.id).url },
        { title: 'Database', href: '#' },
    ];

    const handleUninstallDatabase = () => {
        const confirmed = window.confirm(
            'Are you sure you want to uninstall the database? This will permanently delete all data and cannot be undone.',
        );

        if (confirmed) {
            router.delete(`/servers/${server.id}/database`, {
                preserveScroll: true,
            });
        }
    };

    return (
        <ServerLayout server={server} breadcrumbs={breadcrumbs}>
            <Head title={`Database — ${server.vanity_name}`} />

            <div className="space-y-6">
                <div>
                    <h2 className="text-2xl font-semibold">Database Management</h2>
                    <p className="mt-1 text-sm text-muted-foreground">Install and manage database services for your server.</p>
                </div>

                {/* Status Polling for real-time updates */}
                <div
                    poll={
                        database && ['installing', 'uninstalling', 'updating'].includes(database.status)
                            ? { interval: 2000, only: ['database'] }
                            : undefined
                    }
                >
                    {database ? (
                        <DatabaseStatusDisplay
                            database={database}
                            serverId={server.id}
                            availableEngines={availableEngines}
                            onUninstall={handleUninstallDatabase}
                        />
                    ) : (
                        <DatabaseInstallationForm serverId={server.id} availableEngines={availableEngines} />
                    )}
                </div>
            </div>
        </ServerLayout>
    );
}
