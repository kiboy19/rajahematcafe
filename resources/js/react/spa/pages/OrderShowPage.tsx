import { useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { dateTime, money, paymentMethodLabel, statusBadgeClass, statusLabel } from '../api';
import { mutate, useFlash, useResource } from '../hooks';
import { Badge, Button, ErrorBox, Field, inputClass, Loading, Money, Panel, selectClass } from '../ui';
import type { StaffUser } from '../session';

interface Payment {
    id: number;
    method: string;
    status: string;
    amount: number;
    tendered?: number | null;
    change?: number | null;
    reference?: string | null;
    paid_at?: string | null;
}

interface OrderItem {
    id: number;
    name: string;
    unit_price: number;
    quantity: number;
    line_total: number;
    note?: string | null;
}

interface OrderDetail {
    order: {
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
        note: string | null;
        placed_at: string;
        paid_at: string | null;
        items: OrderItem[];
        payments: Payment[];
        voucher?: { code: string } | null;
        customer?: { name: string; phone: string } | null;
        cashier?: { name: string } | null;
    };
    openSession?: { id: number } | null;
}

export function OrderShowPage({ user }: { user: StaffUser }) {
    const { id } = useParams<{ id: string }>();
    const { data, loading, error, reload } = useResource<OrderDetail>(`/api/pesanan/${id}`);
    const { message, flashSuccess, flashError } = useFlash();

    const [method, setMethod] = useState<'cash' | 'qris' | 'edc' | 'wallet'>('cash');
    const [amount, setAmount] = useState('');
    const [tendered, setTendered] = useState('');
    const [reference, setReference] = useState('');
    const [customerPhone, setCustomerPhone] = useState('');
    const [voidReason, setVoidReason] = useState('');
    const [busy, setBusy] = useState(false);

    if (loading) {
        return <Loading label="Memuat detail pesanan..." />;
    }

    if (error || !data) {
        return <ErrorBox message={error ?? 'Gagal memuat pesanan.'} />;
    }

    const { order, openSession } = data;

    const paidTotal = order.payments
        .filter((p) => p.status === 'paid')
        .reduce((sum, p) => sum + p.amount, 0);
    const remainingBalance = Math.max(0, order.total - paidTotal);

    const submitPayment = async (event: React.FormEvent) => {
        event.preventDefault();
        setBusy(true);

        const result = await mutate('POST', `/api/pesanan/${order.id}/bayar`, {
            method,
            amount: Number(amount || remainingBalance),
            tendered: method === 'cash' ? Number(tendered || amount || remainingBalance) : undefined,
            reference: reference.trim() || undefined,
            customer_phone: method === 'wallet' ? (customerPhone || order.customer?.phone) : undefined,
        });

        setBusy(false);

        if (!result.ok) {
            flashError(result.message);
            return;
        }

        flashSuccess(result.message ?? 'Pembayaran berhasil dicatat.');
        setTendered('');
        setReference('');
        reload();
    };

    const voidPayment = async (paymentId: number) => {
        if (!voidReason || voidReason.length < 5) {
            flashError('Alasan pembatalan minimal 5 karakter.');
            return;
        }

        setBusy(true);
        const result = await mutate('DELETE', `/api/pesanan/${order.id}/bayar/${paymentId}`, {
            reason: voidReason,
        });
        setBusy(false);

        if (!result.ok) {
            flashError(result.message);
            return;
        }

        flashSuccess(result.message ?? 'Pembayaran dibatalkan.');
        setVoidReason('');
        reload();
    };

    return (
        <div className="max-w-3xl flex flex-col gap-4">
            <Link to="/pesanan" className="text-xs font-semibold text-primary hover:underline">
                ← Kembali ke pesanan
            </Link>

            <Panel className="p-5 flex flex-col gap-4">
                <div className="flex items-start justify-between gap-4 border-b border-line pb-4">
                    <div>
                        <p className="text-[10px] uppercase tracking-widest text-ink-2">Pesanan dibuat</p>
                        <h1 className="font-display text-xl font-bold">{order.number}</h1>
                        <p className="text-xs text-ink-2 mt-1">
                            {dateTime(order.placed_at)} · {order.channel}
                            {order.table_label ? ` · Meja ${order.table_label}` : ''}
                        </p>
                    </div>

                    <Badge className={statusBadgeClass(order.status)}>{statusLabel(order.status)}</Badge>
                </div>

                <div className="py-2 flex flex-col gap-3">
                    {order.items.map((item) => (
                        <div key={item.id} className="flex justify-between gap-4 text-sm">
                            <div>
                                <span className="font-semibold">{item.quantity} × {item.name}</span>
                                {item.note && <span className="block text-xs text-ink-2">Catatan: {item.note}</span>}
                            </div>
                            <Money value={item.line_total} className="tabular font-semibold" />
                        </div>
                    ))}
                    {order.note && <p className="text-xs text-ink-2 border-t border-line pt-2">Catatan pesanan: {order.note}</p>}
                </div>

                <div className="border-t border-line pt-3 flex flex-col gap-1 text-xs text-ink-2">
                    <div className="flex justify-between"><span>Subtotal</span><Money value={order.subtotal} /></div>
                    {order.discount > 0 && (
                        <div className="flex justify-between text-ok">
                            <span>Diskon{order.voucher ? ` · ${order.voucher.code}` : ''}</span>
                            <span>−<Money value={order.discount} /></span>
                        </div>
                    )}
                    <div className="flex justify-between"><span>PB1 ({order.tax_percent}%)</span><Money value={order.tax} /></div>
                    <div className="flex justify-between"><span>Layanan ({order.service_percent}%)</span><Money value={order.service} /></div>
                    <div className="flex justify-between font-bold text-ink text-base border-t border-line mt-2 pt-2">
                        <span>Total</span>
                        <Money value={order.total} className="text-primary text-lg" />
                    </div>
                </div>

                <div className="mt-4 border-t border-line pt-4">
                    <div className="flex items-center justify-between gap-2 mb-2">
                        <h3 className="text-xs font-bold uppercase tracking-wider">Pembayaran</h3>
                        <Link to={`/pesanan/${order.id}/struk`} className="text-xs font-semibold text-primary hover:underline">
                            Lihat / cetak struk
                        </Link>
                    </div>

                    {order.payments.length === 0 ? (
                        <p className="text-xs text-ink-2">Belum ada pembayaran.</p>
                    ) : (
                        <div className="space-y-2">
                            {order.payments.map((p) => (
                                <div key={p.id} className="p-3 rounded-input bg-surface-2 flex flex-col gap-1">
                                    <div className="flex justify-between text-xs">
                                        <span className="font-semibold">
                                            {paymentMethodLabel(p.method)} · {statusLabel(p.status)}
                                        </span>
                                        <Money value={p.amount} className={`font-bold tabular ${p.status === 'void' ? 'line-through text-ink-2' : ''}`} />
                                    </div>
                                    {p.reference && <p className="text-[11px] text-ink-2">Ref: {p.reference}</p>}
                                    {p.change !== null && p.change !== undefined && p.change > 0 && (
                                        <p className="text-[11px] text-ink-2">Tunai diterima <Money value={p.tendered} /> · kembali <Money value={p.change} /></p>
                                    )}

                                    {p.status === 'paid' && ['owner', 'manager'].includes(user.role) && (
                                        <div className="mt-2 pt-2 border-t border-line flex gap-2">
                                            <input
                                                className="h-8 flex-1 px-2 rounded-input border border-line-strong bg-surface text-xs"
                                                placeholder="Alasan void (min 5 karakter)..."
                                                value={voidReason}
                                                onChange={(e) => setVoidReason(e.target.value)}
                                            />
                                            <Button variant="danger" className="h-8 px-3 text-xs" onClick={() => voidPayment(p.id)} disabled={busy}>
                                                Batalkan Pembayaran
                                            </Button>
                                        </div>
                                    )}
                                </div>
                            ))}
                        </div>
                    )}

                    <div className="flex justify-between text-xs mt-3 pt-2 border-t border-line font-bold">
                        <span>Terbayar</span>
                        <Money value={paidTotal} className="text-ok text-sm" />
                    </div>
                </div>

                {/* Form Terima Pembayaran */}
                {remainingBalance > 0 && order.status !== 'dibatalkan' && (
                    <div className="mt-4 border-t border-line pt-4">
                        <h3 className="text-xs font-bold uppercase tracking-wider mb-3">Terima Pembayaran</h3>

                        {!openSession ? (
                            <div className="rounded-input bg-warn-bg text-warn p-3 text-xs">
                                Sesi kasir Anda belum dibuka. <Link to="/sesi-kasir" className="font-bold underline">Buka sesi kasir</Link> untuk menerima pembayaran.
                            </div>
                        ) : (
                            <form onSubmit={submitPayment} className="flex flex-col gap-3">
                                <div className="grid gap-3 sm:grid-cols-2">
                                    <Field label="Metode">
                                        <select
                                            className={selectClass}
                                            value={method}
                                            onChange={(e) => setMethod(e.target.value as any)}
                                        >
                                            <option value="cash">Tunai</option>
                                            <option value="qris">QRIS</option>
                                            <option value="edc">Kartu / EDC</option>
                                            <option value="wallet">Saldo Pelanggan</option>
                                        </select>
                                    </Field>

                                    <Field label="Nominal Dibayar (Rp)">
                                        <input
                                            className={inputClass}
                                            type="number"
                                            min={1}
                                            max={remainingBalance}
                                            placeholder={String(remainingBalance)}
                                            value={amount}
                                            onChange={(e) => setAmount(e.target.value)}
                                        />
                                    </Field>
                                </div>

                                {method === 'cash' && (
                                    <Field label="Uang Tunai Diterima (Rp)">
                                        <input
                                            className={inputClass}
                                            type="number"
                                            min={0}
                                            placeholder="Nominal uang dari pelanggan"
                                            value={tendered}
                                            onChange={(e) => setTendered(e.target.value)}
                                        />
                                    </Field>
                                )}

                                {['qris', 'edc'].includes(method) && (
                                    <Field label="Nomor Referensi">
                                        <input
                                            className={inputClass}
                                            placeholder="Nomor referensi dari mesin merchant"
                                            value={reference}
                                            onChange={(e) => setReference(e.target.value)}
                                            required
                                        />
                                    </Field>
                                )}

                                {method === 'wallet' && (
                                    <Field label="Nomor HP Pelanggan">
                                        <input
                                            className={inputClass}
                                            placeholder="0812..."
                                            value={customerPhone || order.customer?.phone || ''}
                                            onChange={(e) => setCustomerPhone(e.target.value)}
                                            required
                                        />
                                    </Field>
                                )}

                                <div className="flex items-center justify-between pt-2">
                                    <span className="text-xs text-ink-2">
                                        Sisa tagihan: <Money value={remainingBalance} className="font-bold text-ink" />
                                    </span>
                                    <Button type="submit" disabled={busy}>
                                        Simpan Pembayaran
                                    </Button>
                                </div>
                            </form>
                        )}
                    </div>
                )}
            </Panel>
        </div>
    );
}