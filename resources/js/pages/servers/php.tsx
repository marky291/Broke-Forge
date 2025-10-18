import { CardContainerAddButton } from '@/components/card-container-add-button';
import { Button } from '@/components/ui/button';
import { CardContainer } from '@/components/ui/card-container';
import { CardFormModal } from '@/components/ui/card-form-modal';
import { CardInput } from '@/components/ui/card-input';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Label } from '@/components/ui/label';
import { PageHeader } from '@/components/ui/page-header';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useEcho } from '@laravel/echo-react';
import ServerLayout from '@/layouts/server/layout';
import { dashboard } from '@/routes';
import { show as showServer } from '@/routes/servers';
import { type BreadcrumbItem, type Server, type ServerPhp } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { AlertCircle, CheckCircle, Loader2, MoreHorizontal } from 'lucide-react';
import { useState } from 'react';

export default function Php({ server }: { server: Server }) {
    // Listen for real-time server updates via Reverb
    useEcho(`servers.${server.id}`, 'ServerUpdated', () => {
        router.reload({ only: ['server'], preserveScroll: true });
    });

    // Get the default PHP version or first installed version
    const defaultPhp = server.phps.find((php) => php.is_cli_default) || server.phps[0];

    const [isAddVersionDialogOpen, setIsAddVersionDialogOpen] = useState(false);

    const openAddVersionDialog = () => {
        resetAddVersion();
        setIsAddVersionDialogOpen(true);
    };

    const { data, setData, post, processing, errors } = useForm({
        version: defaultPhp?.version || '8.3',
        memory_limit: '256M',
        max_execution_time: 30,
        upload_max_filesize: '2M',
    });

    const {
        data: addVersionData,
        setData: setAddVersionData,
        post: postAddVersion,
        processing: addVersionProcessing,
        errors: addVersionErrors,
        reset: resetAddVersion,
        clearErrors: clearAddVersionErrors,
    } = useForm({
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

    const handleRemovePhp = (php: ServerPhp) => {
        if (php.is_cli_default || php.is_site_default) {
            return;
        }

        if (confirm(`Are you sure you want to remove PHP ${php.version}? This action cannot be undone.`)) {
            router.delete(`/servers/${server.id}/php/${php.id}`);
        }
    };

    const handleSetCliDefault = (php: ServerPhp) => {
        if (php.is_cli_default) {
            return;
        }

        router.patch(`/servers/${server.id}/php/${php.id}/set-cli-default`);
    };

    const handleSetSiteDefault = (php: ServerPhp) => {
        if (php.is_site_default) {
            return;
        }

        router.patch(`/servers/${server.id}/php/${php.id}/set-site-default`);
    };

    return (
        <ServerLayout server={server} breadcrumbs={breadcrumbs}>
            <Head title={`PHP — ${server.vanity_name}`} />
            <PageHeader
                title={server.phps.length > 0 ? 'PHP Configuration' : 'PHP Installation'}
                description={
                    server.phps.length > 0
                        ? 'Configure PHP version, extensions, and settings for your server.'
                        : 'Install and configure PHP for your server.'
                }
            >
                {server.phps.length === 0 && (
                    <>
                        <CardContainer
                            title="Install PHP"
                            icon={
                                <svg width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path
                                        d="M6 1L10.5 3.5V8.5L6 11L1.5 8.5V3.5L6 1Z"
                                        stroke="currentColor"
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                    />
                                    <path d="M6 6V11" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" />
                                    <path d="M1.5 3.5L6 6L10.5 3.5" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" />
                                </svg>
                            }
                            action={<CardContainerAddButton label="Add Version" onClick={openAddVersionDialog} />}
                        >
                            <div className="p-12 text-center">
                                <svg
                                    width="48"
                                    height="48"
                                    viewBox="0 0 48 48"
                                    fill="none"
                                    xmlns="http://www.w3.org/2000/svg"
                                    className="mx-auto mb-4 text-muted-foreground/30"
                                >
                                    <path
                                        d="M24 4L42 14V34L24 44L6 34V14L24 4Z"
                                        stroke="currentColor"
                                        strokeWidth="2"
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                    />
                                    <path d="M24 24V44" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
                                    <path d="M6 14L24 24L42 14" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
                                </svg>
                                <p className="text-muted-foreground">No PHP versions installed</p>
                                <p className="mt-1 text-sm text-muted-foreground/70">Add your first PHP version to get started</p>
                            </div>
                        </CardContainer>

                        <CardContainer
                            title="Basic Settings"
                            icon={
                                <svg width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path
                                        d="M4.5 1.5H7.5L8.5 3.5L10.5 4.5V7.5L8.5 8.5L7.5 10.5H4.5L3.5 8.5L1.5 7.5V4.5L3.5 3.5L4.5 1.5Z"
                                        stroke="currentColor"
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                    />
                                    <circle cx="6" cy="6" r="1.5" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" />
                                </svg>
                            }
                        >
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
                    </>
                )}

                {server.phps.length > 0 && (
                    <CardContainer
                        title="Versions"
                        icon={
                            <svg width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path
                                    d="M6 1L10.5 3.5V8.5L6 11L1.5 8.5V3.5L6 1Z"
                                    stroke="currentColor"
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                />
                                <path d="M6 6V11" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" />
                                <path d="M1.5 3.5L6 6L10.5 3.5" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" />
                            </svg>
                        }
                        action={
                            <CardContainerAddButton label="Add Version" onClick={openAddVersionDialog} aria-label="Add Version" />
                        }
                        parentBorder={false}
                    >
                        <div className="space-y-3">
                            {server.phps.map((php) => (
                                <div
                                    key={php.id}
                                    className="divide-y divide-neutral-200 rounded-lg border border-neutral-200 bg-white dark:divide-white/8 dark:border-white/8 dark:bg-white/3"
                                >
                                    <div className="flex items-center justify-between px-6 py-6">
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
                                        <div className="flex items-center gap-2">
                                            {/* Status badges */}
                                            {php.status === 'pending' && (
                                                <span className="inline-flex items-center gap-1 rounded bg-slate-500/10 px-1.5 py-0.5 text-xs text-slate-600 dark:text-slate-400">
                                                    <Loader2 className="size-3 animate-spin" />
                                                    Pending
                                                </span>
                                            )}
                                            {php.status === 'installing' && (
                                                <span className="inline-flex items-center gap-1 rounded bg-blue-500/10 px-1.5 py-0.5 text-xs text-blue-600 dark:text-blue-400">
                                                    <Loader2 className="size-3 animate-spin" />
                                                    Installing
                                                </span>
                                            )}
                                            {php.status === 'active' && (
                                                <span className="inline-flex items-center gap-1 rounded bg-emerald-500/10 px-1.5 py-0.5 text-xs text-emerald-600 dark:text-emerald-400">
                                                    <CheckCircle className="size-3" />
                                                    Active
                                                </span>
                                            )}
                                            {php.status === 'inactive' && (
                                                <span className="inline-flex items-center gap-1 rounded bg-gray-500/10 px-1.5 py-0.5 text-xs text-gray-600 dark:text-gray-400">
                                                    Inactive
                                                </span>
                                            )}
                                            {php.status === 'failed' && (
                                                <span className="inline-flex items-center gap-1 rounded bg-red-500/10 px-1.5 py-0.5 text-xs text-red-600 dark:text-red-400">
                                                    <AlertCircle className="size-3" />
                                                    Failed
                                                </span>
                                            )}
                                            {php.status === 'removing' && (
                                                <span className="inline-flex items-center gap-1 rounded bg-orange-500/10 px-1.5 py-0.5 text-xs text-orange-600 dark:text-orange-400">
                                                    <Loader2 className="size-3 animate-spin" />
                                                    Removing
                                                </span>
                                            )}
                                            <DropdownMenu>
                                                <DropdownMenuTrigger asChild>
                                                    <Button variant="ghost" size="sm" className="size-8 p-0">
                                                        <MoreHorizontal className="size-4" />
                                                        <span className="sr-only">Open menu</span>
                                                    </Button>
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent align="end">
                                                    <DropdownMenuItem onClick={() => handleSetCliDefault(php)} disabled={php.is_cli_default}>
                                                        Set as CLI Default
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem onClick={() => handleSetSiteDefault(php)} disabled={php.is_site_default}>
                                                        Set as Site Default
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem
                                                        variant="destructive"
                                                        onClick={() => handleRemovePhp(php)}
                                                        disabled={php.is_cli_default || php.is_site_default}
                                                    >
                                                        Remove PHP {php.version}
                                                    </DropdownMenuItem>
                                                </DropdownMenuContent>
                                            </DropdownMenu>
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </CardContainer>
                )}

                {defaultPhp && (
                    <form onSubmit={handleSubmit} className="space-y-6">
                        <CardContainer
                            title="PHP Settings"
                            icon={
                                <svg width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path
                                        d="M4.5 1.5H7.5L8.5 3.5L10.5 4.5V7.5L8.5 8.5L7.5 10.5H4.5L3.5 8.5L1.5 7.5V4.5L3.5 3.5L4.5 1.5Z"
                                        stroke="currentColor"
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                    />
                                    <circle cx="6" cy="6" r="1.5" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" />
                                </svg>
                            }
                        >
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

            {/* Add Version Modal */}
            <CardFormModal
                open={isAddVersionDialogOpen}
                onOpenChange={setIsAddVersionDialogOpen}
                title="Add PHP Version"
                description="Install a new PHP version on this server."
                onSubmit={handleAddVersion}
                submitLabel="Install"
                isSubmitting={addVersionProcessing}
                submitDisabled={!addVersionData.version}
                submittingLabel="Installing..."
            >
                <div className="space-y-2">
                    <Label htmlFor="add-version">PHP Version</Label>
                    <Select
                        value={addVersionData.version}
                        onValueChange={(value) => {
                            clearAddVersionErrors('version');
                            setAddVersionData('version', value);
                        }}
                    >
                        <SelectTrigger id="add-version">
                            <SelectValue placeholder="Select PHP version" />
                        </SelectTrigger>
                        <SelectContent>
                            {server.availablePhpVersions.map((option) => (
                                <SelectItem key={option.value} value={option.value}>
                                    {option.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    {addVersionErrors.version && <div className="text-sm text-red-600">{addVersionErrors.version}</div>}
                </div>
            </CardFormModal>
        </ServerLayout>
    );
}
