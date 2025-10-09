import { Alert, AlertDescription } from '@/components/ui/alert';
import { Breadcrumb, BreadcrumbItem, BreadcrumbLink, BreadcrumbList, BreadcrumbPage, BreadcrumbSeparator } from '@/components/ui/breadcrumb';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { PageHeader } from '@/components/ui/page-header';
import { cn } from '@/lib/utils';
import { Download, FileText, Folder, FolderOpen, Loader2, RefreshCw, Trash2, Upload, XCircle } from 'lucide-react';
import { useMemo, useRef, useState, type ChangeEvent } from 'react';
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
    onRefresh: () => void;
    onUpload: (file: File) => void;
    onDownload: (file: FileItem) => void;
    onDelete: (files: FileItem[]) => void;
};

export const ServerFileBrowser = ({ state, onNavigate, onRefresh, onUpload, onDownload, onDelete }: ServerFileBrowserProps) => {
    const fileInputRef = useRef<HTMLInputElement | null>(null);
    const [selectedItems, setSelectedItems] = useState<Set<string>>(new Set());

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

    const handleSelectItem = (itemPath: string, checked: boolean) => {
        setSelectedItems((prev) => {
            const next = new Set(prev);
            if (checked) {
                next.add(itemPath);
            } else {
                next.delete(itemPath);
            }
            return next;
        });
    };

    const handleSelectAll = (checked: boolean) => {
        if (checked) {
            setSelectedItems(new Set(state.items.map((item) => item.path)));
        } else {
            setSelectedItems(new Set());
        }
    };

    const handleDeleteSelected = () => {
        const itemsToDelete = state.items.filter((item) => selectedItems.has(item.path));
        if (itemsToDelete.length === 0) return;

        const fileCount = itemsToDelete.filter((item) => item.type === 'file').length;
        const dirCount = itemsToDelete.filter((item) => item.type === 'directory').length;

        let message = 'Are you sure you want to delete ';
        const parts: string[] = [];
        if (fileCount > 0) parts.push(`${fileCount} ${fileCount === 1 ? 'file' : 'files'}`);
        if (dirCount > 0) parts.push(`${dirCount} ${dirCount === 1 ? 'directory' : 'directories'}`);
        message += parts.join(' and ') + '? This action cannot be undone.';

        if (confirm(message)) {
            onDelete(itemsToDelete);
            setSelectedItems(new Set());
        }
    };

    const handleDownloadSelected = () => {
        const filesToDownload = state.items.filter((item) => selectedItems.has(item.path) && item.type === 'file');
        filesToDownload.forEach((file) => onDownload(file));
    };

    const allItemsSelected = state.items.length > 0 && state.items.every((item) => selectedItems.has(item.path));

    return (
        <PageHeader
            title="File Explorer"
            description="Browse the server's application directory, upload assets, or download existing files."
            action={
                <div className="flex items-center gap-2">
                    {selectedItems.size > 0 ? (
                        <>
                            <span className="text-sm text-muted-foreground">{selectedItems.size} selected</span>
                            <Button variant="outline" size="sm" onClick={handleDownloadSelected}>
                                <Download className="mr-1 h-4 w-4" /> Download
                            </Button>
                            <Button variant="destructive" size="sm" onClick={handleDeleteSelected}>
                                <Trash2 className="mr-1 h-4 w-4" /> Delete
                            </Button>
                            <Button variant="ghost" size="sm" onClick={() => setSelectedItems(new Set())}>
                                Cancel
                            </Button>
                        </>
                    ) : (
                        <>
                            <Button variant="outline" size="sm" onClick={onRefresh} disabled={state.loading}>
                                <RefreshCw className="mr-1 h-4 w-4" /> Refresh
                            </Button>
                            <input ref={fileInputRef} type="file" className="hidden" onChange={handleUploadChange} />
                            <Button size="sm" onClick={handleUploadClick} disabled={state.uploading}>
                                {state.uploading ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <Upload className="mr-2 h-4 w-4" />}
                                Upload
                            </Button>
                        </>
                    )}
                </div>
            }
        >
            {state.error && (
                <Alert variant="destructive">
                    <XCircle className="h-4 w-4" />
                    <AlertDescription>{state.error}</AlertDescription>
                </Alert>
            )}

            <div className="rounded-xl border border-neutral-200/70 bg-neutral-50 p-1.5 dark:border-white/5 dark:bg-white/3">
                <div className="grid gap-2">
                    {/* Header with breadcrumb */}
                    <div className="flex items-center justify-between p-2 pb-1.5">
                        <div>
                            <div className="flex items-center gap-3 pl-4">
                                {state.items.length > 0 && (
                                    <Checkbox checked={allItemsSelected} onCheckedChange={handleSelectAll} aria-label="Select all items" />
                                )}
                                <Breadcrumb>
                                    <BreadcrumbList>
                                        <BreadcrumbItem>
                                            {state.currentPath ? (
                                                <BreadcrumbLink asChild>
                                                    <button
                                                        type="button"
                                                        onClick={() => onNavigate('')}
                                                        className="cursor-pointer text-sm font-medium text-foreground/80 transition-colors hover:text-foreground"
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
                                                            className="cursor-pointer text-sm font-medium text-foreground/80 transition-colors hover:text-foreground"
                                                        >
                                                            {segment.label}
                                                        </button>
                                                    </BreadcrumbLink>
                                                )}
                                            </BreadcrumbItem>
                                        ))}
                                    </BreadcrumbList>
                                </Breadcrumb>
                            </div>
                        </div>
                    </div>

                    <div className="divide-y divide-neutral-200 rounded-lg border border-neutral-200 bg-white dark:divide-white/8 dark:border-white/8 dark:bg-white/3">
                        <div className="relative px-2 py-6">
                            {state.loading && (
                                <div className="absolute inset-0 z-10 flex items-center justify-center rounded-lg bg-background/80">
                                    <Loader2 className="h-6 w-6 animate-spin text-primary" />
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
                                                'flex items-center justify-between gap-3 border-b border-border/60 px-4 py-3 transition-colors last:border-b-0',
                                                item.type === 'directory' && 'hover:bg-muted/50',
                                                selectedItems.has(item.path) && 'bg-muted/30',
                                            )}
                                        >
                                            <div className="flex flex-1 items-center gap-3">
                                                <Checkbox
                                                    checked={selectedItems.has(item.path)}
                                                    onCheckedChange={(checked) => handleSelectItem(item.path, checked as boolean)}
                                                    aria-label={`Select ${item.name}`}
                                                    onClick={(e) => e.stopPropagation()}
                                                />
                                                <button
                                                    type="button"
                                                    onClick={() => {
                                                        if (item.type === 'directory') {
                                                            onNavigate(item.path);
                                                        }
                                                    }}
                                                    disabled={state.loading}
                                                    className={cn(
                                                        'flex flex-1 items-center gap-3 text-left focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                                        item.type === 'directory' ? 'cursor-pointer' : 'cursor-default',
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
                                            </div>

                                            <div className="flex items-center gap-3 text-xs text-muted-foreground">
                                                <span>{formatTimestamp(item.modifiedAt)}</span>
                                                <div className="size-9">
                                                    {item.type === 'file' && (
                                                        <Button
                                                            data-slot="button"
                                                            variant="ghost"
                                                            size="icon"
                                                            onClick={() => onDownload(item)}
                                                            title="Download"
                                                            className="size-9"
                                                        >
                                                            <Download className="h-4 w-4" />
                                                        </Button>
                                                    )}
                                                </div>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </PageHeader>
    );
};

export default ServerFileBrowser;
