import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { CardContainer } from '@/components/ui/card-container';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { PageHeader } from '@/components/ui/page-header';
import ServerLayout from '@/layouts/server/layout';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Form, Head, router } from '@inertiajs/react';
import { Loader2, Trash2 } from 'lucide-react';
import { useState } from 'react';

type Server = {
    id: number;
    vanity_name: string;
    public_ip: string;
    private_ip?: string | null;
    ssh_port: number;
    connection: string;
    created_at: string;
    updated_at: string;
};

export default function Settings({ server }: { server: Server }) {
    const [isDestroying, setIsDestroying] = useState(false);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: dashboard.url() },
        { title: `Server #${server.id}`, href: `/servers/${server.id}` },
        { title: 'Settings', href: '#' },
    ];

    const handleDestroyServer = () => {
        const confirmed = window.confirm('Are you sure you want to destroy this server? This action cannot be undone.');
        if (confirmed) {
            setIsDestroying(true);
            router.delete(`/servers/${server.id}`, {
                onFinish: () => setIsDestroying(false),
                onError: () => setIsDestroying(false),
            });
        }
    };

    return (
        <ServerLayout server={server} breadcrumbs={breadcrumbs}>
            <Head title={`Settings — ${server.vanity_name}`} />

            <PageHeader title="Server Settings" description="Manage your server configuration and connection details">
                <CardContainer title="General Settings">
                    <Form method="put" action={`/servers/${server.id}/settings`} className="space-y-6">
                        {({ processing, errors, recentlySuccessful }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="vanity_name">Name</Label>
                                    <Input id="vanity_name" name="vanity_name" defaultValue={server.vanity_name} required placeholder="My Server" />
                                    <InputError className="mt-1" message={errors.vanity_name} />
                                </div>

                                <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="public_ip">Public IP Address</Label>
                                        <Input id="public_ip" name="public_ip" defaultValue={server.public_ip} required placeholder="192.168.1.1" />
                                        <InputError className="mt-1" message={errors.public_ip} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="private_ip">Private IP Address (optional)</Label>
                                        <Input id="private_ip" name="private_ip" defaultValue={server.private_ip ?? ''} placeholder="10.0.0.1" />
                                        <InputError className="mt-1" message={errors.private_ip} />
                                    </div>
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="ssh_port">SSH Port</Label>
                                    <Input
                                        id="ssh_port"
                                        name="ssh_port"
                                        type="number"
                                        min={1}
                                        max={65535}
                                        defaultValue={server.ssh_port}
                                        required
                                        placeholder="22"
                                    />
                                    <InputError className="mt-1" message={errors.ssh_port} />
                                </div>

                                <div className="flex items-center gap-2">
                                    <Button type="submit" disabled={processing}>
                                        {processing ? 'Saving...' : 'Save Changes'}
                                    </Button>
                                    {recentlySuccessful && <span className="text-sm text-green-600">Settings saved successfully</span>}
                                </div>
                            </>
                        )}
                    </Form>
                </CardContainer>

                <CardContainer title="Connection Information">
                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <div className="text-sm text-muted-foreground">Connection Status</div>
                            <div className="font-medium capitalize">{server.connection}</div>
                        </div>
                        <div>
                            <div className="text-sm text-muted-foreground">Server ID</div>
                            <div className="font-medium">#{server.id}</div>
                        </div>
                        <div>
                            <div className="text-sm text-muted-foreground">Created</div>
                            <div className="font-medium">{new Date(server.created_at).toLocaleString()}</div>
                        </div>
                        <div>
                            <div className="text-sm text-muted-foreground">Last Updated</div>
                            <div className="font-medium">{new Date(server.updated_at).toLocaleString()}</div>
                        </div>
                    </div>
                </CardContainer>

                {/* Danger Zone */}
                <CardContainer title="Danger Zone" description="Irreversible and destructive actions" className="border-red-200 dark:border-red-900">
                    <Button variant="destructive" size="sm" onClick={handleDestroyServer} disabled={isDestroying}>
                        {isDestroying ? (
                            <>
                                <Loader2 className="mr-2 size-4 animate-spin" />
                                Destroying...
                            </>
                        ) : (
                            <>
                                <Trash2 className="mr-2 size-4" />
                                Destroy Server
                            </>
                        )}
                    </Button>
                </CardContainer>
            </PageHeader>
        </ServerLayout>
    );
}
