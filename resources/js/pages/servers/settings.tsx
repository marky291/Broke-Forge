import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import ServerLayout from '@/layouts/server/layout';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Form, Head } from '@inertiajs/react';

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
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: dashboard().url },
        { title: `Server #${server.id}`, href: `/servers/${server.id}` },
        { title: 'Settings', href: '#' },
    ];

    return (
        <ServerLayout server={server} breadcrumbs={breadcrumbs}>
            <Head title={`Settings — ${server.vanity_name}`} />

            <div className="space-y-6">
                <div className="mb-2">
                    <h1 className="text-2xl font-semibold">Server Settings</h1>
                    <p className="text-sm text-muted-foreground mt-1">
                        Manage your server configuration and connection details
                    </p>
                </div>

                <div className="rounded-xl border border-sidebar-border/70 bg-background shadow-sm dark:border-sidebar-border">
                    <div className="px-4 py-3">
                        <div className="text-sm font-medium tracking-wide text-neutral-600 uppercase dark:text-neutral-300">
                            General Settings
                        </div>
                    </div>
                    <Separator />
                    <div className="px-4 py-4">
                        <Form method="put" action={`/servers/${server.id}/settings`} className="space-y-6">
                            {({ processing, errors, recentlySuccessful }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="vanity_name">Name</Label>
                                        <Input
                                            id="vanity_name"
                                            name="vanity_name"
                                            defaultValue={server.vanity_name}
                                            required
                                            placeholder="My Server"
                                        />
                                        <InputError className="mt-1" message={errors.vanity_name} />
                                    </div>

                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div className="grid gap-2">
                                            <Label htmlFor="public_ip">Public IP Address</Label>
                                            <Input
                                                id="public_ip"
                                                name="public_ip"
                                                defaultValue={server.public_ip}
                                                required
                                                placeholder="192.168.1.1"
                                            />
                                            <InputError className="mt-1" message={errors.public_ip} />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="private_ip">Private IP Address (optional)</Label>
                                            <Input
                                                id="private_ip"
                                                name="private_ip"
                                                defaultValue={server.private_ip ?? ''}
                                                placeholder="10.0.0.1"
                                            />
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
                                        {recentlySuccessful && (
                                            <span className="text-sm text-green-600">
                                                Settings saved successfully
                                            </span>
                                        )}
                                    </div>
                                </>
                            )}
                        </Form>
                    </div>
                </div>

                <div className="rounded-xl border border-sidebar-border/70 bg-background shadow-sm dark:border-sidebar-border">
                    <div className="px-4 py-3">
                        <div className="text-sm font-medium tracking-wide text-neutral-600 uppercase dark:text-neutral-300">
                            Connection Information
                        </div>
                    </div>
                    <Separator />
                    <div className="px-4 py-4">
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
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
                    </div>
                </div>
            </div>
        </ServerLayout>
    );
}
