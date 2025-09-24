import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Separator } from '@/components/ui/separator';
import SiteLayout from '@/layouts/server/site-layout';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import { show as showServer } from '@/routes/servers';
import { gitRepository as gitRepositoryRoute } from '@/routes/servers/sites';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import { AppWindow, Check, CheckCircle, Clock, DatabaseIcon, GitBranch, Globe, Layers, Loader2, XCircle } from 'lucide-react';
import { type ReactNode, useEffect, useMemo, useState } from 'react';

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

/**
 * Represents a site hosted on a server.
 * Maps to the ServerSite model on the backend.
 */
type ServerSite = {
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
    icon: LucideIcon;
    highlights: string[];
    keywords: string[];
    nextStep: string;
};

const installationOptions: InstallationOption[] = [
    {
        key: 'install-application',
        title: 'Install Application',
        description: 'Provision a first-party application scaffold ready for deployment.',
        icon: AppWindow,
        highlights: [
            'Generate a fresh BrokeForge-ready project with opinionated defaults.',
            'Includes queue worker, scheduler, and environment scaffolding.',
            'Best suited for greenfield applications where you control the stack.',
        ],
        keywords: ['starter', 'scaffold', 'application', 'fresh install', 'laravel'],
        nextStep: 'Create a new BrokeForge project with environment defaults tuned for this server.',
    },
    {
        key: 'git-repository',
        title: 'Git Repository',
        description: 'Deploy an existing site by pulling from a Git provider.',
        icon: GitBranch,
        highlights: [
            'Connect to GitHub for automated deployments.',
            'Supports zero-downtime releases with BrokeForge deployment hooks.',
            'Great when your production workflow already lives in version control.',
        ],
        keywords: ['deploy', 'git', 'ci', 'existing project', 'repository'],
        nextStep: 'Authorize BrokeForge to access your GitHub repository and deploy the latest commit.',
    },
    {
        key: 'statamic',
        title: 'Statamic',
        description: 'Install Statamic with sensible defaults for content-driven sites.',
        icon: Layers,
        highlights: [
            'Pre-configures caches, storage, and content directories for Statamic.',
            'Applies recommended PHP modules and memory settings tuned for Statamic.',
            'Ideal for marketing or documentation sites with rich content editing.',
        ],
        keywords: ['cms', 'flat file', 'content', 'statamic'],
        nextStep: 'Provision Statamic with the storage, caching, and queue services it expects.',
    },
    {
        key: 'wordpress',
        title: 'WordPress',
        description: 'Spin up a WordPress instance optimised for BrokeForge hosting.',
        icon: Globe,
        highlights: [
            'Hardened defaults for WordPress with smart caching and SSL redirects.',
            'Optional helpers for background jobs, cron replacement, and media storage.',
            'Perfect for teams migrating off shared hosting into a managed stack.',
        ],
        keywords: ['cms', 'wordpress', 'blog', 'site builder'],
        nextStep: 'Set up WordPress with hardened defaults, SSL, and automated cron replacements.',
    },
    {
        key: 'phpmyadmin',
        title: 'phpMyAdmin',
        description: 'Manage the site database using a phpMyAdmin installation.',
        icon: DatabaseIcon,
        highlights: [
            'Launch a scoped phpMyAdmin instance secured behind BrokeForge auth.',
            'Ideal for quick data inspections without exposing the database publicly.',
            'Tear it down easily once you finish administrative database tasks.',
        ],
        keywords: ['database', 'mysql', 'admin', 'tools', 'phpmyadmin'],
        nextStep: 'Provision a secured phpMyAdmin instance scoped to this site’s database.',
    },
];

const statusMeta: Record<string, { badgeClass: string; label: string; icon: ReactNode; description: string }> = {
    active: {
        badgeClass: 'bg-green-500/10 text-green-500 border-green-500/20',
        label: 'Active',
        icon: <CheckCircle className="h-4 w-4 text-green-500" />,
        description: 'The application is serving traffic. Keep an eye on deployments and queued jobs.',
    },
    provisioning: {
        badgeClass: 'bg-blue-500/10 text-blue-500 border-blue-500/20',
        label: 'Provisioning',
        icon: <Loader2 className="h-4 w-4 animate-spin text-blue-500" />,
        description: 'We are preparing the runtime. You will be notified as soon as provisioning finishes.',
    },
    disabled: {
        badgeClass: 'bg-gray-500/10 text-gray-500 border-gray-500/20',
        label: 'Disabled',
        icon: <XCircle className="h-4 w-4 text-gray-500" />,
        description: 'This site is paused. Resume or redeploy when you are ready to serve traffic again.',
    },
    failed: {
        badgeClass: 'bg-red-500/10 text-red-500 border-red-500/20',
        label: 'Failed',
        icon: <XCircle className="h-4 w-4 text-red-500" />,
        description: 'Provisioning failed. Review the logs and retry the deployment when the issue is resolved.',
    },
    default: {
        badgeClass: 'border border-border bg-muted text-muted-foreground',
        label: 'Pending',
        icon: <Clock className="h-4 w-4 text-muted-foreground" />,
        description: 'We have not started provisioning yet. Configure how you want to launch the application.',
    },
};

/**
 * Render the BrokeForge site application view with available installation workflows.
 */
