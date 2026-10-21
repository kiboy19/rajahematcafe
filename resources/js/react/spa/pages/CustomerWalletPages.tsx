import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { mutate, useFlash, useResource } from '../hooks';
import { Button, ErrorBox, Field, inputClass, Loading, Money, PageHeader, Panel } from '../ui';
import type { CustomerUser } from '../session';

interface TopUpCheckout {
    snap_token: string | null;
    redirect_url: string | null;
    order_id: string;
    mode: 'midtrans' | 'mock';
}

interface WalletData {
    customer: CustomerUser;
    minTopup: number;
    dailyLimit: number;
    todayTopups: number;
    recentTopups: Array<{ id: number; amount: number; balance_after: number; created_at: string; note: string | null }>;
}

export function CustomerWalletTopUpPage({ user }: { user: CustomerUser }) {
    const { data, loading, error } = useResource<WalletData>('/api/akun/saldo');
    const { message, flashSuccess, flashError } = useFlash();
    const navigate = useNavigate();
    const [amount, setAmount] = useState('');
    const [busy, setBusy] = useState(false);
    const [lastOrderId, setLastOrderId] = useState<string | null>(null);

    if (loading) return <Loading label="Memuat saldo..." />;
    if (error) return <ErrorBox message={error} />;

    const customer = data?.customer ?? user;
    const minTopup = data?.minTopup ?? 10000;
    const dailyLimit = data?.dailyLimit ?? 2000000;
    const todayTopups = data?.todayTopups ?? 0;
    const recent = data?.recentTopups ?? [];

    const submit = async (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const nominal = parseInt(amount, 10);
        if (isNaN(nominal) || nominal < minTopup) {
            flashError(`Nominal minimal Rp${new Intl.NumberFormat('id-ID').format(minTopup)}.`);
            return;
        }
        if (nominal > dailyLimit - todayTopups) {
            flashError(`Melebihi batas harian (Rp${new Intl.NumberFormat('id-ID').format(dailyLimit)}). Sudah top-up hari ini: Rp${new Intl.NumberFormat('id-ID').format(todayTopups)}.`);
            return;
        }
        setBusy(true);
        const result = await mutate<TopUpCheckout>('POST', '/api/akun/saldo/topup', { amount: nominal });
        setBusy(false);

        if (!result.ok) return flashError(result.message);

        const checkout = result.data!;
        setLastOrderId(checkout.order_id);

        if (checkout.mode === 'midtrans') {
            // Redirect ke halaman pembayaran Midtrans
            if (checkout.redirect_url) {
                window.location.href = checkout.redirect_url;
            } else if (checkout.snap_token) {
                // Fallback: pakai Snap.js jika redirect_url tidak tersedia
                if ((window as any).snap) {
                    (window as any).snap.pay(checkout.snap_token, {
                        onSuccess: () => navigate('/akun?topup=success'),
                        onPending: () => navigate('/akun?topup=pending'),
                        onError: () => flashError('Pembayaran gagal. Silakan coba lagi.'),
                        onClose: () => {},
                    });
                } else {
                    flashError('Gagal memuat pembayaran Midtrans. Silakan coba lagi.');
                }
            } else {
                flashError('Tidak ada URL pembayaran. Hubungi administrator.');
            }
        } else {
            // Mode simulasi: tampilkan tombol simulasi atau auto-settlement jika local
            flashSuccess('Top-up dibuat dalam mode simulasi. Gunakan tombol "Simulasi Lunas" di bawah.');
        }
    };

    const simulate = async () => {
        if (!lastOrderId) return flashError('Tidak ada transaksi untuk disimulasikan.');
        setBusy(true);
        const result = await mutate('POST', '/api/akun/saldo/topup-simulasi', { order_id: lastOrderId });
        setBusy(false);
        if (!result.ok) return flashError(result.message);
        flashSuccess('Saldo berhasil di-top-up (simulasi).');
        setLastOrderId(null);
        window.location.reload();
    };

    return (
        <div className="max-w-xl mx-auto flex flex-col gap-5">
            <PageHeader title="Top-up Saldo" subtitle={`Saldo saat ini: Rp${new Intl.NumberFormat('id-ID').format(customer.balance)}`} />

            <Panel className="p-5">
                <h2 className="font-display text-lg font-bold mb-3">Top-up Online</h2>
                <p className="text-sm text-ink-2 mb-4">
                    Top-up saldo via Midtrans (VA, QRIS, e-Wallet, dll). Saldo masuk otomatis setelah pembayaran terverifikasi.
                </p>

                <form onSubmit={submit} className="space-y-4">
                    <Field label={`Nominal Top-up (Min. Rp${new Intl.NumberFormat('id-ID').format(minTopup)})`}>
                        <input
                            className={inputClass}
                            type="number"
                            min={minTopup}
                            step={minTopup}
                            value={amount}
                            onChange={(e) => setAmount(e.target.value)}
                            placeholder="Contoh: 50000"
                            required
                            disabled={busy}
                        />
                    </Field>
                    <div className="text-xs text-ink-2">
                        Sisa batas harian: <span className="font-bold">{new Intl.NumberFormat('id-ID').format(dailyLimit - todayTopups)}</span>
                        {' / '}
                        Harian: {new Intl.NumberFormat('id-ID').format(dailyLimit)}
                    </div>
                    <Button type="submit" className="w-full h-12" disabled={busy}>
                        {busy ? 'Memproses...' : `Bayar Rp${new Intl.NumberFormat('id-ID').format(parseInt(amount) || minTopup)}`}
                    </Button>
                </form>

                {lastOrderId && (
                    <div className="mt-4 p-3 rounded-input bg-info-bg text-info text-sm space-y-2">
                        <p>Transaksi: <code className="font-mono">{lastOrderId}</code></p>
                        <p>Menunggu pembayaran...</p>
                        <Button variant="outline" className="w-full" onClick={simulate} disabled={busy}>
                            Simulasi Lunas (Hanya Mode Dev)
                        </Button>
                    </div>
                )}
            </Panel>

            <Panel className="p-5">
                <h2 className="font-display text-lg font-bold mb-3">Riwayat Top-up Terbaru</h2>
                {recent.length === 0 ? (
                    <p className="text-xs text-ink-2">Belum ada riwayat top-up.</p>
                ) : (
                    <div className="divide-y divide-line text-sm">
                        {recent.map((t) => (
                            <div key={t.id} className="py-2.5 flex justify-between items-center">
                                <div>
                                    <span className="font-semibold block">Top-up Tunai</span>
                                    <span className="text-xs text-ink-2">{new Date(t.created_at).toLocaleString('id-ID')}</span>
                                </div>
                                <div className="text-right">
                                    <span className="tabular font-bold text-ok">+Rp{new Intl.NumberFormat('id-ID').format(t.amount)}</span>
                                    <span className="block text-[11px] text-ink-2">Saldo: Rp{new Intl.NumberFormat('id-ID').format(t.balance_after)}</span>
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </Panel>
        </div>
    );
}