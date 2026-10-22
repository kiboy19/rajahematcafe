import * as React from 'react';
import { apiRequest, firstError, type ValidationErrors } from '@/react/lib/api';
import { Save, Loader2, Sparkles, Phone, MapPin, Share2 } from 'lucide-react';
import { Button, Input, Textarea, FormField, ImageUpload } from '../../spa/ui-primitives';

export interface SiteBrandingData {
    brand_name: string;
    brand_tagline?: string | null;
    logo_path?: string | null;
    favicon_path?: string | null;
    logo_url?: string | null;
    favicon_url?: string | null;
    description?: string | null;
    address?: string | null;
    phone?: string | null;
    whatsapp_number?: string | null;
    whatsapp_link?: string | null;
    email?: string | null;
    instagram_url?: string | null;
    tiktok_url?: string | null;
    facebook_url?: string | null;
    google_maps_embed_url?: string | null;
    opening_hours?: string | null;
    footer_text?: string | null;
    updated_at?: string | null;
}

export function BrandingApp({ initialSettings }: { initialSettings: SiteBrandingData }) {
    const [settings, setSettings] = React.useState<SiteBrandingData>(initialSettings);
    const [logoFile, setLogoFile] = React.useState<File | null>(null);
    const [logoRemove, setLogoRemove] = React.useState(false);
    const [faviconFile, setFaviconFile] = React.useState<File | null>(null);
    const [faviconRemove, setFaviconRemove] = React.useState(false);

    const [errors, setErrors] = React.useState<ValidationErrors>({});
    const [submitting, setSubmitting] = React.useState(false);
    const [flash, setFlash] = React.useState<{ text: string; type: 'success' | 'error' } | null>(null);

    function notify(text: string, type: 'success' | 'error' = 'success') {
        setFlash({ text, type });
        setTimeout(() => setFlash(null), 4000);
    }

    function update<K extends keyof SiteBrandingData>(key: K, value: SiteBrandingData[K]) {
        setSettings((prev) => ({ ...prev, [key]: value }));
    }

    async function save(event: React.FormEvent) {
        event.preventDefault();
        setSubmitting(true);
        setErrors({});

        const formData = new FormData();
        formData.append('_method', 'PUT');

        formData.append('brand_name', settings.brand_name || '');
        if (settings.brand_tagline) formData.append('brand_tagline', settings.brand_tagline);
        if (settings.description) formData.append('description', settings.description);
        if (settings.address) formData.append('address', settings.address);
        if (settings.phone) formData.append('phone', settings.phone);
        if (settings.whatsapp_number) formData.append('whatsapp_number', settings.whatsapp_number);
        if (settings.email) formData.append('email', settings.email);
        if (settings.instagram_url) formData.append('instagram_url', settings.instagram_url);
        if (settings.tiktok_url) formData.append('tiktok_url', settings.tiktok_url);
        if (settings.facebook_url) formData.append('facebook_url', settings.facebook_url);
        if (settings.google_maps_embed_url) formData.append('google_maps_embed_url', settings.google_maps_embed_url);
        if (settings.opening_hours) formData.append('opening_hours', settings.opening_hours);
        if (settings.footer_text) formData.append('footer_text', settings.footer_text);

        if (logoFile) {
            formData.append('logo', logoFile);
        } else if (logoRemove) {
            formData.append('remove_logo', '1');
        }

        if (faviconFile) {
            formData.append('favicon', faviconFile);
        } else if (faviconRemove) {
            formData.append('remove_favicon', '1');
        }

        const res = await apiRequest<SiteBrandingData>('POST', '/pengaturan/branding', formData);

        setSubmitting(false);

        if (res.ok && res.data) {
            setSettings(res.data);
            setLogoFile(null);
            setLogoRemove(false);
            setFaviconFile(null);
            setFaviconRemove(false);
            notify(res.message ?? 'Branding berhasil disimpan.');
        } else {
            setErrors(res.errors ?? {});
            notify(res.message, 'error');
        }
    }

    return (
        <div className="flex flex-col gap-5">
            {flash && (
                <div
                    role="alert"
                    className={`rounded-input border p-3 text-sm font-medium ${
                        flash.type === 'success'
                            ? 'border-ok/30 bg-ok-bg text-ok'
                            : 'border-bad/30 bg-bad-bg text-bad'
                    }`}
                >
                    {flash.text}
                </div>
            )}

            <form onSubmit={save} className="flex flex-col gap-6">
                {/* Identitas Brand */}
                <fieldset className="flex flex-col gap-4 rounded-card border border-line bg-surface p-5 shadow-1">
                    <legend className="flex items-center gap-2 px-1 text-xs font-bold uppercase tracking-wider text-ink">
                        <Sparkles className="h-4 w-4 text-primary" />
                        Identitas Brand
                    </legend>

                    <div className="grid gap-4 md:grid-cols-2">
                        <FormField label="Nama Brand / Kafe" error={firstError(errors, 'brand_name')}>
                            <Input
                                value={settings.brand_name}
                                onChange={(e) => update('brand_name', e.target.value)}
                                maxLength={120}
                                required
                            />
                        </FormField>

                        <FormField label="Tagline (Slogan)" error={firstError(errors, 'brand_tagline')}>
                            <Input
                                value={settings.brand_tagline ?? ''}
                                onChange={(e) => update('brand_tagline', e.target.value)}
                                placeholder="Contoh: Ngopi Hemat, Rasa Nikmat"
                                maxLength={180}
                            />
                        </FormField>
                    </div>

                    <FormField label="Deskripsi Singkat (SEO & Beranda)" error={firstError(errors, 'description')}>
                        <Textarea
                            value={settings.description ?? ''}
                            onChange={(e) => update('description', e.target.value)}
                            placeholder="Deskripsi profil kafe yang ramah mesin pencari..."
                            maxLength={2000}
                        />
                    </FormField>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <ImageUpload
                            label="Logo Utama"
                            value={settings.logo_url}
                            onChange={(file, remove) => {
                                setLogoFile(file);
                                setLogoRemove(remove);
                            }}
                            error={firstError(errors, 'logo')}
                            hint="PNG/WebP/JPG transparan · maksimal 512 KB"
                            maxSizeMB={0.5}
                        />

                        <ImageUpload
                            label="Favicon Browser"
                            value={settings.favicon_url}
                            onChange={(file, remove) => {
                                setFaviconFile(file);
                                setFaviconRemove(remove);
                            }}
                            error={firstError(errors, 'favicon')}
                            hint="ICO/PNG persegi (32x32 atau 64x64) · maksimal 256 KB"
                            maxSizeMB={0.25}
                        />
                    </div>
                </fieldset>

                {/* Kontak & Lokasi */}
                <fieldset className="flex flex-col gap-4 rounded-card border border-line bg-surface p-5 shadow-1">
                    <legend className="flex items-center gap-2 px-1 text-xs font-bold uppercase tracking-wider text-ink">
                        <Phone className="h-4 w-4 text-primary" />
                        Kontak & Lokasi
                    </legend>

                    <div className="grid gap-4 sm:grid-cols-3">
                        <FormField label="Nomor WhatsApp" error={firstError(errors, 'whatsapp_number')} hint="Format lokal atau internasional">
                            <Input
                                value={settings.whatsapp_number ?? ''}
                                onChange={(e) => update('whatsapp_number', e.target.value)}
                                placeholder="Contoh: 081234567890"
                                maxLength={32}
                            />
                        </FormField>

                        <FormField label="Nomor Telepon Kantor" error={firstError(errors, 'phone')}>
                            <Input
                                value={settings.phone ?? ''}
                                onChange={(e) => update('phone', e.target.value)}
                                placeholder="Contoh: (021) 555-1234"
                                maxLength={32}
                            />
                        </FormField>

                        <FormField label="Email Resmi" error={firstError(errors, 'email')}>
                            <Input
                                type="email"
                                value={settings.email ?? ''}
                                onChange={(e) => update('email', e.target.value)}
                                placeholder="halo@rajahemat.com"
                                maxLength={160}
                            />
                        </FormField>
                    </div>

                    <FormField label="Alamat Lengkap" error={firstError(errors, 'address')}>
                        <Textarea
                            value={settings.address ?? ''}
                            onChange={(e) => update('address', e.target.value)}
                            placeholder="Jl. Kopi No. 12, Kelurahan, Kecamatan, Kota"
                            maxLength={500}
                        />
                    </FormField>

                    <div className="grid gap-4 md:grid-cols-2">
                        <FormField label="Jam Operasional" error={firstError(errors, 'opening_hours')} hint="Tampil di landing page dan footer">
                            <Input
                                value={settings.opening_hours ?? ''}
                                onChange={(e) => update('opening_hours', e.target.value)}
                                placeholder="Senin - Minggu: 08.00 - 23.00 WIB"
                                maxLength={255}
                            />
                        </FormField>

                        <FormField label="Google Maps Embed URL" error={firstError(errors, 'google_maps_embed_url')} hint="URL src dari kode embed iframe Google Maps">
                            <Input
                                value={settings.google_maps_embed_url ?? ''}
                                onChange={(e) => update('google_maps_embed_url', e.target.value)}
                                placeholder="https://www.google.com/maps/embed?pb=..."
                                maxLength={1000}
                            />
                        </FormField>
                    </div>
                </fieldset>

                {/* Media Sosial & Footer */}
                <fieldset className="flex flex-col gap-4 rounded-card border border-line bg-surface p-5 shadow-1">
                    <legend className="flex items-center gap-2 px-1 text-xs font-bold uppercase tracking-wider text-ink">
                        <Share2 className="h-4 w-4 text-primary" />
                        Media Sosial & Footer
                    </legend>

                    <div className="grid gap-4 sm:grid-cols-3">
                        <FormField label="URL Instagram" error={firstError(errors, 'instagram_url')}>
                            <Input
                                type="url"
                                value={settings.instagram_url ?? ''}
                                onChange={(e) => update('instagram_url', e.target.value)}
                                placeholder="https://instagram.com/rajahematcafe"
                                maxLength={255}
                            />
                        </FormField>

                        <FormField label="URL TikTok" error={firstError(errors, 'tiktok_url')}>
                            <Input
                                type="url"
                                value={settings.tiktok_url ?? ''}
                                onChange={(e) => update('tiktok_url', e.target.value)}
                                placeholder="https://tiktok.com/@rajahematcafe"
                                maxLength={255}
                            />
                        </FormField>

                        <FormField label="URL Facebook" error={firstError(errors, 'facebook_url')}>
                            <Input
                                type="url"
                                value={settings.facebook_url ?? ''}
                                onChange={(e) => update('facebook_url', e.target.value)}
                                placeholder="https://facebook.com/rajahematcafe"
                                maxLength={255}
                            />
                        </FormField>
                    </div>

                    <FormField label="Teks Hak Cipta / Footer" error={firstError(errors, 'footer_text')}>
                        <Input
                            value={settings.footer_text ?? ''}
                            onChange={(e) => update('footer_text', e.target.value)}
                            placeholder="© 2026 Rajahemat Cafe. Semua hak cipta dilindungi."
                            maxLength={255}
                        />
                    </FormField>
                </fieldset>

                {/* Submit button */}
                <div className="flex items-center justify-between gap-3 rounded-card border border-line bg-surface p-4 shadow-1">
                    <p className="text-xs text-ink-2">
                        Perubahan akan langsung terlihat pada header, footer, landing page, dan metadata browser.
                    </p>
                    <Button type="submit" disabled={submitting}>
                        <Save className="h-4 w-4" />
                        {submitting && <Loader2 className="h-4 w-4 animate-spin" />}
                        Simpan Branding
                    </Button>
                </div>
            </form>
        </div>
    );
}