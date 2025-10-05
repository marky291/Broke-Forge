import InputError from '@/components/input-error';
import { ServerProviderIcon, getAllProviders, type ServerProvider } from '@/components/server-provider-icon';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { store as storeServer } from '@/routes/servers';
import { Form } from '@inertiajs/react';
import { useState, ReactNode } from 'react';

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

interface DeployServerModalProps {
    trigger: ReactNode;
}

export default function DeployServerModal({ trigger }: DeployServerModalProps) {
    const [defaultName, setDefaultName] = useState<string>('');
    const [phpVersion, setPhpVersion] = useState<string>('8.3');
    const [provider, setProvider] = useState<ServerProvider>('custom');
    const [open, setOpen] = useState(false);

    const providers = getAllProviders();

    const handleDialogOpen = () => {
        setDefaultName(generateFriendlyName());
        setPhpVersion('8.3');
        setProvider('custom');
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild onClick={handleDialogOpen}>
                {trigger}
            </DialogTrigger>
            <DialogContent className="sm:max-w-[525px]">
                <div className="flex items-start gap-4">
                    <div className="flex-shrink-0 mt-1">
                        <ServerProviderIcon provider={provider} size="lg" />
                    </div>
                    <DialogHeader className="flex-1">
                        <DialogTitle>Deploy New Server</DialogTitle>
                        <DialogDescription>
                            Add a new server to your infrastructure. We'll handle the provisioning automatically.
                        </DialogDescription>
                    </DialogHeader>
                </div>
                <Form method="post" action={storeServer()} className="grid gap-4 py-4">
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="provider">Server Host</Label>
                                <Select value={provider || 'custom'} onValueChange={(value) => setProvider(value as ServerProvider)}>
                                    <SelectTrigger id="provider">
                                        <SelectValue placeholder="Select hosting provider" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {providers.map(({ value, label }) => (
                                            <SelectItem key={value} value={value}>
                                                <div className="flex items-center gap-2">
                                                    <ServerProviderIcon provider={value as ServerProvider} size="sm" />
                                                    <span>{label}</span>
                                                </div>
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <input type="hidden" name="provider" value={provider || ''} />
                                <InputError className="mt-1" message={errors.provider} />
                            </div>

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
                                <Button variant="outline" onClick={() => setOpen(false)}>
                                    Cancel
                                </Button>
                                <Button type="submit" disabled={processing}>
                                    {processing ? 'Deploying...' : 'Deploy Server'}
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}