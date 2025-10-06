import ServerFileBrowser from '@/features/server-file-explorer/file-browser';
import { useServerFileBrowser } from '@/features/server-file-explorer/use-server-file-browser';
import SiteLayout from '@/layouts/server/site-layout';
import { dashboard } from '@/routes';
import { show as showServer } from '@/routes/servers';
import { show as showSite } from '@/routes/servers/sites';
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

type SiteProps = {
    id: number;
    domain?: string | null;
    document_root: string;
    status: string;
    git_status?: string | null;
};

type ExplorerPageProps = {
    server: ServerProps;
    site: SiteProps;
};

export default function Explorer({ server, site }: ExplorerPageProps) {
    const { state, refresh, navigateTo, upload, download, deleteFiles } = useServerFileBrowser(server.id, site.id);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: dashboard.url() },
        { title: `Server #${server.id}`, href: showServer(server.id).url },
        { title: site.domain || 'Site', href: showSite([server.id, site.id]).url },
        { title: 'Explorer', href: '#' },
    ];

    return (
        <SiteLayout server={server} site={site} breadcrumbs={breadcrumbs}>
            <Head title={`Explorer — ${site.domain || 'Site'}`} />
            <ServerFileBrowser
                state={state}
                onNavigate={navigateTo}
                onRefresh={refresh}
                onUpload={upload}
                onDownload={download}
                onDelete={deleteFiles}
            />
        </SiteLayout>
    );
}
