import { Link } from 'react-router-dom';
import { useResource } from '../hooks';
import { routeToPath } from '../navigation';
import { Button, Loading, Money, PageHeader, Panel, StatCard } from '../ui';
import type { StaffUser } from '../session';

interface DashboardData {
    stats: {
        today_orders: number;
        today_revenue: number;
        active_orders: number;
        today_payments: number;
    };
    session?: {
        id: number;
        opening_float: number;
        status: string;
        opened_at: string;
        cashier?: { id: number; name: string } | null;
    } | null;
}

export function DashboardPage({ user }: { user: StaffUser }) {
    const { data, loading, error, reload } = useResource<DashboardData>('/api/dashboard');

    if (loading) {
        return <Loading label="Memuat ringkasan dasbor..." />;
    }

    if (error || !data) {
        return (
            <Panel className="p-6">
                <p className="text-bad">{error ?? 'Gagal memuat data dasbor.'}</p>
                <Button className="mt-3" onClick={reload}>Coba Lagi</Button>
            </Panel>
        );
    }

    const { stats, session } = data;
    const isOwnerOrManager = user.role === 'owner' || user.role === 'manager';

    return (
        <div className="flex flex-col gap-6 max-w-7xl w-full">
            <PageHeader
                title={`Halo, ${user.name}`}
                subtitle={`Anda masuk sebagai ${user.role_label}. Pilih modul kerja untuk melanjutkan operasional.`}
                actions={
                    <Button variant="outline" onClick={reload}>
                        Segarkan Data
                    </Button>
                }
            />

            {/* Quick Stat Cards */}
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard label="Pesanan Hari Ini" value={stats.today_orders} />
                <StatCard label="Pendapatan Hari Ini" value={<Money value={stats.today_revenue} />} tone="primary" />
                <StatCard label="Pesanan Aktif Dapur" value={stats.active_orders} tone={stats.active_orders > 0 ? 'warn' : undefined} />
                <StatCard label="Pembayaran Tercatat" value={<Money value={stats.today_payments} />} tone="ok" />
            </div>

            {/* Status Sesi Kasir */}
            <Panel className="p-5 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p className="text-xs uppercase tracking-wider text-ink-2 font-bold">Status Sesi Kasir</p>
                    {session ? (
                        <div className="mt-1 flex flex-wrap items-center gap-2">
                            <span className="w-2.5 h-2.5 rounded-full bg-ok inline-block"></span>
                            <span className="font-semibold text-sm">
                                Sesi aktif {session.cashier?.name ? `oleh ${session.cashier.name}` : ''} · Modal awal <Money value={session.opening_float} />
                            </span>
                        </div>
                    ) : (
                        <div className="mt-1 flex items-center gap-2">
                            <span className="w-2.5 h-2.5 rounded-full bg-bad inline-block"></span>
                            <span className="font-semibold text-sm text-ink-2">Belum ada sesi kasir yang dibuka</span>
                        </div>
                    )}
                </div>

                <Link to="/sesi-kasir">
                    <Button variant={session ? 'outline' : 'primary'}>
                        {session ? (isOwnerOrManager ? 'Pantau Sesi Kasir' : 'Kelola Sesi Kasir') : 'Buka Sesi Kasir'}
                    </Button>
                </Link>
            </Panel>

            {/* Pintasan Modul Sesuai Navigasi Staf */}
            <div>
                <h2 className="font-display text-lg font-bold mb-3">Modul Operasional</h2>
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    {user.navigation.map((item) => (
                        <Link
                            key={item.route}
                            to={routeToPath(item.route)}
                            className="p-4 rounded-card border border-line bg-surface shadow-1 hover:border-primary transition-colors flex flex-col justify-between"
                        >
                            <span className="font-bold text-sm text-ink">{item.label}</span>
                            <span className="text-xs text-primary font-semibold mt-2">Buka modul →</span>
                        </Link>
                    ))}
                </div>
            </div>
        </div>
    );
}