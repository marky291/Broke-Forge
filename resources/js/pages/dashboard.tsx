import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { PlaceholderPattern } from '@/components/ui/placeholder-pattern';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { provisioning as provisioningServer, show as showServer, store as storeServer } from '@/routes/servers';
import { type BreadcrumbItem } from '@/types';
import { Form, Head, Link } from '@inertiajs/react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
];

type Activity = { id: number; type: string; label: string; detail?: string | null; created_at: string };
type Server = {
    id: number;
    name: string;
    public_ip: string;
    ssh_port: number;
    private_ip?: string | null;
    connection?: 'pending' | 'connected' | 'failed' | 'disconnected' | string;
    provision_status: 'pending' | 'connecting' | 'installing' | 'completed' | 'failed';
    created_at: string;
};

function generateFriendlyName(): string {
    const adjectives = [
        'Swift',
        'Brave',
        'Bright',
        'Noble',
        'Quick',
        'Wise',
        'Bold',
        'Grand',
        'Prime',
        'Elite',
        'Alpha',
        'Super',
        'Mega',
        'Ultra',
        'Pro',
        'Epic',
    ];
    const nouns = [
        'Server',
        'Node',
        'Engine',
        'Machine',
        'System',
        'Core',
        'Hub',
        'Base',
        'Cloud',
        'Phoenix',
        'Falcon',
        'Eagle',
        'Tiger',
        'Lion',
        'Bear',
        'Wolf',
    ];

    const adjective = adjectives[Math.floor(Math.random() * adjectives.length)];
    const noun = nouns[Math.floor(Math.random() * nouns.length)];
    const number = Math.floor(Math.random() * 99) + 1;

    return `${adjective} ${noun} ${number}`;
}

