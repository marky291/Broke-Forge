import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { CardContainer } from '@/components/ui/card-container';
import { Input } from '@/components/ui/input';
import { PageHeader } from '@/components/ui/page-header';
import { TablePagination } from '@/components/ui/table-pagination';
import SiteLayout from '@/layouts/server/site-layout';
import { dashboard } from '@/routes';
import { show as showServer } from '@/routes/servers';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { AlertCircle, CheckCircle2, Clock, Loader2, Terminal, XCircle } from 'lucide-react';
import { useState } from 'react';

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
    id?: number;
    command: string;
    output: string;
    errorOutput: string;
    exitCode: number | null;
    ranAt: string;
    durationMs: number;
    success: boolean;
} | null;

type CommandHistoryItem = {
    id: number;
    command: string;
    output: string;
    errorOutput: string;
    exitCode: number | null;
    ranAt: string;
    durationMs: number;
    success: boolean;
};

type PaginatedCommandHistory = {
    data: CommandHistoryItem[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
};

export default function SiteCommands({
    server,
    site,
    executionContext,
    commandResult,
    commandHistory,
}: {
    server: Server;
    site: ServerSite;
    executionContext: ExecutionContext;
    commandResult?: CommandResult;
    commandHistory: PaginatedCommandHistory;
}) {
    const { data, setData, post, processing, errors, reset } = useForm({
        command: '',
    });
    const [isRerunning, setIsRerunning] = useState(false);

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

    const handleRerunCommand = (command: string) => {
        setIsRerunning(true);
        router.post(
            `/servers/${server.id}/sites/${site.id}/commands`,
            { command },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setIsRerunning(false);
                },
                onError: () => {
                    setIsRerunning(false);
                },
            }
        );
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
                            <span className="flex items-center gap-2">
                                <Terminal className="h-5 w-5" />
                                Command Output
                            </span>
                        }
                        action={
                            <Badge
                                variant="outline"
                                className={
                                    result.success
                                        ? 'flex items-center gap-1 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20'
                                        : 'flex items-center gap-1 bg-red-500/10 text-red-600 dark:text-red-400 border-red-500/20'
                                }
                            >
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

                {commandHistory.data.length > 0 && (
                    <CardContainer title="Command History">
                        <div>
                            <div className="divide-y divide-border">
                                {commandHistory.data.map((history) => {
                                    const executedAt = new Date(history.ranAt).toLocaleString();
                                    const durationSeconds = (history.durationMs / 1000).toFixed(2);

                                    return (
                                        <div key={history.id} className="py-4 first:pt-0">
                                            <div className="space-y-2">
                                                <div className="flex items-center justify-between gap-4">
                                                    <div className="flex items-center gap-2">
                                                        <Badge
                                                            variant="outline"
                                                            className={
                                                                history.success
                                                                    ? 'flex items-center gap-1 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20'
                                                                    : 'flex items-center gap-1 bg-red-500/10 text-red-600 dark:text-red-400 border-red-500/20'
                                                            }
                                                        >
                                                            {history.success ? (
                                                                <>
                                                                    <CheckCircle2 className="h-3 w-3" />
                                                                    Success
                                                                </>
                                                            ) : (
                                                                <>
                                                                    <XCircle className="h-3 w-3" />
                                                                    Failed
                                                                </>
                                                            )}
                                                        </Badge>
                                                        <span className="text-sm text-muted-foreground">{executedAt}</span>
                                                        <span className="text-sm text-muted-foreground">•</span>
                                                        <span className="text-sm text-muted-foreground">{durationSeconds}s</span>
                                                    </div>
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() => handleRerunCommand(history.command)}
                                                        disabled={processing || isRerunning}
                                                    >
                                                        {isRerunning ? (
                                                            <span className="inline-flex items-center gap-2">
                                                                <Loader2 className="h-3 w-3 animate-spin" />
                                                                Running...
                                                            </span>
                                                        ) : (
                                                            'Rerun'
                                                        )}
                                                    </Button>
                                                </div>
                                                <div className="rounded-md bg-muted p-2 font-mono text-sm">
                                                    {history.command}
                                                </div>
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                            {commandHistory.last_page > 1 && (
                                <TablePagination
                                    currentPage={commandHistory.current_page}
                                    totalPages={commandHistory.last_page}
                                    totalItems={commandHistory.total}
                                    perPage={commandHistory.per_page}
                                    onPageChange={(page) =>
                                        router.get(
                                            `/servers/${server.id}/sites/${site.id}/commands`,
                                            { page },
                                            { preserveState: true, preserveScroll: true }
                                        )
                                    }
                                />
                            )}
                        </div>
                    </CardContainer>
                )}
            </PageHeader>
        </SiteLayout>
    );
}
