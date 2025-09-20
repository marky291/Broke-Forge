import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { update as updateServer } from '@/routes/servers';
import { type BreadcrumbItem } from '@/types';
import { Form, Head, Link } from '@inertiajs/react';

type Server = { id: number; vanity_name: string; public_ip: string; ssh_port: number; private_ip?: string | null };

export default function Edit({ server }: { server: Server }) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: dashboard().url },
        { title: 'Edit server', href: '#' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Edit server" />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="mb-2 flex items-center justify-between">
                    <h1 className="text-2xl font-semibold">Edit server</h1>
                    <Button variant="outline" asChild>
                        <Link href={dashboard().url}>Back</Link>
                    </Button>
                </div>

                <div className="rounded-xl border border-sidebar-border/70 bg-background shadow-sm dark:border-sidebar-border">
                    <div className="px-4 py-4">
                        <Form method="put" action={updateServer(server.id)} className="space-y-6">
                            {({ processing, errors }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="vanity_name">Name</Label>
                                        <Input id="vanity_name" name="vanity_name" defaultValue={server.vanity_name} required />
                                        <InputError className="mt-1" message={errors.vanity_name} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="public_ip">IP Address</Label>
                                        <Input id="public_ip" name="public_ip" defaultValue={server.public_ip} required />
                                        <InputError className="mt-1" message={errors.public_ip} />
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
                                        />
                                        <InputError className="mt-1" message={errors.ssh_port} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="private_ip">Private IP Address (optional)</Label>
                                        <Input id="private_ip" name="private_ip" defaultValue={server.private_ip ?? ''} />
                                        <InputError className="mt-1" message={errors.private_ip} />
                                    </div>

                                    <Button type="submit" disabled={processing}>
                                        Save changes
                                    </Button>
                                </>
                            )}
                        </Form>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
