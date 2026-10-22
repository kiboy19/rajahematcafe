import { useState } from 'react';
import { Link } from 'react-router-dom';
import { useResource } from '../hooks';
import { mutate, useFlash } from '../hooks';
import { Button, ConfirmDialog, ErrorBox, Field, inputClass, Loading, Money, PageHeader, Panel, QuickAmountChips } from '../ui';
import type { StaffUser } from '../session';

interface SessionSummary {
    id: number;
    opening_float: number;
    expected_cash?: number | null;
    status: 'open' | 'closed';
    opened_at: string;
    closed_at?: string;
    variance?: number;
    closing_note?: string | null;
    cashier?: { id: number; name: string } | null;
}

interface CashierSessionData {
    session: SessionSummary | null;
    cashSales: number;
    cashTopups: number;
    paymentCount: number;
    active_session: SessionSummary | null;
    can_open: boolean;
    can_close: boolean;
    can_force_close: boolean;
}

function elapsedLabel(from: string): string {
    const minutes = Math.max(0, Math.round((Date.now() - new Date(from).getTime()) / 60000));
    if (minutes < 60) {
        return `${minutes} mnt`;
    }

    return `${Math.floor(minutes / 60)} j ${minutes % 60} mnt`;
}

function openedTime(from: string): string {
    return new Date(from).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
}

interface ClosureForm {
    sessionId: number;
    expected: number;
    force: boolean;
}

const floatPresets = [100000, 200000, 300000, 500000];

