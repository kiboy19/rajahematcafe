import { Link } from 'react-router-dom';
import { dateTime, money, statusBadgeClass, statusLabel } from '../api';
import { useResource } from '../hooks';
import { Badge, Button, EmptyState, ErrorBox, Loading, Money, PageHeader, Panel } from '../ui';
import type { CustomerUser } from '../session';

interface CustomerAccountData {
    customer: CustomerUser;
    orders: Array<{ id: number; number: string; total: number; status: string; placed_at: string; items_count?: number }>;
    transactions: Array<{ id: number; type: string; amount: number; balance_after: number; created_at: string; note?: string | null }>;
    stats?: { orders_count: number; total_spent: number } | null;
}

export function CustomerAccountPage({ user }: { user: CustomerUser }) {
    const { data, loading, error } = useResource<CustomerAccountData>('/api/akun');

    if (loading) return <Loading label="Memuat akun..." />;
    if (error) return <ErrorBox message={error} />;

    const customer = data?.customer ?? user;
    const orders = data?.orders ?? [];
    const transactions = data?.transactions ?? [];

    return (
        <div className="max-w-3xl flex flex-col gap-5">
            <PageHeader title={`Halo, ${customer.name}`} subtitle="Kelola akun, saldo, dan riwayat pesanan Anda." />

            {/* Profil & Saldo */}
            <div className="rounded-card bg-accent text-ink-on-dark p-5 shadow-1 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <p className="text-xs uppercase tracking-wider opacity-75 font-bold">Saldo Tersedia</p>
                    <Money value={customer.balance} className="text-3xl font-display font-bold block mt-1" />
                    <p className="text-xs opacity-75 mt-2">{customer.phone} · {customer.email}</p>
                </div>
                <div className="flex flex-col gap-2 shrink-0">
                    <Link to="/akun/saldo">
                        <Button variant="outline" className="w-full bg-surface text-ink hover:bg-surface-2 border-0">
                            Top-up Saldo
                        </Button>
                    </Link>
                    <Link to="/meja">
                        <Button variant="outline" className="w-full bg-surface text-ink hover:bg-surface-2 border-0">
                            Pesan Menu
                        </Button>
                    </Link>
                </div>
            </div>

            {/* Riwayat Pesanan */}
            <Panel className="p-5">
                <h2 className="font-display text-lg font-bold mb-3">Riwayat Pesanan</h2>
                {orders.length === 0 ? (
                    <EmptyState title="Belum ada pesanan." hint="Pindai QR meja di kafe untuk mulai memesan." />
                ) : (
                    <div className="divide-y divide-line">
                        {orders.map((order) => (
                            <Link key={order.id} to={`/akun/pesanan/${order.id}`} className="py-3 flex items-center justify-between hover:bg-surface-2 -mx-2 px-2 rounded-input">
                                <div>
                                    <span className="font-mono font-semibold text-primary block">{order.number}</span>
                                    <span className="text-xs text-ink-2">{dateTime(order.placed_at)} · {order.items_count ?? 1} item</span>
                                </div>
                                <div className="text-right">
                                    <Money value={order.total} className="font-bold tabular block" />
                                    <Badge className={statusBadgeClass(order.status) + ' text-[9px]'}>{statusLabel(order.status)}</Badge>
                                </div>
                            </Link>
                        ))}
                    </div>
                )}
            </Panel>

            {/* Riwayat Mutasi Saldo */}
            <Panel className="p-5">
                <h2 className="font-display text-lg font-bold mb-3">Mutasi Saldo</h2>
                {transactions.length === 0 ? (
                    <p className="text-xs text-ink-2">Belum ada mutasi saldo.</p>
                ) : (
                    <div className="divide-y divide-line text-sm">
                        {transactions.map((t) => (
                            <div key={t.id} className="py-2.5 flex justify-between items-center">
                                <div>
                                    <span className="font-semibold block">{t.type === 'top_up' ? 'Top-up Tunai' : 'Pembayaran Pesanan'}</span>
                                    <span className="text-xs text-ink-2">{dateTime(t.created_at)} {t.note ? `· ${t.note}` : ''}</span>
                                </div>
                                <div className="text-right">
                                    <span className={`tabular font-bold ${t.type === 'top_up' ? 'text-ok' : 'text-bad'}`}>
                                        {t.type === 'top_up' ? '+' : '−'}{money(t.amount)}
                                    </span>
                                    <span className="block text-[11px] text-ink-2">Saldo: {money(t.balance_after)}</span>
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </Panel>
        </div>
    );
}