import { CardList, type CardListAction } from '@/components/card-list';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { CardBadge } from '@/components/ui/card-badge';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { PageHeader } from '@/components/ui/page-header';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import ServerLayout from '@/layouts/server/layout';
import { dashboard } from '@/routes';
import { show as showServer } from '@/routes/servers';
import { type BreadcrumbItem, type Server } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { useEcho } from '@laravel/echo-react';
import { DatabaseIcon, Pencil, Trash2 } from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';

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

type DatabaseItem = {
    id: number;
    name: string;
    type: string;
    version: string;
    port: number;
    status: string;
    created_at: string;
};

export default function Databases({ server, availableDatabases }: { server: Server; availableDatabases?: AvailableDatabases }) {
    const databases = server.databases || [];
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

    const initialType = availableTypeKeys[0] || 'mariadb';
    const initialDefaults = resolveDefaults(initialType);

    const [selectedType, setSelectedType] = useState<string>(initialType);
    const [isInstallDialogOpen, setIsInstallDialogOpen] = useState(false);
    const [isUpdateDialogOpen, setIsUpdateDialogOpen] = useState(false);
    const [selectedDatabase, setSelectedDatabase] = useState<DatabaseItem | null>(null);

    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        type: initialType,
        version: initialDefaults.version,
        port: initialDefaults.port,
        root_password: '',
    });

    const [updateVersion, setUpdateVersion] = useState<string>('');
    const [submitError, setSubmitError] = useState<string | null>(null);

    // Real-time updates via Reverb WebSocket - listens for database changes
    useEcho(`servers.${server.id}`, 'ServerUpdated', () => {
        router.reload({
            only: ['server'],
            preserveScroll: true,
            preserveState: true,
        });
    });

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: dashboard.url() },
        { title: `Server #${server.id}`, href: showServer(server.id).url },
        { title: 'Databases', href: '#' },
    ];

    const handleTypeChange = (type: string) => {
        setSelectedType(type);
        const defaults = resolveDefaults(type);

        setData('type', type);
        setData('version', defaults.version);
        setData('port', defaults.port);
    };

    useEffect(() => {
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
    }, [availableDatabases, availableTypeKeys, data.type, resolveDefaults, setData]);

    const handleInstallSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setSubmitError(null);
        post(`/servers/${server.id}/databases`, {
            preserveScroll: true,
            onError: (formErrors) => {
                if (!formErrors || Object.keys(formErrors).length === 0) {
                    setSubmitError('Something went wrong while starting the installation. Please try again.');
                }
            },
            onSuccess: () => {
                reset('name', 'root_password');
                setIsInstallDialogOpen(false);
            },
        });
    };

    const handleUpdate = (database: DatabaseItem) => {
        setSelectedDatabase(database);
        setUpdateVersion(database.version);
        setIsUpdateDialogOpen(true);
    };

    const handleUpdateSubmit = () => {
        if (!selectedDatabase) return;

        router.patch(
            `/servers/${server.id}/databases/${selectedDatabase.id}`,
            { version: updateVersion },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setIsUpdateDialogOpen(false);
                    setSelectedDatabase(null);
                },
            },
        );
    };

    const handleDelete = (database: DatabaseItem) => {
        if (
            confirm(
                `Are you sure you want to uninstall ${availableDatabases?.[database.type]?.name || database.type} ${database.version}? This will remove all data and cannot be undone.`,
            )
        ) {
            router.delete(`/servers/${server.id}/databases/${database.id}`, {
                preserveScroll: true,
            });
        }
    };

    const getAvailableUpgradeVersions = (database: DatabaseItem) => {
        const currentVersion = database.version;
        return Object.entries(availableDatabases?.[database.type]?.versions || {}).filter(([value]) => {
            return parseFloat(value) > parseFloat(currentVersion);
        });
    };

    return (
        <ServerLayout server={server} breadcrumbs={breadcrumbs}>
            <Head title={`Databases — ${server.vanity_name}`} />
            <PageHeader
                title="Database Management"
                description="Install and manage multiple database services for your server."
            >
                {/* Databases List */}
                <CardList<DatabaseItem>
                    title="Databases"
                    icon={
                        <svg width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <ellipse cx="6" cy="3" rx="4.5" ry="1.5" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" />
                            <path
                                d="M10.5 3v6c0 .828-2.015 1.5-4.5 1.5S1.5 9.828 1.5 9V3"
                                stroke="currentColor"
                                strokeLinecap="round"
                                strokeLinejoin="round"
                            />
                            <path
                                d="M10.5 6c0 .828-2.015 1.5-4.5 1.5S1.5 6.828 1.5 6"
                                stroke="currentColor"
                                strokeLinecap="round"
                                strokeLinejoin="round"
                            />
                        </svg>
                    }
                    onAddClick={() => setIsInstallDialogOpen(true)}
                    addButtonLabel="Add Database"
                    items={databases}
                    keyExtractor={(db) => db.id}
                    renderItem={(db) => (
                        <div className="flex items-center justify-between gap-3">
                            {/* Left: Database info */}
                            <div className="min-w-0 flex-1">
                                <div className="truncate text-sm font-medium">
                                    {availableDatabases?.[db.type]?.name || db.type} {db.version}
                                </div>
                                <div className="truncate text-xs text-muted-foreground">
                                    Port {db.port} · {db.name}
                                </div>
                            </div>

                            {/* Right: Status badge */}
                            <div className="flex-shrink-0">
                                <CardBadge variant={db.status as any} />
                            </div>
                        </div>
                    )}
                    actions={(db) => {
                        const actions: CardListAction[] = [];
                        const isInTransition = db.status === 'pending' || db.status === 'installing' || db.status === 'updating' || db.status === 'uninstalling';

                        if (db.status === 'active') {
                            actions.push({
                                label: 'Update Version',
                                onClick: () => handleUpdate(db),
                                icon: <Pencil className="h-4 w-4" />,
                                disabled: isInTransition,
                            });
                        }

                        if (db.status === 'active') {
                            actions.push({
                                label: 'Uninstall',
                                onClick: () => handleDelete(db),
                                variant: 'destructive',
                                icon: <Trash2 className="h-4 w-4" />,
                                disabled: isInTransition,
                            });
                        }

                        return actions;
                    }}
                    emptyStateMessage="No databases installed on this server yet."
                    emptyStateIcon={<DatabaseIcon className="h-6 w-6 text-muted-foreground" />}
                />
            </PageHeader>

            {/* Install Database Dialog */}
            <Dialog open={isInstallDialogOpen} onOpenChange={setIsInstallDialogOpen}>
                <DialogContent className="max-h-[90vh] max-w-2xl overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>Install Database Service</DialogTitle>
                        <DialogDescription>Choose a database type and configuration to install on your server.</DialogDescription>
                    </DialogHeader>
                    <form onSubmit={handleInstallSubmit} className="space-y-6">
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
                                <div className="space-y-2 md:col-span-2">
                                    <Label htmlFor="name">Database Name</Label>
                                    <Input
                                        id="name"
                                        type="text"
                                        value={data.name}
                                        onChange={(e) => setData('name', e.target.value)}
                                        placeholder={`Leave empty to use default (${selectedType})`}
                                        disabled={processing}
                                    />
                                    <p className="text-xs text-muted-foreground">Optional. This is used to identify your database in the list.</p>
                                    {errors.name && <div className="text-sm text-red-600">{errors.name}</div>}
                                </div>

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
                                        placeholder="Auto-assigned if empty"
                                        disabled={processing}
                                    />
                                    <p className="text-xs text-muted-foreground">Leave empty to auto-assign a unique port.</p>
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
                                        autoComplete="new-password"
                                    />
                                    {errors.root_password && <div className="text-sm text-red-600">{errors.root_password}</div>}
                                </div>
                            </div>
                        </div>

                        {/* Install Button */}
                        <div className="flex justify-end gap-3">
                            <Button type="button" variant="outline" onClick={() => setIsInstallDialogOpen(false)} disabled={processing}>
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

            {/* Update Database Dialog */}
            <Dialog open={isUpdateDialogOpen} onOpenChange={setIsUpdateDialogOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Update Database Version</DialogTitle>
                        <DialogDescription>
                            Update {selectedDatabase && availableDatabases?.[selectedDatabase.type]?.name} to a newer version.
                        </DialogDescription>
                    </DialogHeader>
                    {selectedDatabase && (
                        <div className="space-y-4">
                            <div className="space-y-2">
                                <Label htmlFor="update_version">Select New Version</Label>
                                <Select value={updateVersion} onValueChange={setUpdateVersion}>
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select version" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {getAvailableUpgradeVersions(selectedDatabase).length > 0 ? (
                                            getAvailableUpgradeVersions(selectedDatabase).map(([value, label]) => (
                                                <SelectItem key={value} value={value}>
                                                    {label}
                                                </SelectItem>
                                            ))
                                        ) : (
                                            <SelectItem value={selectedDatabase.version} disabled>
                                                No newer versions available
                                            </SelectItem>
                                        )}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="flex justify-end gap-3">
                                <Button type="button" variant="outline" onClick={() => setIsUpdateDialogOpen(false)}>
                                    Cancel
                                </Button>
                                <Button
                                    onClick={handleUpdateSubmit}
                                    disabled={
                                        processing ||
                                        updateVersion === selectedDatabase.version ||
                                        getAvailableUpgradeVersions(selectedDatabase).length === 0
                                    }
                                >
                                    Update Database
                                </Button>
                            </div>
                        </div>
                    )}
                </DialogContent>
            </Dialog>
        </ServerLayout>
    );
}
