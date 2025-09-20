import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import ServerLayout from '@/layouts/server/layout';
import { dashboard } from '@/routes';
import { show as showServer } from '@/routes/servers';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import {
    AppWindow,
    CheckCircle,
    Clock,
    DatabaseIcon,
    Folder,
    GitBranch,
    Globe,
    Layers,
    Loader2,
    Lock,
    XCircle,
} from 'lucide-react';

type ServerType = {
    id: number;
    vanity_name: string;
    public_ip: string;
    ssh_port: number;
    private_ip?: string | null;
    connection: string;
    created_at: string;
    updated_at: string;
};

type Site = {
    id: number;
    domain: string;
    document_root: string;
    php_version: string;
    ssl_enabled: boolean;
    status: string;
    configuration: Record<string, unknown> | null;
    provisioned_at: string | null;
    created_at: string;
    updated_at: string;
};

type InstallationOption = {
    key: string;
    title: string;
    description: string;
    icon: typeof AppWindow;
};

const installationOptions: InstallationOption[] = [
    {
        key: 'install-application',
        title: 'Install Application',
        description: 'Provision a first-party application scaffold ready for deployment.',
        icon: AppWindow,
    },
    {
        key: 'git-repository',
        title: 'Git Repository',
        description: 'Deploy an existing site by pulling from a Git provider.',
        icon: GitBranch,
    },
    {
        key: 'statamic',
        title: 'Statamic',
        description: 'Install Statamic with sensible defaults for content-driven sites.',
        icon: Layers,
    },
    {
        key: 'wordpress',
        title: 'WordPress',
        description: 'Spin up a WordPress instance optimised for BrokeForge hosting.',
        icon: Globe,
    },
    {
        key: 'phpmyadmin',
        title: 'phpMyAdmin',
        description: 'Manage the site database using a phpMyAdmin installation.',
        icon: DatabaseIcon,
    },
];

const statusMeta: Record<string, { badgeClass: string; label: string; icon: JSX.Element }> = {
    active: {
        badgeClass: 'bg-green-500/10 text-green-500 border-green-500/20',
        label: 'Active',
        icon: <CheckCircle className="h-4 w-4 text-green-500" />,
    },
    provisioning: {
        badgeClass: 'bg-blue-500/10 text-blue-500 border-blue-500/20',
        label: 'Provisioning',
        icon: <Loader2 className="h-4 w-4 animate-spin text-blue-500" />,
    },
    disabled: {
        badgeClass: 'bg-gray-500/10 text-gray-500 border-gray-500/20',
        label: 'Disabled',
        icon: <XCircle className="h-4 w-4 text-gray-500" />,
    },
    failed: {
        badgeClass: 'bg-red-500/10 text-red-500 border-red-500/20',
        label: 'Failed',
        icon: <XCircle className="h-4 w-4 text-red-500" />,
    },
    default: {
        badgeClass: 'border border-border bg-muted text-muted-foreground',
        label: 'Pending',
        icon: <Clock className="h-4 w-4 text-muted-foreground" />,
    },
};

export default function SiteApplication({ server, site }: { server: ServerType; site: Site }) {
    const [selectedOption, setSelectedOption] = useState<InstallationOption['key']>('install-application');

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: dashboard().url },
        { title: `Server #${server.id}`, href: showServer(server.id).url },
        { title: 'Sites', href: `/servers/${server.id}/sites` },
        { title: 'Application', href: '#' },
    ];

    const activeStatus = statusMeta[site.status] ?? statusMeta.default;

    const formattedProvisionedAt = useMemo(() => {
        if (!site.provisioned_at) {
            return 'Provisioning not started';
        }

        try {
            return new Date(site.provisioned_at).toLocaleString();
        } catch (error) {
            return site.provisioned_at;
        }
    }, [site.provisioned_at]);

    const selectedInstallation = installationOptions.find((option) => option.key === selectedOption) ?? installationOptions[0];

    return (
        <ServerLayout server={server} site={site} breadcrumbs={breadcrumbs}>
            <Head title={`Application — ${site.domain}`} />
            <div className="space-y-6">
                <div className="space-y-1">
                    <h1 className="text-2xl font-semibold">{site.domain}</h1>
                    <p className="text-sm text-muted-foreground">
                        Configure how this application is installed and managed on your server.
                    </p>
                </div>

                <div className="grid gap-4 lg:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)]">
                    <Card>
                        <CardHeader>
                            <CardTitle>Site Overview</CardTitle>
                        </CardHeader>
                        <Separator />
                        <CardContent className="space-y-4 text-sm">
                            <div>
                                <div className="text-muted-foreground">Status</div>
                                <div className="mt-1 flex items-center gap-2">
                                    {activeStatus.icon}
                                    <Badge className={activeStatus.badgeClass}>{activeStatus.label}</Badge>
                                </div>
                            </div>
                            <div>
                                <div className="text-muted-foreground">Document Root</div>
                                <div className="mt-1 flex items-center gap-2">
                                    <Folder className="h-4 w-4 text-muted-foreground" />
                                    <span className="font-medium">{site.document_root}</span>
                                </div>
                            </div>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <div className="text-muted-foreground">PHP Version</div>
                                    <div className="mt-1 font-medium">{site.php_version}</div>
                                </div>
                                <div>
                                    <div className="text-muted-foreground">SSL</div>
                                    <div className="mt-1 flex items-center gap-2 font-medium">
                                        <Lock className={`h-4 w-4 ${site.ssl_enabled ? 'text-green-500' : 'text-muted-foreground'}`} />
                                        {site.ssl_enabled ? 'Enabled' : 'Disabled'}
                                    </div>
                                </div>
                            </div>
                            <div>
                                <div className="text-muted-foreground">Provisioned At</div>
                                <div className="mt-1 font-medium">{formattedProvisionedAt}</div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Installation Methods</CardTitle>
                        </CardHeader>
                        <Separator />
                        <CardContent className="space-y-4">
                            <div className="grid gap-3 sm:grid-cols-2">
                                {installationOptions.map((option) => {
                                    const Icon = option.icon;
                                    const isSelected = selectedOption === option.key;

                                    return (
                                        <Button
                                            key={option.key}
                                            type="button"
                                            variant={isSelected ? 'default' : 'outline'}
                                            className="h-auto justify-start gap-3 p-4 text-left"
                                            onClick={() => setSelectedOption(option.key)}
                                        >
                                            <Icon className="h-5 w-5" />
                                            <div className="flex flex-col items-start gap-1">
                                                <span className="text-sm font-semibold">{option.title}</span>
                                                <span className="text-xs text-muted-foreground">{option.description}</span>
                                            </div>
                                        </Button>
                                    );
                                })}
                            </div>

                            <div className="rounded-lg border border-dashed border-border bg-muted/40 p-4 text-sm">
                                <div className="font-medium">Selected Method</div>
                                <div className="mt-1 text-muted-foreground">
                                    {selectedInstallation.description}
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </ServerLayout>
    );
}
