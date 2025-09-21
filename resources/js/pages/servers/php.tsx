import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import ServerLayout from '@/layouts/server/layout';
import { dashboard } from '@/routes';
import { show as showServer } from '@/routes/servers';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { CheckIcon, CodeIcon, Download } from 'lucide-react';

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

type AvailablePhpVersions = {
    [key: string]: string;
};

type PhpExtensions = {
    [key: string]: string;
};

type InstalledPhp = {
    id: number;
    service_name: string;
    configuration: {
        version: string;
        extensions: string[];
        memory_limit?: string;
        max_execution_time?: number;
        upload_max_filesize?: string;
    };
    status: string;
    installed_at?: string;
} | null;

export default function Php({
    server,
    availablePhpVersions,
    phpExtensions,
    installedPhp,
}: {
    server: Server;
    availablePhpVersions: AvailablePhpVersions;
    phpExtensions: PhpExtensions;
    installedPhp: InstalledPhp;
}) {
    const { data, setData, post, processing, errors } = useForm({
        version: installedPhp?.configuration?.version || '8.3',
        extensions: installedPhp?.configuration?.extensions || [],
        memory_limit: installedPhp?.configuration?.memory_limit || '256M',
        max_execution_time: installedPhp?.configuration?.max_execution_time || 30,
        upload_max_filesize: installedPhp?.configuration?.upload_max_filesize || '2M',
    });

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: dashboard().url },
        { title: `Server #${server.id}`, href: showServer(server.id).url },
        { title: 'PHP', href: '#' },
    ];

    const handleExtensionChange = (extension: string, checked: boolean) => {
        if (checked) {
            setData('extensions', [...data.extensions, extension]);
        } else {
            setData(
                'extensions',
                data.extensions.filter((ext) => ext !== extension),
            );
        }
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(`/servers/${server.id}/php`);
    };

    return (
        <ServerLayout server={server} breadcrumbs={breadcrumbs}>
            <Head title={`PHP — ${server.vanity_name}`} />
            <div className="space-y-6">
                <div>
                    <h2 className="text-2xl font-semibold">{installedPhp ? 'PHP Configuration' : 'PHP Installation'}</h2>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {installedPhp
                            ? 'Configure PHP version, extensions, and settings for your server.'
                            : 'Install and configure PHP for your server.'}
                    </p>
                </div>

                {!installedPhp && (
                    <div className="rounded-xl border border-sidebar-border/70 bg-background shadow-sm dark:border-sidebar-border">
                        <div className="px-4 py-3">
                            <div className="flex items-center gap-2">
                                <Download className="h-5 w-5 text-blue-600" />
                                <div className="text-sm font-medium tracking-wide text-neutral-600 uppercase dark:text-neutral-300">Install PHP</div>
                            </div>
                        </div>
                        <Separator />
                        <div className="px-4 py-4">
                            <div className="mb-6 text-sm text-muted-foreground">
                                No PHP installation found on this server. Configure and install PHP to get started.
                            </div>

                            <form onSubmit={handleSubmit} className="space-y-6">
                                {/* PHP Version */}
                                <div className="space-y-4">
                                    <h3 className="font-medium">PHP Version</h3>
                                    <div className="space-y-2">
                                        <Label htmlFor="version">Version</Label>
                                        <Select value={data.version} onValueChange={(value) => setData('version', value)}>
                                            <SelectTrigger className="w-full md:w-1/3">
                                                <SelectValue placeholder="Select PHP version" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {Object.entries(availablePhpVersions).map(([value, label]) => (
                                                    <SelectItem key={value} value={value}>
                                                        {label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        {errors.version && <div className="text-sm text-red-600">{errors.version}</div>}
                                    </div>
                                </div>

                                {/* PHP Settings */}
                                <div className="space-y-4">
                                    <h3 className="font-medium">Basic Settings</h3>
                                    <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                                        <div className="space-y-2">
                                            <Label htmlFor="memory_limit">Memory Limit</Label>
                                            <Input
                                                id="memory_limit"
                                                value={data.memory_limit}
                                                onChange={(e) => setData('memory_limit', e.target.value)}
                                                placeholder="256M"
                                            />
                                            {errors.memory_limit && <div className="text-sm text-red-600">{errors.memory_limit}</div>}
                                        </div>

                                        <div className="space-y-2">
                                            <Label htmlFor="max_execution_time">Max Execution Time (seconds)</Label>
                                            <Input
                                                id="max_execution_time"
                                                type="number"
                                                value={data.max_execution_time}
                                                onChange={(e) => setData('max_execution_time', parseInt(e.target.value) || 30)}
                                                placeholder="30"
                                            />
                                            {errors.max_execution_time && <div className="text-sm text-red-600">{errors.max_execution_time}</div>}
                                        </div>

                                        <div className="space-y-2">
                                            <Label htmlFor="upload_max_filesize">Upload Max File Size</Label>
                                            <Input
                                                id="upload_max_filesize"
                                                value={data.upload_max_filesize}
                                                onChange={(e) => setData('upload_max_filesize', e.target.value)}
                                                placeholder="2M"
                                            />
                                            {errors.upload_max_filesize && <div className="text-sm text-red-600">{errors.upload_max_filesize}</div>}
                                        </div>
                                    </div>
                                </div>

                                {/* PHP Extensions */}
                                <div className="space-y-4">
                                    <h3 className="font-medium">PHP Extensions</h3>
                                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                                        {Object.entries(phpExtensions).map(([extension, description]) => (
                                            <div key={extension} className="flex items-start space-x-3">
                                                <Checkbox
                                                    id={extension}
                                                    checked={data.extensions.includes(extension)}
                                                    onCheckedChange={(checked) => handleExtensionChange(extension, !!checked)}
                                                />
                                                <div className="grid gap-1.5 leading-none">
                                                    <Label
                                                        htmlFor={extension}
                                                        className="text-sm leading-none font-medium peer-disabled:cursor-not-allowed peer-disabled:opacity-70"
                                                    >
                                                        {extension}
                                                    </Label>
                                                    <p className="text-xs text-muted-foreground">{description}</p>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </div>

                                {/* Install Button */}
                                <div className="flex justify-end">
                                    <Button type="submit" disabled={processing}>
                                        {processing ? 'Installing...' : 'Install PHP'}
                                    </Button>
                                </div>
                            </form>
                        </div>
                    </div>
                )}

                {installedPhp && (
                    <div className="rounded-xl border border-sidebar-border/70 bg-background shadow-sm dark:border-sidebar-border">
                        <div className="px-4 py-3">
                            <div className="flex items-center gap-2">
                                <CheckIcon className="h-5 w-5 text-green-600" />
                                <div className="text-sm font-medium tracking-wide text-neutral-600 uppercase dark:text-neutral-300">
                                    Current PHP Installation
                                </div>
                            </div>
                        </div>
                        <Separator />
                        <div className="px-4 py-4">
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-4">
                                <div>
                                    <div className="text-sm text-muted-foreground">Version</div>
                                    <div className="font-medium">{installedPhp.configuration.version}</div>
                                </div>
                                <div>
                                    <div className="text-sm text-muted-foreground">Memory Limit</div>
                                    <div className="font-medium">{installedPhp.configuration.memory_limit}</div>
                                </div>
                                <div>
                                    <div className="text-sm text-muted-foreground">Max Execution Time</div>
                                    <div className="font-medium">{installedPhp.configuration.max_execution_time}s</div>
                                </div>
                                <div>
                                    <div className="text-sm text-muted-foreground">Status</div>
                                    <div className="font-medium capitalize">{installedPhp.status}</div>
                                </div>
                            </div>
                            {installedPhp.configuration.extensions && installedPhp.configuration.extensions.length > 0 && (
                                <div className="mt-4">
                                    <div className="mb-2 text-sm text-muted-foreground">Installed Extensions</div>
                                    <div className="flex flex-wrap gap-2">
                                        {installedPhp.configuration.extensions.map((ext) => (
                                            <span
                                                key={ext}
                                                className="inline-flex items-center rounded-full bg-primary/10 px-2 py-1 text-xs font-medium text-primary"
                                            >
                                                {ext}
                                            </span>
                                        ))}
                                    </div>
                                </div>
                            )}
                        </div>
                    </div>
                )}

                {installedPhp && (
                    <form onSubmit={handleSubmit} className="space-y-6">
                        {/* PHP Version */}
                        <div className="rounded-xl border border-sidebar-border/70 bg-background shadow-sm dark:border-sidebar-border">
                            <div className="px-4 py-3">
                                <div className="flex items-center gap-2">
                                    <CodeIcon className="h-5 w-5" />
                                    <div className="text-sm font-medium tracking-wide text-neutral-600 uppercase dark:text-neutral-300">
                                        Update PHP Version
                                    </div>
                                </div>
                            </div>
                            <Separator />
                            <div className="px-4 py-4">
                                <div className="space-y-2">
                                    <Label htmlFor="version">Version</Label>
                                    <Select value={data.version} onValueChange={(value) => setData('version', value)}>
                                        <SelectTrigger className="w-full md:w-1/3">
                                            <SelectValue placeholder="Select PHP version" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {Object.entries(availablePhpVersions).map(([value, label]) => (
                                                <SelectItem key={value} value={value}>
                                                    {label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {errors.version && <div className="text-sm text-red-600">{errors.version}</div>}
                                </div>
                            </div>
                        </div>

                        {/* PHP Settings */}
                        <div className="rounded-xl border border-sidebar-border/70 bg-background shadow-sm dark:border-sidebar-border">
                            <div className="px-4 py-3">
                                <div className="text-sm font-medium tracking-wide text-neutral-600 uppercase dark:text-neutral-300">PHP Settings</div>
                            </div>
                            <Separator />
                            <div className="px-4 py-4">
                                <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                                    <div className="space-y-2">
                                        <Label htmlFor="memory_limit">Memory Limit</Label>
                                        <Input
                                            id="memory_limit"
                                            value={data.memory_limit}
                                            onChange={(e) => setData('memory_limit', e.target.value)}
                                            placeholder="256M"
                                        />
                                        {errors.memory_limit && <div className="text-sm text-red-600">{errors.memory_limit}</div>}
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="max_execution_time">Max Execution Time (seconds)</Label>
                                        <Input
                                            id="max_execution_time"
                                            type="number"
                                            value={data.max_execution_time}
                                            onChange={(e) => setData('max_execution_time', parseInt(e.target.value) || 30)}
                                            placeholder="30"
                                        />
                                        {errors.max_execution_time && <div className="text-sm text-red-600">{errors.max_execution_time}</div>}
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="upload_max_filesize">Upload Max File Size</Label>
                                        <Input
                                            id="upload_max_filesize"
                                            value={data.upload_max_filesize}
                                            onChange={(e) => setData('upload_max_filesize', e.target.value)}
                                            placeholder="2M"
                                        />
                                        {errors.upload_max_filesize && <div className="text-sm text-red-600">{errors.upload_max_filesize}</div>}
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* PHP Extensions */}
                        <div className="rounded-xl border border-sidebar-border/70 bg-background shadow-sm dark:border-sidebar-border">
                            <div className="px-4 py-3">
                                <div className="text-sm font-medium tracking-wide text-neutral-600 uppercase dark:text-neutral-300">
                                    PHP Extensions
                                </div>
                            </div>
                            <Separator />
                            <div className="px-4 py-4">
                                <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                                    {Object.entries(phpExtensions).map(([extension, description]) => (
                                        <div key={extension} className="flex items-start space-x-3">
                                            <Checkbox
                                                id={extension}
                                                checked={data.extensions.includes(extension)}
                                                onCheckedChange={(checked) => handleExtensionChange(extension, !!checked)}
                                            />
                                            <div className="grid gap-1.5 leading-none">
                                                <Label
                                                    htmlFor={extension}
                                                    className="text-sm leading-none font-medium peer-disabled:cursor-not-allowed peer-disabled:opacity-70"
                                                >
                                                    {extension}
                                                </Label>
                                                <p className="text-xs text-muted-foreground">{description}</p>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        </div>

                        {/* Submit */}
                        <div className="flex justify-end">
                            <Button type="submit" disabled={processing}>
                                {processing ? 'Updating...' : 'Update PHP'}
                            </Button>
                        </div>
                    </form>
                )}
            </div>
        </ServerLayout>
    );
}
