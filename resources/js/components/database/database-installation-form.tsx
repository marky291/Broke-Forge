import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Form } from '@inertiajs/react';
import { DatabaseIcon } from 'lucide-react';

interface DatabaseType {
    name: string;
    description: string;
    versions: Record<string, string>;
    default_version: string;
    default_port: number;
}

interface DatabaseInstallationFormProps {
    serverId: number;
    availableTypes: Record<string, DatabaseType>;
}

export default function DatabaseInstallationForm({ serverId, availableTypes }: DatabaseInstallationFormProps) {
    return (
        <div className="rounded-xl border border-sidebar-border/70 bg-background shadow-sm dark:border-sidebar-border">
            <div className="px-4 py-3">
                <div className="flex items-center gap-2">
                    <DatabaseIcon className="h-5 w-5 text-blue-600" />
                    <div className="text-sm font-medium tracking-wide text-neutral-600 uppercase dark:text-neutral-300">Install Database Service</div>
                </div>
            </div>

            <Form method="post" action={`/servers/${serverId}/database`} resetOnSuccess={['root_password']} className="px-4 py-4">
                {({ processing, errors, data, setData }) => (
                    <div className="space-y-6">
                        {/* Database Type Selection */}
                        <div className="space-y-4">
                            <h3 className="font-medium">Database Type</h3>
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                                {Object.entries(availableTypes).map(([type, config]) => (
                                    <div
                                        key={type}
                                        className={`relative cursor-pointer rounded-lg border-2 p-4 transition-all ${
                                            data.type === type
                                                ? 'border-primary bg-primary/5'
                                                : 'border-sidebar-border/70 bg-background hover:border-primary/50'
                                        } ${processing ? 'opacity-75' : ''}`}
                                        onClick={() =>
                                            !processing &&
                                            setData({
                                                ...data,
                                                type,
                                                version: config.default_version,
                                                port: config.default_port,
                                            })
                                        }
                                    >
                                        <div className="flex items-start gap-3">
                                            <div
                                                className={`flex-shrink-0 rounded-md p-2 ${
                                                    data.type === type ? 'bg-primary text-primary-foreground' : 'bg-muted'
                                                }`}
                                            >
                                                <DatabaseIcon className="h-6 w-6" />
                                            </div>
                                            <div className="min-w-0 flex-1">
                                                <h3 className="font-medium">{config.name}</h3>
                                                <p className="mt-1 text-sm text-muted-foreground">{config.description}</p>
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>
                            {errors.type && <div className="text-sm text-destructive">{errors.type}</div>}
                        </div>

                        {/* Configuration */}
                        <div className="space-y-4">
                            <h3 className="font-medium">Configuration</h3>
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div className="space-y-2 md:col-span-2">
                                    <Label htmlFor="name">
                                        Database Name <span className="text-destructive">*</span>
                                    </Label>
                                    <Input
                                        id="name"
                                        name="name"
                                        type="text"
                                        value={data.name || ''}
                                        onChange={(e) => setData('name', e.target.value)}
                                        placeholder="my_database"
                                        disabled={processing}
                                        required
                                        pattern="[a-zA-Z0-9_-]+"
                                    />
                                    <p className="text-xs text-muted-foreground">Only letters, numbers, hyphens, and underscores (no spaces)</p>
                                    {errors.name && <div className="text-sm text-destructive">{errors.name}</div>}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="version">Version</Label>
                                    <Select value={data.version || ''} onValueChange={(value) => setData('version', value)} disabled={processing}>
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select version" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {data.type &&
                                                Object.entries(availableTypes[data.type]?.versions || {}).map(([value, label]) => (
                                                    <SelectItem key={value} value={value}>
                                                        {label}
                                                    </SelectItem>
                                                ))}
                                        </SelectContent>
                                    </Select>
                                    {errors.version && <div className="text-sm text-destructive">{errors.version}</div>}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="port">Port</Label>
                                    <Input
                                        id="port"
                                        name="port"
                                        type="number"
                                        value={data.port || ''}
                                        onChange={(e) => setData('port', parseInt(e.target.value))}
                                        placeholder="Default port"
                                        disabled={processing}
                                    />
                                    {errors.port && <div className="text-sm text-destructive">{errors.port}</div>}
                                </div>

                                <div className="space-y-2 md:col-span-2">
                                    <Label htmlFor="root_password">
                                        Root Password <span className="text-destructive">*</span>
                                    </Label>
                                    <Input
                                        id="root_password"
                                        name="root_password"
                                        type="password"
                                        value={data.root_password || ''}
                                        onChange={(e) => setData('root_password', e.target.value)}
                                        placeholder="Enter secure password"
                                        disabled={processing}
                                        required
                                    />
                                    {errors.root_password && <div className="text-sm text-destructive">{errors.root_password}</div>}
                                </div>
                            </div>
                        </div>

                        {/* Submit Button */}
                        <div className="flex justify-end">
                            <Button type="submit" disabled={processing || !data.type || !data.name}>
                                {processing ? 'Installing...' : 'Install Database'}
                            </Button>
                        </div>
                    </div>
                )}
            </Form>
        </div>
    );
}
