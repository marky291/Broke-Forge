import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { CardContainer } from '@/components/ui/card-container';
import { Input } from '@/components/ui/input';
import { PageHeader } from '@/components/ui/page-header';
import SiteLayout from '@/layouts/server/site-layout';
import { dashboard } from '@/routes';
import { show as showServer } from '@/routes/servers';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { AlertCircle, CheckCircle2, Clock, Loader2, Terminal, XCircle } from 'lucide-react';

type Server = {
    id: number;
    vanity_name: string;
    connection: string;
};

/**
 * Represents a site hosted on a server.
 * Maps to the ServerSite model on the backend.
 */
type ServerSite = {
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
    site: ServerSite;
    executionContext: ExecutionContext;
    commandResult?: CommandResult;
}) {
    const { data, setData, post, processing, errors, reset } = useForm({
        command: '',
    });

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: dashboard.url() },
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
    const formattedTimeout =
        executionContext.timeout % 60 === 0
            ? `${timeoutInMinutes} minute${timeoutInMinutes === 1 ? '' : 's'}`
            : `${executionContext.timeout} seconds`;

    const hasCommand = data.command.trim().length > 0;
    const result = commandResult ?? null;
    const resultExecutedAt = result ? new Date(result.ranAt).toLocaleString() : null;
    const resultDurationSeconds = result ? (result.durationMs / 1000).toFixed(2) : null;

    return (
        <SiteLayout server={server} site={site} breadcrumbs={breadcrumbs}>
            <Head title={`Commands — ${site.domain}`} />
            <PageHeader
                title="Commands"
                description="Execute ad-hoc commands on your server within the site's context."
            >
                <CardContainer title="Execute Command">
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
                                {errors.command && <p className="mt-2 text-sm text-destructive">{errors.command}</p>}
                            </div>
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
                </CardContainer>

                {result && (
                    <CardContainer
                        title={
                            <div className="flex items-center justify-between">
                                <span className="flex items-center gap-2">
                                    <Terminal className="h-5 w-5" />
                                    Command Output
                                </span>
                                <Badge variant={result.success ? 'default' : 'destructive'}>
                                    {result.success ? (
                                        <span className="inline-flex items-center gap-1">
                                            <CheckCircle2 className="h-3 w-3" />
                                            Success
                                        </span>
                                    ) : (
                                        <span className="inline-flex items-center gap-1">
                                            <XCircle className="h-3 w-3" />
                                            Failed
                                        </span>
                                    )}
                                </Badge>
                            </div>
                        }
                    >
                        <div className="space-y-4">
                            <div className="flex items-center justify-between text-sm text-muted-foreground">
                                <span className="inline-flex items-center gap-2">
                                    <Clock className="h-4 w-4" />
                                    Executed at {resultExecutedAt}
                                </span>
                                <span>Duration: {resultDurationSeconds}s</span>
                            </div>

                            <div>
                                <div className="mb-2 text-sm font-medium text-muted-foreground">Command</div>
                                <div className="rounded-md bg-muted p-3 font-mono text-sm">{result.command}</div>
                            </div>

                            {result.output && (
                                <div>
                                    <div className="mb-2 text-sm font-medium text-muted-foreground">Output</div>
                                    <pre className="overflow-x-auto rounded-md bg-muted p-3 font-mono text-sm whitespace-pre-wrap">
                                        {result.output}
                                    </pre>
                                </div>
                            )}

                            {result.errorOutput && (
                                <div>
                                    <div className="mb-2 text-sm font-medium text-destructive">Error Output</div>
                                    <pre className="overflow-x-auto rounded-md bg-destructive/10 p-3 font-mono text-sm text-destructive whitespace-pre-wrap">
                                        {result.errorOutput}
                                    </pre>
                                </div>
                            )}

                            <div className="flex items-center gap-4 text-sm text-muted-foreground">
                                <span>Exit Code: <span className="font-mono">{result.exitCode ?? 'N/A'}</span></span>
                            </div>
                        </div>
                    </CardContainer>
                )}

                <Alert>
                    <AlertCircle className="h-4 w-4" />
                    <AlertTitle>Execution Context</AlertTitle>
                    <AlertDescription>
                        Commands run as <code className="rounded bg-muted px-1 py-0.5">{executionContext.user}</code> in{' '}
                        <code className="rounded bg-muted px-1 py-0.5">{executionContext.workingDirectory}</code> with a{' '}
                        {formattedTimeout} timeout.
                    </AlertDescription>
                </Alert>
            </PageHeader>
        </SiteLayout>
    );
}