export default function SiteApplication({ server, site }: { server: ServerType; site: ServerSite }) {
    const initialOptionKey = installationOptions[0]?.key ?? 'install-application';
    const [selectedOption, setSelectedOption] = useState<InstallationOption['key'] | ''>(initialOptionKey);
    const [searchQuery, setSearchQuery] = useState('');
    const gitRepositorySetupUrl = gitRepositoryRoute({ server: server.id, site: site.id }).url;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: dashboard().url },
        { title: `Server #${server.id}`, href: showServer(server.id).url },
        { title: 'Sites', href: `/servers/${server.id}/sites` },
        { title: 'Application', href: '#' },
    ];

    const activeStatus = statusMeta[site.status] ?? statusMeta.default;
    const filteredOptions = useMemo(() => {
        const query = searchQuery.trim().toLowerCase();

        if (!query) {
            return installationOptions;
        }

        return installationOptions.filter((option) => {
            const haystack = [option.title, option.description, ...option.highlights, ...option.keywords].join(' ').toLowerCase();

            return haystack.includes(query);
        });
    }, [searchQuery]);

    useEffect(() => {
        if (!filteredOptions.length) {
            setSelectedOption('');
            return;
        }

        if (!selectedOption || !filteredOptions.some((option) => option.key === selectedOption)) {
            setSelectedOption(filteredOptions[0].key);
        }
    }, [filteredOptions, selectedOption]);

    const selectedInstallation = useMemo(() => {
        if (!selectedOption) {
            return null;
        }

        return installationOptions.find((option) => option.key === selectedOption) ?? null;
    }, [selectedOption]);

    /**
     * Navigate to the appropriate workflow when an installation option is chosen.
     */
    const handleOptionSelect = (option: InstallationOption) => {
        if (option.key === 'git-repository') {
            setSelectedOption(option.key);
            router.visit(gitRepositorySetupUrl);
            return;
        }

        setSelectedOption(option.key);
    };

    return (
        <SiteLayout server={server} site={site} breadcrumbs={breadcrumbs}>
            <Head title={`Application — ${site.domain}`} />
            <div className="space-y-8">
                <div className="space-y-2">
                    <div className="flex flex-wrap items-center gap-3">
                        <h1 className="text-2xl font-semibold">{site.domain}</h1>
                        <div className="flex items-center gap-2 text-xs text-muted-foreground">
                            {activeStatus.icon}
                            <Badge className={activeStatus.badgeClass}>{activeStatus.label}</Badge>
                        </div>
                    </div>
                    <p className="text-sm text-muted-foreground">
                        Choose how you want to bootstrap this site. Pick an installer and BrokeForge will surface the right workflow.
                    </p>
                </div>

                <div className="space-y-6">
                    <Card>
                        <CardHeader className="space-y-4">
                            <div>
                                <CardTitle>Initialise This Site</CardTitle>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    Search the available installers and pick the option that matches how you want to launch.
                                </p>
                            </div>
                            <Input
                                type="search"
                                value={searchQuery}
                                onChange={(event) => setSearchQuery(event.target.value)}
                                placeholder="Search installers (e.g. git, wordpress, database)"
                                className="max-w-md"
                            />
                        </CardHeader>
                        <Separator />
                        <CardContent className="space-y-5">
                            {filteredOptions.length > 0 ? (
                                <>
                                    <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                                        {filteredOptions.map((option) => {
                                            const Icon = option.icon;
                                            const isActive = option.key === selectedOption;

                                            return (
                                                <button
                                                    key={option.key}
                                                    type="button"
                                                    onClick={() => handleOptionSelect(option)}
                                                    aria-pressed={isActive}
                                                    className={cn(
                                                        'flex h-full w-full flex-col rounded-xl border bg-background p-4 text-left transition-all focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none',
                                                        isActive
                                                            ? 'border-primary/60 bg-primary/5 shadow-sm ring-1 ring-primary/30'
                                                            : 'border-border hover:border-primary/40 hover:bg-muted/50',
                                                    )}
                                                >
                                                    <span
                                                        className={cn(
                                                            'mb-3 inline-flex h-10 w-10 items-center justify-center rounded-lg border bg-muted',
                                                            isActive
                                                                ? 'border-primary/40 bg-primary/10 text-primary'
                                                                : 'border-transparent text-muted-foreground',
                                                        )}
                                                    >
                                                        <Icon className="h-5 w-5" />
                                                    </span>
                                                    <span className="text-sm leading-tight font-semibold">{option.title}</span>
                                                    <span className="mt-1 text-xs text-muted-foreground">{option.description}</span>
                                                </button>
                                            );
                                        })}
                                    </div>

                                    {selectedInstallation && (
                                        <div className="space-y-4 rounded-xl border border-dashed border-primary/40 bg-primary/5 p-5 text-sm">
                                            <div>
                                                <div className="text-sm font-semibold">{selectedInstallation.title}</div>
                                                <p className="mt-1 text-sm text-muted-foreground">{selectedInstallation.nextStep}</p>
                                            </div>
                                            <ul className="space-y-2 text-xs text-muted-foreground">
                                                {selectedInstallation.highlights.map((highlight) => (
                                                    <li key={highlight} className="flex items-start gap-2">
                                                        <Check className="mt-0.5 h-3.5 w-3.5 text-primary" />
                                                        <span>{highlight}</span>
                                                    </li>
                                                ))}
                                            </ul>
                                        </div>
                                    )}
                                </>
                            ) : (
                                <div className="rounded-xl border border-dashed border-border bg-muted/30 p-6 text-center text-sm text-muted-foreground">
                                    No installers match “{searchQuery}”. Try a different keyword.
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </SiteLayout>
    );
}
