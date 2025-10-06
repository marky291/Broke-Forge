import { useCallback, useEffect, useRef } from 'react';

interface PollingOptions {
    url: string;
    interval?: number;
    enabled?: boolean;
    onSuccess?: (data: any) => void;
    onError?: (error: Error) => void;
    stopCondition?: (data: any) => boolean;
}

export function useStatusPolling({ url, interval = 2000, enabled = true, onSuccess, onError, stopCondition }: PollingOptions) {
    const intervalRef = useRef<NodeJS.Timeout | null>(null);
    const mountedRef = useRef(true);

    const poll = useCallback(async () => {
        try {
            const response = await fetch(url, {
                headers: { Accept: 'application/json' },
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();

            if (mountedRef.current) {
                onSuccess?.(data);

                // Check if we should stop polling
                if (stopCondition?.(data)) {
                    if (intervalRef.current) {
                        clearInterval(intervalRef.current);
                        intervalRef.current = null;
                    }
                }
            }
        } catch (error) {
            if (mountedRef.current) {
                onError?.(error as Error);
                console.error('Polling error:', error);
            }
        }
    }, [url, onSuccess, onError, stopCondition]);

    useEffect(() => {
        mountedRef.current = true;

        if (enabled) {
            // Initial poll
            poll();

            // Set up interval
            intervalRef.current = setInterval(poll, interval);
        }

        return () => {
            mountedRef.current = false;
            if (intervalRef.current) {
                clearInterval(intervalRef.current);
                intervalRef.current = null;
            }
        };
    }, [enabled, interval, poll]);

    const stop = useCallback(() => {
        if (intervalRef.current) {
            clearInterval(intervalRef.current);
            intervalRef.current = null;
        }
    }, []);

    const start = useCallback(() => {
        if (!intervalRef.current && enabled) {
            poll();
            intervalRef.current = setInterval(poll, interval);
        }
    }, [enabled, interval, poll]);

    return { stop, start };
}
