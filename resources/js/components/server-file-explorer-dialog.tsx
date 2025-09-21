import { Alert, AlertDescription } from '@/components/ui/alert';
import { Breadcrumb, BreadcrumbItem, BreadcrumbLink, BreadcrumbList, BreadcrumbPage, BreadcrumbSeparator } from '@/components/ui/breadcrumb';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { cn } from '@/lib/utils';
import { Download, FileText, Folder, FolderOpen, Loader2, RefreshCw, Upload } from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState, type ChangeEvent } from 'react';

const getCsrfToken = (): string | null => {
    const match = document.cookie.split('; ').find((row) => row.startsWith('XSRF-TOKEN='));

    if (!match) {
        return null;
    }

    const value = match.split('=')[1];

    try {
        return decodeURIComponent(value);
    } catch (error) {
        return null;
    }
};

type FileItem = {
    name: string;
    path: string;
    type: 'file' | 'directory';
    size: number | null;
    modifiedAt: string;
    permissions: string;
};

type ServerFileExplorerDialogProps = {
    serverId: number;
    open: boolean;
    onOpenChange: (next: boolean) => void;
};

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

const ServerFileExplorerDialog = ({ serverId, open, onOpenChange }: ServerFileExplorerDialogProps) => {
    const [items, setItems] = useState<FileItem[]>([]);
    const [currentPath, setCurrentPath] = useState('');
    const [loading, setLoading] = useState(false);
    const [uploading, setUploading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const abortController = useRef<AbortController | null>(null);
    const fileInputRef = useRef<HTMLInputElement | null>(null);

    const loadDirectory = useCallback(
        async (path: string) => {
            if (abortController.current) {
                abortController.current.abort();
            }

            const controller = new AbortController();
            abortController.current = controller;

            setLoading(true);
            setError(null);

            const query = path ? `?path=${encodeURIComponent(path)}` : '';

            try {
                const response = await fetch(`/servers/${serverId}/files${query}`, {
                    credentials: 'same-origin',
                    signal: controller.signal,
                    headers: {
                        Accept: 'application/json',
                    },
                });

                const payload = await response.json().catch(() => null);

                if (!response.ok) {
                    const message = payload?.message ?? 'Failed to load directory contents.';

                    throw new Error(message);
                }

                if (!payload || typeof payload !== 'object') {
                    throw new Error('Unexpected response while loading directory contents.');
                }

                const items = Array.isArray(payload.items) ? (payload.items as FileItem[]) : [];
                const finalPath = typeof payload.path === 'string' ? payload.path : path;

                setItems(items);
                setCurrentPath(finalPath);
            } catch (requestError) {
                if ((requestError as Error).name === 'AbortError') {
                    return;
                }

                setError((requestError as Error).message);
            } finally {
                if (!controller.signal.aborted) {
                    setLoading(false);
                }
            }
        },
        [serverId],
    );

    const handleRefresh = useCallback(() => {
        void loadDirectory(currentPath);
    }, [currentPath, loadDirectory]);

    const handleNavigateUp = useCallback(() => {
        if (!currentPath) {
            return;
        }

        const segments = currentPath.split('/').filter(Boolean);
        segments.pop();
        const nextPath = segments.join('/');

        void loadDirectory(nextPath);
    }, [currentPath, loadDirectory]);

    const handleUploadClick = useCallback(() => {
        fileInputRef.current?.click();
    }, []);

    const handleUpload = useCallback(
        async (event: ChangeEvent<HTMLInputElement>) => {
            const file = event.target.files?.[0];
            event.target.value = '';

            if (!file) {
                return;
            }

            const csrfToken = getCsrfToken();

            if (!csrfToken) {
                setError('Unable to upload because the CSRF token could not be determined.');

                return;
            }

            const formData = new FormData();
            formData.append('file', file);

            if (currentPath) {
                formData.append('path', currentPath);
            }

            setUploading(true);
            setError(null);

            try {
                const response = await fetch(`/servers/${serverId}/files/upload`, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        Accept: 'application/json',
                    },
                    body: formData,
                });

                const payload = await response.json().catch(() => null);

                if (!response.ok) {
                    const message = payload?.message ?? 'File upload failed.';

                    throw new Error(message);
                }

                void loadDirectory(currentPath);
            } catch (requestError) {
                setError((requestError as Error).message);
            } finally {
                setUploading(false);
            }
        },
        [currentPath, loadDirectory, serverId],
    );

    const handleDownload = useCallback(
        (file: FileItem) => {
            const url = `/servers/${serverId}/files/download?path=${encodeURIComponent(file.path)}`;

            window.open(url, '_blank', 'noopener');
        },
        [serverId],
    );

    const breadcrumbSegments = useMemo(() => {
        if (!currentPath) {
            return [] as Array<{ label: string; path: string }>;
        }

        const segments = currentPath.split('/').filter(Boolean);

        return segments.map((segment, index) => ({
            label: segment,
            path: segments.slice(0, index + 1).join('/'),
        }));
    }, [currentPath]);

    useEffect(() => {
        if (open) {
            void loadDirectory('');
        } else {
            if (abortController.current) {
                abortController.current.abort();
                abortController.current = null;
            }
            setItems([]);
            setCurrentPath('');
            setError(null);
        }
    }, [loadDirectory, open]);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="flex min-h-[420px] flex-col gap-4 sm:max-w-3xl">
                <DialogHeader className="space-y-2">
                    <DialogTitle>Server File Explorer</DialogTitle>
                    <DialogDescription>
                        Browse the application user's home directory. Download existing files or upload new assets directly to the server.
                    </DialogDescription>
                </DialogHeader>

                <div className="flex flex-1 flex-col gap-3">
                    <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <Breadcrumb>
                            <BreadcrumbList>
                                <BreadcrumbItem>
                                    {currentPath ? (
                                        <BreadcrumbLink asChild>
                                            <button
                                                type="button"
                                                onClick={() => void loadDirectory('')}
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
                                                    onClick={() => void loadDirectory(segment.path)}
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

                        <div className="flex items-center gap-2">
                            <Button variant="outline" size="sm" onClick={handleNavigateUp} disabled={!currentPath || loading}>
                                Up
                            </Button>
                            <Button variant="outline" size="sm" onClick={handleRefresh} disabled={loading}>
                                <RefreshCw className="mr-1 h-4 w-4" /> Refresh
                            </Button>
                            <input ref={fileInputRef} type="file" className="hidden" onChange={handleUpload} />
                            <Button size="sm" onClick={handleUploadClick} disabled={uploading}>
                                {uploading ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <Upload className="mr-2 h-4 w-4" />}
                                Upload
                            </Button>
                        </div>
                    </div>

                    {error && (
                        <Alert variant="destructive">
                            <AlertDescription>{error}</AlertDescription>
                        </Alert>
                    )}

                    <div className="relative flex-1 rounded-md border border-dashed">
                        {loading && (
                            <div className="absolute inset-0 z-10 flex items-center justify-center bg-background/70">
                                <Loader2 className="h-6 w-6 animate-spin" />
                            </div>
                        )}

                        {items.length === 0 && !loading ? (
                            <div className="flex h-full flex-col items-center justify-center gap-2 px-4 text-center text-sm text-muted-foreground">
                                <FolderOpen className="h-10 w-10 text-muted-foreground/60" />
                                <p>This directory is empty.</p>
                            </div>
                        ) : (
                            <div className="max-h-[360px] overflow-y-auto">
                                {items.map((item) => (
                                    <button
                                        type="button"
                                        key={item.path}
                                        onClick={() => {
                                            if (item.type === 'directory') {
                                                void loadDirectory(item.path);
                                            }
                                        }}
                                        className={cn(
                                            'flex w-full items-center justify-between gap-3 px-4 py-3 text-left transition-colors',
                                            'hover:bg-muted/60 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                        )}
                                    >
                                        <div className="flex min-w-0 flex-1 items-center gap-3">
                                            {item.type === 'directory' ? (
                                                <Folder className="h-5 w-5 text-blue-500" />
                                            ) : (
                                                <FileText className="h-5 w-5 text-muted-foreground" />
                                            )}
                                            <div className="min-w-0">
                                                <div className="truncate text-sm font-medium text-foreground">{item.name}</div>
                                                <div className="text-xs text-muted-foreground">
                                                    {item.type === 'directory' ? 'Directory' : `${formatBytes(item.size)} • ${item.permissions}`}
                                                </div>
                                            </div>
                                        </div>

                                        <div className="flex items-center gap-3 text-xs text-muted-foreground">
                                            <span>{formatTimestamp(item.modifiedAt)}</span>
                                            {item.type === 'file' && (
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    onClick={(event) => {
                                                        event.stopPropagation();
                                                        handleDownload(item);
                                                    }}
                                                    title="Download"
                                                >
                                                    <Download className="h-4 w-4" />
                                                </Button>
                                            )}
                                        </div>
                                    </button>
                                ))}
                            </div>
                        )}
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" onClick={() => onOpenChange(false)}>
                        Close
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
};

export default ServerFileExplorerDialog;
