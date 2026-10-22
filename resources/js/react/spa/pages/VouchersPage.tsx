import { useState } from 'react';
import { money, statusBadgeClass, statusLabel } from '../api';
import { useResource } from '../hooks';
import { mutate, useFlash } from '../hooks';
import { Badge, Button, EmptyState, ErrorBox, Field, inputClass, Loading, Money, PageHeader, Panel, selectClass } from '../ui';

interface Voucher {
    id: number;
    code: string;
    name: string;
    type: string;
    value: number;
    max_discount: number | null;
    min_subtotal: number;
    quota: number | null;
    used_count: number;
    quota_per_customer: number | null;
    is_active: boolean;
    starts_at: string | null;
    expires_at: string | null;
}

export function VouchersPage() {
    const { data, loading, error, reload } = useResource<Voucher[]>('/api/voucher');
    const { message, flashSuccess, flashError } = useFlash();

    const [showForm, setShowForm] = useState(false);
    const [form, setForm] = useState({
        code: '',
        name: '',
        type: 'percentage',
        value: '',
        max_discount: '',
        min_subtotal: '',
        quota: '',
        quota_per_customer: '',
        starts_at: '',
        expires_at: '',
        is_active: true,
    });
    const [busy, setBusy] = useState(false);

    if (loading) return <Loading label="Memuat voucher..." />;
    if (error) return <ErrorBox message={error} />;

    const vouchers = data ?? [];

    const handleSubmit = async (event: React.FormEvent) => {
        event.preventDefault();
        setBusy(true);
        const payload = {
            code: form.code.trim().toUpperCase(),
            name: form.name.trim(),
            type: form.type,
            value: Number(form.value),
            max_discount: form.max_discount ? Number(form.max_discount) : null,
            min_subtotal: form.min_subtotal ? Number(form.min_subtotal) : 0,
            quota: form.quota ? Number(form.quota) : null,
            quota_per_customer: form.quota_per_customer ? Number(form.quota_per_customer) : null,
            starts_at: form.starts_at || null,
            expires_at: form.expires_at || null,
            is_active: form.is_active,
        };
        const result = await mutate('POST', '/api/voucher', payload);
        setBusy(false);
        if (!result.ok) return flashError(result.message);
        flashSuccess('Voucher dibuat.');
        setShowForm(false);
        setForm({ code: '', name: '', type: 'percentage', value: '', max_discount: '', min_subtotal: '', quota: '', quota_per_customer: '', starts_at: '', expires_at: '', is_active: true });
        reload();
    };

    const toggleActive = async (voucher: Voucher) => {
        setBusy(true);
        const result = await mutate('PATCH', `/api/voucher/${voucher.id}/status`);
        setBusy(false);
        if (!result.ok) return flashError(result.message);
        flashSuccess(`Voucher ${voucher.code} ${voucher.is_active ? 'dinonaktifkan' : 'diaktifkan'}.`);
        reload();
    };

    return (
        <div className="max-w-7xl w-full flex flex-col gap-5">
            <PageHeader title="Voucher" actions={<Button variant="outline" onClick={() => setShowForm(true)}>Buat Voucher</Button>} />

            {showForm && (
                <Panel className="p-5">
                    <form onSubmit={handleSubmit} className="grid gap-3 sm:grid-cols-2">
                        <Field label="Kode Voucher (unik)"><input className={inputClass} placeholder="CONTOH10" maxLength={30} value={form.code} onChange={(e) => setForm({ ...form, code: e.target.value })} required /></Field>
                        <Field label="Nama"><input className={inputClass} placeholder="Diskon 10%" value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} required /></Field>
                        <Field label="Tipe">
                            <select className={selectClass} value={form.type} onChange={(e) => setForm({ ...form, type: e.target.value })}>
                                <option value="percentage">Persentase</option>
                                <option value="fixed">Nominal Tetap</option>
                                <option value="free_shipping">Gratis Ongkir</option>
                            </select>
                        </Field>
                        <Field label="Nilai"><input className={inputClass} type="number" min={0} placeholder="10" value={form.value} onChange={(e) => setForm({ ...form, value: e.target.value })} required /></Field>
                        <Field label="Max Diskon (Rp, opsional)"><input className={inputClass} type="number" min={0} placeholder="Tidak dibatasi" value={form.max_discount} onChange={(e) => setForm({ ...form, max_discount: e.target.value })} /></Field>
                        <Field label="Min. Subtotal (Rp)"><input className={inputClass} type="number" min={0} placeholder="0" value={form.min_subtotal} onChange={(e) => setForm({ ...form, min_subtotal: e.target.value })} /></Field>
                        <Field label="Kuota Total (opsional)"><input className={inputClass} type="number" min={1} placeholder="Tidak dibatasi" value={form.quota} onChange={(e) => setForm({ ...form, quota: e.target.value })} /></Field>
                        <Field label="Kuota per Pelanggan (opsional)"><input className={inputClass} type="number" min={1} placeholder="Tidak dibatasi" value={form.quota_per_customer} onChange={(e) => setForm({ ...form, quota_per_customer: e.target.value })} /></Field>
                        <Field label="Mulai Berlaku"><input className={inputClass} type="datetime-local" value={form.starts_at} onChange={(e) => setForm({ ...form, starts_at: e.target.value })} /></Field>
                        <Field label="Berakhir"><input className={inputClass} type="datetime-local" value={form.expires_at} onChange={(e) => setForm({ ...form, expires_at: e.target.value })} /></Field>
                        <Field label="Status" className="sm:col-span-2"><label className="flex items-center gap-2"><input type="checkbox" checked={form.is_active} onChange={(e) => setForm({ ...form, is_active: e.target.checked })} /> Aktif</label></Field>
                        <div className="sm:col-span-2 flex gap-2 pt-2">
                            <Button type="submit" disabled={busy}>{busy ? 'Menyimpan...' : 'Simpan Voucher'}</Button>
                            <Button variant="outline" type="button" onClick={() => setShowForm(false)}>Batal</Button>
                        </div>
                    </form>
                </Panel>
            )}

            {vouchers.length === 0 ? (
                <EmptyState title="Belum ada voucher." hint="Buat voucher pertama untuk memulai promosi." />
            ) : (
                <Panel className="overflow-hidden">
                    <table className="w-full text-sm">
                        <thead><tr className="border-b border-line bg-surface-2 text-[11px] uppercase tracking-wider text-ink-2"><th className="p-3 text-left font-bold">Kode</th><th className="p-3 text-left font-bold">Nama</th><th className="p-3 text-left font-bold">Tipe</th><th className="p-3 text-left font-bold">Nilai</th><th className="p-3 text-left font-bold">Status</th><th className="p-3 text-left font-bold">Digunakan</th><th className="p-3 text-left font-bold">Aksi</th></tr></thead>
                        <tbody className="divide-y divide-line">
                            {vouchers.map((v) => (
                                <tr key={v.id}>
                                    <td className="p-3 font-mono font-semibold">{v.code}</td>
                                    <td className="p-3">{v.name}</td>
                                    <td className="p-3 text-xs capitalize">{v.type.replace('_', ' ')}</td>
                                    <td className="p-3">{v.type === 'fixed' ? <Money value={v.value} /> : v.value + '%'}</td>
                                    <td className="p-3"><Badge className={v.is_active ? 'bg-ok-bg text-ok border-ok/20' : 'bg-surface-2 text-ink-2 border-line'}> {v.is_active ? 'Aktif' : 'Nonaktif'} </Badge></td>
                                    <td className="p-3 tabular">{v.quota ? `${v.used_count} / ${v.quota}` : v.used_count}</td>
                                    <td className="p-3"><Button variant="outline" className="text-xs" onClick={() => toggleActive(v)} disabled={busy}>{v.is_active ? 'Nonaktifkan' : 'Aktifkan'}</Button></td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </Panel>
            )}
        </div>
    );
}