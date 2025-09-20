import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Separator } from '@/components/ui/separator';
import ServerLayout from '@/layouts/server/layout';
import { dashboard } from '@/routes';
import { show as showServer } from '@/routes/servers';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { AlertCircle, CheckCircle2, Clock, Loader2, Terminal, XCircle } from 'lucide-react';

type Server = {
    id: number;
    vanity_name: string;
    connection: string;
    ssh_app_user?: string | null;
};

type Site = {
    id: number;
    domain: string;
    document_root: string | null;
    status: string;
};

type ExecutionContext = {
    workingDirectory: string;
    user: string | null;
    timeout: number;
};

type CommandResult = {
    command: string;
    output: string;
    errorOutput: string;
    exitCode: number | null;
    ranAt: string;
    durationMs: number;
    success: boolean;
} | null;

export default function SiteCommands({
    server,
    site,
    executionContext,
    commandResult,
}: {
    server: Server;
    site: Site;
    executionContext: ExecutionContext;
    commandResult?: CommandResult;
}) {
    const { data, setData, post, processing, errors, reset } = useForm({
        command: '',
    });

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: dashboard().url },
        { title: `Server #${server.id}`, href: showServer(server.id).url },
        { title: 'Sites', href: `/servers/${server.id}/sites` },
        { title: site.domain, href: `/servers/${server.id}/sites/${site.id}` },
        { title: 'Commands', href: '#' },
    ];

    const handleSubmit = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        post(`/servers/${server.id}/sites/${site.id}/commands`, {
            preserveScroll: true,
            onSuccess: () => {
                reset('command');
            },
        });
    };

    const timeoutInMinutes = executionContext.timeout / 60;
    const formattedTimeout = executionContext.timeout % 60 === 0
        ? `${timeoutInMinutes} minute${timeoutInMinutes === 1 ? '' : 's'}`
        : `${executionContext.timeout} seconds`;

    const hasCommand = data.command.trim().length > 0;
    const result = commandResult ?? null;
    const resultExecutedAt = result ? new Date(result.ranAt).toLocaleString() : null;
    const resultDurationSeconds = result ? (result.durationMs / 1000).toFixed(2) : null;

    return (
        <ServerLayout server={server} site={site} breadcrumbs={breadcrumbs}>
            <Head title={`Commands — ${site.domain}`} />
            <div className="space-y-6">
                <div className="space-y-1">
                    <h1 className="text-2xl font-semibold">Commands</h1>
                    <p className="text-sm text-muted-foreground">
                        Run ad-hoc commands for <span className="font-medium">{site.domain}</span> directly from BrokeForge.
                    </p>
                </div>

                <Alert>
                    <Terminal className="h-4 w-4" />
                    <AlertTitle>Shell access, simplified</AlertTitle>
                    <AlertDescription>
                        BrokeForge allows you to execute arbitrary commands inside the site root. Commands run as
                        <code className="mx-1 rounded bg-muted px-1.5 py-0.5 text-xs font-medium">
                            {executionContext.user ?? 'ssh_app_user'}
                        </code>
                        from
                        <code className="mx-1 rounded bg-muted px-1.5 py-0.5 text-xs font-medium">
                            {executionContext.workingDirectory}
                        </code>
                        and timeout after {formattedTimeout}. Use with caution—these commands have full access to your site files.
                    </AlertDescription>
                </Alert>

                <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,0.75fr)]">
                    <Card>
                        <CardHeader>
                            <CardTitle>Execute Command</CardTitle>
                        </CardHeader>
                        <Separator />
                        <CardContent className="space-y-4">
                            <form onSubmit={handleSubmit} className="space-y-4">
                                <div>
                                    <label htmlFor="command" className="text-sm font-medium text-muted-foreground">
                                        Command
                                    </label>
                                    <Input
                                        id="command"
                                        value={data.command}
                                        onChange={(event) => setData('command', event.target.value)}
                                        placeholder="e.g. php artisan migrate"
                                        className="mt-2"
                                        name="command"
                                    />
                                    {errors.command && (
                                        <p className="mt-2 text-sm text-destructive">{errors.command}</p>
                                    )}
                                </div>
                                <Alert variant="destructive">
                                    <AlertCircle className="h-4 w-4" />
                                    <AlertTitle>Proceed carefully</AlertTitle>
                                    <AlertDescription>
                                        Commands are executed on your production server. Double-check before running destructive actions.
                                    </AlertDescription>
                                </Alert>
                                <Button type="submit" disabled={!hasCommand || processing}>
                                    {processing ? (
                                        <span className="inline-flex items-center gap-2">
                                            <Loader2 className="h-4 w-4 animate-spin" />
                                            Running...
                                        </span>
                                    ) : (
                                        'Run Command'
                                    )}
                                </Button>
                            </form>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Execution Context</CardTitle>
                        </CardHeader>
                        <Separator />
                        <CardContent className="space-y-4 text-sm">
                            <div className="flex items-start justify-between rounded-lg border border-sidebar-border/70 bg-background p-4">
                                <div>
                                    <div className="font-medium">Working directory</div>
                                    <div className="text-muted-foreground">{executionContext.workingDirectory}</div>
                                </div>
                            </div>
                            <div className="flex items-start justify-between rounded-lg border border-sidebar-border/70 bg-background p-4">
                                <div>
                                    <div className="font-medium">SSH user</div>
                                    <div className="text-muted-foreground">{executionContext.user ?? 'Not configured'}</div>
                                </div>
                            </div>
                            <div className="flex items-start justify-between rounded-lg border border-sidebar-border/70 bg-background p-4">
                                <div>
                                    <div className="font-medium">Timeout</div>
                                    <div className="flex items-center gap-2 text-muted-foreground">
                                        <Clock className="h-4 w-4" />
                                        {formattedTimeout}
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {result && (
                    <Card>
                        <CardHeader>
                            <div className="flex items-center justify-between">
                                <CardTitle>Last Command</CardTitle>
                                <Badge variant={result.success ? 'default' : 'destructive'} className="flex items-center gap-1">
                                    {result.success ? (
                                        <>
                                            <CheckCircle2 className="h-3.5 w-3.5" />
                                            Success
                                        </>
                                    ) : (
                                        <>
                                            <XCircle className="h-3.5 w-3.5" />
                                            Failed
                                        </>
                                    )}
                                </Badge>
                            </div>
                        </CardHeader>
                        <Separator />
                        <CardContent className="space-y-4 text-sm">
                            <div className="grid gap-2">
                                <div className="text-muted-foreground">Command</div>
                                <code className="rounded bg-muted px-2 py-1 text-sm">{result.command}</code>
                            </div>
                            <div className="grid gap-2">
                                <div className="text-muted-foreground">Executed</div>
                                <div>{resultExecutedAt}</div>
                            </div>
                            <div className="grid gap-2">
                                <div className="text-muted-foreground">Duration</div>
                                <div>{resultDurationSeconds ? `${resultDurationSeconds} seconds` : '—'}</div>
                            </div>
                            <div className="grid gap-2">
                                <div className="text-muted-foreground">Exit Code</div>
                                <div>{result.exitCode ?? 'N/A'}</div>
                            </div>
                            <div className="grid gap-2">
                                <div className="text-muted-foreground">Output</div>
                                <pre className="overflow-auto rounded-lg bg-muted px-4 py-3 font-mono text-sm leading-relaxed whitespace-pre-wrap">
                                    {result.output || '—'}
                                </pre>
                            </div>
                            {result.errorOutput && (
                                <div className="grid gap-2">
                                    <div className="text-muted-foreground">Error Output</div>
                                    <pre className="overflow-auto rounded-lg bg-destructive/10 px-4 py-3 font-mono text-sm leading-relaxed text-destructive whitespace-pre-wrap">
                                        {result.errorOutput}
                                    </pre>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                )}
            </div>
        </ServerLayout>
    );
}
