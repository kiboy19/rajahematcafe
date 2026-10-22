import { useState } from 'react';
import * as React from 'react';
import { useResource, mutate } from '../hooks';
import { dateTime, statusBadgeClass, statusLabel } from '../api';
import { Badge, Button, EmptyState, ErrorBox, Toast, AlertDialog, AlertDialogContent, AlertDialogHeader, AlertDialogTitle, AlertDialogDescription, AlertDialogFooter, AlertDialogCancel, AlertDialogAction } from '../ui-primitives';
import { Loading } from '../ui';

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

type OrderStatusMap = Record<number, string>;

export function KdsPage() {
    const { data, loading, error, reload } = useResource<KdsOrder[]>('/api/kds', { pollMs: 3000 });
    const [busyId, setBusyId] = useState<number | null>(null);
    const [cancelTarget, setCancelTarget] = useState<KdsOrder | null>(null);
    const [cancelReason, setCancelReason] = useState('');
    const [cancelError, setCancelError] = useState<string | null>(null);
    const [toastMessage, setToastMessage] = useState<{ text: string; tone: 'success' | 'error' } | null>(null);
    const [lastUpdated, setLastUpdated] = useState<string | null>(null);
    const [optimisticStatus, setOptimisticStatus] = useState<OrderStatusMap>({});

    // Catatan pembaruan terakhir untuk indikator polling.
    React.useEffect(() => {
        if (data) {
            setLastUpdated(new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' }));
        }
    }, [data]);

    if (loading) return <Loading label="Memuat antrean dapur..." />;
    if (error) return <ErrorBox message={error} />;

    const orders = data ?? [];

    const updateStatus = async (order: KdsOrder, status: string, reason?: string) => {
        // Optimistic update: tampilkan status baru seketika
        setOptimisticStatus((prev) => ({ ...prev, [order.id]: status }));
        setBusyId(order.id);

        const result = await mutate('PATCH', `/api/kds/${order.id}`, { status, reason });
        setBusyId(null);

        if (result.ok) {
            setOptimisticStatus((prev) => {
                const next = { ...prev };
                delete next[order.id];
                return next;
            });
            reload();
            setToastMessage({ text: result.message ?? `Status diperbarui ke ${statusLabel(status)}`, tone: 'success' });
        } else {
            setOptimisticStatus((prev) => {
                const next = { ...prev };
                delete next[order.id];
                return next;
            });
            setToastMessage({ text: result.message || 'Gagal memperbarui status.', tone: 'error' });
        }
    };

    const handleConfirmCancel = async () => {
        if (!cancelTarget) return;
        if (cancelReason.trim().length < 5) {
            setCancelError('Alasan pembatalan minimal 5 karakter.');
            return;
        }
        const target = cancelTarget;
        const reason = cancelReason.trim();
        setCancelTarget(null);
        setCancelReason('');
        setCancelError(null);
        await updateStatus(target, 'dibatalkan', reason);
    };

    return (
        <div className="min-h-[calc(100vh-7rem)] -m-4 sm:-m-6 bg-kds-bg text-kds-ink p-3 sm:p-4">
            <div className="flex items-center justify-between gap-3 mb-4">
                <div>
                    <h1 className="text-xl font-bold font-display">Antrean Dapur</h1>
                    <p className="text-xs text-kds-muted">
                        Auto-refresh 3s {lastUpdated ? `· Terakhir: ${lastUpdated}` : ''} · {orders.length} pesanan aktif
                    </p>
                </div>
                <Button variant="outline" className="border-kds-line bg-kds-card text-kds-ink hover:bg-kds-line" onClick={reload}>
                    Segarkan
                </Button>
            </div>

            {orders.length === 0 ? (
                <div className="rounded-card border border-kds-line bg-kds-card p-12 text-center">
                    <p className="text-lg font-bold">Tidak ada pesanan aktif.</p>
                    <p className="text-sm text-kds-muted mt-1">Pesanan baru akan muncul di layar ini.</p>
                </div>
            ) : (
                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
                    {orders.map((order) => {
                        const effectiveStatus = optimisticStatus[order.id] ?? order.status;
                        const isBusy = busyId === order.id;
                        const minutes = Math.max(0, Math.floor((Date.now() - new Date(order.placed_at).getTime()) / 60000));
                        const border = minutes >= 20 ? 'border-bad' : minutes >= 10 ? 'border-warn' : 'border-kds-line';

                        return (
                            <article
                                key={order.id}
                                aria-busy={isBusy || undefined}
                                className={`rounded-card border ${border} bg-kds-card flex flex-col transition-opacity ${isBusy ? 'opacity-60' : ''}`}
                            >
                                <header className="p-4 border-b border-kds-line flex items-start justify-between gap-3">
                                    <div>
                                        <p className="text-2xl font-bold leading-none font-display">{order.table_label ? `Meja ${order.table_label}` : 'Bungkus'}</p>
                                        <p className="text-xs text-kds-muted mt-1 font-mono">{order.number}</p>
                                    </div>
                                    <div className="text-right">
                                        <Badge className={statusBadgeClass(effectiveStatus)}>{statusLabel(effectiveStatus)}</Badge>
                                        <p className={`text-xs mt-1 font-variant-numeric:tabular-nums ${minutes >= 10 ? 'text-warn font-bold' : 'text-kds-muted'}`}>
                                            {minutes} menit
                                        </p>
                                    </div>
                                </header>
                                <ul className="p-4 flex flex-col gap-3 flex-1">
                                    {order.items.map((item) => (
                                        <li key={item.id} className="flex gap-3">
                                            <span className="min-w-8 h-8 rounded-input bg-primary text-white font-bold text-sm flex items-center justify-center font-variant-numeric:tabular-nums">
                                                {item.quantity}
                                            </span>
                                            <div>
                                                <p className="text-lg font-semibold leading-tight">{item.name}</p>
                                                {item.note && <p className="text-sm text-amber-300">Catatan: {item.note}</p>}
                                            </div>
                                        </li>
                                    ))}
                                    {order.note && <li className="text-sm text-amber-300 border-t border-kds-line pt-3">Catatan: {order.note}</li>}
                                </ul>
                                <footer className="p-4 pt-0 flex gap-2 flex-wrap">
                                    {(transitions[effectiveStatus] ?? []).map((transition) => (
                                        <Button
                                            key={transition.value}
                                            variant={transition.value === 'dibatalkan' ? 'destructive' : 'default'}
                                            className="flex-1"
                                            pending={isBusy}
                                            onClick={() => {
                                                if (transition.value === 'dibatalkan') {
                                                    setCancelTarget(order);
                                                    setCancelReason('');
                                                    setCancelError(null);
                                                } else {
                                                    updateStatus(order, transition.value);
                                                }
                                            }}
                                            disabled={isBusy}
                                        >
                                            {transition.label}
                                        </Button>
                                    ))}
                                </footer>
                            </article>
                        );
                    })}
                </div>
            )}

            {/* Modal Alasan Pembatalan (Pengganti window.prompt) */}
            <AlertDialog open={!!cancelTarget} onOpenChange={(open) => !open && setCancelTarget(null)}>
                <AlertDialogContent className="bg-surface text-ink max-w-sm rounded-card">
                    <AlertDialogHeader>
                        <AlertDialogTitle>Batalkan Pesanan {cancelTarget?.number}?</AlertDialogTitle>
                        <AlertDialogDescription>
                            Tuliskan alasan pembatalan (minimal 5 karakter) agar dicatat di sistem.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <div className="py-2">
                        <textarea
                            className="w-full rounded-input border border-line bg-surface p-2 text-sm focus:outline-primary"
                            rows={3}
                            placeholder="Alasan pembatalan..."
                            value={cancelReason}
                            onChange={(e) => {
                                setCancelReason(e.target.value);
                                if (cancelError) setCancelError(null);
                            }}
                            autoFocus
                        />
                        {cancelError && <p className="mt-1 text-xs text-bad">{cancelError}</p>}
                    </div>
                    <AlertDialogFooter>
                        <AlertDialogCancel onClick={() => setCancelTarget(null)}>Tutup</AlertDialogCancel>
                        <AlertDialogAction onClick={handleConfirmCancel} className="bg-bad text-white hover:bg-bad/90">
                            Batalkan Pesanan
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>

            <Toast message={toastMessage} />
        </div>
    );
}