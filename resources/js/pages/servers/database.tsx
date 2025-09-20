import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import ServerLayout from '@/layouts/server/layout';
import { dashboard } from '@/routes';
import { show as showServer } from '@/routes/servers';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm, router } from '@inertiajs/react';
import { CheckIcon, DatabaseIcon, Download, Loader2 } from 'lucide-react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { useEffect, useState } from 'react';

type Server = {
    id: number;
    vanity_name: string;
    public_ip: string;
    ssh_port: number;
    private_ip?: string | null;
    connection: string;
    created_at: string;
    updated_at: string;
};

type DatabaseVersion = {
    [key: string]: string;
};

type AvailableDatabase = {
    name: string;
    description: string;
    icon: string;
    versions: DatabaseVersion;
    default_version: string;
};

type AvailableDatabases = {
    [key: string]: AvailableDatabase;
};

type InstalledDatabase = {
    id: number;
    service_name: string;
    configuration: {
        type: string;
        version: string;
        root_password?: string;
    };
    status: string;
    progress_step?: number | null;
    progress_total?: number | null;
    progress_label?: string | null;
    installed_at?: string;
} | null;

export default function Database({
    server,
    availableDatabases,
    installedDatabase,
}: {
    server: Server;
    availableDatabases: AvailableDatabases;
    installedDatabase: InstalledDatabase;
}) {
    const [selectedType, setSelectedType] = useState<string>(
        installedDatabase?.configuration?.type || 'mysql'
    );

    const { data, setData, post, processing, errors, reset } = useForm({
        type: installedDatabase?.configuration?.type || 'mysql',
        version: installedDatabase?.configuration?.version || availableDatabases.mysql?.default_version || '8.0',
        root_password: '',
    });

    const [submitError, setSubmitError] = useState<string | null>(null);
    const [progress, setProgress] = useState<{ step: number; total: number; label?: string } | null>(
        installedDatabase?.status === 'installing' && installedDatabase?.progress_total
            ? {
                step: installedDatabase.progress_step ?? 0,
                total: installedDatabase.progress_total ?? 0,
                label: installedDatabase.progress_label ?? undefined,
            }
            : null
    );

    const isInstalling = installedDatabase?.status === 'installing';

    useEffect(() => {
        if (!isInstalling) return;
        let cancelled = false;
        const id = window.setInterval(async () => {
            try {
                const res = await fetch(`/servers/${server.id}/database/status`, { headers: { 'Accept': 'application/json' } });
                if (!res.ok) return;
                const json = await res.json();
                if (cancelled) return;
                if (json.progress_total) {
                    setProgress({ step: json.progress_step ?? 0, total: json.progress_total ?? 0, label: json.progress_label ?? undefined });
                }
                if (json.status === 'installed' || json.status === 'failed' || json.status === 'uninstalled') {
                    window.clearInterval(id);
                    // Reload just the installedDatabase prop to update UI quickly
                    router.reload({ only: ['installedDatabase'] });
                }
            } catch {
                // ignore transient errors
            }
        }, 1500);
        return () => {
            cancelled = true;
            window.clearInterval(id);
        };
    }, [isInstalling, server.id]);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: dashboard().url },
        { title: `Server #${server.id}`, href: showServer(server.id).url },
        { title: 'Database', href: '#' },
    ];

    const handleTypeChange = (type: string) => {
        setSelectedType(type);
        setData({
            ...data,
            type,
            version: availableDatabases[type]?.default_version || '',
        });
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setSubmitError(null);
        post(`/servers/${server.id}/database`, {
            preserveScroll: true,
            onError: (formErrors) => {
                if (!formErrors || Object.keys(formErrors).length === 0) {
                    setSubmitError('Something went wrong while starting the installation. Please try again.');
                }
            },
            onSuccess: () => {
                reset('root_password');
            },
        });
    };

    return (
        <ServerLayout server={server} breadcrumbs={breadcrumbs}>
            <Head title={`Database — ${server.vanity_name}`} />
            <div className="space-y-6">
                <div>
                    <h2 className="text-2xl font-semibold">
                        {installedDatabase ? 'Database Configuration' : 'Database Installation'}
                    </h2>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {installedDatabase
                            ? 'Configure and manage database services for your server.'
                            : 'Install and configure a database service for your server.'
                        }
                    </p>
                </div>

                {!installedDatabase && (
                    <div className="rounded-xl border border-sidebar-border/70 bg-background shadow-sm dark:border-sidebar-border">
                        <div className="px-4 py-3">
                            <div className="flex items-center gap-2">
                                <Download className="h-5 w-5 text-blue-600" />
                                <div className="text-sm font-medium tracking-wide text-neutral-600 uppercase dark:text-neutral-300">
                                    Install Database Service
                                </div>
                            </div>
                        </div>
                        <Separator />
                        <div className="px-4 py-4">
                            <div className="mb-6 text-sm text-muted-foreground">
                                No database service is currently installed on this server. Choose a database type and configuration to get started.
                            </div>

                            <form onSubmit={handleSubmit} className="space-y-6">
                                {submitError && (
                                    <Alert variant="destructive">
                                        <AlertTitle>Installation failed</AlertTitle>
                                        <AlertDescription>{submitError}</AlertDescription>
                                    </Alert>
                                )}
                                {/* Database Type Selection */}
                                <div className="space-y-4">
                                    <h3 className="font-medium">Database Type</h3>
                                    <div className={`grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3 ${processing ? 'opacity-75' : ''}`}>
                                        {Object.entries(availableDatabases).map(([type, database]) => (
                                            <div key={type} className="relative">
                                                <div
                                                    className={`relative rounded-lg border-2 p-4 transition-all cursor-pointer ${
                                                        selectedType === type
                                                            ? 'border-primary bg-primary/5'
                                                            : 'border-sidebar-border/70 bg-background hover:border-primary/50'
                                                    }`}
                                                    onClick={() => !processing && handleTypeChange(type)}
                                                >
                                                    <div className="flex items-start gap-3">
                                                        <div
                                                            className={`flex-shrink-0 rounded-md p-2 ${
                                                                selectedType === type ? 'bg-primary text-primary-foreground' : 'bg-muted'
                                                            }`}
                                                        >
                                                            <DatabaseIcon className="h-6 w-6" />
                                                        </div>
                                                        <div className="min-w-0 flex-1">
                                                            <h3 className="font-medium">{database.name}</h3>
                                                            <p className="mt-1 text-sm text-muted-foreground">{database.description}</p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                    {errors.type && <div className="text-sm text-red-600">{errors.type}</div>}
                                </div>

                                {/* Configuration */}
                                <div className="space-y-4">
                                    <h3 className="font-medium">Configuration</h3>
                                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                        <div className="space-y-2">
                                            <Label htmlFor="version">Version</Label>
                                            <Select
                                                value={data.version}
                                                onValueChange={(value) => setData('version', value)}
                                            >
                                                <SelectTrigger disabled={processing}>
                                                    <SelectValue placeholder="Select version" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {Object.entries(availableDatabases[selectedType]?.versions || {}).map(([value, label]) => (
                                                        <SelectItem key={value} value={value}>
                                                            {label}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                            {errors.version && <div className="text-sm text-red-600">{errors.version}</div>}
                                        </div>

                                        <div className="space-y-2">
                                            <Label htmlFor="root_password">
                                                Root Password <span className="text-red-500">*</span>
                                            </Label>
                                            <Input
                                                id="root_password"
                                                type="password"
                                                value={data.root_password}
                                                onChange={(e) => setData('root_password', e.target.value)}
                                                required
                                                placeholder="Enter root password"
                                                disabled={processing}
                                            />
                                            {errors.root_password && <div className="text-sm text-red-600">{errors.root_password}</div>}
                                        </div>
                                    </div>
                                </div>

                                {/* Install Button */}
                                <div className="flex justify-end">
                                    <Button type="submit" disabled={processing}>
                                        {processing ? (
                                            <span className="inline-flex items-center gap-2">
                                                <Loader2 className="h-4 w-4 animate-spin" />
                                                Installing...
                                            </span>
                                        ) : (
                                            'Install Database'
                                        )}
                                    </Button>
                                </div>
                            </form>
                        </div>
                    </div>
                )}

                {installedDatabase && (
                    <div className="rounded-xl border border-sidebar-border/70 bg-background shadow-sm dark:border-sidebar-border">
                        <div className="px-4 py-3">
                            <div className="flex items-center gap-2">
                                <CheckIcon className="h-5 w-5 text-green-600" />
                                <div className="text-sm font-medium tracking-wide text-neutral-600 uppercase dark:text-neutral-300">
                                    Current Database
                                </div>
                            </div>
                        </div>
                        <Separator />
                        <div className="px-4 py-4">
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                                <div>
                                    <div className="text-sm text-muted-foreground">Type</div>
                                    <div className="font-medium capitalize">
                                        {availableDatabases[installedDatabase.configuration.type]?.name || installedDatabase.configuration.type}
                                    </div>
                                </div>
                                <div>
                                    <div className="text-sm text-muted-foreground">Version</div>
                                    <div className="font-medium">{installedDatabase.configuration.version}</div>
                                </div>
                                <div>
                                    <div className="text-sm text-muted-foreground">Status</div>
                                    <div className="font-medium capitalize">{installedDatabase.status}
                                        {isInstalling && progress?.total ? (
                                            <span className="ml-2 text-xs text-muted-foreground">({progress.step}/{progress.total})</span>
                                        ) : null}
                                    </div>
                                </div>
                            </div>
                            {isInstalling && (
                                <div className="mt-4">
                                    <div className="mb-1 flex items-center justify-between">
                                        <div className="text-sm text-muted-foreground">{progress?.label || 'Running installation steps...'}</div>
                                        {progress?.total ? (
                                            <div className="text-xs text-muted-foreground">{Math.floor(((progress.step ?? 0) / (progress.total ?? 1)) * 100)}%</div>
                                        ) : null}
                                    </div>
                                    <div className="h-2 w-full overflow-hidden rounded bg-muted">
                                        <div
                                            className="h-full bg-primary transition-all"
                                            style={{ width: progress?.total ? `${Math.floor(((progress.step ?? 0) / (progress.total ?? 1)) * 100)}%` : '25%' }}
                                        />
                                    </div>
                                    <div className="mt-2 text-xs text-muted-foreground">Do not close this page — we’re installing the database over SSH.</div>
                                </div>
                            )}
                        </div>
                    </div>
                )}

                {installedDatabase && !isInstalling && (
                    <form onSubmit={handleSubmit} className="space-y-6">
                        {/* Configuration */}
                        <div className="rounded-xl border border-sidebar-border/70 bg-background shadow-sm dark:border-sidebar-border">
                            <div className="px-4 py-3">
                                <div className="flex items-center gap-2">
                                    <DatabaseIcon className="h-5 w-5" />
                                    <div className="text-sm font-medium tracking-wide text-neutral-600 uppercase dark:text-neutral-300">
                                        Update Configuration
                                    </div>
                                </div>
                            </div>
                            <Separator />
                            <div className="px-4 py-4">
                                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label htmlFor="version">Version</Label>
                                        <Select
                                            value={data.version}
                                            onValueChange={(value) => setData('version', value)}
                                        >
                                            <SelectTrigger disabled={processing}>
                                                <SelectValue placeholder="Select version" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {Object.entries(availableDatabases[selectedType]?.versions || {}).map(([value, label]) => (
                                                    <SelectItem key={value} value={value}>
                                                        {label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        {errors.version && <div className="text-sm text-red-600">{errors.version}</div>}
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="root_password">
                                            Root Password
                                        </Label>
                                        <Input
                                            id="root_password"
                                            type="password"
                                            value={data.root_password}
                                            onChange={(e) => setData('root_password', e.target.value)}
                                            placeholder="Enter new password (optional)"
                                            disabled={processing}
                                        />
                                        {errors.root_password && <div className="text-sm text-red-600">{errors.root_password}</div>}
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* Submit */}
                        <div className="flex justify-end">
                            <Button type="submit" disabled={processing}>
                                {processing ? (
                                    <span className="inline-flex items-center gap-2">
                                        <Loader2 className="h-4 w-4 animate-spin" />
                                        Updating...
                                    </span>
                                ) : (
                                    'Update Database'
                                )}
                            </Button>
                        </div>
                    </form>
                )}
            </div>
        </ServerLayout>
    );
}
