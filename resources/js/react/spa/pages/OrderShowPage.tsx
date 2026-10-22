import { useEffect, useRef, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { dateTime, money, statusBadgeClass, statusLabel } from '../api';
import { mutate, useFlash, useResource } from '../hooks';
import {
    Badge,
    Button,
    ErrorBox,
    Field,
    inputClass,
    Toast,
} from '../ui-primitives';
import { Loading, Money, Panel } from '../ui';
import { payWithSnap } from '../midtrans';
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

type Method = 'midtrans' | 'cash' | 'wallet' | 'edc' | 'qris';

const methods: Array<{ value: Method; label: string; hint: string }> = [
    { value: 'midtrans', label: 'Pembayaran Digital', hint: 'QRIS, e-wallet, atau Virtual Account lewat Midtrans. Lunas otomatis.' },
    { value: 'cash', label: 'Tunai', hint: 'Terima uang tunai, sistem menghitung kembalian.' },
    { value: 'wallet', label: 'Saldo Pelanggan', hint: 'Potong saldo member berdasarkan nomor HP.' },
    { value: 'edc', label: 'Mesin EDC (manual)', hint: 'Gesek/tap di mesin EDC kafe, lalu masukkan kode referensi.' },
    { value: 'qris', label: 'QRIS Statis (manual)', hint: 'Scan QRIS merchant kafe, lalu masukkan kode referensi.' },
];

const manualMethods: Method[] = ['edc', 'qris'];

/** Baris pembayaran pending/settled dari Midtrans (tanpa kasir & tanpa sesi kasir). */
function isGatewayPayment(payment: Payment): boolean {
    return payment.reference?.startsWith('MIDTRANS-') === true;
}

interface SnapCheckout {
    snap_token?: string | null;
    redirect_url?: string | null;
    order_id?: string;
}

declare global {
    interface Window {
        snap?: {
            pay: (
                token: string,
                callbacks?: {
                    onSuccess?: () => void;
                    onPending?: () => void;
                    onError?: () => void;
                    onClose?: () => void;
                }
            ) => void;
        };
    }
}

/**
 * Dialog pembayaran digital kasir.
 * Membuka Snap lewat `snap_token` (popup) atau `redirect_url` (fallback),
 * lalu menunggu webhook memastikan lunas — respons browser bukan bukti bayar.
 */
function MidtransStatusCard({
    order,
    checkout,
    onClose,
    onPaid,
    onError,
}: {
    order: OrderDetail['order'];
    checkout: SnapCheckout | null;
    onClose: () => void;
    onPaid: () => void;
    onError: (message: string) => void;
}) {
    const { data } = useResource<OrderDetail>(`/api/pesanan/${order.id}`, { pollMs: 5000 });
    const paid = data?.order.payments.some(
        (payment) => payment.status === 'paid' && payment.amount >= order.total
    );
    const snapOpened = useRef(false);

    useEffect(() => {
        if (!checkout || snapOpened.current) {
            return;
        }

        const token = checkout.snap_token ?? '';
        const redirectUrl = checkout.redirect_url ?? '';

        if (token) {
            snapOpened.current = true;
            payWithSnap(token, {
                onSuccess: () => undefined,
                onPending: () => undefined,
                onError: () => onError('Pembayaran Midtrans gagal. Silakan coba lagi.'),
                onClose: () => undefined,
            }).catch((error: Error) => onError(error.message));

            return;
        }

        if (redirectUrl) {
            snapOpened.current = true;
            window.location.href = redirectUrl;

            return;
        }

        snapOpened.current = true;
        onError('Halaman pembayaran Midtrans tidak tersedia. Muat ulang halaman dan coba lagi.');
    }, [checkout, onError]);

    return (
        <Panel className="border-primary bg-primary/5 p-4 flex flex-col gap-3">
            <div className="flex items-center justify-between gap-2">
                <div className="flex items-center gap-3">
                    <span className="relative flex h-8 w-8 items-center justify-center rounded-full bg-primary/10">
                        <svg className="h-5 w-5 text-primary animate-spin" viewBox="0 0 24 24"><circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" fill="none"/><path className="opacity-75" d="M12 2a10 10 0 0 1 10 10" stroke="currentColor" strokeWidth="4" strokeLinecap="round" fill="none"/></svg>
                    </span>
                    <div>
                        <p className="font-semibold text-ink">Pembayaran Digital Dibuka</p>
                        <p className="text-xs text-ink-2">Pesanan {order.number} · <Money value={order.total} className="font-bold" /></p>
                    </div>
                </div>
                <Button variant="ghost" size="sm" onClick={onClose}>Tutup</Button>
            </div>

            <p role="status" aria-live="polite" className="rounded-input bg-info-bg p-3 text-xs text-info">
                {paid
                    ? 'Pembayaran diterima. Halaman diperbarui otomatis.'
                    : 'Menunggu pembayaran… Status diperbarui setiap 5 detik.'}
            </p>

            {(checkout?.snap_token || checkout?.redirect_url) && !paid && (
                <Button
                    variant="outline"
                    size="sm"
                    onClick={() => {
                        const url = checkout.redirect_url;
                        if (url) {
                            window.location.href = url;
                        } else if (checkout.snap_token) {
                            payWithSnap(checkout.snap_token).catch((error: Error) => onError(error.message));
                        }
                    }}
                >
                    Buka halaman pembayaran
                </Button>
            )}

            {paid && (
                <Button
                    onClick={() => window.location.href = `/pesanan/${order.id}/struk`}
                >
                    Cetak Struk
                </Button>
            )}
        </Panel>
    );
}

export function OrderShowPage({ user }: { user: StaffUser }) {
    const { id } = useParams<{ id: string }>();
    const { data, loading, error, reload } = useResource<OrderDetail>(`/api/pesanan/${id}`);
    const { message: toast, flashSuccess, flashError } = useFlash();

    const [method, setMethod] = useState<Method>('midtrans');
    const [amount, setAmount] = useState('');
    const [tendered, setTendered] = useState('');
    const [reference, setReference] = useState('');
    const [customerPhone, setCustomerPhone] = useState('');
    const [voidReason, setVoidReason] = useState('');
    const [busy, setBusy] = useState(false);
    const [midtransOrder, setMidtransOrder] = useState<OrderDetail['order'] | null>(null);
    const [midtransCheckout, setMidtransCheckout] = useState<SnapCheckout | null>(null);

    if (loading) {
        return <Loading label="Memuat detail pesanan..." />;
    }

    if (error || !data) {
        return <ErrorBox message={error ?? 'Gagal memuat pesanan.'} />;
    }

    const { order, openSession } = data;
    const paymentTotal = order.payments
        .filter((p) => p.status === 'paid')
        .reduce((sum, p) => sum + p.amount, 0);
    const remainingBalance = Math.max(0, order.total - paymentTotal);
    const gatewayPending = order.payments.some(
        (p) => p.status === 'pending' && isGatewayPayment(p)
    );
    const editableAmount = manualMethods.includes(method) || method === 'cash';

    const submitPayment = async (event: React.FormEvent) => {
        event.preventDefault();

        if (method === 'midtrans') {
            setBusy(true);
            const result = await mutate<SnapCheckout>('POST', `/api/pesanan/${order.id}/bayar/midtrans`);
            setBusy(false);

            if (!result.ok) {
                flashError(result.message);
                return;
            }

            setMidtransCheckout(result.data ?? null);
            setMidtransOrder(order);
            return;
        }

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
        setAmount('');
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
                    {order.paid_at && (
                        <Link
                            to={`/pesanan/${order.id}/struk`}
                            className="mb-3 inline-flex h-9 items-center rounded-input bg-primary px-3 text-xs font-semibold text-white hover:opacity-90"
                        >
                            Cetak Invoice / Struk
                        </Link>
                    )}

                    {order.payments.length === 0 ? (
                        <p className="text-xs text-ink-2">Belum ada pembayaran.</p>
                    ) : (
                        <div className="space-y-2">
                            {order.payments.map((p) => (
                                <div key={p.id} className="p-3 rounded-input bg-surface-2 flex flex-col gap-1">
                                    <div className="flex justify-between text-xs">
                                        <span className="font-semibold">
                                            {isGatewayPayment(p) ? 'Midtrans (Digital)' : methodLabel(p.method)} · {statusLabel(p.status)}
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
                                            <Button variant="destructive" className="h-8 px-3 text-xs" onClick={() => voidPayment(p.id)} disabled={busy}>
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
                        <Money value={paymentTotal} className="text-ok text-sm" />
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
                                <fieldset>
                                    <legend className="mb-2 text-xs font-semibold text-ink-2">
                                        Metode pembayaran · sisa tagihan <Money value={remainingBalance} className="font-bold text-ink" />
                                    </legend>
                                    <div className="grid gap-2 sm:grid-cols-2">
                                        {methods.map((option) => (
                                            <label
                                                key={option.value}
                                                className={`flex cursor-pointer gap-2 rounded-input border p-3 text-xs transition ${
                                                    method === option.value
                                                        ? 'border-primary bg-primary/5'
                                                        : 'border-line hover:border-line-strong'
                                                }`}
                                            >
                                                <input
                                                    type="radio"
                                                    name="method"
                                                    value={option.value}
                                                    checked={method === option.value}
                                                    onChange={() => setMethod(option.value)}
                                                    className="mt-0.5"
                                                />
                                                <span>
                                                    <span className="block font-semibold text-ink">{option.label}</span>
                                                    <span className="block text-ink-2">{option.hint}</span>
                                                </span>
                                            </label>
                                        ))}
                                    </div>
                                </fieldset>

                                {editableAmount && (
                                    <Field label="Nominal Dibayar (Rp)">
                                        <input
                                            className={inputClass}
                                            type="number"
                                            inputMode="numeric"
                                            min={1}
                                            max={remainingBalance}
                                            placeholder={String(remainingBalance)}
                                            value={amount}
                                            onChange={(e) => setAmount(e.target.value)}
                                        />
                                        <span className="mt-1 block text-[11px] text-ink-2">
                                            Kosongkan untuk melunasi penuh sisa tagihan.
                                        </span>
                                    </Field>
                                )}

                                {method === 'cash' && (
                                    <Field label="Uang Tunai Diterima (Rp)">
                                        <input
                                            className={inputClass}
                                            type="number"
                                            inputMode="numeric"
                                            min={0}
                                            placeholder="Nominal uang dari pelanggan"
                                            value={tendered}
                                            onChange={(e) => setTendered(e.target.value)}
                                        />
                                    </Field>
                                )}

                                {manualMethods.includes(method) && (
                                    <Field label="Nomor Referensi (opsional)">
                                        <input
                                            className={inputClass}
                                            placeholder="Kode dari struk EDC / QRIS merchant, jika ada"
                                            value={reference}
                                            onChange={(e) => setReference(e.target.value)}
                                        />
                                    </Field>
                                )}

                                {method === 'wallet' && (
                                    <Field label="Nomor HP Pelanggan">
                                        <input
                                            className={inputClass}
                                            type="tel"
                                            inputMode="tel"
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
                                    <Button type="submit" pending={busy}>
                                        {method === 'midtrans' ? 'Buat Pembayaran Digital' : 'Simpan Pembayaran'}
                                    </Button>
                                </div>

                                {gatewayPending && method !== 'midtrans' && (
                                    <p role="status" className="rounded-input bg-info-bg p-3 text-[11px] text-info">
                                        Ada pembayaran digital Midtrans yang masih menunggu. Pembayaran akan lunas otomatis setelah pelanggan menyelesaikannya.
                                    </p>
                                )}
                            </form>
                        )}
                    </div>
                )}
            </Panel>

            {midtransOrder && (
                <MidtransStatusCard
                    order={midtransOrder}
                    checkout={midtransCheckout}
                    onClose={() => {
                        setMidtransOrder(null);
                        setMidtransCheckout(null);
                        reload();
                    }}
                    onPaid={() => {
                        flashSuccess('Pembayaran digital diterima. Pesanan lunas.');
                        setMidtransOrder(null);
                        setMidtransCheckout(null);
                        reload();
                    }}
                    onError={flashError}
                />
            )}

            <Toast message={toast} />
        </div>
    );
}

function methodLabel(method: string): string {
    return {
        cash: 'Tunai',
        qris: 'QRIS',
        edc: 'Kartu EDC',
        wallet: 'Saldo Pelanggan',
        va: 'Virtual Account',
    }[method] ?? method;
}
