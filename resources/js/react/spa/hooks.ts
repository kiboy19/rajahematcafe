import { useEffect, useRef, useState } from 'react';
import { apiRequest, type ApiResult } from '@/react/lib/api';

/**
 * Pemuat data JSON dengan status loading/error dan reload manual.
 * Dipakai semua halaman SPA agar pola fetch konsisten.
 */
export function useResource<T = Record<string, unknown>>(
    url: string,
    options: { pollMs?: number } = {}
): {
    data: T | null;
    raw: Record<string, unknown> | null;
    loading: boolean;
    error: string | null;
    reload: () => void;
} {
    const [data, setData] = useState<T | null>(null);
    const [raw, setRaw] = useState<Record<string, unknown> | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [tick, setTick] = useState(0);
    const previousUrl = useRef<string | null>(null);
    const pollMs = options.pollMs;

    useEffect(() => {
        let active = true;
        const urlChanged = previousUrl.current !== null && previousUrl.current !== url;
        previousUrl.current = url;

        if (urlChanged) {
            setLoading(true);
            setError(null);
            setData(null);
            setRaw(null);
        }

        apiRequest<T>('GET', url).then((result) => {
            if (!active) {
                return;
            }

            setLoading(false);

            if (result.ok) {
                setData(result.data ?? null);
                setRaw(result.raw ?? null);
                setError(null);
            } else {
                setError(result.message);
            }
        });

        return () => {
            active = false;
        };
    }, [url, tick]);

    useEffect(() => {
        if (!pollMs) {
            return;
        }

        const id = window.setInterval(() => setTick((value) => value + 1), pollMs);

        return () => window.clearInterval(id);
    }, [pollMs]);

    return { data, raw, loading, error, reload: () => setTick((value) => value + 1) };
}

/**
 * Menempelkan pesan flash sukses/error ke halaman; otomatis hilang sendiri.
 */
export function useFlash(): {
    message: { text: string; tone: 'success' | 'error' } | null;
    flashSuccess: (text: string) => void;
    flashError: (text: string) => void;
} {
    const [message, setMessage] = useState<{ text: string; tone: 'success' | 'error' } | null>(null);
    const timer = useRef<number | null>(null);

    useEffect(() => {
        return () => {
            if (timer.current) {
                window.clearTimeout(timer.current);
            }
        };
    }, []);

    const show = (text: string, tone: 'success' | 'error') => {
        setMessage({ text, tone });

        if (timer.current) {
            window.clearTimeout(timer.current);
        }

        timer.current = window.setTimeout(() => setMessage(null), 5000);
    };

    return {
        message,
        flashSuccess: (text) => show(text, 'success'),
        flashError: (text) => show(text, 'error'),
    };
}

/**
 * Jalankan mutasi API lalu kembalikan pesan sukses/error yang siap ditampilkan.
 */
export async function mutate<T = unknown>(
    method: 'POST' | 'PATCH' | 'PUT' | 'DELETE',
    url: string,
    body?: unknown
): Promise<ApiResult<T>> {
    return apiRequest<T>(method, url, body);
}
