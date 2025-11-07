import { CardList, type CardListAction } from '@/components/card-list';
import { Button } from '@/components/ui/button';
import { CardBadge } from '@/components/ui/card-badge';
import { CardContainer } from '@/components/ui/card-container';
import { CardFormModal } from '@/components/ui/card-form-modal';
import { Label } from '@/components/ui/label';
import { PageHeader } from '@/components/ui/page-header';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import ServerLayout from '@/layouts/server/layout';
import { dashboard } from '@/routes';
import { show as showServer } from '@/routes/servers';
import { type BreadcrumbItem, type Server, type ServerNode } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { useEcho } from '@laravel/echo-react';
import { RotateCw, Trash2 } from 'lucide-react';
import { useState } from 'react';

export default function Node({ server }: { server: Server }) {
    // Listen for real-time server updates via Reverb
    useEcho(`servers.${server.id}`, 'ServerUpdated', () => {
        router.reload({ only: ['server'], preserveScroll: true });
    });

    const [isAddVersionDialogOpen, setIsAddVersionDialogOpen] = useState(false);

    const openAddVersionDialog = () => {
        resetAddVersion();
        setIsAddVersionDialogOpen(true);
    };

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
        { title: 'Node', href: '#' },
    ];

    const handleAddVersion = (e: React.FormEvent) => {
        e.preventDefault();
        postAddVersion(`/servers/${server.id}/node/install`, {
            onSuccess: () => {
                setIsAddVersionDialogOpen(false);
                resetAddVersion();
            },
        });
    };

    const handleRemoveNode = (node: ServerNode) => {
        if (node.is_default) {
            return;
        }

        if (confirm(`Are you sure you want to remove Node.js ${node.version}? This action cannot be undone.`)) {
            router.delete(`/servers/${server.id}/node/${node.id}`);
        }
    };

    const handleRetryNode = (node: ServerNode) => {
        if (!confirm(`Retry installing Node.js ${node.version}?`)) {
            return;
        }
        router.post(
            `/servers/${server.id}/node/${node.id}/retry`,
            {},
            {
                preserveScroll: true,
            },
        );
    };

    const handleSetDefault = (node: ServerNode) => {
        if (node.is_default) {
            return;
        }

        router.patch(`/servers/${server.id}/node/${node.id}/set-default`);
    };

    const handleUpdateComposer = () => {
        if (!confirm('Update Composer to the latest version?')) {
            return;
        }

        router.post(`/servers/${server.id}/composer/update`, {}, { preserveScroll: true });
    };

    const handleRetryComposer = () => {
        if (!confirm('Retry updating Composer?')) {
            return;
        }

        router.post(`/servers/${server.id}/composer/retry`, {}, { preserveScroll: true });
    };

    return (
        <ServerLayout server={server} breadcrumbs={breadcrumbs}>
            <Head title={`Node.js & Composer — ${server.vanity_name}`} />
            <PageHeader
                title={server.nodes.length > 0 ? 'Node.js & Composer' : 'Node.js Installation'}
                description={
                    server.nodes.length > 0
                        ? 'Manage Node.js versions and Composer on your server.'
                        : 'Install and configure Node.js and Composer for your server.'
                }
            >
                {server.nodes.length === 0 && (
                    <CardContainer
                        title="Install Node.js"
                        icon={
                            <svg width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <rect x="2" y="2" width="8" height="8" rx="1" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" />
                                <path d="M2 5h8M5 2v8" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" />
                            </svg>
                        }
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
                                <rect
                                    x="8"
                                    y="8"
                                    width="32"
                                    height="32"
                                    rx="4"
                                    stroke="currentColor"
                                    strokeWidth="2"
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                />
                                <path d="M8 20h32M20 8v32" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
                            </svg>
                            <p className="text-muted-foreground">No Node.js versions installed</p>
                            <p className="mt-1 text-sm text-muted-foreground/70">
                                Install your first Node.js version to get started. Composer will be installed automatically.
                            </p>
                            <Button onClick={openAddVersionDialog} className="mt-4">
                                Install Node.js
                            </Button>
                        </div>
                    </CardContainer>
                )}

                {server.nodes.length > 0 && (
                    <>
                        {/* Node.js Versions Section */}
                        <CardList<ServerNode>
                            title="Node.js Versions"
                            icon={
                                <svg width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <rect
                                        x="2"
                                        y="2"
                                        width="8"
                                        height="8"
                                        rx="1"
                                        stroke="currentColor"
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                    />
                                    <path d="M2 5h8M5 2v8" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" />
                                </svg>
                            }
                            onAddClick={openAddVersionDialog}
                            addButtonLabel="Add Version"
                            items={server.nodes}
                            keyExtractor={(node) => node.id}
                            renderItem={(node) => (
                                <div className="flex items-center justify-between gap-3">
                                    {/* Left: Node Version + Badge */}
                                    <div className="flex items-center gap-2">
                                        <span className="text-sm font-medium">Node.js {node.version}</span>
                                        {node.is_default && (
                                            <span className="rounded-full bg-primary/10 px-2 py-0.5 text-xs font-medium text-primary">Default</span>
                                        )}
                                    </div>

                                    {/* Right: Status Badge */}
                                    <div className="flex-shrink-0">
                                        <CardBadge variant={node.status as any} />
                                    </div>
                                </div>
                            )}
                            actions={(node) => {
                                const actions: CardListAction[] = [];
                                const isInTransition = node.status === 'pending' || node.status === 'installing' || node.status === 'removing';

                                // Add retry action for failed Node installations
                                if (node.status === 'failed') {
                                    actions.push({
                                        label: 'Retry Installation',
                                        onClick: () => handleRetryNode(node),
                                        icon: <RotateCw className="h-4 w-4" />,
                                        disabled: addVersionProcessing,
                                    });
                                }

                                // Set as default action
                                if (!node.is_default && node.status !== 'failed') {
                                    actions.push({
                                        label: 'Set as Default',
                                        onClick: () => handleSetDefault(node),
                                        disabled: isInTransition || node.status === 'failed',
                                    });
                                }

                                // Remove action (only if not default)
                                if (!node.is_default) {
                                    actions.push({
                                        label: `Remove Node.js ${node.version}`,
                                        onClick: () => handleRemoveNode(node),
                                        variant: 'destructive',
                                        icon: <Trash2 className="h-4 w-4" />,
                                        disabled: isInTransition,
                                    });
                                }

                                return actions;
                            }}
                        />

                        {/* Composer Section */}
                        {server.composer && (
                            <CardList<ServerComposer>
                                title="Composer"
                                icon={
                                    <svg width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <circle cx="6" cy="6" r="4" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" />
                                        <path d="M6 3v6M3 6h6" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" />
                                    </svg>
                                }
                                items={[server.composer]}
                                keyExtractor={(composer) => composer.version}
                                renderItem={(composer) => (
                                    <div className="flex items-center justify-between gap-3">
                                        {/* Left: Composer Version */}
                                        <div className="flex items-center gap-2">
                                            <span className="text-sm font-medium">Composer {composer.version}</span>
                                        </div>

                                        {/* Right: Status Badge */}
                                        <div className="flex-shrink-0">
                                            <CardBadge variant={composer.status as any} />
                                        </div>
                                    </div>
                                )}
                                actions={(composer) => {
                                    const actions: CardListAction[] = [];
                                    const isInTransition = composer.status === 'installing';

                                    // Add retry action for failed Composer updates
                                    if (composer.status === 'failed') {
                                        actions.push({
                                            label: 'Retry Update',
                                            onClick: handleRetryComposer,
                                            icon: <RotateCw className="h-4 w-4" />,
                                            disabled: isInTransition,
                                        });
                                    }

                                    // Add update action if not failed or installing
                                    if (composer.status !== 'failed' && composer.status !== 'installing') {
                                        actions.push({
                                            label: 'Update Composer',
                                            onClick: handleUpdateComposer,
                                            disabled: isInTransition,
                                        });
                                    }

                                    return actions;
                                }}
                            />
                        )}
                    </>
                )}
            </PageHeader>

            {/* Add Version Modal */}
            <CardFormModal
                open={isAddVersionDialogOpen}
                onOpenChange={setIsAddVersionDialogOpen}
                title="Add Node.js Version"
                description={
                    server.nodes.length === 0
                        ? 'Install Node.js on this server. Composer will be installed automatically.'
                        : 'Install an additional Node.js version on this server.'
                }
                onSubmit={handleAddVersion}
                submitLabel="Install"
                isSubmitting={addVersionProcessing}
                submitDisabled={!addVersionData.version}
                submittingLabel="Installing..."
            >
                <div className="space-y-2">
                    <Label htmlFor="add-version">Node.js Version</Label>
                    <Select
                        value={addVersionData.version}
                        onValueChange={(value) => {
                            clearAddVersionErrors('version');
                            setAddVersionData('version', value);
                        }}
                    >
                        <SelectTrigger id="add-version">
                            <SelectValue placeholder="Select Node.js version" />
                        </SelectTrigger>
                        <SelectContent>
                            {server.availableNodeVersions.map((option) => (
                                <SelectItem key={option.value} value={String(option.value)}>
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
