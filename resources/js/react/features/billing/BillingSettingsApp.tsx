import * as React from 'react';
import { apiRequest, firstError, type ValidationErrors } from '@/react/lib/api';
import { Save, Loader2, ReceiptText } from 'lucide-react';
import { Button, Input, FormField, Switch } from '../../spa/ui-primitives';

export interface BillingSettings {
    tax_name: string;
    tax_percent: number;
    tax_enabled: boolean;
    service_name: string;
    service_percent: number;
    service_enabled: boolean;
    updated_at?: string | null;
}

export function BillingSettingsApp({ initialSettings }: { initialSettings: BillingSettings }) {
    const [settings, setSettings] = React.useState(initialSettings);
    const [reason, setReason] = React.useState('');
    const [errors, setErrors] = React.useState<ValidationErrors>({});
    const [submitting, setSubmitting] = React.useState(false);
    const [flash, setFlash] = React.useState<{ text: string; type: 'success' | 'error' } | null>(null);

    function notify(text: string, type: 'success' | 'error' = 'success') {
        setFlash({ text, type });
        setTimeout(() => setFlash(null), 4000);
    }

    async function save(event: React.FormEvent) {
        event.preventDefault();
        setSubmitting(true);
        setErrors({});

        const res = await apiRequest<BillingSettings>('PUT', '/pengaturan/tagihan', {
            ...settings,
            reason,
        });

        setSubmitting(false);

        if (res.ok && res.data) {
            setSettings(res.data);
            setReason('');
            notify(res.message ?? 'Pengaturan berhasil disimpan.');
        } else {
            setErrors(res.errors ?? {});
            notify(res.message, 'error');
        }
    }

    return (
        <div className="flex flex-col gap-5">
            {flash && (
                <div role="alert" className={`rounded-input border p-3 text-sm font-medium ${flash.type === 'success' ? 'border-ok/30 bg-ok-bg text-ok' : 'border-bad/30 bg-bad-bg text-bad'}`}>
                    {flash.text}
                </div>
            )}
            <form onSubmit={save} className="flex flex-col gap-5">
                <div className="grid gap-5 md:grid-cols-2">
                    <fieldset className="flex flex-col gap-4 rounded-card border border-line bg-surface p-5 shadow-1">
                        <legend className="flex items-center gap-2 px-1 text-xs font-bold uppercase tracking-wider text-ink"><ReceiptText className="h-4 w-4 text-primary" />Pajak</legend>
                        <FormField label="Nama Pajak" error={firstError(errors, 'tax_name')}>
                            <Input value={settings.tax_name} onChange={(e) => setSettings({ ...settings, tax_name: e.target.value })} maxLength={40} required />
                        </FormField>
                        <FormField label="Persentase (%)" error={firstError(errors, 'tax_percent')}>
                            <Input type="number" min={0} max={100} step={0.01} value={settings.tax_percent} onChange={(e) => setSettings({ ...settings, tax_percent: Number(e.target.value) })} required />
                        </FormField>
                        <div className="flex items-center justify-between rounded-input border border-line p-3"><span className="text-xs font-semibold">Aktif</span><Switch checked={settings.tax_enabled} onCheckedChange={(value) => setSettings({ ...settings, tax_enabled: value })} /></div>
                    </fieldset>
                    <fieldset className="flex flex-col gap-4 rounded-card border border-line bg-surface p-5 shadow-1">
                        <legend className="flex items-center gap-2 px-1 text-xs font-bold uppercase tracking-wider text-ink"><ReceiptText className="h-4 w-4 text-primary" />Service Charge</legend>
                        <FormField label="Nama Biaya" error={firstError(errors, 'service_name')}>
                            <Input value={settings.service_name} onChange={(e) => setSettings({ ...settings, service_name: e.target.value })} maxLength={40} required />
                        </FormField>
                        <FormField label="Persentase (%)" error={firstError(errors, 'service_percent')}>
                            <Input type="number" min={0} max={100} step={0.01} value={settings.service_percent} onChange={(e) => setSettings({ ...settings, service_percent: Number(e.target.value) })} required />
                        </FormField>
                        <div className="flex items-center justify-between rounded-input border border-line p-3"><span className="text-xs font-semibold">Aktif</span><Switch checked={settings.service_enabled} onCheckedChange={(value) => setSettings({ ...settings, service_enabled: value })} /></div>
                    </fieldset>
                </div>
                <FormField label="Alasan Perubahan (opsional)" error={firstError(errors, 'reason')}>
                    <Input value={reason} onChange={(e) => setReason(e.target.value)} maxLength={255} placeholder="Contoh: Penyesuaian tarif pemerintah" />
                </FormField>
                <div className="flex items-center justify-between gap-3 rounded-card border border-line bg-surface p-4 shadow-1">
                    <p className="text-xs text-ink-2">Perubahan berlaku untuk transaksi baru. Order lama tetap memakai snapshot tarif saat checkout.</p>
                    <Button type="submit" disabled={submitting}><Save className="h-4 w-4" />{submitting && <Loader2 className="h-4 w-4 animate-spin" />}Simpan Pengaturan</Button>
                </div>
            </form>
        </div>
    );
}