export default function Dashboard({ activities, servers }: { activities: Activity[]; servers: Server[] }) {
    const [defaultName, setDefaultName] = useState<string>('');
    const [phpVersion, setPhpVersion] = useState<string>('8.3');

    const handleDialogOpen = () => {
        setDefaultName(generateFriendlyName());
        setPhpVersion('8.3');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                {/* Create Server modal trigger and content lives with Servers header below */}

                <div className="rounded-xl border p-4">
                    <div className="mb-4 flex items-center justify-between">
                        <h2 className="text-lg font-semibold">Servers</h2>
                        {/* Secondary trigger near list header */}
                        <Dialog>
                            <DialogTrigger asChild>
                                <Button size="sm" variant="outline" onClick={handleDialogOpen}>
                                    Create Server
                                </Button>
                            </DialogTrigger>
                            <DialogContent>
                                <DialogHeader>
                                    <DialogTitle>Create Server</DialogTitle>
                                </DialogHeader>
                                <Form method="post" action={storeServer()} className="grid gap-4">
                                    {({ processing, errors }) => (
                                        <>
                                            <div className="grid gap-2">
                                                <Label htmlFor="vanity_name">Name</Label>
                                                <Input
                                                    id="vanity_name"
                                                    name="vanity_name"
                                                    defaultValue={defaultName}
                                                    placeholder="e.g., Production Web Server"
                                                    required
                                                />
                                                <InputError className="mt-1" message={errors.vanity_name} />
                                            </div>
                                            <div className="grid gap-2">
                                                <Label htmlFor="public_ip">IP Address</Label>
                                                <Input id="public_ip" name="public_ip" placeholder="203.0.113.10" required />
                                                <InputError className="mt-1" message={errors.public_ip} />
                                            </div>
                                            <div className="grid gap-2">
                                                <Label htmlFor="private_ip">Private IP (optional)</Label>
                                                <Input id="private_ip" name="private_ip" placeholder="10.0.0.5" />
                                                <InputError className="mt-1" message={errors.private_ip} />
                                            </div>
                                            <div className="grid gap-2">
                                                <Label htmlFor="php_version">PHP Version</Label>
                                                <Select value={phpVersion} onValueChange={setPhpVersion}>
                                                    <SelectTrigger id="php_version">
                                                        <SelectValue placeholder="Select PHP version" />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem value="8.4">PHP 8.4</SelectItem>
                                                        <SelectItem value="8.3">PHP 8.3</SelectItem>
                                                        <SelectItem value="8.2">PHP 8.2</SelectItem>
                                                        <SelectItem value="8.1">PHP 8.1</SelectItem>
                                                        <SelectItem value="8.0">PHP 8.0</SelectItem>
                                                        <SelectItem value="7.4">PHP 7.4</SelectItem>
                                                    </SelectContent>
                                                </Select>
                                                <input type="hidden" name="php_version" value={phpVersion} />
                                                <InputError className="mt-1" message={errors.php_version} />
                                            </div>
                                            <div className="flex justify-end gap-2 pt-2">
                                                <Button type="submit" disabled={processing}>
                                                    Create Server
                                                </Button>
                                            </div>
                                        </>
                                    )}
                                </Form>
                            </DialogContent>
                        </Dialog>
                    </div>
                    {servers && servers.length > 0 ? (
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                            {servers.map((s) => {
                                const status = s.connection ?? 'pending';
                                const formattedStatus = status.charAt(0).toUpperCase() + status.slice(1);

                                const provisionStatus = s.provision_status ?? 'pending';
                                const serverUrl = provisionStatus === 'completed' ? showServer(s.id) : provisioningServer(s.id);
                                return (
                                    <Link key={s.id} href={serverUrl} className="group block">
                                        <div className="relative overflow-hidden rounded-xl border border-sidebar-border/70 p-4 transition-colors group-hover:bg-muted/40 dark:border-sidebar-border">
                                            <div className="mb-1 text-sm font-medium">{s.name}</div>
                                            <div className="text-sm text-muted-foreground">
                                                {s.public_ip}:{s.ssh_port} ({s.id})
                                            </div>
                                            {s.private_ip && <div className="text-xs text-muted-foreground">Private: {s.private_ip}</div>}
                                            {(() => {
                                                const color =
                                                    status === 'connected'
                                                        ? 'green'
                                                        : status === 'failed'
                                                          ? 'red'
                                                          : status === 'disconnected'
                                                            ? 'gray'
                                                            : 'amber';
                                                const dot =
                                                    color === 'green'
                                                        ? 'bg-green-500'
                                                        : color === 'red'
                                                          ? 'bg-red-500'
                                                          : color === 'gray'
                                                            ? 'bg-gray-500'
                                                            : 'bg-amber-500';
                                                const ping =
                                                    color === 'green'
                                                        ? 'bg-green-400'
                                                        : color === 'red'
                                                          ? 'bg-red-400'
                                                          : color === 'gray'
                                                            ? 'bg-gray-400'
                                                            : 'bg-amber-400';
                                                return (
                                                    <div className="mt-3 flex items-center gap-2 text-xs">
                                                        <span className="relative inline-flex h-2 w-2">
                                                            <span
                                                                className={`absolute inline-flex h-full w-full animate-ping rounded-full opacity-60 ${ping}`}
                                                            ></span>
                                                            <span className={`relative inline-flex h-2 w-2 rounded-full ${dot}`}></span>
                                                        </span>
                                                        <span className="text-muted-foreground">{formattedStatus}</span>
                                                    </div>
                                                );
                                            })()}
                                            <div className="mt-3 text-xs text-muted-foreground">
                                                Created {new Date(s.created_at).toLocaleString()}
                                            </div>
                                        </div>
                                    </Link>
                                );
                            })}
                        </div>
                    ) : (
                        <div className="px-4 py-4 text-sm text-muted-foreground">No servers yet.</div>
                    )}
                </div>
                <div className="grid auto-rows-min gap-4 md:grid-cols-3">
                    <div className="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                        <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/20 dark:stroke-neutral-100/20" />
                    </div>
                    <div className="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                        <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/20 dark:stroke-neutral-100/20" />
                    </div>
                    <div className="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                        <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/20 dark:stroke-neutral-100/20" />
                    </div>
                </div>
                <div className="relative min-h-[100vh] flex-1 overflow-hidden rounded-xl border border-sidebar-border/70 md:min-h-min dark:border-sidebar-border">
                    <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/20 dark:stroke-neutral-100/20" />
                    <div className="absolute inset-0 z-10 flex h-full w-full flex-col">
                        <div className="flex items-center justify-between px-4 pt-3">
                            <h3 className="text-sm font-medium tracking-wide text-neutral-600 uppercase dark:text-neutral-300">Activity</h3>
                        </div>
                        <div className="mt-2 flex-1 overflow-auto px-4 pb-3">
                            {activities && activities.length > 0 ? (
                                <ul className="space-y-2 text-sm">
                                    {activities.map((a) => (
                                        <li
                                            key={a.id}
                                            className="rounded-md border bg-background/60 p-2 backdrop-blur supports-[backdrop-filter]:bg-background/40"
                                        >
                                            <div className="flex items-center justify-between">
                                                <div className="font-medium">{a.label}</div>
                                                <div className="text-xs text-muted-foreground">{new Date(a.created_at).toLocaleString()}</div>
                                            </div>
                                            {a.detail && <div className="text-xs text-muted-foreground">{a.detail}</div>}
                                        </li>
                                    ))}
                                </ul>
                            ) : (
                                <div className="text-sm text-muted-foreground">No recent activity.</div>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
