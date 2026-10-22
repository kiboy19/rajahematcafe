type SnapCallbacks = {
    onSuccess?: () => void;
    onPending?: () => void;
    onError?: () => void;
    onClose?: () => void;
};

type SnapWindow = Window & { snap?: { pay: (token: string, options?: SnapCallbacks) => void } };

interface SnapConfig {
    clientKey: string;
    isProduction: boolean;
}

let config: SnapConfig | null = null;
let loader: Promise<void> | null = null;

/** Dipanggil sekali dari boot SPA dengan konfigurasi gateway dari server. */
export function setMidtransConfig(clientKey: string | null | undefined, isProduction?: boolean): void {
    if (clientKey) {
        config = { clientKey, isProduction: Boolean(isProduction) };
    }
}

/**
 * Memuat script Midtrans Snap hanya saat dibutuhkan (halaman pembayaran),
 * bukan di setiap halaman — menghindari unduhan besar dan telemetri Faro
 * pihak ketiga (terblokir ad-blocker, memunculkan ERR_BLOCKED_BY_CLIENT
 * di console semua halaman).
 */
export function loadMidtransSnap(): Promise<void> {
    const target = window as SnapWindow;

    if (target.snap) {
        return Promise.resolve();
    }

    if (loader) {
        return loader;
    }

    if (!config) {
        return Promise.reject(new Error('Midtrans belum dikonfigurasi. Hubungi administrator.'));
    }

    loader = new Promise<void>((resolve, reject) => {
        const script = document.createElement('script');
        script.src = config!.isProduction
            ? 'https://app.midtrans.com/snap/snap.js'
            : 'https://app.sandbox.midtrans.com/snap/snap.js';
        script.dataset.clientKey = config!.clientKey;
        script.async = true;
        script.onload = () => resolve();
        script.onerror = () => {
            loader = null;
            reject(new Error('Gagal memuat Midtrans Snap. Periksa koneksi internet Anda.'));
        };
        document.head.appendChild(script);
    });

    return loader;
}

/** Muat Snap bila perlu, lalu buka popup pembayaran dengan token. */
export function payWithSnap(token: string, options?: SnapCallbacks): Promise<void> {
    return loadMidtransSnap().then(() => {
        const target = window as SnapWindow;
        if (!target.snap) {
            throw new Error('Midtrans Snap tidak tersedia.');
        }

        target.snap.pay(token, options);
    });
}
