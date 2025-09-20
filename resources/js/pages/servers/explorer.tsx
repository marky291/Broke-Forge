import ServerFileBrowser from '@/features/server-file-explorer/file-browser';
import { useServerFileBrowser } from '@/features/server-file-explorer/use-server-file-browser';
import ServerLayout from '@/layouts/server/layout';
import { dashboard } from '@/routes';
import { show as showServer } from '@/routes/servers';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';

type ServerProps = {
    id: number;
    vanity_name: string;
    public_ip: string;
    private_ip?: string | null;
    ssh_port: number;
    connection: string;
    created_at: string;
    updated_at: string;
};

type ExplorerPageProps = {
    server: ServerProps;
};

export default function Explorer({ server }: ExplorerPageProps) {
    const { state, refresh, navigateTo, navigateUp, upload, download, dismissError } = useServerFileBrowser(server.id);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: dashboard().url },
        { title: `Server #${server.id}`, href: showServer(server.id).url },
        { title: 'Explorer', href: '#' },
    ];

    return (
        <ServerLayout server={server} breadcrumbs={breadcrumbs}>
            <Head title={`Explorer — ${server.vanity_name}`} />
            <ServerFileBrowser
                state={state}
                onNavigate={navigateTo}
                onNavigateUp={navigateUp}
                onRefresh={refresh}
                onUpload={upload}
                onDownload={download}
                onDismissError={dismissError}
            />
        </ServerLayout>
    );
}