export function CashierSessionPage({ user }: { user: StaffUser }) {
    const { data, loading, error, reload } = useResource<CashierSessionData>('/api/sesi-kasir');
    const { flashSuccess, flashError } = useFlash();

    const [openingFloat, setOpeningFloat] = useState('');
    const [countedCash, setCountedCash] = useState('');
    const [closingNote, setClosingNote] = useState('');
    const [busy, setBusy] = useState(false);
    const [confirm, setConfirm] = useState<ClosureForm | null>(null);

    if (loading) {
        return <Loading label="Memuat sesi kasir..." />;
    }

    if (error || !data) {
        return <ErrorBox message={error ?? 'Gagal memuat data sesi kasir.'} />;
    }

    const isMonitorOnly = (user.role === 'owner' || user.role === 'manager') && !data.can_close;
    const display = data.session ?? data.active_session;

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

    const closeSession = async ({ sessionId, force }: ClosureForm) => {
        if (!countedCash) {
            flashError('Masukkan uang laci terhitung.');
            return;
        }

        setBusy(true);
        const body = { counted_cash: Number(countedCash), closing_note: closingNote.trim() || undefined };
        const result = force
            ? await mutate('POST', `/api/sesi-kasir/${sessionId}/tutup-paksa`, { ...body, closing_note: closingNote.trim() })
            : await mutate('POST', '/api/sesi-kasir/tutup', body);
        setBusy(false);

        if (!result.ok) {
            flashError(result.message);
            return;
        }

        flashSuccess(result.message ?? 'Sesi kasir ditutup.');
        setCountedCash('');
        setClosingNote('');
        reload();
    };

    const expectedCash = (target: SessionSummary | null | undefined) => target
        ? target.opening_float + data.cashSales - data.cashTopups
        : 0;
    const expectedFor = display?.id === data.session?.id
        ? data.session?.opening_float + data.cashSales - data.cashTopups
        : display?.expected_cash ?? null;
    const variance = display && countedCash !== '' ? Number(countedCash) - (expectedFor ?? 0) : 0;
    const hasVariance = countedCash !== '' && variance !== 0;

    return (
        <div className="flex flex-col gap-5 max-w-7xl w-full">
            <PageHeader
                title="Sesi Kasir"
                subtitle={isMonitorOnly ? 'Pemantauan sesi kasir yang sedang berjalan.' : 'Rekap uang laci dan penutupan operasional kasir.'}
                actions={
                    display ? (
                        <span className="inline-flex items-center gap-2 rounded-full bg-ok-bg px-3 py-1.5 text-xs font-semibold text-ok">
                            <span aria-hidden="true" className="h-2 w-2 rounded-full bg-ok" />
                            Sesi terbuka · sejak {openedTime(display.opened_at)} ({elapsedLabel(display.opened_at)})
                        </span>
                    ) : (
                        <span className="inline-flex items-center gap-2 rounded-full bg-warn-bg px-3 py-1.5 text-xs font-semibold text-warn">
                            <span aria-hidden="true" className="h-2 w-2 rounded-full bg-warn" />
                            Belum ada sesi terbuka
                        </span>
                    )
                }
            />

            {display ? (
                <div className="grid gap-5 lg:grid-cols-[1.55fr_1fr] items-start">
                    <div className="flex flex-col gap-5">
                        <Panel className="p-5">
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <p className="text-xs font-semibold uppercase tracking-wider text-ink-2">Kasir sesi ini</p>
                                    <p className="mt-1 font-display text-2xl font-bold">{display.cashier?.name ?? user.name}</p>
                                    <p className="text-xs text-ink-2">
                                        Dibuka {openedTime(display.opened_at)} · {elapsedLabel(display.opened_at)} berjalan
                                    </p>
                                </div>
                                {isMonitorOnly && (
                                    <span className="rounded-full border border-line bg-surface-2 px-3 py-1 text-xs font-semibold text-ink-2">
                                        Mode pantau
                                    </span>
                                )}
                            </div>
                        </Panel>

                        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                            <Panel className="p-4">
                                <p className="text-xs font-semibold uppercase tracking-wider text-ink-2">Modal Awal</p>
                                <Money value={display.opening_float} className="mt-2 block font-display text-xl font-bold" />
                            </Panel>
                            <Panel className="p-4">
                                <p className="text-xs font-semibold uppercase tracking-wider text-ink-2">Penjualan Tunai</p>
                                <Money value={data.cashSales} className="mt-2 block font-display text-xl font-bold text-ok" />
                            </Panel>
                            <Panel className="p-4">
                                <p className="text-xs font-semibold uppercase tracking-wider text-ink-2">Top-Up Saldo</p>
                                <Money value={data.cashTopups} className="mt-2 block font-display text-xl font-bold text-bad" />
                            </Panel>
                            <Panel className="p-4">
                                <p className="text-xs font-semibold uppercase tracking-wider text-ink-2">Jumlah Pesanan</p>
                                <p className="mt-2 font-display text-xl font-bold tabular">{data.paymentCount}</p>
                            </Panel>
                        </div>

                        <Panel className="p-5">
                            <h3 className="text-sm font-semibold">Ringkasan Uang Laci</h3>
                            <div className="mt-3 space-y-2 text-sm">
                                <div className="flex justify-between"><span className="text-ink-2">Modal Awal</span><Money value={display.opening_float} /></div>
                                <div className="flex justify-between"><span className="text-ink-2">Penjualan Tunai (+)</span><Money value={data.cashSales} className="text-ok" /></div>
                                <div className="flex justify-between"><span className="text-ink-2">Top-Up Saldo (−)</span><Money value={data.cashTopups} className="text-bad" /></div>
                            </div>
                            <div className="mt-3 flex items-baseline justify-between border-t border-line pt-3">
                                <span className="text-sm font-semibold">Uang di laci seharusnya</span>
                                <Money value={expectedFor ?? expectedCash(display)} className="font-display text-2xl font-bold text-primary" />
                            </div>
                        </Panel>

                        {display.variance !== undefined && display.variance !== 0 && (
                            <Panel className={`p-4 ${display.variance < 0 ? 'border-bad/30 bg-bad-bg text-bad' : 'border-ok/30 bg-ok-bg text-ok'}`}>
                                <p className="font-semibold text-sm">
                                    Selisih kas tercatat: <Money value={Math.abs(display.variance)} className="font-bold" />
                                </p>
                                <p className="mt-1 text-xs">{display.closing_note ?? 'Tanpa catatan.'}</p>
                            </Panel>
                        )}
                    </div>

                    <div className="flex flex-col gap-5">
                        {data.can_close || data.can_force_close ? (
                            <Panel className="p-5 flex flex-col gap-4 border-bad/30">
                                <div>
                                    <h3 className="font-display text-lg font-bold">{data.can_force_close ? 'Tutup Paksa Kas' : 'Tutup Kas'}</h3>
                                    <p className="mt-1 text-xs text-ink-2">
                                        {data.can_force_close
                                            ? `Anda menutup sesi milik ${display.cashier?.name ?? 'kasir lain'}. Tindakan ini dicatat di audit log dan tidak dapat dibatalkan.`
                                            : 'Hitung uang fisik di laci, lalu masukkan jumlahnya. Tindakan ini tidak dapat dibatalkan.'}
                                    </p>
                                </div>

                                <Field label="Uang Laci Terhitung (Rp)">
                                    <input
                                        className={inputClass}
                                        type="number"
                                        min={0}
                                        inputMode="numeric"
                                        step={1000}
                                        placeholder="0"
                                        value={countedCash}
                                        onChange={(event) => setCountedCash(event.target.value)}
                                    />
                                </Field>
                                <Field
                                    label="Catatan"
                                    hint={data.can_force_close ? 'Wajib: alasan penutupan paksa.' : hasVariance ? 'Wajib diisi jika ada selisih.' : 'Opsional.'}
                                >
                                    <input
                                        className={inputClass}
                                        maxLength={255}
                                        placeholder={data.can_force_close ? 'Contoh: kasir pulang lebih awal' : 'Contoh: kurang 5000'}
                                        value={closingNote}
                                        onChange={(event) => setClosingNote(event.target.value)}
                                    />
                                </Field>

                                {countedCash !== '' && (
                                    <div className={`rounded-input border p-3 text-sm ${hasVariance ? (variance < 0 ? 'border-bad/20 bg-bad-bg text-bad' : 'border-ok/20 bg-ok-bg text-ok') : 'border-line bg-surface-2 text-ink-2'}`} role="status">
                                        Selisih: {variance > 0 ? '+' : variance < 0 ? '−' : ''}<Money value={Math.abs(variance)} className="font-semibold" />
                                        {!hasVariance && ' (pas)'}
                                    </div>
                                )}

                                <Button
                                    variant="danger"
                                    className="h-11 w-full"
                                    onClick={() => setConfirm({ sessionId: display.id, expected: expectedFor ?? 0, force: data.can_force_close })}
                                    disabled={busy || !countedCash || (!data.can_force_close && hasVariance && !closingNote.trim()) || (data.can_force_close && !closingNote.trim())}
                                >
                                    {data.can_force_close ? 'Tutup Paksa Sesi Kasir' : 'Tutup Sesi Kasir'}
                                </Button>
                                {countedCash !== '' && hasVariance && !closingNote.trim() && (
                                    <p className="text-xs text-bad">Isi catatan untuk menjelaskan selisih kas.</p>
                                )}
                            </Panel>
                        ) : (
                            <Panel className="p-5 flex flex-col gap-3">
                                <h3 className="text-sm font-semibold">Pemantauan</h3>
                                <p className="text-xs text-ink-2">
                                    Sesi kasir ini dikelola oleh {display.cashier?.name ?? 'kasir'}. Anda memantau operasional tanpa mengubah transaksi sesi.
                                </p>
                                <Link to="/laporan/harian" className="text-xs font-semibold text-primary hover:underline">
                                    Lihat laporan harian →
                                </Link>
                            </Panel>
                        )}
                    </div>
                </div>
            ) : (
                <div className="grid gap-5 lg:grid-cols-[1.55fr_1fr] items-start">
                    <Panel className="p-5">
                        <h3 className="text-sm font-semibold">Belum ada sesi kasir terbuka</h3>
                        <p className="mt-1 text-xs text-ink-2">
                            Kasir membuka sesi sebelum menerima pembayaran tunai atau top-up. Pembayaran non-tunai tetap dapat diproses tanpa sesi.
                        </p>
                    </Panel>

                    {data.can_open ? (
                        <Panel className="p-5 flex flex-col gap-3">
                            <h3 className="font-display text-lg font-bold">Buka Sesi</h3>
                            <QuickAmountChips amounts={floatPresets} value={openingFloat} onChange={setOpeningFloat} />
                            <Field label="Modal Awal (Rp)">
                                <input
                                    className={inputClass}
                                    type="number"
                                    min={0}
                                    inputMode="numeric"
                                    placeholder="0"
                                    value={openingFloat}
                                    onChange={(event) => setOpeningFloat(event.target.value)}
                                />
                            </Field>
                            <Button className="h-11 w-full" onClick={openSession} disabled={busy || !openingFloat}>
                                Buka Sesi Kasir
                            </Button>
                        </Panel>
                    ) : (
                        <Panel className="p-5">
                            <p className="text-xs text-ink-2">Sesi kasir dibuka oleh kasir yang bertugas.</p>
                        </Panel>
                    )}
                </div>
            )}

            <ConfirmDialog
                open={confirm !== null}
                onOpenChange={(open) => !open && setConfirm(null)}
                title={confirm?.force ? 'Tutup paksa sesi kasir?' : 'Tutup sesi kasir?'}
                description={
                    <>
                        Uang laci terhitung <Money value={countedCash} className="font-semibold" /> dengan selisih{' '}
                        <Money value={Math.abs(variance)} className="font-semibold" />.
                        {confirm?.force && ` Sesi milik ${display?.cashier?.name ?? 'kasir'} akan ditutup dan tercatat di audit log.`}
                        {' '}Setelah ditutup, sesi tidak dapat dibuka kembali.
                    </>
                }
                confirmText={confirm?.force ? 'Ya, tutup paksa' : 'Ya, tutup sesi'}
                onConfirm={() => confirm && closeSession(confirm)}
            />
        </div>
    );
}
