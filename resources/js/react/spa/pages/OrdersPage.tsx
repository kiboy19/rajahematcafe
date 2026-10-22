import { Link, useSearchParams } from 'react-router-dom';
import { dateTime, money, statusBadgeClass, statusLabel, orderChannelLabel } from '../api';
import { useResource } from '../hooks';
import { Badge, Button, EmptyState, ErrorBox, Loading, Money, PageHeader, SegmentedTabs, inputClass } from '../ui';

interface Order {
    id: number;
    number: string;
    channel: string;
    status: string;
    table_label: string | null;
    total: number;
    paid_total?: number | null;
    paid_at: string | null;
    placed_at: string;
    items_count?: number;
    cashier?: { name: string } | null;
    customer?: { name: string } | null;
}

interface OrdersMeta {
    current_page: number;
    last_page: number;
    total: number;
}

const statusTabs = [
    { value: '', label: 'Semua' },
    { value: 'baru', label: 'Baru' },
    { value: 'diproses', label: 'Diproses' },
    { value: 'siap', label: 'Siap' },
    { value: 'selesai', label: 'Selesai' },
    { value: 'menunggu_pembayaran', label: 'Belum bayar' },
    { value: 'dibatalkan', label: 'Dibatalkan' },
];

const methodOptions = [
    { value: '', label: 'Semua metode bayar' },
    { value: 'cash', label: 'Tunai' },
    { value: 'qris', label: 'QRIS' },
    { value: 'va', label: 'Virtual Account' },
    { value: 'edc', label: 'Kartu EDC' },
    { value: 'wallet', label: 'Saldo Pelanggan' },
];

export function OrdersPage() {
    const [searchParams, setSearchParams] = useSearchParams();

    const status = searchParams.get('status') ?? '';
    const method = searchParams.get('metode') ?? '';
    const from = searchParams.get('dari') ?? '';
    const to = searchParams.get('sampai') ?? '';
    const page = Number(searchParams.get('page') ?? 1) || 1;

    const setFilter = (key: string, value: string) => {
        const next = new URLSearchParams(searchParams);
        if (value) {
            next.set(key, value);
        } else {
            next.delete(key);
        }
        next.delete('page');
        setSearchParams(next, { replace: true });
    };

    const setPage = (nextPage: number) => {
        const next = new URLSearchParams(searchParams);
        if (nextPage > 1) {
            next.set('page', String(nextPage));
        } else {
            next.delete('page');
        }
        setSearchParams(next);
    };

    const hasFilter = Boolean(status || method || from || to);
    const query = searchParams.toString();
    const { data, raw, loading, error } = useResource<Order[]>(`/api/pesanan${query ? `?${query}` : ''}`);

    const orders = data ?? [];
    const meta = raw?.meta as OrdersMeta | undefined;

    return (
        <div className="max-w-7xl w-full flex flex-col gap-5">
            <PageHeader title="Pesanan" subtitle={`Riwayat transaksi · ${meta?.total ?? 0} pesanan`} />

            <div className="flex flex-col gap-3 rounded-card border border-line bg-surface p-4 shadow-1">
                <SegmentedTabs value={status} onChange={(value) => setFilter('status', value)} options={statusTabs} />

                <div className="flex flex-wrap items-end gap-3">
                    <label className="flex flex-col gap-1 text-[11px] font-semibold text-ink-2">
                        Metode bayar
                        <select className={inputClass} value={method} onChange={(event) => setFilter('metode', event.target.value)}>
                            {methodOptions.map((option) => (
                                <option key={option.value} value={option.value}>{option.label}</option>
                            ))}
                        </select>
                    </label>

                    <label className="flex flex-col gap-1 text-[11px] font-semibold text-ink-2">
                        Dari tanggal
                        <input type="date" className={inputClass} value={from} onChange={(event) => setFilter('dari', event.target.value)} />
                    </label>

                    <label className="flex flex-col gap-1 text-[11px] font-semibold text-ink-2">
                        Sampai tanggal
                        <input type="date" className={inputClass} value={to} onChange={(event) => setFilter('sampai', event.target.value)} />
                    </label>

                    {hasFilter && (
                        <Button variant="ghost" onClick={() => setSearchParams({}, { replace: true })}>Reset filter</Button>
                    )}
                </div>
            </div>

            {loading ? (
                <Loading label="Memuat pesanan..." />
            ) : error ? (
                <ErrorBox message={error} />
            ) : orders.length === 0 ? (
                <EmptyState
                    title={hasFilter ? 'Tidak ada pesanan pada filter ini.' : 'Belum ada pesanan.'}
                    hint={hasFilter ? 'Coba ubah status, metode bayar, atau rentang tanggal.' : 'Pesanan dari kasir dan pelanggan akan muncul di sini.'}
                />
            ) : (
                <ul className="divide-y divide-line overflow-hidden rounded-card border border-line bg-surface shadow-1">
                    {orders.map((order) => {
                        const paid = (order.paid_total ?? 0) >= order.total;
                        const remaining = Math.max(0, order.total - (order.paid_total ?? 0));

                        return (
                            <li key={order.id} className="group relative hover:bg-surface-2/50">
                                <div className="flex flex-wrap items-start justify-between gap-3 p-4">
                                    <div className="min-w-0 flex-1">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <Link
                                                to={`/pesanan/${order.id}`}
                                                className="font-semibold text-primary hover:underline after:absolute after:inset-0 after:content-['']"
                                            >
                                                {order.number}
                                            </Link>
                                            <Badge className={statusBadgeClass(order.status)}>{statusLabel(order.status)}</Badge>
                                            {order.table_label && (
                                                <span className="text-xs text-ink-2">Meja {order.table_label}</span>
                                            )}
                                        </div>
                                        <p className="mt-1 text-xs text-ink-2">
                                            {dateTime(order.placed_at)} · {orderChannelLabel(order.channel)} · {order.items_count ?? 0} item
                                            {order.cashier?.name && <> · Kasir {order.cashier.name}</>}
                                            {order.customer?.name && <> · {order.customer.name}</>}
                                        </p>
                                    </div>

                                    <div className="flex items-center gap-4">
                                        <div className="text-right">
                                            <Money value={order.total} className="block font-display text-lg font-bold" />
                                            {paid ? (
                                                <span className="text-xs font-semibold text-ok">lunas</span>
                                            ) : (
                                                <span className="text-xs font-semibold text-bad">
                                                    kurang <Money value={remaining} />
                                                </span>
                                            )}
                                        </div>
                                        <Link
                                            to={`/pesanan/${order.id}/struk`}
                                            className="relative z-10 text-xs font-semibold text-primary hover:underline"
                                        >
                                            Struk
                                        </Link>
                                    </div>
                                </div>
                            </li>
                        );
                    })}
                </ul>
            )}

            {meta && meta.last_page > 1 && (
                <nav className="flex items-center justify-between gap-2" aria-label="Navigasi halaman">
                    <span className="text-xs text-ink-2">
                        Halaman {meta.current_page} dari {meta.last_page} · {meta.total} pesanan
                    </span>
                    <div className="flex gap-2">
                        <Button variant="outline" disabled={meta.current_page <= 1} onClick={() => setPage(meta.current_page - 1)}>
                            Sebelumnya
                        </Button>
                        <Button variant="outline" disabled={meta.current_page >= meta.last_page} onClick={() => setPage(meta.current_page + 1)}>
                            Berikutnya
                        </Button>
                    </div>
                </nav>
            )}
        </div>
    );
}
