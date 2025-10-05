import { Button } from '@/components/ui/button';
import { CardContainer } from '@/components/ui/card-container';
import { CardInput } from '@/components/ui/card-input';
import { CardInputDropdown } from '@/components/ui/card-input-dropdown';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { PageHeader } from '@/components/ui/page-header';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import ServerLayout from '@/layouts/server/layout';
import { dashboard } from '@/routes';
import { show as showServer } from '@/routes/servers';
import { type BreadcrumbItem, type Server, type ServerPhp } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';

type AvailablePhpVersions = {
    [key: string]: string;
};

export default function Php({
    server,
    availablePhpVersions,
    installedPhpVersions,
}: {
    server: Server;
    availablePhpVersions: AvailablePhpVersions;
    installedPhpVersions: ServerPhp[];
}) {
    // Get the default PHP version or first installed version
    const defaultPhp = installedPhpVersions.find(php => php.is_cli_default) || installedPhpVersions[0];

    const [isAddVersionDialogOpen, setIsAddVersionDialogOpen] = useState(false);

    const { data, setData, post, processing, errors } = useForm({
        version: defaultPhp?.version || '8.3',
        memory_limit: '256M',
        max_execution_time: 30,
        upload_max_filesize: '2M',
    });

    const { data: addVersionData, setData: setAddVersionData, post: postAddVersion, processing: addVersionProcessing, errors: addVersionErrors, reset: resetAddVersion } = useForm({
        version: '',
    });

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: dashboard.url() },
        { title: `Server #${server.id}`, href: showServer(server.id).url },
        { title: 'PHP', href: '#' },
    ];

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(`/servers/${server.id}/php`);
    };

    const handleAddVersion = (e: React.FormEvent) => {
        e.preventDefault();
        postAddVersion(`/servers/${server.id}/php/install`, {
            onSuccess: () => {
                setIsAddVersionDialogOpen(false);
                resetAddVersion();
            },
        });
    };

    return (
        <ServerLayout server={server} breadcrumbs={breadcrumbs}>
            <Head title={`PHP — ${server.vanity_name}`} />
            <PageHeader
                title={installedPhpVersions.length > 0 ? 'PHP Configuration' : 'PHP Installation'}
                description={installedPhpVersions.length > 0
                    ? 'Configure PHP version, extensions, and settings for your server.'
                    : 'Install and configure PHP for your server.'}
            >
                {installedPhpVersions.length === 0 && (
                    <CardContainer
                        title="Install PHP"
                        description="No PHP installation found on this server. Configure and install PHP to get started."
                    >
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
                                    <div className="space-y-4">
                                        <CardInput
                                            label="Memory Limit"
                                            value={data.memory_limit}
                                            onChange={(e) => setData('memory_limit', e.target.value)}
                                            placeholder="256M"
                                            error={errors.memory_limit}
                                        />

                                        <CardInput
                                            label="Max Execution Time (seconds)"
                                            type="number"
                                            value={data.max_execution_time}
                                            onChange={(e) => setData('max_execution_time', parseInt(e.target.value) || 30)}
                                            placeholder="30"
                                            error={errors.max_execution_time}
                                        />

                                        <CardInput
                                            label="Upload Max File Size"
                                            value={data.upload_max_filesize}
                                            onChange={(e) => setData('upload_max_filesize', e.target.value)}
                                            placeholder="2M"
                                            error={errors.upload_max_filesize}
                                        />
                                    </div>
                                </div>

                            {/* Install Button */}
                            <div className="flex justify-end">
                                <Button type="submit" disabled={processing}>
                                    {processing ? 'Installing...' : 'Install PHP'}
                                </Button>
                            </div>
                        </form>
                    </CardContainer>
                )}

                {installedPhpVersions.length > 0 && (
                    <>
                        <CardContainer
                            title="Versions"
                            action={
                                <Dialog open={isAddVersionDialogOpen} onOpenChange={setIsAddVersionDialogOpen}>
                                    <DialogTrigger asChild>
                                        <Button variant="outline" size="sm">Add Version</Button>
                                    </DialogTrigger>
                                    <DialogContent>
                                        <form onSubmit={handleAddVersion}>
                                            <DialogHeader>
                                                <DialogTitle>Add PHP Version</DialogTitle>
                                                <DialogDescription>
                                                    Install a new PHP version on this server.
                                                </DialogDescription>
                                            </DialogHeader>
                                            <div className="py-4">
                                                <div className="space-y-2">
                                                    <Label htmlFor="add-version">PHP Version</Label>
                                                    <Select value={addVersionData.version} onValueChange={(value) => setAddVersionData('version', value)}>
                                                        <SelectTrigger id="add-version">
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
                                                    {addVersionErrors.version && <div className="text-sm text-red-600">{addVersionErrors.version}</div>}
                                                </div>
                                            </div>
                                            <DialogFooter>
                                                <Button type="button" variant="outline" onClick={() => setIsAddVersionDialogOpen(false)}>
                                                    Cancel
                                                </Button>
                                                <Button type="submit" disabled={addVersionProcessing || !addVersionData.version}>
                                                    {addVersionProcessing ? 'Installing...' : 'Install'}
                                                </Button>
                                            </DialogFooter>
                                        </form>
                                    </DialogContent>
                                </Dialog>
                            }
                        >
                            <div className="space-y-3">
                                {installedPhpVersions.map((php) => (
                                    <div key={php.id} className="flex items-center justify-between">
                                        <div className="flex items-center gap-2">
                                            <span className="font-medium">PHP {php.version}</span>
                                            {php.is_cli_default && (
                                                <span className="rounded-full bg-primary/10 px-2 py-0.5 text-xs font-medium text-primary">
                                                    CLI Default
                                                </span>
                                            )}
                                            {php.is_site_default && (
                                                <span className="rounded-full bg-blue-500/10 px-2 py-0.5 text-xs font-medium text-blue-600 dark:text-blue-400">
                                                    Site Default
                                                </span>
                                            )}
                                        </div>
                                        <div className="text-sm text-muted-foreground capitalize">{php.status}</div>
                                    </div>
                                ))}
                            </div>
                        </CardContainer>
                    </>
                )}

                {defaultPhp && (
                    <form onSubmit={handleSubmit} className="space-y-6">
                        <CardContainer title="PHP Settings">
                            <div className="space-y-4">
                                <CardInput
                                    label="Memory Limit"
                                    value={data.memory_limit}
                                    onChange={(e) => setData('memory_limit', e.target.value)}
                                    placeholder="256M"
                                    error={errors.memory_limit}
                                />

                                <CardInput
                                    label="Max Execution Time (seconds)"
                                    type="number"
                                    value={data.max_execution_time}
                                    onChange={(e) => setData('max_execution_time', parseInt(e.target.value) || 30)}
                                    placeholder="30"
                                    error={errors.max_execution_time}
                                />

                                <CardInput
                                    label="Upload Max File Size"
                                    value={data.upload_max_filesize}
                                    onChange={(e) => setData('upload_max_filesize', e.target.value)}
                                    placeholder="2M"
                                    error={errors.upload_max_filesize}
                                />
                            </div>
                        </CardContainer>

                        {/* Submit */}
                        <div className="flex justify-end">
                            <Button type="submit" disabled={processing}>
                                {processing ? 'Updating...' : 'Update PHP'}
                            </Button>
                        </div>
                    </form>
                )}
            </PageHeader>
        </ServerLayout>
    );
}
