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
import { DatabaseIcon, Layers, Loader2, Pencil, RotateCw, Trash2 } from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';

type DatabaseVersion = {
    [key: string]: string;
};

type AvailableService = {
    name: string;
    description: string;
    icon: string;
    versions: DatabaseVersion;
    default_version: string;
    default_port: number;
};

type AvailableServices = {
    [key: string]: AvailableService;
};

type ServiceItem = {
    id: number;
    name: string;
    type: string;
    version: string;
    port: number;
    status: string;
    created_at: string;
};

type InstallDialogType = 'database' | 'cache-queue' | null;

export default function Services({
    server,
    availableDatabases,
    availableCacheQueue,
}: {
    server: Server;
    availableDatabases?: AvailableServices;
    availableCacheQueue?: AvailableServices;
}) {
    // Filter databases (exclude cache/queue like Redis)
    const databases = useMemo(() => {
        const allDatabases = server.databases || [];
        return allDatabases.filter((db) => db.type !== 'redis');
    }, [server.databases]);

    // Filter cache/queue services (Redis only)
    const cacheQueueServices = useMemo(() => {
        const allDatabases = server.databases || [];
        return allDatabases.filter((db) => db.type === 'redis');
    }, [server.databases]);

    // Check if there's an active database (non-failed, non-uninstalling)
    const hasActiveDatabase = useMemo(() => {
        return databases.some((db) => db.status !== 'failed' && db.status !== 'uninstalling');
    }, [databases]);

    // Check if there's an active cache/queue service (non-failed, non-uninstalling)
    const hasActiveCacheQueue = useMemo(() => {
        return cacheQueueServices.some((service) => service.status !== 'failed' && service.status !== 'uninstalling');
    }, [cacheQueueServices]);

    const [installDialogType, setInstallDialogType] = useState<InstallDialogType>(null);
    const [isUpdateDialogOpen, setIsUpdateDialogOpen] = useState(false);
    const [selectedService, setSelectedService] = useState<ServiceItem | null>(null);
    const [updateVersion, setUpdateVersion] = useState<string>('');
    const [submitError, setSubmitError] = useState<string | null>(null);

    // Determine which service types are available for the current dialog
    const currentServices = installDialogType === 'database' ? availableDatabases : availableCacheQueue;
    const availableTypeKeys = useMemo(() => Object.keys(currentServices || {}), [currentServices]);

    const fallbackDefaults: Record<string, { version: string; port: number }> = useMemo(
        () => ({
            mysql: { version: '8.0', port: 3306 },
            mariadb: { version: '11.4', port: 3306 },
            postgresql: { version: '16', port: 5432 },
            redis: { version: '7.2', port: 6379 },
        }),
        [],
    );

    const resolveDefaults = useCallback(
        (type: string | undefined) => {
            if (!type) {
                return { version: '', port: 3306 };
            }

            const config = currentServices?.[type];

            if (config) {
                return { version: config.default_version, port: config.default_port };
            }

            return fallbackDefaults[type] ?? { version: '', port: 3306 };
        },
        [currentServices, fallbackDefaults],
    );

    const initialType = availableTypeKeys[0] || 'mariadb';
    const initialDefaults = resolveDefaults(initialType);

    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        type: initialType,
        version: initialDefaults.version,
        port: initialDefaults.port,
        root_password: '',
    });

    // Real-time updates via Reverb WebSocket
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
        { title: 'Services', href: '#' },
    ];

    const handleTypeChange = (type: string) => {
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

        if (data.type && currentServices?.[data.type]) {
            return;
        }

        const defaults = resolveDefaults(firstType);
        setData('type', firstType);
        setData('version', defaults.version);
        setData('port', defaults.port);
    }, [currentServices, availableTypeKeys, data.type, resolveDefaults, setData]);

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
                setInstallDialogType(null);
            },
        });
    };

    const handleUpdate = (service: ServiceItem) => {
        setSelectedService(service);
        setUpdateVersion(service.version);
        setIsUpdateDialogOpen(true);
    };

    const handleUpdateSubmit = () => {
        if (!selectedService) return;

        router.patch(
            `/servers/${server.id}/databases/${selectedService.id}`,
            { version: updateVersion },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setIsUpdateDialogOpen(false);
                    setSelectedService(null);
                },
            },
        );
    };

    const handleDelete = (service: ServiceItem, serviceType: 'database' | 'cache-queue') => {
        const serviceName = currentServices?.[service.type]?.name || service.type;
        if (
            confirm(
                `Are you sure you want to uninstall ${serviceName} ${service.version}? This will remove all data and cannot be undone.`,
            )
        ) {
            router.delete(`/servers/${server.id}/databases/${service.id}`, {
                preserveScroll: true,
            });
        }
    };

    const handleRetry = (service: ServiceItem) => {
        const serviceName = currentServices?.[service.type]?.name || service.type;
        if (!confirm(`Retry installing ${serviceName} ${service.version}?`)) {
            return;
        }
        router.post(`/servers/${server.id}/databases/${service.id}/retry`, {}, {
            preserveScroll: true,
        });
    };

    const getAvailableUpgradeVersions = (service: ServiceItem) => {
        const currentVersion = service.version;
        const services = service.type === 'redis' ? availableCacheQueue : availableDatabases;
        return Object.entries(services?.[service.type]?.versions || {}).filter(([value]) => {
            return parseFloat(value) > parseFloat(currentVersion);
        });
    };

    return (
        <ServerLayout server={server} breadcrumbs={breadcrumbs}>
            <Head title={`Services — ${server.vanity_name}`} />
            <PageHeader title="Services Management" description="Install and manage database, caching, and queueing services for your server.">
                {/* Databases Section */}
                <CardList<ServiceItem>
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
                    onAddClick={hasActiveDatabase ? undefined : () => setInstallDialogType('database')}
                    addButtonLabel="Add Database"
                    items={databases}
                    keyExtractor={(db) => db.id}
                    renderItem={(db) => (
                        <div className="flex items-center justify-between gap-3">
                            <div className="min-w-0 flex-1">
                                <div className="truncate text-sm font-medium">
                                    {availableDatabases?.[db.type]?.name || db.type} {db.version}
                                </div>
                                <div className="truncate text-xs text-muted-foreground">
                                    Port {db.port} · {db.name}
                                </div>
                            </div>
                            <div className="flex-shrink-0">
                                <CardBadge variant={db.status as any} />
                            </div>
                        </div>
                    )}
                    actions={(db) => {
                        const actions: CardListAction[] = [];
                        const isInTransition = db.status === 'pending' || db.status === 'installing' || db.status === 'updating' || db.status === 'uninstalling';

                        if (db.status === 'failed') {
                            actions.push({
                                label: 'Retry Installation',
                                onClick: () => handleRetry(db),
                                icon: <RotateCw className="h-4 w-4" />,
                                disabled: processing,
                            });
                        }

                        if (db.status === 'active') {
                            const upgradeVersions = getAvailableUpgradeVersions(db);
                            if (upgradeVersions.length > 0) {
                                actions.push({
                                    label: 'Update Version',
                                    onClick: () => handleUpdate(db),
                                    icon: <Pencil className="h-4 w-4" />,
                                    disabled: isInTransition,
                                });
                            }
                        }

                        if (db.status === 'active' || db.status === 'failed') {
                            actions.push({
                                label: 'Uninstall',
                                onClick: () => handleDelete(db, 'database'),
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

                {/* Cache & Queue Section */}
                <div className="mt-8">
                    <CardList<ServiceItem>
                        title="Cache & Queue"
                        icon={
                            <svg width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <rect x="1.5" y="1.5" width="9" height="3" rx="0.75" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" />
                                <rect x="1.5" y="7.5" width="9" height="3" rx="0.75" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" />
                                <path d="M4.5 3V7.5M7.5 3V7.5" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" />
                            </svg>
                        }
                        onAddClick={hasActiveCacheQueue ? undefined : () => setInstallDialogType('cache-queue')}
                        addButtonLabel="Add Cache & Queue"
                        items={cacheQueueServices}
                        keyExtractor={(service) => service.id}
                        renderItem={(service) => (
                            <div className="flex items-center justify-between gap-3">
                                <div className="min-w-0 flex-1">
                                    <div className="truncate text-sm font-medium">
                                        {availableCacheQueue?.[service.type]?.name || service.type} {service.version}
                                    </div>
                                    <div className="truncate text-xs text-muted-foreground">
                                        Port {service.port} · {service.name}
                                    </div>
                                </div>
                                <div className="flex-shrink-0">
                                    <CardBadge variant={service.status as any} />
                                </div>
                            </div>
                        )}
                        actions={(service) => {
                            const actions: CardListAction[] = [];
                            const isInTransition =
                                service.status === 'pending' ||
                                service.status === 'installing' ||
                                service.status === 'updating' ||
                                service.status === 'uninstalling';

                            if (service.status === 'failed') {
                                actions.push({
                                    label: 'Retry Installation',
                                    onClick: () => handleRetry(service),
                                    icon: <RotateCw className="h-4 w-4" />,
                                    disabled: processing,
                                });
                            }

                            if (service.status === 'active') {
                                const upgradeVersions = getAvailableUpgradeVersions(service);
                                if (upgradeVersions.length > 0) {
                                    actions.push({
                                        label: 'Update Version',
                                        onClick: () => handleUpdate(service),
                                        icon: <Pencil className="h-4 w-4" />,
                                        disabled: isInTransition,
                                    });
                                }
                            }

                            if (service.status === 'active' || service.status === 'failed') {
                                actions.push({
                                    label: 'Uninstall',
                                    onClick: () => handleDelete(service, 'cache-queue'),
                                    variant: 'destructive',
                                    icon: <Trash2 className="h-4 w-4" />,
                                    disabled: isInTransition,
                                });
                            }

                            return actions;
                        }}
                        emptyStateMessage="No cache or queue services installed on this server yet."
                        emptyStateIcon={<Layers className="h-6 w-6 text-muted-foreground" />}
                    />
                </div>
            </PageHeader>

            {/* Install Service Dialog */}
            <Dialog open={installDialogType !== null} onOpenChange={(open) => !open && setInstallDialogType(null)}>
                <DialogContent className="max-h-[90vh] max-w-2xl overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>Install {installDialogType === 'database' ? 'Database' : 'Cache & Queue'} Service</DialogTitle>
                        <DialogDescription>
                            Choose a {installDialogType === 'database' ? 'database' : 'caching or queueing'} service to install on your server.
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={handleInstallSubmit} className="space-y-6">
                        {submitError && (
                            <Alert variant="destructive">
                                <AlertTitle>Installation failed</AlertTitle>
                                <AlertDescription>{submitError}</AlertDescription>
                            </Alert>
                        )}

                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            {/* Service Type */}
                            <div className="space-y-2 md:col-span-2">
                                <Label htmlFor="type">
                                    {installDialogType === 'database' ? 'Database' : 'Service'} Type <span className="text-red-500">*</span>
                                </Label>
                                <Select value={data.type} onValueChange={handleTypeChange}>
                                    <SelectTrigger disabled={processing}>
                                        <SelectValue placeholder={`Select ${installDialogType === 'database' ? 'database' : 'service'} type`} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {Object.entries(currentServices || {}).map(([type, service]) => (
                                            <SelectItem key={type} value={type}>
                                                {service.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {currentServices?.[data.type]?.description && (
                                    <p className="text-xs text-muted-foreground">{currentServices[data.type].description}</p>
                                )}
                                {errors.type && <div className="text-sm text-red-600">{errors.type}</div>}
                            </div>

                            {/* Name */}
                            <div className="space-y-2 md:col-span-2">
                                <Label htmlFor="name">{installDialogType === 'database' ? 'Database' : 'Instance'} Name</Label>
                                <Input
                                    id="name"
                                    type="text"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    placeholder={`Leave empty to use default (${data.type})`}
                                    disabled={processing}
                                />
                                <p className="text-xs text-muted-foreground">Optional. This is used to identify your {installDialogType === 'database' ? 'database' : 'instance'} in the list.</p>
                                {errors.name && <div className="text-sm text-red-600">{errors.name}</div>}
                            </div>

                            {/* Version */}
                            <div className="space-y-2">
                                <Label htmlFor="version">
                                    Version <span className="text-red-500">*</span>
                                </Label>
                                <Select value={data.version} onValueChange={(value) => setData('version', value)}>
                                    <SelectTrigger disabled={processing}>
                                        <SelectValue placeholder="Select version" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {Object.entries(currentServices?.[data.type]?.versions || {}).map(([value, label]) => (
                                            <SelectItem key={value} value={value}>
                                                {label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {errors.version && <div className="text-sm text-red-600">{errors.version}</div>}
                            </div>

                            {/* Port */}
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

                            {/* Root Password */}
                            <div className="space-y-2 md:col-span-2">
                                <Label htmlFor="root_password">
                                    {installDialogType === 'database' ? 'Root Password' : 'Password'} <span className="text-red-500">*</span>
                                </Label>
                                <Input
                                    id="root_password"
                                    type="password"
                                    value={data.root_password}
                                    onChange={(e) => setData('root_password', e.target.value)}
                                    required
                                    placeholder="Enter a secure password"
                                    disabled={processing}
                                    autoComplete="new-password"
                                />
                                <p className="text-xs text-muted-foreground">Strong password required (minimum 8 characters).</p>
                                {errors.root_password && <div className="text-sm text-red-600">{errors.root_password}</div>}
                            </div>
                        </div>

                        {/* Install Button */}
                        <div className="flex justify-end gap-3">
                            <Button type="button" variant="outline" onClick={() => setInstallDialogType(null)} disabled={processing}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={processing}>
                                {processing ? (
                                    <span className="inline-flex items-center gap-2">
                                        <Loader2 className="h-4 w-4 animate-spin" />
                                        Installing...
                                    </span>
                                ) : (
                                    `Install ${installDialogType === 'database' ? 'Database' : 'Service'}`
                                )}
                            </Button>
                        </div>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Update Service Dialog */}
            <Dialog open={isUpdateDialogOpen} onOpenChange={setIsUpdateDialogOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Update Service Version</DialogTitle>
                        <DialogDescription>
                            Update{' '}
                            {selectedService && ((availableDatabases?.[selectedService.type]?.name || availableCacheQueue?.[selectedService.type]?.name) || selectedService.type)}{' '}
                            to a newer version.
                        </DialogDescription>
                    </DialogHeader>
                    {selectedService && (
                        <div className="space-y-4">
                            <div className="space-y-2">
                                <Label htmlFor="update_version">Select New Version</Label>
                                <Select value={updateVersion} onValueChange={setUpdateVersion}>
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select version" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {getAvailableUpgradeVersions(selectedService).length > 0 ? (
                                            getAvailableUpgradeVersions(selectedService).map(([value, label]) => (
                                                <SelectItem key={value} value={value}>
                                                    {label}
                                                </SelectItem>
                                            ))
                                        ) : (
                                            <SelectItem value={selectedService.version} disabled>
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
                                        updateVersion === selectedService.version ||
                                        getAvailableUpgradeVersions(selectedService).length === 0
                                    }
                                >
                                    Update Service
                                </Button>
                            </div>
                        </div>
                    )}
                </DialogContent>
            </Dialog>
        </ServerLayout>
    );
}
