import { useState } from 'react';
import { money, paymentMethodLabel } from '../api';
import { useResource } from '../hooks';
import { Button, EmptyState, ErrorBox, inputClass, Loading, Money, PageHeader, Panel, StatCard } from '../ui';

interface ReportData {
    date: string;
    orders: Array<{ id: number; number: string; channel: string; status: string; total: number }>;
    orderCount: number;
    grossSales: number;
    discountTotal: number;
    taxTotal: number;
    serviceTotal: number;
    paymentsByMethod: Array<{ method: string; total: number; jumlah: number }>;
    cashPaid: number;
    cashTopups: number;
    sessions: Array<{ id: number; opening_float: number; expected_cash: number; counted_cash: number | null; variance: number | null; status: string; cashier?: { name: string } | null }>;
}

export function ReportsPage() {
    const [date, setDate] = useState(() => new Date().toISOString().slice(0, 10));
    const { data, loading, error, reload } = useResource<ReportData>(`/api/laporan/harian?tanggal=${date}`);

    return (
        <div className="max-w-7xl w-full flex flex-col gap-5">
            <PageHeader
                title="Laporan Harian"
                subtitle="Rekap penjualan, pembayaran, dan sesi kasir."
                actions={
                    <div className="flex items-center gap-2">
                        <input type="date" className={inputClass + ' w-auto'} value={date} onChange={(event) => setDate(event.target.value)} />
                        <Button variant="outline" onClick={reload}>Tampilkan</Button>
                    </div>
                }
            />

            {loading ? <Loading label="Memuat laporan..." /> : error ? <ErrorBox message={error} /> : data && (
                <>
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <StatCard label="Pesanan" value={data.orderCount} />
                        <StatCard label="Penjualan Bruto" value={<Money value={data.grossSales} />} tone="primary" />
                        <StatCard label="Total Diskon" value={<Money value={data.discountTotal} />} tone="warn" />
                        <StatCard label="PB1 + Layanan" value={<Money value={data.taxTotal + data.serviceTotal} />} />
                    </div>

                    <div className="grid gap-5 lg:grid-cols-2 items-start">
                    <Panel className="p-5">
                        <h2 className="font-display text-lg font-bold mb-3">Pembayaran per Metode</h2>
                        {data.paymentsByMethod.length === 0 ? (
                            <p className="text-sm text-ink-2">Belum ada pembayaran pada tanggal ini.</p>
                        ) : (
                            <div className="space-y-2">
                                {data.paymentsByMethod.map((row) => (
                                    <div key={row.method} className="flex justify-between text-sm border-b border-line pb-2">
                                        <span className="font-semibold">{paymentMethodLabel(row.method)} <span className="text-ink-2 font-normal">({row.jumlah} transaksi)</span></span>
                                        <Money value={row.total} className="font-bold" />
                                    </div>
                                ))}
                                <div className="flex justify-between text-xs text-ink-2 pt-1">
                                    <span>Tunai masuk laci</span><Money value={data.cashPaid} />
                                </div>
                                <div className="flex justify-between text-xs text-ink-2">
                                    <span>Top-up saldo (tunai)</span><Money value={data.cashTopups} />
                                </div>
                            </div>
                        )}
                    </Panel>

                    <Panel className="p-5">
                        <h2 className="font-display text-lg font-bold mb-3">Sesi Kasir</h2>
                        {data.sessions.length === 0 ? (
                            <p className="text-sm text-ink-2">Tidak ada sesi kasir pada tanggal ini.</p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead><tr className="border-b border-line text-[11px] uppercase tracking-wider text-ink-2"><th className="p-2 text-left">Kasir</th><th className="p-2 text-right">Modal Awal</th><th className="p-2 text-right">Diperlukan</th><th className="p-2 text-right">Terhitung</th><th className="p-2 text-right">Selisih</th></tr></thead>
                                    <tbody className="divide-y divide-line">
                                        {data.sessions.map((s) => (
                                            <tr key={s.id}>
                                                <td className="p-2">{s.cashier?.name ?? '-'}</td>
                                                <td className="p-2 text-right tabular"><Money value={s.opening_float} /></td>
                                                <td className="p-2 text-right tabular"><Money value={s.expected_cash} /></td>
                                                <td className="p-2 text-right tabular"><Money value={s.counted_cash} /></td>
                                                <td className={`p-2 text-right tabular font-bold ${s.variance && s.variance !== 0 ? 'text-bad' : 'text-ok'}`}><Money value={s.variance} /></td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </Panel>
                    </div>

                    <Panel className="p-5">
                        <h2 className="font-display text-lg font-bold mb-3">Daftar Pesanan</h2>
                        {data.orders.length === 0 ? (
                            <EmptyState title="Tidak ada pesanan pada tanggal ini." />
                        ) : (
                            <div className="space-y-1">
                                {data.orders.map((o) => (
                                    <div key={o.id} className="flex justify-between text-sm border-b border-line py-2">
                                        <span className="font-mono">{o.number}</span>
                                        <span className="text-ink-2 text-xs">{o.channel}</span>
                                        <Money value={o.total} className="font-semibold" />
                                    </div>
                                ))}
                            </div>
                        )}
                    </Panel>
                </>
            )}
        </div>
    );
}