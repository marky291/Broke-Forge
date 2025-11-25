import { CardList, type CardListAction } from '@/components/card-list';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { CardBadge } from '@/components/ui/card-badge';
import { CardContainer } from '@/components/ui/card-container';
import { CardFormModal } from '@/components/ui/card-form-modal';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { PageHeader } from '@/components/ui/page-header';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import ServerLayout from '@/layouts/server/layout';
import { dashboard } from '@/routes';
import { show as showServer } from '@/routes/servers';
import { type BreadcrumbItem, type Server } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useEcho } from '@laravel/echo-react';
import { ArrowLeft, Database, Edit2, Plus, RotateCw, Trash2, Users, X } from 'lucide-react';
import { useState } from 'react';

type DatabaseType = {
    id: number;
    name: string;
    type: string;
    version: string;
    port: number;
    status: string;
    error_log: string | null;
    created_at: string;
    updated_at: string;
};

type Schema = {
    id: number;
    name: string;
    character_set: string;
    collation: string;
    status: string;
    error_log: string | null;
    created_at: string;
};

type ManagedUser = {
    id: number;
    is_root: boolean;
    username: string;
    host: string;
    privileges: string;
    status: string;
    error_log: string | null;
    update_status: string | null;
    update_error_log: string | null;
    schemas: { id: number; name: string }[];
    created_at: string;
};

