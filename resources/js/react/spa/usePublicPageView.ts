import * as React from 'react';

/**
 * Mengirim satu beacon kunjungan halaman publik ke backend. Dipakai PublicShell
 * agar setiap halaman publik tercatat tanpa mengganggu navigasi pengguna.
 */
export function usePublicPageView(): void {
    const sent = React.useRef<string | null>(null);

    React.useEffect(() => {
        const path = window.location.pathname;

        if (sent.current === path) {
            return;
        }

        sent.current = path;

        const payload = JSON.stringify({ path, title: document.title });
        const endpoint = '/api/publik/analitik';
        const csrf = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

        if (typeof navigator !== 'undefined' && typeof navigator.sendBeacon === 'function') {
            const blob = new Blob([payload], { type: 'application/json' });
            navigator.sendBeacon(endpoint, blob);

            return;
        }

        void fetch(endpoint, {
            method: 'POST',
            credentials: 'same-origin',
            keepalive: true,
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
            },
            body: payload,
        });
    }, []);
}
