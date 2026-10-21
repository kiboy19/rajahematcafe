import { useState } from 'react';
import { dateTime, money } from '../api';
import { mutate, useFlash, useResource } from '../hooks';
import { Button, EmptyState, ErrorBox, Field, inputClass, Loading, Money, PageHeader, Panel } from '../ui';

interface WalletData {
    phone: string;
    customer: { id: number; name: string; phone: string; balance: number } | null;
    session: { id: number } | null;
    minTopup: number;
    dailyLimit: number;
    todayTopups: number;
    recentTopups: Array<{ id: number; amount: number; balance_after: number; created_at: string; customer?: { name: string; phone: string } | null }>;
}

export function WalletPage() {
    const [phone, setPhone] = useState('');
    const [query, setQuery] = useState('');
    const { data, loading, error, reload } = useResource<WalletData>(`/api/saldo${query ? `?hp=${encodeURIComponent(query)}` : ''}`);
    const { message, flashSuccess, flashError } = useFlash();

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

        setBusy(true);
        const result = await mutate('POST', '/api/saldo/topup', {
            phone: data.customer.phone,
            amount: Number(amount),
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
        <div className="max-w-3xl flex flex-col gap-5">
            <PageHeader title="Top-Up Saldo" subtitle="Isi saldo pelanggan dengan pembayaran tunai di kasir." />

            <Panel className="p-5">
                <form onSubmit={search} className="flex gap-2">
                    <input
                        className={inputClass}
                        type="tel"
                        placeholder="Cari nomor HP pelanggan (0812...)"
                        value={phone}
                        onChange={(event) => setPhone(event.target.value)}
                    />
                    <Button type="submit">Cari</Button>
                </form>

                {loading && <p className="mt-4 text-sm text-ink-2">Memuat data pelanggan...</p>}
                {error && <div className="mt-4"><ErrorBox message={error} /></div>}

                {!loading && !error && data && (
                    <>
                        {data.customer ? (
                            <div className="mt-5 pt-5 border-t border-line">
                                <div className="bg-surface-2 rounded-card p-4 flex items-start justify-between">
                                    <div>
                                        <p className="text-xs text-ink-2">Pelanggan</p>
                                        <p className="font-bold text-lg">{data.customer.name}</p>
                                        <p className="text-sm text-ink-2">{data.customer.phone}</p>
                                    </div>
                                    <div className="text-right">
                                        <p className="text-xs text-ink-2">Saldo Saat Ini</p>
                                        <Money value={data.customer.balance} className="text-2xl font-display font-bold text-primary tabular" />
                                    </div>
                                </div>

                                <div className="mt-2 text-xs text-ink-2 flex justify-between">
                                    <span>Top-up hari ini: <Money value={data.todayTopups} /></span>
                                    <span>Minimal: <Money value={data.minTopup} /> · Batas: <Money value={data.dailyLimit} /></span>
                                </div>

                                {data.session ? (
                                    <form onSubmit={topUp} className="mt-4 grid gap-3">
                                        <Field label="Nominal Top-Up (Rp)">
                                            <input
                                                className={inputClass}
                                                type="number"
                                                min={data.minTopup}
                                                placeholder={String(data.minTopup)}
                                                value={amount}
                                                onChange={(event) => setAmount(event.target.value)}
                                                required
                                            />
                                        </Field>
                                        <Field label="Catatan (opsional)">
                                            <input className={inputClass} maxLength={255} value={note} onChange={(event) => setNote(event.target.value)} />
                                        </Field>
                                        <Button type="submit" className="h-11" disabled={busy || !amount}>
                                            Simpan Top-Up
                                        </Button>
                                    </form>
                                ) : (
                                    <div className="mt-4 rounded-input bg-warn-bg text-warn p-3 text-xs">
                                        Buka sesi kasir terlebih dahulu: uang top-up masuk ke laci kas.
                                    </div>
                                )}
                            </div>
                        ) : data.phone ? (
                            <div className="mt-4 rounded-input bg-warn-bg text-warn p-3 text-sm">
                                Pelanggan dengan nomor {data.phone} tidak ditemukan. Pastikan pelanggan sudah mendaftar.
                            </div>
                        ) : (
                            <EmptyState title="Cari pelanggan" hint="Masukkan nomor HP yang terdaftar untuk melihat saldo dan melakukan top-up." />
                        )}
                    </>
                )}
            </Panel>

            <Panel className="overflow-hidden">
                <div className="px-4 py-3 border-b border-line bg-surface-2">
                    <h2 className="text-sm font-bold">Top-Up Terakhir di Sesi Ini</h2>
                </div>
                {data?.recentTopups?.length ? (
                    <table className="w-full text-sm">
                        <tbody className="divide-y divide-line">
                            {data.recentTopups.map((tx) => (
                                <tr key={tx.id}>
                                    <td className="p-3">
                                        <span className="font-semibold block">{tx.customer?.name ?? '-'}</span>
                                        <span className="text-xs text-ink-2">{tx.customer?.phone} · {dateTime(tx.created_at)}</span>
                                    </td>
                                    <td className="p-3 text-right">
                                        <Money value={tx.amount} className="font-semibold text-ok" />
                                        <span className="block text-xs text-ink-2">Saldo: <Money value={tx.balance_after} /></span>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                ) : (
                    <p className="p-4 text-sm text-ink-2">Belum ada top-up pada sesi ini.</p>
                )}
            </Panel>
        </div>
    );
}