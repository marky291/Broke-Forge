import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { CardContainer } from '@/components/ui/card-container';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { PageHeader } from '@/components/ui/page-header';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import ServerLayout from '@/layouts/server/layout';
import { dashboard } from '@/routes';
import { show as showServer } from '@/routes/servers';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { CheckIcon, DatabaseIcon, Download, Loader2, Plus } from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';

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
    default_port: number;
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

type DatabaseItem = {
    id: number;
    name: string;
    type: string;
    version: string;
    port: number;
    status: string;
    created_at: string;
};

export default function Database({
    server,
    availableDatabases,
    installedDatabase,
    databases,
}: {
    server: Server;
    availableDatabases?: AvailableDatabases;
    installedDatabase: InstalledDatabase;
    databases: DatabaseItem[];
}) {
    const fallbackDefaults: Record<string, { version: string; port: number }> = useMemo(
        () => ({
            mysql: { version: '8.0', port: 3306 },
            mariadb: { version: '11.4', port: 3306 },
            postgresql: { version: '16', port: 5432 },
        }),
        [],
    );

    const availableTypeKeys = useMemo(() => Object.keys(availableDatabases || {}), [availableDatabases]);

    const resolveDefaults = useCallback(
        (type: string | undefined) => {
            if (!type) {
                return { version: '', port: 3306 };
            }

            const config = availableDatabases?.[type];

            if (config) {
                return { version: config.default_version, port: config.default_port };
            }

            return fallbackDefaults[type] ?? { version: '', port: 3306 };
        },
        [availableDatabases, fallbackDefaults],
    );

    const initialType = installedDatabase?.configuration?.type || availableTypeKeys[0] || 'mariadb';
    const initialDefaults = resolveDefaults(initialType);

    const [selectedType, setSelectedType] = useState<string>(initialType);

    const { data, setData, post, processing, errors, reset } = useForm({
        type: installedDatabase?.configuration?.type || initialType,
        version: installedDatabase?.configuration?.version || initialDefaults.version,
        port: installedDatabase?.port ?? initialDefaults.port,
        root_password: '',
    });

    const [submitError, setSubmitError] = useState<string | null>(null);
    const [isDialogOpen, setIsDialogOpen] = useState(false);
    const [progress, setProgress] = useState<{ step: number; total: number; label?: string } | null>(
        installedDatabase?.status === 'installing' && installedDatabase?.progress_total
            ? {
                  step: installedDatabase.progress_step ?? 0,
                  total: installedDatabase.progress_total ?? 0,
                  label: installedDatabase.progress_label ?? undefined,
              }
            : null,
    );

    const isInstalling = installedDatabase?.status === 'installing';

    useEffect(() => {
        if (!isInstalling) return;
        let cancelled = false;
        const id = window.setInterval(async () => {
            try {
                const res = await fetch(`/servers/${server.id}/database/status`, { headers: { Accept: 'application/json' } });
                if (!res.ok) return;
                const json = await res.json();
                if (cancelled) return;
                if (json.progress_total) {
                    setProgress({ step: json.progress_step ?? 0, total: json.progress_total ?? 0, label: json.progress_label ?? undefined });
                }
                if (json.status === 'installed' || json.status === 'failed' || json.status === 'uninstalled') {
                    window.clearInterval(id);
                    // Reload both installedDatabase and databases to show completion state
                    router.reload({ only: ['installedDatabase', 'databases'] });
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
        { title: 'Dashboard', href: dashboard.url() },
        { title: `Server #${server.id}`, href: showServer(server.id).url },
        { title: 'Database', href: '#' },
    ];

    const handleTypeChange = (type: string) => {
        setSelectedType(type);
        const defaults = resolveDefaults(type);

        setData('type', type);
        setData('version', defaults.version);
        setData('port', defaults.port);
    };

    useEffect(() => {
        if (installedDatabase) {
            return;
        }

        if (!availableTypeKeys.length) {
            return;
        }

        const [firstType] = availableTypeKeys;

        if (!firstType) {
            return;
        }

        if (data.type && availableDatabases?.[data.type]) {
            return;
        }

        const defaults = resolveDefaults(firstType);
        setSelectedType(firstType);
        setData('type', firstType);
        setData('version', defaults.version);
        setData('port', defaults.port);
    }, [availableDatabases, availableTypeKeys, data.type, installedDatabase, resolveDefaults, setData]);

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
                setIsDialogOpen(false);
            },
        });
    };

    return (
        <ServerLayout server={server} breadcrumbs={breadcrumbs}>
            <Head title={`Database — ${server.vanity_name}`} />
            <PageHeader
                title={installedDatabase ? 'Database Configuration' : 'Database Installation'}
                description={installedDatabase
                    ? 'Configure and manage database services for your server.'
                    : 'Install and configure a database service for your server.'}
            >
                {/* Databases List */}
                <CardContainer
                    title="Databases"
                    description="Database services installed on this server."
                    action={
                        <Dialog open={isDialogOpen} onOpenChange={setIsDialogOpen}>
                            <DialogTrigger asChild>
                                <Button size="sm" disabled={installedDatabase !== null}>
                                    <Plus className="h-4 w-4 mr-1" />
                                    Add Database
                                </Button>
                            </DialogTrigger>
                            <DialogContent className="max-w-2xl max-h-[90vh] overflow-y-auto">
                                <DialogHeader>
                                    <DialogTitle>Install Database Service</DialogTitle>
                                    <DialogDescription>
                                        Choose a database type and configuration to install on your server.
                                    </DialogDescription>
                                </DialogHeader>
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
                                        <div className={`grid grid-cols-1 gap-4 ${processing ? 'opacity-75' : ''}`}>
                                            {Object.entries(availableDatabases || {}).map(([type, database]) => (
                                                <div key={type} className="relative">
                                                    <div
                                                        className={`relative cursor-pointer rounded-lg border-2 p-4 transition-all ${
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
                                                <Select value={data.version} onValueChange={(value) => setData('version', value)}>
                                                    <SelectTrigger disabled={processing}>
                                                        <SelectValue placeholder="Select version" />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {Object.entries(availableDatabases?.[selectedType]?.versions || {}).map(([value, label]) => (
                                                            <SelectItem key={value} value={value}>
                                                                {label}
                                                            </SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                                {errors.version && <div className="text-sm text-red-600">{errors.version}</div>}
                                            </div>

                                            <div className="space-y-2">
                                                <Label htmlFor="port">Port</Label>
                                                <Input
                                                    id="port"
                                                    type="number"
                                                    value={data.port}
                                                    onChange={(e) => setData('port', parseInt(e.target.value))}
                                                    placeholder="3306"
                                                    disabled={processing}
                                                />
                                                {errors.port && <div className="text-sm text-red-600">{errors.port}</div>}
                                            </div>

                                            <div className="space-y-2 md:col-span-2">
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
                                    <div className="flex justify-end gap-3">
                                        <Button type="button" variant="outline" onClick={() => setIsDialogOpen(false)} disabled={processing}>
                                            Cancel
                                        </Button>
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
                            </DialogContent>
                        </Dialog>
                    }
                >
                    {databases.length > 0 ? (
                        <div className="divide-y divide-sidebar-border/70">
                            {databases.map((db) => (
                                <div key={db.id} className="flex items-center justify-between py-3 first:pt-0 last:pb-0">
                                    <div className="flex items-center gap-3">
                                        <div className="flex-shrink-0 rounded-md bg-muted p-2">
                                            <DatabaseIcon className="h-5 w-5" />
                                        </div>
                                        <div>
                                            <div className="font-medium">
                                                {availableDatabases?.[db.type]?.name || db.type} {db.version}
                                            </div>
                                            <div className="text-sm text-muted-foreground">
                                                Port {db.port} · {db.name}
                                            </div>
                                        </div>
                                    </div>
                                    <div className="flex items-center gap-3">
                                        <div
                                            className={`rounded-full px-2.5 py-0.5 text-xs font-medium ${
                                                db.status === 'active'
                                                    ? 'bg-green-500/10 text-green-600 dark:text-green-500'
                                                    : db.status === 'installing'
                                                      ? 'bg-blue-500/10 text-blue-600 dark:text-blue-500'
                                                      : db.status === 'uninstalling'
                                                        ? 'bg-orange-500/10 text-orange-600 dark:text-orange-500'
                                                        : 'bg-gray-500/10 text-gray-600 dark:text-gray-500'
                                            }`}
                                        >
                                            {db.status}
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <div className="flex flex-col items-center justify-center py-8 text-center">
                            <div className="mb-3 rounded-full bg-muted p-3">
                                <DatabaseIcon className="h-6 w-6 text-muted-foreground" />
                            </div>
                            <p className="text-sm text-muted-foreground">No database installed on this server yet.</p>
                        </div>
                    )}
                </CardContainer>

                {installedDatabase && (
                    <CardContainer title="Current Database">
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                            <div>
                                <div className="text-sm text-muted-foreground">Type</div>
                                <div className="font-medium capitalize">
                                    {availableDatabases?.[installedDatabase.configuration.type]?.name || installedDatabase.configuration.type}
                                </div>
                            </div>
                            <div>
                                <div className="text-sm text-muted-foreground">Version</div>
                                <div className="font-medium">{installedDatabase.configuration.version}</div>
                            </div>
                            <div>
                                <div className="text-sm text-muted-foreground">Status</div>
                                <div className="font-medium capitalize">
                                    {installedDatabase.status}
                                    {isInstalling && progress?.total ? (
                                        <span className="ml-2 text-xs text-muted-foreground">
                                            ({progress.step}/{progress.total})
                                        </span>
                                    ) : null}
                                </div>
                            </div>
                        </div>
                        {isInstalling && (
                            <div className="mt-4">
                                <div className="mb-1 flex items-center justify-between">
                                    <div className="text-sm text-muted-foreground">{progress?.label || 'Running installation steps...'}</div>
                                    {progress?.total ? (
                                        <div className="text-xs text-muted-foreground">
                                            {Math.floor(((progress.step ?? 0) / (progress.total ?? 1)) * 100)}%
                                        </div>
                                    ) : null}
                                </div>
                                <div className="h-2 w-full overflow-hidden rounded bg-muted">
                                    <div
                                        className="h-full bg-primary transition-all"
                                        style={{
                                            width: progress?.total
                                                ? `${Math.floor(((progress.step ?? 0) / (progress.total ?? 1)) * 100)}%`
                                                : '25%',
                                        }}
                                    />
                                </div>
                                <div className="mt-2 text-xs text-muted-foreground">
                                    Do not close this page — we're installing the database over SSH.
                                </div>
                            </div>
                        )}
                    </CardContainer>
                )}

                {installedDatabase && !isInstalling && (
                    <form onSubmit={handleSubmit} className="space-y-6">
                        <CardContainer title="Update Configuration">
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div className="space-y-2">
                                    <Label htmlFor="version">Version</Label>
                                    <Select value={data.version} onValueChange={(value) => setData('version', value)}>
                                        <SelectTrigger disabled={processing}>
                                            <SelectValue placeholder="Select version" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {Object.entries(availableDatabases?.[selectedType]?.versions || {}).map(([value, label]) => (
                                                <SelectItem key={value} value={value}>
                                                    {label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {errors.version && <div className="text-sm text-red-600">{errors.version}</div>}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="root_password">Root Password</Label>
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
                        </CardContainer>

                        {/* Submit */}
                        <div className="flex justify-between">
                            <Button
                                type="button"
                                variant="destructive"
                                disabled={processing}
                                onClick={() => {
                                    if (confirm('Are you sure you want to uninstall this database? This will remove all data and cannot be undone.')) {
                                        router.delete(`/servers/${server.id}/database`, {
                                            preserveScroll: true,
                                        });
                                    }
                                }}
                            >
                                Uninstall Database
                            </Button>
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
            </PageHeader>
        </ServerLayout>
    );
}