export default function DatabaseDetails({
    server,
    database,
    schemas,
    managedUsers,
}: {
    server: Server;
    database: DatabaseType;
    schemas: Schema[];
    managedUsers: ManagedUser[];
}) {
    const [showSchemaDialog, setShowSchemaDialog] = useState(false);
    const [showUserDialog, setShowUserDialog] = useState(false);
    const [editingUser, setEditingUser] = useState<ManagedUser | null>(null);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: dashboard.url() },
        { title: `Server #${server.id}`, href: showServer(server.id).url },
        { title: 'Services', href: `/servers/${server.id}/services` },
        { title: database.name, href: '#' },
    ];

    // Real-time updates via Reverb WebSocket
    useEcho(`servers.${server.id}`, 'ServerUpdated', () => {
        router.reload({
            only: ['database', 'schemas', 'managedUsers'],
            preserveScroll: true,
            preserveState: true,
        });
    });

    const databaseTypeName = {
        mysql: 'MySQL',
        mariadb: 'MariaDB',
        postgresql: 'PostgreSQL',
    }[database.type] || database.type;

    const schemaForm = useForm({
        name: '',
        user: '',
        password: '',
    });

    const userForm = useForm({
        username: '',
        password: '',
        host: '%',
        privileges: 'read_write',
        schema_ids: [] as number[],
    });

    const handleCreateSchema = (e: React.FormEvent) => {
        e.preventDefault();
        schemaForm.post(`/servers/${server.id}/databases/${database.id}/schemas`, {
            preserveScroll: true,
            onSuccess: () => {
                setShowSchemaDialog(false);
                schemaForm.reset();
            },
        });
    };

    const handleDeleteSchema = (schemaId: number) => {
        if (!confirm('Are you sure you want to delete this database? This will permanently delete all data.')) {
            return;
        }
        router.delete(`/servers/${server.id}/databases/${database.id}/schemas/${schemaId}`, {
            preserveScroll: true,
        });
    };

    const handleCreateUser = (e: React.FormEvent) => {
        e.preventDefault();
        userForm.post(`/servers/${server.id}/databases/${database.id}/users`, {
            preserveScroll: true,
            onSuccess: () => {
                setShowUserDialog(false);
                setEditingUser(null);
                userForm.reset();
            },
        });
    };

    const handleUpdateUser = (e: React.FormEvent) => {
        e.preventDefault();
        if (!editingUser) return;

        userForm.patch(`/servers/${server.id}/databases/${database.id}/users/${editingUser.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                setShowUserDialog(false);
                setEditingUser(null);
                userForm.reset();
            },
        });
    };

    const handleDeleteUser = (userId: number) => {
        if (!confirm('Are you sure you want to delete this database user?')) {
            return;
        }
        router.delete(`/servers/${server.id}/databases/${database.id}/users/${userId}`, {
            preserveScroll: true,
        });
    };

    const handleRetryUser = (userId: number) => {
        router.post(`/servers/${server.id}/databases/${database.id}/users/${userId}/retry`, {}, {
            preserveScroll: true,
        });
    };

    const handleCancelUpdate = (userId: number) => {
        router.post(`/servers/${server.id}/databases/${database.id}/users/${userId}/cancel-update`, {}, {
            preserveScroll: true,
        });
    };

    const openEditUserDialog = (user: ManagedUser) => {
        setEditingUser(user);
        userForm.setData({
            username: user.username,
            password: '',
            host: user.host,
            privileges: user.privileges,
            schema_ids: user.schemas.map((s) => s.id),
        });
        setShowUserDialog(true);
    };

    const privilegeLabels = {
        all: 'All Privileges',
        read_only: 'Read Only',
        read_write: 'Read/Write',
    };

    return (
        <ServerLayout server={server} breadcrumbs={breadcrumbs}>
            <Head title={`${database.name} — ${server.vanity_name}`} />
            <PageHeader
                title={
                    <div className="flex items-center gap-3">
                        <Link href={`/servers/${server.id}/services`}>
                            <Button variant="ghost" size="sm" className="-ml-2">
                                <ArrowLeft className="h-4 w-4" />
                            </Button>
                        </Link>
                        <span>{database.name}</span>
                    </div>
                }
                description={`${databaseTypeName} ${database.version} database on port ${database.port}`}
            >
                {/* Database Service Overview Card */}
                <CardContainer title="Database Service Information" parentBorder={true}>
                    <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
                        <div>
                            <div className="text-sm font-medium text-muted-foreground">Name</div>
                            <div className="mt-1 text-base">{database.name}</div>
                        </div>
                        <div>
                            <div className="text-sm font-medium text-muted-foreground">Type</div>
                            <div className="mt-1 text-base">{databaseTypeName}</div>
                        </div>
                        <div>
                            <div className="text-sm font-medium text-muted-foreground">Version</div>
                            <div className="mt-1 text-base">{database.version}</div>
                        </div>
                        <div>
                            <div className="text-sm font-medium text-muted-foreground">Port</div>
                            <div className="mt-1 text-base">{database.port}</div>
                        </div>
                        <div>
                            <div className="text-sm font-medium text-muted-foreground">Status</div>
                            <div className="mt-1">
                                <CardBadge variant={database.status as any} />
                            </div>
                        </div>
                        <div>
                            <div className="text-sm font-medium text-muted-foreground">Created</div>
                            <div className="mt-1 text-base">
                                {new Date(database.created_at).toLocaleDateString('en-US', {
                                    year: 'numeric',
                                    month: 'short',
                                    day: 'numeric',
                                })}
                            </div>
                        </div>
                    </div>

                    {database.error_log && (
                        <Alert variant="destructive" className="mt-6">
                            <AlertTitle>Installation Error</AlertTitle>
                            <AlertDescription className="mt-2 text-sm font-mono whitespace-pre-wrap">
                                {database.error_log}
                            </AlertDescription>
                        </Alert>
                    )}
                </CardContainer>

                {/* Databases Section */}
                <CardList<Schema>
                    title="Databases"
                    description="Create and manage databases within this database service"
                    icon={<Database className="h-3 w-3" />}
                    onAddClick={() => setShowSchemaDialog(true)}
                    addButtonLabel="Create Database"
                    items={schemas}
                    keyExtractor={(schema) => schema.id.toString()}
                    renderItem={(schema) => (
                        <div className="flex items-center justify-between gap-3">
                            <div className="min-w-0 flex-1">
                                <div className="truncate text-sm font-medium text-foreground">{schema.name}</div>
                            </div>
                            <div className="flex-shrink-0">
                                <CardBadge variant={schema.status as any} />
                            </div>
                        </div>
                    )}
                    actions={(schema) => {
                        const actions: CardListAction[] = [];
                        const isInTransition = schema.status === 'pending' || schema.status === 'installing' || schema.status === 'removing';

                        if (schema.status === 'active' || schema.status === 'failed') {
                            actions.push({
                                label: 'Delete Database',
                                onClick: () => handleDeleteSchema(schema.id),
                                variant: 'destructive',
                                icon: <Trash2 className="h-4 w-4" />,
                                disabled: isInTransition,
                            });
                        }

                        return actions;
                    }}
                    emptyStateMessage="No databases created yet"
                    emptyStateIcon={<Database className="h-6 w-6 text-muted-foreground" />}
                />

                {/* Database Users Section */}
                {schemas.length === 0 ? (
                    <div className="mt-8">
                        <CardContainer title="Database Users" description="Create and manage database users with specific permissions and schema access" parentBorder={true}>
                            <Alert>
                                <AlertDescription>Create at least one database before creating users.</AlertDescription>
                            </Alert>
                        </CardContainer>
                    </div>
                ) : (
                    <CardList<ManagedUser>
                        title="Database Users"
                        description="Create and manage database users with specific permissions and schema access"
                        icon={<Users className="h-3 w-3" />}
                        onAddClick={() => setShowUserDialog(true)}
                        addButtonLabel="Create User"
                        items={managedUsers}
                        keyExtractor={(user) => user.id.toString()}
                        renderItem={(user) => (
                            <div className="flex items-center justify-between gap-3">
                                <div className="min-w-0 flex-1">
                                    <div className="flex items-center gap-2">
                                        <span className="truncate text-sm font-medium text-foreground">{user.username}</span>
                                        {user.is_root && (
                                            <span className="rounded-md bg-amber-100 px-1.5 py-0.5 text-xs font-medium text-amber-800 dark:bg-amber-900 dark:text-amber-200">
                                                Root
                                            </span>
                                        )}
                                    </div>
                                </div>
                                <div className="flex flex-shrink-0 items-center gap-2">
                                    {/* Show update_status if present, otherwise show main status */}
                                    <CardBadge variant={(user.update_status || user.status) as any} />
                                </div>
                            </div>
                        )}
                        actions={(user) => {
                            const actions: CardListAction[] = [];
                            const isInTransition = user.status === 'pending' || user.status === 'installing' || user.status === 'removing';
                            const isUpdating = user.update_status === 'pending' || user.update_status === 'updating';

                            if (!user.is_root) {
                                // Show retry for failed updates
                                if (user.update_status === 'failed') {
                                    actions.push({
                                        label: 'Retry Update',
                                        onClick: () => handleRetryUser(user.id),
                                        icon: <RotateCw className="h-4 w-4" />,
                                    });
                                }

                                // Show cancel for pending, updating, or failed updates
                                if (user.update_status) {
                                    actions.push({
                                        label: 'Cancel Update',
                                        onClick: () => handleCancelUpdate(user.id),
                                        icon: <X className="h-4 w-4" />,
                                        variant: 'secondary',
                                    });
                                }

                                // Show edit/delete only when not in transition and not updating
                                if ((user.status === 'active' || user.status === 'failed') && !isInTransition && !isUpdating) {
                                    actions.push({
                                        label: 'Edit User',
                                        onClick: () => openEditUserDialog(user),
                                        icon: <Edit2 className="h-4 w-4" />,
                                    });

                                    actions.push({
                                        label: 'Delete User',
                                        onClick: () => handleDeleteUser(user.id),
                                        variant: 'destructive',
                                        icon: <Trash2 className="h-4 w-4" />,
                                    });
                                }
                            }

                            return actions;
                        }}
                        emptyStateMessage="No database users created yet"
                        emptyStateIcon={<Users className="h-6 w-6 text-muted-foreground" />}
                    />
                )}
            </PageHeader>

            {/* Create Database Modal */}
            <CardFormModal
                open={showSchemaDialog}
                onOpenChange={setShowSchemaDialog}
                title="Create Database"
                description="Create a new database within this database service"
                onSubmit={handleCreateSchema}
                submitLabel="Create Database"
                isSubmitting={schemaForm.processing}
            >
                <div>
                    <Label htmlFor="schema_name">Database Name</Label>
                    <Input
                        id="schema_name"
                        value={schemaForm.data.name}
                        onChange={(e) => schemaForm.setData('name', e.target.value)}
                        placeholder="my_application_db"
                        required
                    />
                    {schemaForm.errors.name && <p className="text-sm text-destructive mt-1">{schemaForm.errors.name}</p>}
                </div>
                <div>
                    <Label htmlFor="user">User (optional)</Label>
                    <Input
                        id="user"
                        value={schemaForm.data.user}
                        onChange={(e) => schemaForm.setData('user', e.target.value)}
                        placeholder="app_user"
                    />
                    {schemaForm.errors.user && <p className="text-sm text-destructive mt-1">{schemaForm.errors.user}</p>}
                </div>
                <div>
                    <Label htmlFor="password">Password (optional)</Label>
                    <Input
                        id="password"
                        type="password"
                        value={schemaForm.data.password}
                        onChange={(e) => schemaForm.setData('password', e.target.value)}
                        placeholder="••••••••"
                    />
                    {schemaForm.errors.password && <p className="text-sm text-destructive mt-1">{schemaForm.errors.password}</p>}
                </div>
            </CardFormModal>

            {/* Create/Edit User Dialog */}
            <Dialog
                open={showUserDialog}
                onOpenChange={(open) => {
                    setShowUserDialog(open);
                    if (!open) {
                        setEditingUser(null);
                        userForm.reset();
                    }
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{editingUser ? 'Edit Database User' : 'Create Database User'}</DialogTitle>
                        <DialogDescription>
                            {editingUser ? 'Update user password, privileges, or schema access' : 'Create a new database user with specific permissions'}
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={editingUser ? handleUpdateUser : handleCreateUser} className="space-y-4">
                        {!editingUser && (
                            <>
                                <div>
                                    <Label htmlFor="username">Username</Label>
                                    <Input
                                        id="username"
                                        value={userForm.data.username}
                                        onChange={(e) => userForm.setData('username', e.target.value)}
                                        placeholder="app_user"
                                        required
                                    />
                                    {userForm.errors.username && <p className="text-sm text-destructive mt-1">{userForm.errors.username}</p>}
                                </div>
                                <div>
                                    <Label htmlFor="host">Host (IP or %)</Label>
                                    <Input
                                        id="host"
                                        value={userForm.data.host}
                                        onChange={(e) => userForm.setData('host', e.target.value)}
                                        placeholder="% (any host)"
                                    />
                                    <p className="text-xs text-muted-foreground mt-1">Use % for any IP, localhost, or specific IP address</p>
                                </div>
                            </>
                        )}
                        <div>
                            <Label htmlFor="password">{editingUser ? 'New Password (leave blank to keep current)' : 'Password'}</Label>
                            <Input
                                id="password"
                                type="password"
                                value={userForm.data.password}
                                onChange={(e) => userForm.setData('password', e.target.value)}
                                placeholder="••••••••"
                                required={!editingUser}
                            />
                            {userForm.errors.password && <p className="text-sm text-destructive mt-1">{userForm.errors.password}</p>}
                        </div>
                        <div>
                            <Label htmlFor="privileges">Privileges</Label>
                            <Select value={userForm.data.privileges} onValueChange={(value) => userForm.setData('privileges', value)}>
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All Privileges</SelectItem>
                                    <SelectItem value="read_write">Read/Write (SELECT, INSERT, UPDATE, DELETE)</SelectItem>
                                    <SelectItem value="read_only">Read Only (SELECT)</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div>
                            <Label>Databases (select one or more)</Label>
                            <div className="border rounded-md p-3 space-y-2 max-h-40 overflow-y-auto">
                                {schemas.map((schema) => (
                                    <label key={schema.id} className="flex items-center gap-2 cursor-pointer">
                                        <input
                                            type="checkbox"
                                            checked={userForm.data.schema_ids.includes(schema.id)}
                                            onChange={(e) => {
                                                if (e.target.checked) {
                                                    userForm.setData('schema_ids', [...userForm.data.schema_ids, schema.id]);
                                                } else {
                                                    userForm.setData(
                                                        'schema_ids',
                                                        userForm.data.schema_ids.filter((id) => id !== schema.id),
                                                    );
                                                }
                                            }}
                                            className="rounded"
                                        />
                                        <span className="text-sm">{schema.name}</span>
                                    </label>
                                ))}
                            </div>
                        </div>
                        <div className="flex justify-end gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => {
                                    setShowUserDialog(false);
                                    setEditingUser(null);
                                    userForm.reset();
                                }}
                            >
                                Cancel
                            </Button>
                            <Button type="submit" disabled={userForm.processing}>
                                {userForm.processing ? (editingUser ? 'Updating...' : 'Creating...') : editingUser ? 'Update User' : 'Create User'}
                            </Button>
                        </div>
                    </form>
                </DialogContent>
            </Dialog>
        </ServerLayout>
    );
}
