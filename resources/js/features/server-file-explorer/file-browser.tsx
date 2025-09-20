import { useMemo, useRef, type ChangeEvent } from 'react';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Breadcrumb, BreadcrumbItem, BreadcrumbLink, BreadcrumbList, BreadcrumbPage, BreadcrumbSeparator } from '@/components/ui/breadcrumb';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { cn } from '@/lib/utils';
import { Download, FileText, Folder, FolderOpen, Loader2, RefreshCw, Upload } from 'lucide-react';
import type { FileBrowserState, FileItem } from './types';

const formatBytes = (size: number | null): string => {
    if (size === null || Number.isNaN(size)) {
        return '—';
    }

    if (size === 0) {
        return '0 B';
    }

    const base = Math.log(size) / Math.log(1024);
    const unitIndex = Math.floor(base);
    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    const value = size / Math.pow(1024, unitIndex);

    return `${value.toFixed(value >= 10 || unitIndex === 0 ? 0 : 1)} ${units[unitIndex]}`;
};

const formatTimestamp = (timestamp: string): string => {
    const date = new Date(timestamp);

    if (Number.isNaN(date.getTime())) {
        return '—';
    }

    return date.toLocaleString();
};

type ServerFileBrowserProps = {
    state: FileBrowserState;
    onNavigate: (path: string) => void;
    onNavigateUp: () => void;
    onRefresh: () => void;
    onUpload: (file: File) => void;
    onDownload: (file: FileItem) => void;
    onDismissError: () => void;
};

export const ServerFileBrowser = ({
    state,
    onNavigate,
    onNavigateUp,
    onRefresh,
    onUpload,
    onDownload,
    onDismissError,
}: ServerFileBrowserProps) => {
    const fileInputRef = useRef<HTMLInputElement | null>(null);

    const breadcrumbSegments = useMemo(() => {
        if (!state.currentPath) {
            return [] as Array<{ label: string; path: string }>;
        }

        const segments = state.currentPath.split('/').filter(Boolean);

        return segments.map((segment, index) => ({
            label: segment,
            path: segments.slice(0, index + 1).join('/'),
        }));
    }, [state.currentPath]);

    const handleUploadClick = () => {
        fileInputRef.current?.click();
    };

    const handleUploadChange = (event: ChangeEvent<HTMLInputElement>) => {
        const file = event.target.files?.[0];
        event.target.value = '';

        if (!file) {
            return;
        }

        onUpload(file);
    };

    return (
        <div className="space-y-4">
            <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 className="text-2xl font-semibold">File Explorer</h2>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Browse the server&rsquo;s application directory, upload assets, or download existing files.
                    </p>
                </div>
                <div className="flex items-center gap-2">
                    <Button variant="outline" size="sm" onClick={onNavigateUp} disabled={!state.currentPath || state.loading}>
                        Up
                    </Button>
                    <Button variant="outline" size="sm" onClick={onRefresh} disabled={state.loading}>
                        <RefreshCw className="mr-1 h-4 w-4" /> Refresh
                    </Button>
                    <input ref={fileInputRef} type="file" className="hidden" onChange={handleUploadChange} />
                    <Button size="sm" onClick={handleUploadClick} disabled={state.uploading}>
                        {state.uploading ? (
                            <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                        ) : (
                            <Upload className="mr-2 h-4 w-4" />
                        )}
                        Upload
                    </Button>
                </div>
            </div>

            <Breadcrumb>
                <BreadcrumbList>
                    <BreadcrumbItem>
                        {state.currentPath ? (
                            <BreadcrumbLink asChild>
                                <button
                                    type="button"
                                    onClick={() => onNavigate('')}
                                    className="text-sm font-medium text-foreground/80 hover:text-foreground"
                                >
                                    Home
                                </button>
                            </BreadcrumbLink>
                        ) : (
                            <BreadcrumbPage>Home</BreadcrumbPage>
                        )}
                    </BreadcrumbItem>

                    {breadcrumbSegments.map((segment, index) => (
                        <BreadcrumbItem key={segment.path}>
                            <BreadcrumbSeparator />
                            {index === breadcrumbSegments.length - 1 ? (
                                <BreadcrumbPage>{segment.label}</BreadcrumbPage>
                            ) : (
                                <BreadcrumbLink asChild>
                                    <button
                                        type="button"
                                        onClick={() => onNavigate(segment.path)}
                                        className="text-sm font-medium text-foreground/80 hover:text-foreground"
                                    >
                                        {segment.label}
                                    </button>
                                </BreadcrumbLink>
                            )}
                        </BreadcrumbItem>
                    ))}
                </BreadcrumbList>
            </Breadcrumb>

            {state.error && (
                <Alert variant="destructive" role="alert">
                    <AlertDescription className="flex items-center justify-between gap-4">
                        <span>{state.error}</span>
                        <Button variant="ghost" size="sm" onClick={onDismissError}>
                            Dismiss
                        </Button>
                    </AlertDescription>
                </Alert>
            )}

            <Card>
                <CardHeader className="flex items-center justify-between">
                    <CardTitle className="text-base font-medium">{state.currentPath || 'Home'}</CardTitle>
                </CardHeader>
                <Separator />
                <CardContent className="p-0">
                    <div className="relative">
                        {state.loading && (
                            <div className="absolute inset-0 z-10 flex items-center justify-center bg-background/70">
                                <Loader2 className="h-6 w-6 animate-spin" />
                            </div>
                        )}

                        {state.items.length === 0 && !state.loading ? (
                            <div className="flex h-56 flex-col items-center justify-center gap-2 px-4 text-center text-sm text-muted-foreground">
                                <FolderOpen className="h-10 w-10 text-muted-foreground/60" />
                                <p>This directory is empty.</p>
                            </div>
                        ) : (
                            <div className="max-h-[480px] overflow-y-auto">
                                {state.items.map((item) => (
                                    <div
                                        key={item.path}
                                        className={cn(
                                            'flex items-center justify-between gap-3 px-4 py-3 transition-colors',
                                            'border-b last:border-b-0 border-border/60'
                                        )}
                                    >
                                        <button
                                            type="button"
                                            onClick={() => {
                                                if (item.type === 'directory') {
                                                    onNavigate(item.path);
                                                }
                                            }}
                                            className={cn(
                                                'flex flex-1 items-center gap-3 text-left focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring',
                                                item.type === 'directory' ? 'hover:text-foreground' : ''
                                            )}
                                        >
                                            {item.type === 'directory' ? (
                                                <Folder className="h-5 w-5 text-blue-500" />
                                            ) : (
                                                <FileText className="h-5 w-5 text-muted-foreground" />
                                            )}
                                            <div className="min-w-0">
                                                <div className="truncate text-sm font-medium text-foreground">{item.name}</div>
                                                <div className="text-xs text-muted-foreground">
                                                    {item.type === 'directory'
                                                        ? 'Directory'
                                                        : `${formatBytes(item.size)} • ${item.permissions}`}
                                                </div>
                                            </div>
                                        </button>

                                        <div className="flex items-center gap-3 text-xs text-muted-foreground">
                                            <span>{formatTimestamp(item.modifiedAt)}</span>
                                            {item.type === 'file' && (
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    onClick={() => onDownload(item)}
                                                    title="Download"
                                                >
                                                    <Download className="h-4 w-4" />
                                                </Button>
                                            )}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                </CardContent>
            </Card>
        </div>
    );
};

export default ServerFileBrowser;
