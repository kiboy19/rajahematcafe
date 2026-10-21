import { useState } from 'react';
import { useResource } from '../hooks';
import { mutate } from '../hooks';
import { dateTime, statusBadgeClass, statusLabel } from '../api';
import { Badge, Button, EmptyState, ErrorBox, Loading, Panel } from '../ui';

interface KdsOrder {
    id: number;
    number: string;
    table_label: string | null;
    status: string;
    placed_at: string;
    note: string | null;
    items: Array<{ id: number; name: string; quantity: number; note: string | null }>;
}

const transitions: Record<string, Array<{ value: string; label: string }>> = {
    baru: [{ value: 'diproses', label: 'Mulai Proses' }, { value: 'dibatalkan', label: 'Batalkan' }],
    diproses: [{ value: 'siap', label: 'Tandai Siap' }, { value: 'dibatalkan', label: 'Batalkan' }],
    siap: [{ value: 'selesai', label: 'Selesaikan' }],
};

export function KdsPage() {
    const { data, loading, error, reload } = useResource<KdsOrder[]>('/api/kds', { pollMs: 20000 });
    const [busyId, setBusyId] = useState<number | null>(null);

    if (loading) return <Loading label="Memuat antrean dapur..." />;
    if (error) return <ErrorBox message={error} />;

    const orders = data ?? [];

    const updateStatus = async (order: KdsOrder, status: string) => {
        const reason = status === 'dibatalkan' ? window.prompt('Alasan pembatalan (minimal 5 karakter):') : undefined;
        if (status === 'dibatalkan' && (!reason || reason.length < 5)) return;

        setBusyId(order.id);
        const result = await mutate('PATCH', `/api/kds/${order.id}`, { status, reason });
        setBusyId(null);
        if (result.ok) reload();
        else window.alert(result.message);
    };

    return (
        <div className="min-h-[calc(100vh-7rem)] -m-4 sm:-m-6 bg-kds-bg text-kds-ink p-3 sm:p-4">
            <div className="flex items-center justify-between gap-3 mb-4">
                <div>
                    <h1 className="text-xl font-bold">Antrean Dapur</h1>
                    <p className="text-xs text-kds-muted">Memperbarui otomatis setiap 20 detik · {orders.length} pesanan aktif</p>
                </div>
                <Button variant="outline" className="border-kds-line bg-kds-card text-kds-ink hover:bg-kds-line" onClick={reload}>Segarkan</Button>
            </div>

            {orders.length === 0 ? (
                <div className="rounded-card border border-kds-line bg-kds-card p-12 text-center">
                    <p className="text-lg font-bold">Tidak ada pesanan aktif.</p>
                    <p className="text-sm text-kds-muted mt-1">Pesanan baru akan muncul di layar ini.</p>
                </div>
            ) : (
                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
                    {orders.map((order) => {
                        const minutes = Math.max(0, Math.floor((Date.now() - new Date(order.placed_at).getTime()) / 60000));
                        const border = minutes >= 20 ? 'border-bad' : minutes >= 10 ? 'border-warn' : 'border-kds-line';

                        return (
                            <article key={order.id} className={`rounded-card border ${border} bg-kds-card flex flex-col`}>
                                <header className="p-4 border-b border-kds-line flex items-start justify-between gap-3">
                                    <div><p className="text-2xl font-bold leading-none">{order.table_label ? `Meja ${order.table_label}` : 'Bungkus'}</p><p className="text-xs text-kds-muted mt-1 font-mono">{order.number}</p></div>
                                    <div className="text-right"><Badge className={statusBadgeClass(order.status)}>{statusLabel(order.status)}</Badge><p className={`text-xs mt-1 ${minutes >= 10 ? 'text-warn font-bold' : 'text-kds-muted'}`}>{minutes} menit</p></div>
                                </header>
                                <ul className="p-4 flex flex-col gap-3 flex-1">
                                    {order.items.map((item) => <li key={item.id} className="flex gap-3"><span className="min-w-8 h-8 rounded-input bg-primary text-white font-bold text-sm flex items-center justify-center tabular">{item.quantity}</span><div><p className="text-lg font-semibold leading-tight">{item.name}</p>{item.note && <p className="text-sm text-amber-300">Catatan: {item.note}</p>}</div></li>)}
                                    {order.note && <li className="text-sm text-amber-300 border-t border-kds-line pt-3">Catatan: {order.note}</li>}
                                </ul>
                                <footer className="p-4 pt-0 flex gap-2 flex-wrap">
                                    {(transitions[order.status] ?? []).map((transition) => <Button key={transition.value} variant={transition.value === 'dibatalkan' ? 'danger' : 'primary'} className="flex-1" onClick={() => updateStatus(order, transition.value)} disabled={busyId === order.id}>{transition.label}</Button>)}
                                </footer>
                            </article>
                        );
                    })}
                </div>
            )}
        </div>
    );
}