import { useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { dateTime, money, statusBadgeClass, statusLabel } from '../api';
import { mutate, useFlash, useResource } from '../hooks';
import { Badge, Button, EmptyState, ErrorBox, Field, inputClass, Loading, Money, PageHeader, Panel } from '../ui';
import type { CustomerUser } from '../session';

interface MenuItem {
    id: number;
    name: string;
    price: number;
    is_available: boolean;
}

interface MenuCategory {
    id: number;
    name: string;
    menu_items: MenuItem[];
}

interface CafeTableOption {
    id: number;
    label: string;
    seats: number;
}

interface CustomerMenuData {
    customer: CustomerUser;
    table: { id: number; label: string } | null;
    tables: CafeTableOption[];
    categories: MenuCategory[];
    lines: Array<{ menu_item: MenuItem; quantity: number; line_total: number; note: string | null }>;
    bill: { subtotal: number; tax: number; service: number; grand_total: number };
}

export function CustomerMenuPage({ user }: { user: CustomerUser }) {
    const { data, loading, error, reload } = useResource<CustomerMenuData>('/api/meja');
    const { message, flashSuccess, flashError } = useFlash();
    const navigate = useNavigate();
    const [busy, setBusy] = useState(false);
    const [voucherCode, setVoucherCode] = useState('');
    const [note, setNote] = useState('');

    if (loading) return <Loading label="Memuat menu..." />;
    if (error || !data) return <ErrorBox message={error ?? 'Gagal memuat menu.'} />;

    const chooseTable = async (tableId: number) => {
        setBusy(true);
        const result = await mutate('POST', '/api/meja/pilih', { table_id: tableId });
        setBusy(false);
        if (!result.ok) return flashError(result.message);
        flashSuccess('Meja dipilih. Silakan pilih menu.');
        reload();
    };

    if (!data.table) {
        return (
            <div className="max-w-xl mx-auto flex flex-col gap-4">
                <PageHeader title="Pilih Meja Anda" subtitle="Pilih nomor meja tempat Anda duduk untuk mulai memesan." />
                {message && <ErrorBox message={message.text} />}
                {data.tables.length === 0 ? (
                    <EmptyState title="Belum ada meja tersedia." hint="Hubungi kasir untuk info meja." />
                ) : (
                    <div className="grid grid-cols-3 gap-3">
                        {data.tables.map((table) => (
                            <button
                                key={table.id}
                                className="bg-surface border border-line rounded-card shadow-1 p-4 flex flex-col items-center gap-1 hover:border-primary hover:shadow-2 transition disabled:opacity-50"
                                onClick={() => chooseTable(table.id)}
                                disabled={busy}
                            >
                                <span className="font-display text-xl font-bold text-primary">{table.label}</span>
                                <span className="text-xs text-ink-2">{table.seats} kursi</span>
                            </button>
                        ))}
                    </div>
                )}
                <p className="text-xs text-ink-2 text-center mt-2">
                    Sudah memindai QR di meja? Silakan pesan langsung dari halaman menu meja.
                </p>
            </div>
        );
    }

    const add = async (item: MenuItem) => {
        setBusy(true);
        const result = await mutate('POST', '/api/meja/keranjang', { menu_item_id: item.id, quantity: 1 });
        setBusy(false);
        if (!result.ok) return flashError(result.message);
        flashSuccess(`${item.name} ditambahkan.`);
        reload();
    };

    const update = async (id: number, quantity: number) => {
        setBusy(true);
        const result = await mutate('PATCH', '/api/meja/keranjang', { menu_item_id: id, quantity });
        setBusy(false);
        if (!result.ok) return flashError(result.message);
        reload();
    };

    const checkout = async () => {
        if (data.lines.length === 0) return flashError('Keranjang Anda masih kosong.');
        setBusy(true);
        const result = await mutate<{ order_id: number; order_number: string; total: number; paid: boolean }>(
            'POST',
            '/api/meja/pesan',
            { voucher_code: voucherCode || undefined, note: note || undefined }
        );
        setBusy(false);
        if (!result.ok) return flashError(result.message);

        // Arahkan langsung ke halaman pembayaran pesanan
        navigate(`/akun/pesanan/${result.data?.order_id}/bayar`);
    };

    return (
        <div className="max-w-xl mx-auto flex flex-col gap-4">
            <div className="rounded-card bg-accent text-ink-on-dark p-4 shadow-1 flex items-center justify-between">
                <div>
                    <span className="text-[10px] uppercase font-bold tracking-wider opacity-75 block">Pemesanan di Meja</span>
                    <span className="font-display text-xl font-bold block">{data.table.label}</span>
                </div>
                <div className="text-right">
                    <span className="text-[10px] uppercase font-bold tracking-wider opacity-75 block">Saldo Anda</span>
                    <Money value={user.balance} className="text-base font-bold tabular block" />
                </div>
            </div>

            {data.categories.map((category) => category.menu_items.length > 0 && (
                <section key={category.id} className="bg-surface border border-line rounded-card shadow-1 p-4">
                    <h2 className="font-display text-sm font-bold text-ink uppercase tracking-wider mb-3">{category.name}</h2>
                    <div className="flex flex-col divide-y divide-line">
                        {category.menu_items.map((item) => (
                            <div key={item.id} className="py-3 first:pt-0 last:pb-0 flex items-center justify-between gap-3">
                                <div className="min-w-0">
                                    <p className="font-semibold text-sm text-ink truncate">{item.name}</p>
                                    <Money value={item.price} className="text-xs text-ink-2 tabular mt-0.5" />
                                </div>
                                <Button variant="outline" className="h-9 px-3 text-xs" onClick={() => add(item)} disabled={busy || !item.is_available}>
                                    + Tambah
                                </Button>
                            </div>
                        ))}
                    </div>
                </section>
            ))}

            <section className="bg-surface border-2 border-primary/20 rounded-card shadow-1 p-4">
                <h3 className="font-display text-sm font-bold text-ink mb-3">Keranjang Pesanan ({data.lines.length})</h3>
                {data.lines.length === 0 ? (
                    <EmptyState title="Keranjang masih kosong." hint="Pilih menu di atas untuk mulai memesan." />
                ) : (
                    <div className="flex flex-col divide-y divide-line">
                        {data.lines.map((line) => (
                            <div key={line.menu_item.id} className="py-2.5 flex items-center justify-between gap-3 text-xs">
                                <div className="flex-1">
                                    <span className="font-semibold text-sm block">{line.menu_item.name}</span>
                                    <Money value={line.line_total} className="text-ink-2" />
                                </div>
                                <div className="flex items-center gap-2">
                                    <button className="w-9 h-9 rounded-full border border-line font-bold" onClick={() => update(line.menu_item.id, line.quantity - 1)} disabled={busy}>−</button>
                                    <span className="w-5 text-center font-bold">{line.quantity}</span>
                                    <button className="w-9 h-9 rounded-full border border-line font-bold" onClick={() => update(line.menu_item.id, line.quantity + 1)} disabled={busy}>+</button>
                                </div>
                            </div>
                        ))}
                    </div>
                )}

                {data.lines.length > 0 && (
                    <div className="mt-4 pt-4 border-t border-line">
                        <div className="space-y-1 text-xs text-ink-2">
                            <div className="flex justify-between"><span>Subtotal</span><Money value={data.bill.subtotal} /></div>
                            <div className="flex justify-between"><span>PB1</span><Money value={data.bill.tax} /></div>
                            <div className="flex justify-between"><span>Layanan</span><Money value={data.bill.service} /></div>
                            <div className="flex justify-between pt-2 border-t border-line font-bold text-ink">
                                <span>Total</span>
                                <Money value={data.bill.grand_total} className="text-primary text-lg" />
                            </div>
                        </div>
                        <Field label="Voucher (opsional)" className="mt-4">
                            <input className={inputClass} value={voucherCode} onChange={(e) => setVoucherCode(e.target.value)} placeholder="KODE VOUCHER" />
                        </Field>
                        <Field label="Catatan Pesanan" className="mt-3">
                            <input className={inputClass} value={note} onChange={(e) => setNote(e.target.value)} placeholder="Catatan untuk dapur" />
                        </Field>
                        <Button className="w-full mt-4 h-12" onClick={checkout} disabled={busy}>
                            Lanjut ke Pembayaran →
                        </Button>
                    </div>
                )}
            </section>
        </div>
    );
}

interface OrderDetail {
    id: number;
    number: string;
    status: string;
    channel: string;
    table_label: string | null;
    subtotal: number;
    discount: number;
    tax: number;
    service: number;
    total: number;
    note: string | null;
    placed_at: string;
    paid_at: string | null;
    items: Array<{ id: number; name: string; quantity: number; line_total: number; note: string | null }>;
    payments: Array<{ id: number; method: string; status: string; amount: number }>;
}

interface CustomerOrderData {
    order: OrderDetail;
    refresh: number | null;
}

export function CustomerPaymentPage({ user }: { user: CustomerUser }) {
    const { id } = useParams<{ id: string }>();
    const { data, loading, error } = useResource<CustomerOrderData>(`/api/akun/pesanan/${id}`);
    const { flashSuccess, flashError } = useFlash();
    const navigate = useNavigate();
    const [method, setMethod] = useState<'wallet' | 'qris' | 'va'>('wallet');
    const [submitting, setSubmitting] = useState(false);

    if (loading) return <Loading label="Memuat pembayaran pesanan..." />;
    if (error || !data) return <ErrorBox message={error ?? 'Pesanan tidak ditemukan.'} />;

    const { order } = data;

    if (order.paid_at || order.status !== 'menunggu_pembayaran') {
        return (
            <div className="max-w-xl mx-auto flex flex-col gap-4">
                <Panel className="p-6 text-center space-y-3">
                    <p className="text-ok font-bold text-lg">✓ Pesanan Sudah Dibayar</p>
                    <p className="text-sm text-ink-2">Pesanan Anda telah dikirim ke dapur dan sedang diproses.</p>
                    <Link to={`/akun/pesanan/${order.id}`}>
                        <Button className="mt-2">Lihat Status Pesanan</Button>
                    </Link>
                </Panel>
            </div>
        );
    }

    const pay = async () => {
        setSubmitting(true);
        const result = await mutate<{
            order_id: number;
            paid: boolean;
            payment?: { redirect_url?: string; snap_token?: string; mode?: 'midtrans' | 'mock' };
        }>(
            'POST',
            `/api/meja/pesanan/${order.id}/bayar`,
            { payment_method: method }
        );
        setSubmitting(false);

        if (!result.ok) {
            flashError(result.message);
            return;
        }

        if (result.data?.paid) {
            flashSuccess('Pembayaran berhasil! Pesanan diteruskan ke dapur.');
            navigate(`/akun/pesanan/${order.id}`);
            return;
        }

        const payment = result.data?.payment;

        // Jika mode mock (lokal tanpa server key Midtrans), sediakan simulasi
        if (payment?.mode === 'mock') {
            const confirmSimulate = window.confirm(
                'Pembayaran online dibuat dalam mode simulasi lokal. Klik OK untuk mensimulasikan pelunasan (settlement).'
            );
            if (confirmSimulate) {
                setSubmitting(true);
                const sim = await mutate<{ paid: boolean }>('POST', `/api/meja/pesanan/${order.id}/bayar-simulasi`);
                setSubmitting(false);
                if (sim.ok) {
                    flashSuccess('Pembayaran lunas! Pesanan masuk ke antrean dapur.');
                    navigate(`/akun/pesanan/${order.id}`);
                    return;
                }
                flashError(sim.message);
            }
        }

        if (payment?.redirect_url) {
            window.location.href = payment.redirect_url;
        } else if (payment?.snap_token && (window as any).snap) {
            (window as any).snap.pay(payment.snap_token, {
                onSuccess: () => navigate(`/akun/pesanan/${order.id}`),
                onPending: () => navigate(`/akun/pesanan/${order.id}`),
                onError: () => flashError('Pembayaran gagal. Silakan coba lagi.'),
            });
        } else {
            flashSuccess('Pembayaran dibuat. Silakan selesaikan pembayaran.');
            navigate(`/akun/pesanan/${order.id}`);
        }
    };

    return (
        <div className="max-w-xl mx-auto flex flex-col gap-4">
            <PageHeader title="Pilih Metode Pembayaran" subtitle={`Pesanan #${order.number} · Meja ${order.table_label ?? '—'}`} />

            {message && (
                <div className={`p-3 rounded-card text-xs font-semibold ${message.tone === 'error' ? 'bg-bad/10 text-bad border border-bad' : 'bg-ok/10 text-ok border border-ok'}`}>
                    {message.text}
                </div>
            )}

            <Panel className="p-5">
                <div className="flex justify-between items-center pb-4 border-b border-line">
                    <span className="text-sm text-ink-2">Total Pembayaran</span>
                    <Money value={order.total} className="text-2xl font-display font-bold text-primary" />
                </div>

                <div className="mt-4 space-y-3">
                    {/* Opsi 1: Saldo Akun */}
                    <label
                        className={`p-4 border rounded-card flex items-center justify-between cursor-pointer transition ${
                            method === 'wallet' ? 'border-primary bg-primary/5' : 'border-line hover:border-ink-2'
                        }`}
                    >
                        <div className="flex items-center gap-3">
                            <input
                                type="radio"
                                name="method"
                                value="wallet"
                                checked={method === 'wallet'}
                                onChange={() => setMethod('wallet')}
                            />
                            <div>
                                <span className="font-semibold block text-sm">Saldo Kafe</span>
                                <span className="text-xs text-ink-2">
                                    Tersedia: {money(user.balance)} {user.balance < order.total && <span className="text-bad">(kurang)</span>}
                                </span>
                            </div>
                        </div>
                        {user.balance < order.total && (
                            <Link to="/akun/saldo" className="text-xs text-primary font-semibold hover:underline">
                                Top-up Saldo
                            </Link>
                        )}
                    </label>

                    {/* Opsi 2: QRIS */}
                    <label
                        className={`p-4 border rounded-card flex items-center justify-between cursor-pointer transition ${
                            method === 'qris' ? 'border-primary bg-primary/5' : 'border-line hover:border-ink-2'
                        }`}
                    >
                        <div className="flex items-center gap-3">
                            <input
                                type="radio"
                                name="method"
                                value="qris"
                                checked={method === 'qris'}
                                onChange={() => setMethod('qris')}
                            />
                            <div>
                                <span className="font-semibold block text-sm">QRIS (Gopay / OVO / ShopeePay / BCA / All Bank)</span>
                                <span className="text-xs text-ink-2">Pindai kode QR instan via aplikasi e-wallet / m-banking</span>
                            </div>
                        </div>
                    </label>

                    {/* Opsi 3: Virtual Account */}
                    <label
                        className={`p-4 border rounded-card flex items-center justify-between cursor-pointer transition ${
                            method === 'va' ? 'border-primary bg-primary/5' : 'border-line hover:border-ink-2'
                        }`}
                    >
                        <div className="flex items-center gap-3">
                            <input
                                type="radio"
                                name="method"
                                value="va"
                                checked={method === 'va'}
                                onChange={() => setMethod('va')}
                            />
                            <div>
                                <span className="font-semibold block text-sm">Virtual Account (Bank Transfer)</span>
                                <span className="text-xs text-ink-2">BCA, Mandiri, BNI, BRI, Permata</span>
                            </div>
                        </div>
                    </label>
                </div>

                <Button className="w-full mt-6 h-12" onClick={pay} disabled={submitting}>
                    {submitting ? 'Memproses...' : `Bayar Sekarang (${money(order.total)})`}
                </Button>
            </Panel>
        </div>
    );
}

export function CustomerOrderShowPage() {
    const { id } = useParams<{ id: string }>();
    const { data, loading, error } = useResource<CustomerOrderData>(`/api/akun/pesanan/${id}`, { pollMs: 10000 });

    if (loading) return <Loading label="Memuat pesanan..." />;
    if (error || !data) return <ErrorBox message={error ?? 'Pesanan tidak ditemukan.'} />;

    const { order } = data;
    const isWaitingPayment = order.status === 'menunggu_pembayaran';
    const paid = order.payments.filter((p) => p.status === 'paid').reduce((sum, p) => sum + p.amount, 0);

    return (
        <div className="max-w-xl mx-auto flex flex-col gap-4">
            <Link to="/akun" className="text-xs font-semibold text-primary hover:underline">← Kembali ke akun</Link>

            <Panel className="p-5">
                <div className="flex items-start justify-between gap-3 border-b border-line pb-4">
                    <div>
                        <p className="text-[10px] uppercase tracking-wider text-ink-2">Nomor pesanan</p>
                        <h1 className="font-display text-xl font-bold">{order.number}</h1>
                        <p className="text-xs text-ink-2 mt-1">{dateTime(order.placed_at)} · {order.table_label ? `Meja ${order.table_label}` : 'Takeaway'}</p>
                    </div>
                    <Badge className={statusBadgeClass(order.status)}>{statusLabel(order.status)}</Badge>
                </div>

                {isWaitingPayment && (
                    <div className="mt-4 p-4 rounded-card bg-warn/10 border border-warn text-ink flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div>
                            <p className="font-bold text-sm text-warn">Belum Dibayar</p>
                            <p className="text-xs text-ink-2 mt-0.5">Pesanan belum masuk ke dapur. Selesaikan pembayaran sekarang.</p>
                        </div>
                        <Link to={`/akun/pesanan/${order.id}/bayar`} className="shrink-0">
                            <Button className="w-full sm:w-auto h-9 text-xs">Bayar Sekarang ({money(order.total)})</Button>
                        </Link>
                    </div>
                )}

                <div className="py-4 space-y-3">
                    {order.items.map((item) => (
                        <div key={item.id} className="flex justify-between text-sm">
                            <span>{item.quantity} × {item.name}</span>
                            <Money value={item.line_total} />
                        </div>
                    ))}
                </div>

                <div className="border-t border-line pt-3 text-xs space-y-1">
                    <div className="flex justify-between"><span>Subtotal</span><Money value={order.subtotal} /></div>
                    {order.discount > 0 && <div className="flex justify-between text-ok"><span>Diskon</span><Money value={order.discount} /></div>}
                    <div className="flex justify-between"><span>PB1</span><Money value={order.tax} /></div>
                    <div className="flex justify-between"><span>Layanan</span><Money value={order.service} /></div>
                    <div className="flex justify-between border-t border-line pt-2 font-bold text-base">
                        <span>Total</span>
                        <Money value={order.total} className="text-primary" />
                    </div>
                </div>

                <div className="mt-4 rounded-input bg-info-bg text-info p-3 text-xs">
                    {order.status === 'selesai'
                        ? 'Pesanan selesai. Terima kasih!'
                        : order.status === 'dibatalkan'
                        ? 'Pesanan dibatalkan.'
                        : order.status === 'menunggu_pembayaran'
                        ? 'Menunggu pelunasan. Pesanan akan otomatis dikirim ke dapur segera setelah pembayaran selesai.'
                        : 'Pesanan sedang diproses oleh dapur. Halaman akan diperbarui otomatis.'}
                </div>

                <div className="mt-3 text-xs text-ink-2">Terbayar: <Money value={paid} /></div>
            </Panel>
        </div>
    );
}
