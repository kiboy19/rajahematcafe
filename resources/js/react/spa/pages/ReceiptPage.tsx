import { Link } from 'react-router-dom';
import { useResource } from '../hooks';
import { dateTime, statusLabel } from '../api';
import { ErrorBox, Loading, Money, Panel } from '../ui';

interface ReceiptOrder {
    id: number;
    number: string;
    channel: string;
    status: string;
    table_label: string | null;
    subtotal: number;
    discount: number;
    tax: number;
    service: number;
    total: number;
    tax_percent: number;
    service_percent: number;
    placed_at: string;
    paid_at: string | null;
    note: string | null;
    items: Array<{ id: number; name: string; quantity: number; unit_price: number; line_total: number; note: string | null }>;
    payments: Array<{ id: number; method: string; status: string; amount: number; tendered: number | null; change: number | null; reference: string | null; paid_at: string | null }>;
    cashier?: { name: string } | null;
    customer?: { name: string; phone: string } | null;
}

export function ReceiptPage() {
    const id = window.location.pathname.split('/').filter(Boolean).at(-2);
    const { data, loading, error } = useResource<ReceiptOrder>(`/api/pesanan/${id}/struk`);

    if (loading) return <Loading label="Memuat struk..." />;
    if (error || !data) return <ErrorBox message={error ?? 'Struk tidak ditemukan.'} />;

    const order = (data as unknown as { order?: ReceiptOrder }).order ?? data;

    return (
        <div className="max-w-md mx-auto">
            <div className="print:hidden flex items-center justify-between mb-4">
                <Link to={`/pesanan/${order.id}`} className="text-xs font-semibold text-primary hover:underline">← Kembali</Link>
                <button className="h-9 px-3 rounded-input border border-line text-xs font-semibold" onClick={() => window.print()}>Cetak</button>
            </div>

            <Panel className="p-6 print:border-0 print:shadow-none">
                <div className="text-center border-b border-line pb-4">
                    <h1 className="font-display text-xl font-bold">RAJAHEMAT CAFE</h1>
                    <p className="text-xs text-ink-2 mt-1">Coffee &amp; Eatery</p>
                    <p className="text-xs text-ink-2 mt-3">{order.number}</p>
                    <p className="text-[11px] text-ink-2">{dateTime(order.placed_at)}</p>
                </div>

                <div className="py-4 space-y-2 text-sm">
                    {order.items.map((item) => (
                        <div key={item.id} className="flex justify-between gap-3">
                            <div>
                                <span className="font-semibold">{item.quantity} × {item.name}</span>
                                <span className="block text-xs text-ink-2"><Money value={item.unit_price} /></span>
                            </div>
                            <Money value={item.line_total} className="font-semibold" />
                        </div>
                    ))}
                </div>

                <div className="border-t border-line pt-3 space-y-1 text-xs text-ink-2">
                    <div className="flex justify-between"><span>Subtotal</span><Money value={order.subtotal} /></div>
                    {order.discount > 0 && <div className="flex justify-between"><span>Diskon</span><Money value={order.discount} /></div>}
                    <div className="flex justify-between"><span>PB1</span><Money value={order.tax} /></div>
                    <div className="flex justify-between"><span>Layanan</span><Money value={order.service} /></div>
                    <div className="flex justify-between border-t border-line pt-2 text-base font-bold text-ink"><span>Total</span><Money value={order.total} className="text-primary" /></div>
                </div>

                <div className="border-t border-line mt-4 pt-4 text-xs">
                    <p className="font-bold mb-2">Pembayaran</p>
                    {order.payments.map((payment) => (
                        <div key={payment.id} className="flex justify-between py-1">
                            <span>{payment.method} · {statusLabel(payment.status)}</span>
                            <Money value={payment.amount} />
                        </div>
                    ))}
                </div>

                <p className="text-center text-xs text-ink-2 mt-6">Terima kasih atas kunjungan Anda.</p>
            </Panel>
        </div>
    );
}
