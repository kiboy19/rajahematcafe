import { useState } from 'react';
import { Link } from 'react-router-dom';
import { dateTime, money } from '../api';
import { mutate, useFlash, useResource } from '../hooks';
import { Button, EmptyState, ErrorBox, Field, inputClass, Loading, Money, PageHeader, Panel, QuickAmountChips } from '../ui';

interface WalletData {
    phone: string;
    customer: { id: number; name: string; phone: string; balance: number } | null;
    session: { id: number; cashier?: { name: string } | null } | null;
    activeSession: { id: number; status: string; opened_at: string; opening_float: number; cashier?: { id: number; name: string } | null } | null;
    minTopup: number;
    dailyLimit: number;
    todayTopups: number;
    recentTopups: Array<{ id: number; amount: number; balance_after: number; created_at: string; customer?: { name: string; phone: string } | null }>;
}

const quickAmounts = [20000, 50000, 100000, 200000, 500000];

export function WalletPage() {
    const [phone, setPhone] = useState('');
    const [query, setQuery] = useState('');
    const { data, loading, error, reload } = useResource<WalletData>(`/api/saldo${query ? `?hp=${encodeURIComponent(query)}` : ''}`);
    const { flashSuccess, flashError } = useFlash();

    const [amount, setAmount] = useState('');
    const [note, setNote] = useState('');
    const [busy, setBusy] = useState(false);

    const search = (event: React.FormEvent) => {
        event.preventDefault();
        setQuery(phone.trim());
    };

    const topUp = async (event: React.FormEvent) => {
        event.preventDefault();
        if (!data?.customer) {
            flashError('Cari pelanggan terlebih dahulu.');
            return;
        }

        const numericAmount = Number(amount);
        if (numericAmount < (data.minTopup || 10000)) {
            flashError(`Minimal top-up ${money(data.minTopup || 10000)}.`);
            return;
        }

        setBusy(true);
        const result = await mutate('POST', '/api/saldo/topup', {
            phone: data.customer.phone,
            amount: numericAmount,
            note: note.trim() || undefined,
        });
        setBusy(false);

        if (!result.ok) {
            flashError(result.message);
            return;
        }

        flashSuccess(result.message ?? 'Top-up berhasil.');
        setAmount('');
        setNote('');
        reload();
    };

    return (
        <div className="flex flex-col gap-5 max-w-7xl w-full">
            <PageHeader
                title="Top-Up Saldo"
                subtitle="Isi saldo pelanggan tunai di kasir."
                actions={
                    data?.session ? (
                        <span className="inline-flex items-center gap-2 rounded-full bg-ok-bg px-3 py-1.5 text-xs font-semibold text-ok">
                            <span aria-hidden="true" className="h-2 w-2 rounded-full bg-ok" />
                            Sesi kasir aktif · Uang masuk laci kas
                        </span>
                    ) : data?.activeSession ? (
                        <Link
                            to="/sesi-kasir"
                            className="inline-flex items-center gap-2 rounded-full bg-info-bg px-3 py-1.5 text-xs font-semibold text-info hover:underline"
                        >
                            <span aria-hidden="true" className="h-2 w-2 rounded-full bg-info" />
                            Sesi aktif oleh {data.activeSession.cashier?.name ?? 'kasir lain'}
                        </Link>
                    ) : (
                        <Link
                            to="/sesi-kasir"
                            className="inline-flex items-center gap-2 rounded-full bg-warn-bg px-3 py-1.5 text-xs font-semibold text-warn hover:underline"
                        >
                            <span aria-hidden="true" className="h-2 w-2 rounded-full bg-warn" />
                            Buka sesi kasir dahulu →
                        </Link>
                    )
                }
            />

            <div className="grid gap-5 lg:grid-cols-[1.4fr_1fr] items-start">
                <div className="flex flex-col gap-5">
                    <Panel className="p-5 flex flex-col gap-4">
                        <div>
                            <h2 className="text-xs font-bold uppercase tracking-wider text-ink-2">Langkah 1 · Cari Pelanggan</h2>
                            <form onSubmit={search} className="mt-2 flex gap-2">
                                <label className="sr-only" htmlFor="phone-search">Nomor HP Pelanggan</label>
                                <input
                                    id="phone-search"
                                    className={inputClass}
                                    type="tel"
                                    inputMode="numeric"
                                    autoComplete="tel"
                                    placeholder="Nomor HP (contoh: 08123456789)"
                                    value={phone}
                                    onChange={(event) => setPhone(event.target.value)}
                                />
                                <Button type="submit">Cari</Button>
                            </form>
                        </div>

                        {loading && <Loading label="Mencari pelanggan..." />}
                        {error && <ErrorBox message={error} />}

                        {!loading && !error && data && (
                            <>
                                {data.customer ? (
                                    <>
                                        <div className="rounded-card bg-surface-2 p-4">
                                            <h2 className="text-xs font-bold uppercase tracking-wider text-ink-2">Langkah 2 · Konfirmasi Pelanggan</h2>
                                            <div className="mt-3 flex items-start justify-between">
                                                <div>
                                                    <p className="font-display text-xl font-bold">{data.customer.name}</p>
                                                    <p className="text-xs text-ink-2">{data.customer.phone}</p>
                                                </div>
                                                <div className="text-right">
                                                    <p className="text-xs text-ink-2">Saldo saat ini</p>
                                                    <Money value={data.customer.balance} className="block font-display text-2xl font-bold text-primary" />
                                                </div>
                                            </div>
                                            <div className="mt-3 flex justify-between border-t border-line pt-2 text-xs text-ink-2">
                                                <span>Top-up hari ini: <Money value={data.todayTopups} /></span>
                                                <span>Min: <Money value={data.minTopup} /> · Limit: <Money value={data.dailyLimit} /></span>
                                            </div>
                                        </div>

                                        {data.session ? (
                                            <form onSubmit={topUp} className="flex flex-col gap-3">
                                                <h2 className="text-xs font-bold uppercase tracking-wider text-ink-2">Langkah 3 · Isi Nominal</h2>
                                                <QuickAmountChips amounts={quickAmounts} value={amount} onChange={setAmount} />
                                                <Field label="Nominal Top-Up (Rp)">
                                                    <input
                                                        className={inputClass}
                                                        type="number"
                                                        min={data.minTopup}
                                                        step={1000}
                                                        inputMode="numeric"
                                                        placeholder={String(data.minTopup)}
                                                        value={amount}
                                                        onChange={(event) => setAmount(event.target.value)}
                                                        required
                                                    />
                                                </Field>
                                                <Field label="Catatan (opsional)">
                                                    <input
                                                        className={inputClass}
                                                        maxLength={255}
                                                        placeholder="Contoh: uang pas, titipan"
                                                        value={note}
                                                        onChange={(event) => setNote(event.target.value)}
                                                    />
                                                </Field>
                                                <Button type="submit" className="h-11" disabled={busy || !amount}>
                                                    {amount ? `Simpan Top-Up · ${money(Number(amount))}` : 'Simpan Top-Up'}
                                                </Button>
                                            </form>
                                        ) : (
                                            <div className="rounded-input border border-warn/20 bg-warn-bg p-3 text-xs text-warn">
                                                {data.activeSession
                                                    ? `Sesi kasir aktif oleh ${data.activeSession.cashier?.name ?? 'kasir lain'}. Hanya kasir pemegang sesi yang dapat menerima top-up tunai.`
                                                    : 'Sesi kasir belum dibuka. Top-up tunai membutuhkan uang fisik masuk ke laci.'}{' '}
                                                <Link to="/sesi-kasir" className="font-semibold underline">
                                                    Lihat sesi kasir
                                                </Link>
                                            </div>
                                        )}
                                    </>
                                ) : data.phone ? (
                                    <div className="rounded-input border border-warn/20 bg-warn-bg p-3 text-sm text-warn">
                                        Pelanggan dengan nomor <span className="font-semibold">{data.phone}</span> tidak ditemukan.
                                    </div>
                                ) : (
                                    <EmptyState
                                        title="Masukkan nomor HP"
                                        hint="Ketik nomor HP pelanggan lalu tekan Cari untuk memeriksa saldo dan mengisi saldo."
                                    />
                                )}
                            </>
                        )}
                    </Panel>
                </div>

                <div className="flex flex-col gap-5">
                    <Panel className="overflow-hidden">
                        <div className="border-b border-line bg-surface-2 px-4 py-3">
                            <h2 className="text-xs font-bold uppercase tracking-wider text-ink-2">Top-Up Terakhir di Sesi Ini</h2>
                        </div>
                        {data?.recentTopups?.length ? (
                            <ul className="divide-y divide-line">
                                {data.recentTopups.map((tx) => (
                                    <li key={tx.id} className="flex items-center justify-between p-4 text-sm">
                                        <div>
                                            <span className="font-semibold block">{tx.customer?.name ?? 'Pelanggan'}</span>
                                            <span className="text-xs text-ink-2">{tx.customer?.phone ?? '-'} · {dateTime(tx.created_at)}</span>
                                        </div>
                                        <div className="text-right">
                                            <Money value={tx.amount} className="block font-semibold text-ok" />
                                            <span className="text-xs text-ink-2">Saldo akhir: <Money value={tx.balance_after} /></span>
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        ) : (
                            <p className="p-4 text-sm text-ink-2">Belum ada transaksi top-up pada sesi kasir yang aktif.</p>
                        )}
                    </Panel>
                </div>
            </div>
        </div>
    );
}
