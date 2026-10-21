import { apiRequest, type ApiResult, type ValidationErrors } from '@/react/lib/api';

export type Payload = Record<string, unknown>;

export async function getJson<T = Payload>(url: string): Promise<ApiResult<T>> {
    return apiRequest<T>('GET', url);
}

export async function postJson<T = Payload>(url: string, body: unknown): Promise<ApiResult<T>> {
    return apiRequest<T>('POST', url, body);
}

export async function patchJson<T = Payload>(url: string, body: unknown): Promise<ApiResult<T>> {
    return apiRequest<T>('PATCH', url, body);
}

export async function deleteJson<T = Payload>(url: string, body?: unknown): Promise<ApiResult<T>> {
    return apiRequest<T>('DELETE', url, body);
}

export function money(value: number | string | null | undefined): string {
    return `Rp ${Number(value ?? 0).toLocaleString('id-ID')}`;
}

export function dateTime(iso?: string | null): string {
    if (!iso) return '-';
    try { return new Date(iso).toLocaleString('id-ID', { dateStyle: 'short', timeStyle: 'short' }); } catch { return iso; }
}

export function statusBadgeClass(status: string): string {
    const map: Record<string, string> = {
        baru: 'bg-info-bg text-info',
        diproses: 'bg-warn-bg text-warn',
        siap: 'bg-ok-bg text-ok',
        selesai: 'bg-surface-2 text-ink',
        dibatalkan: 'bg-bad-bg text-bad',
        paid: 'bg-ok-bg text-ok',
        pending: 'bg-info-bg text-info',
        void: 'bg-bad-bg text-bad',
    };
    return map[status.toLowerCase()] ?? 'bg-surface-2 text-ink';
}

export function statusLabel(status: string): string {
    return status.charAt(0).toUpperCase() + status.slice(1);
}

export function orderChannelLabel(ch: string): string {
    return ch === 'dine_in' ? 'Dine-in' : ch === 'takeaway' ? 'Takeaway' : ch === 'qr_table' ? 'QR Meja' : ch;
}

export function paymentMethodLabel(m: string): string {
    return m === 'cash' ? 'Tunai' : m === 'qris' ? 'QRIS' : m === 'edc' ? 'EDC' : m === 'wallet' ? 'Saldo' : m;
}

export function methodBadgeClass(m: string): string {
    const map: Record<string, string> = { cash: 'bg-primary/10 text-primary', qris: 'bg-info-bg text-info', edc: 'bg-surface-2 text-ink', wallet: 'bg-ok-bg text-ok' };
    return map[m] ?? 'bg-surface-2 text-ink';
}