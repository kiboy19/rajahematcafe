import * as React from 'react';
import { apiRequest, firstError, type ValidationErrors } from '@/react/lib/api';
import { formatRupiah } from '@/react/lib/utils';
import { Plus, Pencil, Trash2, Search, Loader2, SlidersHorizontal, ImageIcon } from 'lucide-react';
import { Button, Input, FormField, Badge, Switch, Table, TableWrap, TableHeader, TableBody, TableRow, TableHead, TableCell, Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter, AlertDialog, AlertDialogContent, AlertDialogHeader, AlertDialogTitle, AlertDialogDescription, AlertDialogFooter, AlertDialogAction, AlertDialogCancel, ImageUpload } from '../../spa/ui-primitives';

export interface MenuCategory {
    id: number;
    name: string;
    is_active: boolean;
}

export interface MenuItem {
    id: number;
    category_id: number;
    name: string;
    price: number;
    is_available: boolean;
    sort_order: number;
    image_url?: string | null;
    category?: MenuCategory;
}

type FormState = {
    category_id: string;
    name: string;
    price: number;
    sort_order: number;
    is_available: boolean;
};

const emptyForm: FormState = {
    category_id: '',
    name: '',
    price: 0,
    sort_order: 0,
    is_available: true,
};

export function MenuItemsApp({
    initialItems = [],
    initialCategories = [],
}: {
    initialItems?: MenuItem[];
    initialCategories?: MenuCategory[];
}) {
    const [items, setItems] = React.useState<MenuItem[]>(initialItems);
    const categories = initialCategories;
    const [search, setSearch] = React.useState('');
    const [categoryFilter, setCategoryFilter] = React.useState('all');
    const [availabilityFilter, setAvailabilityFilter] = React.useState('all');

    const [dialogTarget, setDialogTarget] = React.useState<MenuItem | 'create' | null>(null);
    const [deleteTarget, setDeleteTarget] = React.useState<MenuItem | null>(null);
    const [errors, setErrors] = React.useState<ValidationErrors>({});
    const [submitting, setSubmitting] = React.useState(false);
    const [flash, setFlash] = React.useState<{ text: string; type: 'success' | 'error' } | null>(null);
    const [form, setForm] = React.useState<FormState>(emptyForm);
    const [formImageFile, setFormImageFile] = React.useState<File | null>(null);
    const [formImageRemove, setFormImageRemove] = React.useState(false);
    const [formImageUrl, setFormImageUrl] = React.useState<string | null>(null);

    function notify(text: string, type: 'success' | 'error' = 'success') {
        setFlash({ text, type });
        setTimeout(() => setFlash(null), 4000);
    }

    function updateForm<K extends keyof FormState>(key: K, value: FormState[K]) {
        setForm((current) => ({ ...current, [key]: value }));
    }

    function openCreate() {
        setErrors({});
        setForm({
            ...emptyForm,
            category_id: categories.find((c) => c.is_active)?.id.toString() ?? '',
        });
        setFormImageFile(null);
        setFormImageRemove(false);
        setFormImageUrl(null);
        setDialogTarget('create');
    }

    function openEdit(item: MenuItem) {
        setErrors({});
        setForm({
            category_id: item.category_id.toString(),
            name: item.name,
            price: item.price,
            sort_order: item.sort_order,
            is_available: item.is_available,
        });
        setFormImageFile(null);
        setFormImageRemove(false);
        setFormImageUrl(item.image_url ?? null);
        setDialogTarget(item);
    }

    function buildFormData(): FormData {
        const data = new FormData();
        data.append('category_id', String(Number(form.category_id)));
        data.append('name', form.name);
        data.append('price', String(form.price));
        data.append('sort_order', String(form.sort_order));
        data.append('is_available', form.is_available ? '1' : '0');

        if (dialogTarget !== 'create') {
            // PHP hanya mengisi $_FILES untuk POST; pakai method spoofing untuk PATCH.
            data.append('_method', 'PATCH');
        }

        if (formImageFile) {
            data.append('image', formImageFile);
        }

        if (formImageRemove && !formImageFile) {
            data.append('remove_image', '1');
        }

        return data;
    }

    async function saveItem(event: React.FormEvent) {
        event.preventDefault();
        if (dialogTarget === null) return;

        setSubmitting(true);
        setErrors({});

        const isCreate = dialogTarget === 'create';
        // POST selalu dipakai agar multipart terbaca PHP; PATCH di-spoof via _method.
        const res = await apiRequest<MenuItem>('POST', isCreate ? '/menu' : `/menu/${dialogTarget.id}`, buildFormData());

        setSubmitting(false);

        if (res.ok && res.data) {
            setItems((prev) =>
                isCreate
                    ? [...prev, res.data!]
                    : prev.map((item) => (item.id === res.data!.id ? { ...item, ...res.data! } : item))
            );
            setDialogTarget(null);
            notify(isCreate ? 'Menu berhasil ditambahkan.' : 'Menu berhasil diperbarui.');
            return;
        }

        setErrors(res.errors ?? {});
        notify(res.message, 'error');
    }

    async function toggleAvailability(item: MenuItem) {
        const res = await apiRequest<MenuItem>('PATCH', `/menu/${item.id}/ketersediaan`);

        if (res.ok && res.data) {
            setItems((prev) =>
                prev.map((current) =>
                    current.id === item.id ? { ...current, is_available: res.data!.is_available } : current
                )
            );
            notify(`${item.name} sekarang ${res.data.is_available ? 'tersedia' : 'habis'}.`);
        } else {
            notify(res.message, 'error');
        }
    }

    async function deleteItem() {
        if (!deleteTarget) return;

        const res = await apiRequest('DELETE', `/menu/${deleteTarget.id}`);

        if (res.ok) {
            setItems((prev) => prev.filter((item) => item.id !== deleteTarget.id));
            notify('Menu berhasil dihapus.');
        } else {
            notify(res.message, 'error');
        }

        setDeleteTarget(null);
    }

    const filtered = items.filter((item) => {
        const matchSearch = item.name.toLowerCase().includes(search.toLowerCase());
        const matchCategory = categoryFilter === 'all' || item.category_id.toString() === categoryFilter;
        const matchAvailability =
            availabilityFilter === 'all' ||
            (availabilityFilter === 'available' ? item.is_available : !item.is_available);

        return matchSearch && matchCategory && matchAvailability;
    });

    const categoryName = (item: MenuItem) =>
        item.category?.name ?? categories.find((c) => c.id === item.category_id)?.name ?? '—';

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

            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="relative min-w-60 max-w-sm flex-1">
                    <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-ink-2" />
                    <Input
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Cari menu…"
                        className="pl-9"
                    />
                </div>
                <Button onClick={openCreate}>
                    <Plus className="h-4 w-4" />
                    Tambah Menu
                </Button>
            </div>

            <div className="flex flex-wrap items-center gap-2 rounded-card border border-line bg-surface p-3">
                <SlidersHorizontal className="h-4 w-4 text-ink-2" />
                <label className="sr-only" htmlFor="filter-kategori">
                    Filter kategori
                </label>
                <select
                    id="filter-kategori"
                    value={categoryFilter}
                    onChange={(e) => setCategoryFilter(e.target.value)}
                    className="h-9 rounded-input border border-line bg-surface px-2 text-xs"
                >
                    <option value="all">Semua kategori</option>
                    {categories.map((category) => (
                        <option key={category.id} value={category.id}>
                            {category.name}
                        </option>
                    ))}
                </select>
                <label className="sr-only" htmlFor="filter-status">
                    Filter ketersediaan
                </label>
                <select
                    id="filter-status"
                    value={availabilityFilter}
                    onChange={(e) => setAvailabilityFilter(e.target.value)}
                    className="h-9 rounded-input border border-line bg-surface px-2 text-xs"
                >
                    <option value="all">Semua status</option>
                    <option value="available">Tersedia</option>
                    <option value="unavailable">Habis</option>
                </select>
                <span className="ml-auto text-xs text-ink-2">
                    {filtered.length} dari {items.length} menu
                </span>
            </div>

            <section className="overflow-hidden rounded-card border border-line bg-surface shadow-1">
                <TableWrap>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Nama Menu</TableHead>
                                <TableHead>Kategori</TableHead>
                                <TableHead className="text-right">Harga</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead className="text-right">Aksi</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {filtered.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={5} className="py-8 text-center text-sm text-ink-2">
                                        Tidak ada menu ditemukan.
                                    </TableCell>
                                </TableRow>
                            ) : (
                                filtered.map((item) => (
                                    <TableRow key={item.id}>
                                        <TableCell className="font-semibold">
                                            <div className="flex items-center gap-3">
                                                {item.image_url ? (
                                                    <img
                                                        src={item.image_url}
                                                        alt={item.name}
                                                        className="h-10 w-10 rounded object-cover"
                                                    />
                                                ) : (
                                                    <div className="flex h-10 w-10 items-center justify-center rounded bg-line/40 text-ink-2">
                                                        <ImageIcon className="h-4 w-4" />
                                                    </div>
                                                )}
                                                <span>{item.name}</span>
                                            </div>
                                        </TableCell>
                                        <TableCell className="text-xs text-ink-2">{categoryName(item)}</TableCell>
                                        <TableCell className="tabular text-right font-semibold">
                                            {formatRupiah(item.price)}
                                        </TableCell>
                                        <TableCell>
                                            <button
                                                type="button"
                                                onClick={() => toggleAvailability(item)}
                                                className="cursor-pointer rounded-full focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                                                title="Klik untuk mengubah ketersediaan"
                                            >
                                                <Badge variant={item.is_available ? 'success' : 'danger'}>
                                                    {item.is_available ? 'Tersedia' : 'Habis'}
                                                </Badge>
                                            </button>
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <div className="inline-flex items-center gap-1">
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() => openEdit(item)}
                                                    aria-label={`Ubah ${item.name}`}
                                                >
                                                    <Pencil className="h-4 w-4" />
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() => setDeleteTarget(item)}
                                                    className="text-bad hover:bg-bad-bg hover:text-bad"
                                                    aria-label={`Hapus ${item.name}`}
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                </Button>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))
                            )}
                        </TableBody>
                    </Table>
                </TableWrap>
            </section>

            <Dialog open={dialogTarget !== null} onOpenChange={(open) => !open && setDialogTarget(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{dialogTarget === 'create' ? 'Tambah Menu' : 'Ubah Menu'}</DialogTitle>
                        <DialogDescription>
                            Harga disimpan sebagai Rupiah integer dan menjadi snapshot saat checkout.
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={saveItem} className="flex flex-col gap-4">
                        <FormField label="Nama Menu" error={firstError(errors, 'name')}>
                            <Input
                                value={form.name}
                                onChange={(e) => updateForm('name', e.target.value)}
                                required
                                autoFocus
                                maxLength={120}
                            />
                        </FormField>

                        <FormField label="Kategori" error={firstError(errors, 'category_id')}>
                            <select
                                value={form.category_id}
                                onChange={(e) => updateForm('category_id', e.target.value)}
                                required
                                className="h-11 rounded-input border border-line-strong bg-surface px-3 text-sm"
                            >
                                <option value="">Pilih kategori…</option>
                                {categories
                                    .filter((category) => category.is_active)
                                    .map((category) => (
                                        <option key={category.id} value={category.id}>
                                            {category.name}
                                        </option>
                                    ))}
                            </select>
                        </FormField>

                        <FormField label="Harga (Rp)" error={firstError(errors, 'price')}>
                            <Input
                                type="number"
                                min={0}
                                max={100000000}
                                step={1}
                                value={form.price}
                                onChange={(e) => updateForm('price', parseInt(e.target.value, 10) || 0)}
                                required
                            />
                        </FormField>

                        <FormField label="Urutan Tampil" error={firstError(errors, 'sort_order')}>
                            <Input
                                type="number"
                                min={0}
                                max={65535}
                                value={form.sort_order}
                                onChange={(e) => updateForm('sort_order', parseInt(e.target.value, 10) || 0)}
                            />
                        </FormField>

                        <ImageUpload
                            label="Gambar Menu"
                            value={formImageUrl}
                            onChange={(file, remove) => {
                                setFormImageFile(file);
                                setFormImageRemove(remove);
                                if (file) setFormImageUrl(URL.createObjectURL(file));
                            }}
                            error={firstError(errors, 'image')}
                            hint="Opsional · JPEG, PNG, WebP · maksimal 2 MB"
                        />

                        <div className="flex items-center justify-between rounded-input border border-line p-3">
                            <div>
                                <span className="block text-xs font-semibold">Tersedia untuk dijual</span>
                                <span className="text-[11px] text-ink-2">Menu habis tidak tampil di POS dan QR</span>
                            </div>
                            <Switch
                                checked={form.is_available}
                                onCheckedChange={(value) => updateForm('is_available', value)}
                            />
                        </div>

                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setDialogTarget(null)}>
                                Batal
                            </Button>
                            <Button type="submit" disabled={submitting}>
                                {submitting && <Loader2 className="h-4 w-4 animate-spin" />}
                                Simpan
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <AlertDialog open={deleteTarget !== null} onOpenChange={(open) => !open && setDeleteTarget(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Hapus Menu {deleteTarget?.name}?</AlertDialogTitle>
                        <AlertDialogDescription>
                            Menu yang sudah pernah masuk transaksi tidak dapat dihapus. Gunakan status Habis untuk
                            menyembunyikannya dari katalog.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Batal</AlertDialogCancel>
                        <AlertDialogAction variant="destructive" onClick={deleteItem}>
                            Ya, Hapus
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </div>
    );
}