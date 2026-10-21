import { Link } from 'react-router-dom';
import { dateTime, money, statusBadgeClass, statusLabel, orderChannelLabel } from '../api';
import { useResource } from '../hooks';
import { Badge, EmptyState, ErrorBox, Loading, PageHeader } from '../ui';

interface Order {
    id: number;
    number: string;
    channel: string;
    status: string;
    table_label: string | null;
    total: number;
    paid_at: string | null;
    placed_at: string;
    items_count?: number;
    cashier?: { name: string } | null;
    customer?: { name: string } | null;
}

export function OrdersPage() {
    const { data, loading, error } = useResource<{ data: Order[]; meta?: { current_page: number; last_page: number; total: number } }>('/api/pesanan');

    if (loading) {
        return <Loading label="Memuat pesanan..." />;
    }

    if (error) {
        return <ErrorBox message={error} />;
    }

    const orders = data?.data ?? [];

    return (
        <div className="max-w-5xl flex flex-col gap-5">
            <PageHeader
                title="Pesanan"
                subtitle={`Daftar seluruh pesanan · ${data?.meta?.total ?? 0} pesanan`}
            />

            {orders.length === 0 ? (
                <EmptyState title="Belum ada pesanan." hint="Pesanan yang dibuat dari POS atau QR meja akan tampil di sini." />
            ) : (
                <div className="overflow-x-auto rounded-card border border-line bg-surface shadow-1">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b border-line bg-surface-2 text-[11px] uppercase tracking-wider text-ink-2">
                                <th className="px-4 py-3 text-left font-bold">Nomor</th>
                                <th className="px-4 py-3 text-left font-bold">Waktu</th>
                                <th className="px-4 py-3 text-left font-bold">Kanal</th>
                                <th className="px-4 py-3 text-left font-bold">Status</th>
                                <th className="px-4 py-3 text-right font-bold">Item</th>
                                <th className="px-4 py-3 text-right font-bold">Total</th>
                                <th className="px-4 py-3 text-left font-bold">Kasir</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-line">
                            {orders.map((order) => (
                                <tr key={order.id} className="hover:bg-surface-2/50">
                                    <td className="px-4 py-3 font-semibold">
                                        <Link to={`/pesanan/${order.id}`} className="text-primary hover:underline">
                                            {order.number}
                                        </Link>
                                    </td>
                                    <td className="px-4 py-3 text-xs text-ink-2">{dateTime(order.placed_at)}</td>
                                    <td className="px-4 py-3 text-xs">
                                        {orderChannelLabel(order.channel)}
                                        {order.table_label && <span className="block text-ink-2">Meja {order.table_label}</span>}
                                    </td>
                                    <td className="px-4 py-3">
                                        <Badge className={statusBadgeClass(order.status)}>{statusLabel(order.status)}</Badge>
                                    </td>
                                    <td className="px-4 py-3 text-right tabular">{order.items_count ?? '-'}</td>
                                    <td className="px-4 py-3 text-right tabular font-semibold">{money(order.total)}</td>
                                    <td className="px-4 py-3 text-xs text-ink-2">
                                        {order.cashier?.name ?? '-'}
                                        {order.customer && <span className="block">{order.customer.name}</span>}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </div>
    );
}