import { getCsrfTokenFromCookie } from '@/lib/csrf';
import { useCallback, useEffect, useRef, useState } from 'react';
import type { FileBrowserState, FileItem } from './types';

const initialState: FileBrowserState = {
    items: [],
    currentPath: '',
    loading: false,
    uploading: false,
    error: null,
};

export const useServerFileBrowser = (serverId: number) => {
    const [state, setState] = useState<FileBrowserState>(initialState);
    const abortController = useRef<AbortController | null>(null);

    const loadDirectory = useCallback(
        async (targetPath: string = '') => {
            if (abortController.current) {
                abortController.current.abort();
            }

            const controller = new AbortController();
            abortController.current = controller;

            setState((prev) => ({ ...prev, loading: true, error: null }));

            const query = targetPath ? `?path=${encodeURIComponent(targetPath)}` : '';

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
                const resolvedPath = typeof payload.path === 'string' ? payload.path : targetPath;

                setState((prev) => ({
                    ...prev,
                    items,
                    currentPath: resolvedPath,
                    loading: false,
                }));
            } catch (error) {
                if ((error as Error).name === 'AbortError') {
                    return;
                }

                setState((prev) => ({
                    ...prev,
                    loading: false,
                    error: (error as Error).message,
                }));
            } finally {
                if (abortController.current === controller) {
                    abortController.current = null;
                }
            }
        },
        [serverId],
    );

    const refresh = useCallback(() => {
        void loadDirectory(state.currentPath);
    }, [loadDirectory, state.currentPath]);

    const navigateTo = useCallback(
        (path: string) => {
            void loadDirectory(path);
        },
        [loadDirectory],
    );

    const navigateUp = useCallback(() => {
        if (!state.currentPath) {
            return;
        }

        const segments = state.currentPath.split('/').filter(Boolean);
        segments.pop();
        const nextPath = segments.join('/');

        void loadDirectory(nextPath);
    }, [loadDirectory, state.currentPath]);

    const upload = useCallback(
        async (file: File) => {
            const csrfToken = getCsrfTokenFromCookie();

            if (!csrfToken) {
                setState((prev) => ({
                    ...prev,
                    error: 'Unable to upload because the CSRF token could not be determined.',
                }));

                return;
            }

            setState((prev) => ({ ...prev, uploading: true, error: null }));

            const formData = new FormData();
            formData.append('file', file);

            if (state.currentPath) {
                formData.append('path', state.currentPath);
            }

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

                await loadDirectory(state.currentPath);
            } catch (error) {
                setState((prev) => ({
                    ...prev,
                    error: (error as Error).message,
                }));
            } finally {
                setState((prev) => ({ ...prev, uploading: false }));
            }
        },
        [loadDirectory, serverId, state.currentPath],
    );

    const download = useCallback(
        (file: FileItem) => {
            const url = `/servers/${serverId}/files/download?path=${encodeURIComponent(file.path)}`;
            window.open(url, '_blank', 'noopener');
        },
        [serverId],
    );

    const dismissError = useCallback(() => {
        setState((prev) => ({ ...prev, error: null }));
    }, []);

    useEffect(() => {
        void loadDirectory('');

        return () => {
            if (abortController.current) {
                abortController.current.abort();
            }
        };
    }, [loadDirectory]);

    return {
        state,
        refresh,
        navigateTo,
        navigateUp,
        upload,
        download,
        dismissError,
    };
};
