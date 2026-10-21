import { useState } from 'react';
import { useResource } from '../hooks';
import { mutate, useFlash } from '../hooks';
import { Button, ErrorBox, Field, inputClass, Loading, Money, Panel } from '../ui';

interface CashierSessionData {
    session?: {
        id: number;
        opening_float: number;
        expected_cash: number;
        status: 'open' | 'closed';
        opened_at: string;
        closed_at?: string;
        variance?: number;
        closing_note?: string | null;
    } | null;
    cashSales: number;
    cashTopups: number;
    paymentCount: number;
}

export function CashierSessionPage() {
    const { data, loading, error, reload } = useResource<CashierSessionData>('/api/sesi-kasir');
    const { message, flashSuccess, flashError } = useFlash();

    const [openingFloat, setOpeningFloat] = useState('');
    const [countedCash, setCountedCash] = useState('');
    const [closingNote, setClosingNote] = useState('');
    const [action, setAction] = useState<'open' | 'close' | null>(null);
    const [busy, setBusy] = useState(false);

    const session = data?.session;

    if (loading) {
        return <Loading label="Memuat sesi kasir..." />;
    }

    if (error || !data) {
        return <ErrorBox message={error ?? 'Gagal memuat data sesi kasir.'} />;
    }

    const openSession = async () => {
        if (!openingFloat) {
            flashError('Masukkan modal awal.');
            return;
        }

        setBusy(true);
        const result = await mutate('POST', '/api/sesi-kasir/buka', { opening_float: Number(openingFloat) });
        setBusy(false);

        if (!result.ok) {
            flashError(result.message);
            return;
        }

        flashSuccess('Sesi kasir dibuka.');
        reload();
    };

    const closeSession = async () => {
        if (!countedCash) {
            flashError('Masukkan uang laci terhitung.');
            return;
        }

        setBusy(true);
        const result = await mutate('POST', '/api/sesi-kasir/tutup', { counted_cash: Number(countedCash), closing_note: closingNote.trim() || undefined });
        setBusy(false);

        if (!result.ok) {
            flashError(result.message);
            return;
        }

        flashSuccess('Sesi kasir ditutup.');
        reload();
    };

    const variance = session ? Number(countedCash || 0) - (session.opening_float + data.cashSales - data.cashTopups) : 0;
    const expectedCash = session ? session.opening_float + data.cashSales - data.cashTopups : 0;

    return (
        <div className="max-w-2xl flex flex-col gap-5">
            <h1 className="font-display text-2xl font-bold">Sesi Kasir</h1>

            {session ? (
                <>
                    <Panel className="p-5 flex flex-col gap-4">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="bg-surface-2 rounded-input p-4">
                                <p className="text-xs text-ink-2 font-semibold">Modal Awal</p>
                                <Money value={session.opening_float} className="mt-1 font-display text-2xl font-bold tabular" />
                            </div>
                            <div className="bg-surface-2 rounded-input p-4">
                                <p className="text-xs text-ink-2 font-semibold">Jumlah Pesanan</p>
                                <p className="mt-1 font-display text-2xl font-bold tabular">{data.paymentCount}</p>
                            </div>
                            <div className="bg-surface-2 rounded-input p-4">
                                <p className="text-xs text-ink-2 font-semibold">Penjualan Tunai</p>
                                <Money value={data.cashSales} className="mt-1 font-display text-2xl font-bold tabular" />
                            </div>
                            <div className="bg-surface-2 rounded-input p-4">
                                <p className="text-xs text-ink-2 font-semibold">Top-Up Saldo</p>
                                <Money value={data.cashTopups} className="mt-1 font-display text-2xl font-bold tabular" />
                            </div>
                        </div>

                        <div className="pt-4 border-t border-line">
                            <h3 className="font-semibold text-sm mb-2">Ringkasan Uang Laci</h3>
                            <div className="space-y-2 text-sm">
                                <div className="flex justify-between"><span>Modal Awal</span><Money value={session.opening_float} className="tabular" /></div>
                                <div className="flex justify-between"><span>Penjualan Tunai (+)</span><Money value={data.cashSales} className="tabular text-ok" /></div>
                                <div className="flex justify-between text-bad"><span>Top-Up Saldo (−)</span><Money value={data.cashTopups} className="tabular" /></div>
                                <div className="flex justify-between font-bold pt-2 border-t border-line"><span>Diperlukan di Laci</span><Money value={expectedCash} className="tabular" /></div>
                            </div>
                        </div>

                        {session.variance !== undefined && session.variance !== 0 && (
                            <div className={`p-3 rounded-input ${session.variance < 0 ? 'bg-bad-bg border border-bad/20 text-bad' : 'bg-ok-bg border border-ok/20 text-ok'}`}>
                                <p className="font-semibold">Selisih Kas: <Money value={Math.abs(session.variance)} /></p>
                                <p className="text-xs mt-1">{session.closing_note ?? 'Tidak ada catatan.'}</p>
                            </div>
                        )}

                        <div className="pt-4 border-t border-line">
                            <h3 className="font-semibold text-sm mb-2">Tutup Sesi Kasir</h3>
                            <div className="grid gap-3 sm:grid-cols-2">
                                <Field label="Uang Laci Terhitung (Rp)">
                                    <input className={inputClass} type="number" min={0} placeholder="0" value={countedCash} onChange={(event) => setCountedCash(event.target.value)} />
                                </Field>
                                <Field label="Catatan (wajib jika ada selisih)">
                                    <input className={inputClass} placeholder="Contoh: kurang 5000" value={closingNote} onChange={(event) => setClosingNote(event.target.value)} />
                                </Field>
                            </div>
                            <Button className="w-full h-11" onClick={closeSession} disabled={busy || !countedCash}>
                                Tutup Sesi Kasir
                            </Button>
                        </div>
                    </Panel>
                </>
            ) : (
                <Panel className="p-5 flex flex-col gap-4">
                    <p className="text-sm text-ink-2">Belum ada sesi kasir yang terbuka. Buka sesi untuk memulai operasional hari ini.</p>
                    <div className="max-w-sm">
                        <Field label="Modal Awal (Rp)">
                            <input className={inputClass} type="number" min={0} placeholder="0" value={openingFloat} onChange={(event) => setOpeningFloat(event.target.value)} autoFocus />
                        </Field>
                        <Button className="w-full h-11 mt-2" onClick={openSession} disabled={busy || !openingFloat}>
                            Buka Sesi Kasir
                        </Button>
                    </div>
                </Panel>
            )}
        </div>
    );
}