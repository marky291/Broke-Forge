import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { cn } from '@/lib/utils';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { provisioning as provisioningServer, show as showServer, store as storeServer } from '@/routes/servers';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Form, Head, Link, usePage } from '@inertiajs/react';
import {
    Activity,
    ArrowRight,
    Clock,
    FileText,
    Globe,
    Plus,
    Rocket,
    Server as ServerIcon,
    Settings,
    TrendingUp,
    Users,
    Zap
} from 'lucide-react';
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
    const { auth } = usePage<SharedData>().props;
    const [defaultName, setDefaultName] = useState<string>('');
    const [phpVersion, setPhpVersion] = useState<string>('8.3');

    const handleDialogOpen = () => {
        setDefaultName(generateFriendlyName());
        setPhpVersion('8.3');
    };

    // Calculate statistics
    const stats = {
        totalServers: servers.length,
        activeServers: servers.filter(s => s.connection === 'connected').length,
        pendingServers: servers.filter(s => s.provision_status === 'pending' || s.provision_status === 'installing').length,
        recentActivity: activities.filter(a => {
            const date = new Date(a.created_at);
            const dayAgo = new Date();
            dayAgo.setDate(dayAgo.getDate() - 1);
            return date > dayAgo;
        }).length
    };

    const getTimeAgo = (dateString: string) => {
        const date = new Date(dateString);
        const now = new Date();
        const diffInSeconds = Math.floor((now.getTime() - date.getTime()) / 1000);

        if (diffInSeconds < 60) return 'just now';
        if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)}m ago`;
        if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)}h ago`;
        if (diffInSeconds < 604800) return `${Math.floor(diffInSeconds / 86400)}d ago`;
        return date.toLocaleDateString();
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />
            <div className="space-y-8">
                {/* Welcome Section */}
                <div className="relative overflow-hidden rounded-2xl bg-gradient-to-br from-primary/10 via-primary/5 to-background border">
                    <div className="absolute inset-0 bg-grid-white/10 [mask-image:linear-gradient(0deg,transparent,rgba(255,255,255,0.5))]" />
                    <div className="relative px-6 py-8 sm:px-8 sm:py-10">
                        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-6">
                            <div>
                                <h1 className="text-3xl font-bold tracking-tight">
                                    Welcome back, {auth.user.name.split(' ')[0]}!
                                </h1>
                                <p className="mt-2 text-muted-foreground">
                                    Here's an overview of your infrastructure and recent activity
                                </p>
                            </div>
                            <Dialog>
                                <DialogTrigger asChild>
                                    <Button size="lg" onClick={handleDialogOpen} className="shadow-lg">
                                        <Plus className="mr-2 size-5" />
                                        Deploy New Server
                                    </Button>
                                </DialogTrigger>
                                <DialogContent className="sm:max-w-[525px]">
                                    <DialogHeader>
                                        <DialogTitle>Deploy New Server</DialogTitle>
                                        <DialogDescription>
                                            Add a new server to your infrastructure. We'll handle the provisioning automatically.
                                        </DialogDescription>
                                    </DialogHeader>
                                    <Form method="post" action={storeServer()} className="grid gap-4 py-4">
                                        {({ processing, errors }) => (
                                            <>
                                                <div className="grid gap-2">
                                                    <Label htmlFor="vanity_name">Server Name</Label>
                                                    <Input
                                                        id="vanity_name"
                                                        name="vanity_name"
                                                        defaultValue={defaultName}
                                                        placeholder="e.g., Production Web Server"
                                                        required
                                                        className="col-span-3"
                                                    />
                                                    <InputError className="mt-1" message={errors.vanity_name} />
                                                </div>
                                                <div className="grid grid-cols-2 gap-4">
                                                    <div className="grid gap-2">
                                                        <Label htmlFor="public_ip">IP Address</Label>
                                                        <Input id="public_ip" name="public_ip" placeholder="203.0.113.10" required />
                                                        <InputError className="mt-1" message={errors.public_ip} />
                                                    </div>
                                                    <div className="grid gap-2">
                                                        <Label htmlFor="ssh_port">SSH Port</Label>
                                                        <Input id="ssh_port" name="ssh_port" type="number" defaultValue="22" placeholder="22" required />
                                                        <InputError className="mt-1" message={errors.ssh_port} />
                                                    </div>
                                                </div>
                                                <div className="grid gap-2">
                                                    <Label htmlFor="php_version">PHP Version</Label>
                                                    <Select value={phpVersion} onValueChange={setPhpVersion}>
                                                        <SelectTrigger id="php_version">
                                                            <SelectValue placeholder="Select PHP version" />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem value="8.4">PHP 8.4 (Latest)</SelectItem>
                                                            <SelectItem value="8.3">PHP 8.3 (Stable)</SelectItem>
                                                            <SelectItem value="8.2">PHP 8.2</SelectItem>
                                                            <SelectItem value="8.1">PHP 8.1</SelectItem>
                                                            <SelectItem value="8.0">PHP 8.0</SelectItem>
                                                            <SelectItem value="7.4">PHP 7.4 (Legacy)</SelectItem>
                                                        </SelectContent>
                                                    </Select>
                                                    <input type="hidden" name="php_version" value={phpVersion} />
                                                    <InputError className="mt-1" message={errors.php_version} />
                                                </div>
                                                <div className="flex justify-end gap-3 pt-4">
                                                    <DialogTrigger asChild>
                                                        <Button variant="outline">Cancel</Button>
                                                    </DialogTrigger>
                                                    <Button type="submit" disabled={processing}>
                                                        {processing ? 'Deploying...' : 'Deploy Server'}
                                                    </Button>
                                                </div>
                                            </>
                                        )}
                                    </Form>
                                </DialogContent>
                            </Dialog>
                        </div>
                    </div>
                </div>

                {/* Statistics Cards */}
                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Total Servers</CardTitle>
                            <ServerIcon className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{stats.totalServers}</div>
                            <p className="text-xs text-muted-foreground mt-1">
                                {stats.activeServers} active, {stats.totalServers - stats.activeServers} inactive
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Active Servers</CardTitle>
                            <Activity className="h-4 w-4 text-green-600" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{stats.activeServers}</div>
                            <p className="text-xs text-muted-foreground mt-1">
                                {stats.totalServers > 0 ? Math.round((stats.activeServers / stats.totalServers) * 100) : 0}% online
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Pending Setup</CardTitle>
                            <Clock className="h-4 w-4 text-amber-600" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{stats.pendingServers}</div>
                            <p className="text-xs text-muted-foreground mt-1">
                                {stats.pendingServers === 1 ? 'Server' : 'Servers'} being provisioned
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Recent Activity</CardTitle>
                            <TrendingUp className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{stats.recentActivity}</div>
                            <p className="text-xs text-muted-foreground mt-1">
                                Events in last 24 hours
                            </p>
                        </CardContent>
                    </Card>
                </div>

                {/* Main Content Grid */}
                <div className="grid gap-6 lg:grid-cols-3">
                    {/* Servers Section - 2 columns wide */}
                    <div className="lg:col-span-2 space-y-6">
                        <Card>
                            <CardHeader>
                                <div className="flex items-center justify-between">
                                    <div>
                                        <CardTitle>Your Servers</CardTitle>
                                        <CardDescription>Manage and monitor your server infrastructure</CardDescription>
                                    </div>
                                    <Button variant="outline" size="sm" asChild>
                                        <Link href="/servers">
                                            View All
                                            <ArrowRight className="ml-2 size-4" />
                                        </Link>
                                    </Button>
                                </div>
                            </CardHeader>
                            <CardContent>
                                {servers && servers.length > 0 ? (
                                    <div className="space-y-4">
                                        {servers.slice(0, 3).map((s) => {
                                            const status = s.connection ?? 'pending';
                                            const provisionStatus = s.provision_status ?? 'pending';
                                            const serverUrl = provisionStatus === 'completed' ? showServer(s.id) : provisioningServer(s.id);

                                            const statusConfig = {
                                                connected: { color: 'text-green-600', bg: 'bg-green-500', label: 'Online', icon: Zap },
                                                failed: { color: 'text-red-600', bg: 'bg-red-500', label: 'Failed', icon: Activity },
                                                disconnected: { color: 'text-gray-600', bg: 'bg-gray-500', label: 'Offline', icon: Activity },
                                                pending: { color: 'text-amber-600', bg: 'bg-amber-500', label: 'Pending', icon: Clock },
                                            }[status] || { color: 'text-gray-600', bg: 'bg-gray-500', label: status, icon: Activity };

                                            return (
                                                <Link key={s.id} href={serverUrl} className="block group">
                                                    <div className="relative overflow-hidden rounded-lg border bg-card p-6 transition-all hover:shadow-md hover:border-primary/50">
                                                        <div className="flex items-start justify-between">
                                                            <div className="flex-1">
                                                                <div className="flex items-center gap-3 mb-2">
                                                                    <ServerIcon className="size-5 text-muted-foreground" />
                                                                    <h3 className="font-semibold text-base">{s.name}</h3>
                                                                </div>
                                                                <div className="space-y-1">
                                                                    <p className="text-sm text-muted-foreground">
                                                                        <span className="font-medium">IP:</span> {s.public_ip}:{s.ssh_port}
                                                                    </p>
                                                                    {s.private_ip && (
                                                                        <p className="text-sm text-muted-foreground">
                                                                            <span className="font-medium">Private:</span> {s.private_ip}
                                                                        </p>
                                                                    )}
                                                                </div>
                                                            </div>
                                                            <div className="flex flex-col items-end gap-2">
                                                                <div className={cn("flex items-center gap-2 px-2.5 py-1 rounded-full text-xs font-medium",
                                                                    status === 'connected' ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' :
                                                                    status === 'failed' ? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' :
                                                                    status === 'pending' ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400' :
                                                                    'bg-gray-100 text-gray-700 dark:bg-gray-900/30 dark:text-gray-400'
                                                                )}>
                                                                    <span className={cn("size-1.5 rounded-full", statusConfig.bg)} />
                                                                    {statusConfig.label}
                                                                </div>
                                                                <p className="text-xs text-muted-foreground">
                                                                    {getTimeAgo(s.created_at)}
                                                                </p>
                                                            </div>
                                                        </div>
                                                        <div className="absolute top-0 right-0 opacity-0 group-hover:opacity-100 transition-opacity p-2">
                                                            <ArrowRight className="size-4 text-muted-foreground" />
                                                        </div>
                                                    </div>
                                                </Link>
                                            );
                                        })}
                                    </div>
                                ) : (
                                    <div className="text-center py-12">
                                        <ServerIcon className="mx-auto size-12 text-muted-foreground/30" />
                                        <h3 className="mt-4 text-sm font-medium">No servers yet</h3>
                                        <p className="mt-1 text-sm text-muted-foreground">Get started by deploying your first server</p>
                                        <Dialog>
                                            <DialogTrigger asChild>
                                                <Button onClick={handleDialogOpen} className="mt-4" size="sm">
                                                    <Plus className="mr-2 size-4" />
                                                    Deploy Server
                                                </Button>
                                            </DialogTrigger>
                                        </Dialog>
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        {/* Quick Actions */}
                        <Card>
                            <CardHeader>
                                <CardTitle>Quick Actions</CardTitle>
                                <CardDescription>Common tasks and shortcuts</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="grid grid-cols-2 gap-3">
                                    <Button variant="outline" className="justify-start" asChild>
                                        <Link href="/sites">
                                            <Globe className="mr-2 size-4" />
                                            Manage Sites
                                        </Link>
                                    </Button>
                                    <Button variant="outline" className="justify-start" asChild>
                                        <Link href="/database">
                                            <FileText className="mr-2 size-4" />
                                            Databases
                                        </Link>
                                    </Button>
                                    <Button variant="outline" className="justify-start" asChild>
                                        <Link href="/settings">
                                            <Settings className="mr-2 size-4" />
                                            Settings
                                        </Link>
                                    </Button>
                                    <Button variant="outline" className="justify-start" asChild>
                                        <Link href="/docs">
                                            <Rocket className="mr-2 size-4" />
                                            Docs
                                        </Link>
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    {/* Right Column - Activity Feed */}
                    <div className="space-y-6">
                        <Card className="h-fit">
                            <CardHeader>
                                <div className="flex items-center justify-between">
                                    <div>
                                        <CardTitle>Recent Activity</CardTitle>
                                        <CardDescription>Latest events and changes</CardDescription>
                                    </div>
                                    <Button variant="ghost" size="sm">
                                        <Clock className="size-4" />
                                    </Button>
                                </div>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-4 max-h-[500px] overflow-y-auto">
                                    {activities && activities.length > 0 ? (
                                        activities.slice(0, 10).map((activity) => {
                                            const activityIcons: Record<string, any> = {
                                                'server': ServerIcon,
                                                'site': Globe,
                                                'database': FileText,
                                                'user': Users,
                                                'default': Activity
                                            };
                                            const Icon = activityIcons[activity.type] || activityIcons.default;

                                            return (
                                                <div key={activity.id} className="flex gap-3 pb-3 last:pb-0 border-b last:border-0">
                                                    <div className="flex-shrink-0 mt-0.5">
                                                        <div className="size-8 rounded-full bg-muted flex items-center justify-center">
                                                            <Icon className="size-4 text-muted-foreground" />
                                                        </div>
                                                    </div>
                                                    <div className="flex-1 min-w-0">
                                                        <p className="text-sm font-medium leading-tight">
                                                            {activity.label}
                                                        </p>
                                                        {activity.detail && (
                                                            <p className="text-xs text-muted-foreground mt-1">
                                                                {activity.detail}
                                                            </p>
                                                        )}
                                                        <p className="text-xs text-muted-foreground mt-1">
                                                            {getTimeAgo(activity.created_at)}
                                                        </p>
                                                    </div>
                                                </div>
                                            );
                                        })
                                    ) : (
                                        <div className="text-center py-8">
                                            <Activity className="mx-auto size-8 text-muted-foreground/30" />
                                            <p className="mt-2 text-sm text-muted-foreground">No recent activity</p>
                                            <p className="text-xs text-muted-foreground mt-1">Activity will appear here as you use the platform</p>
                                        </div>
                                    )}
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
