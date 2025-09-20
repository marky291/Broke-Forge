export type FileItem = {
    name: string;
    path: string;
    type: 'file' | 'directory';
    size: number | null;
    modifiedAt: string;
    permissions: string;
};

export type FileBrowserState = {
    items: FileItem[];
    currentPath: string;
    loading: boolean;
    uploading: boolean;
    error: string | null;
};
