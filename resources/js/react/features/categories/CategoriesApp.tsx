import * as React from 'react';
import { apiRequest, firstError, type ValidationErrors } from '@/react/lib/api';
import { Button } from '@/react/components/ui/button';
import { Input } from '@/react/components/ui/input';
import { FormField } from '@/react/components/ui/label';
import { Badge } from '@/react/components/ui/badge';
import { Switch } from '@/react/components/ui/switch';
import {
    Table,
    TableWrap,
    TableHeader,
    TableBody,
    TableRow,
    TableHead,
    TableCell,
} from '@/react/components/ui/table';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
    DialogFooter,
} from '@/react/components/ui/dialog';
import {
    AlertDialog,
    AlertDialogContent,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogAction,
    AlertDialogCancel,
} from '@/react/components/ui/alert-dialog';
import { Plus, Pencil, Trash2, Search, ArrowUpDown, Loader2, ImageIcon } from 'lucide-react';
import { ImageUpload } from '@/react/components/ui/image-upload';

export interface Category {
    id: number;
    name: string;
    sort_order: number;
    is_active: boolean;
    image_url?: string | null;
    menu_items_count?: number;
}

export function CategoriesApp({ initialCategories = [] }: { initialCategories?: Category[] }) {
    const [categories, setCategories] = React.useState<Category[]>(initialCategories);
    const [search, setSearch] = React.useState('');
    const [createOpen, setCreateOpen] = React.useState(false);
    const [editTarget, setEditTarget] = React.useState<Category | null>(null);
    const [deleteTarget, setDeleteTarget] = React.useState<Category | null>(null);

    const [formName, setFormName] = React.useState('');
    const [formSortOrder, setFormSortOrder] = React.useState(0);
    const [formIsActive, setFormIsActive] = React.useState(true);
    const [formImageFile, setFormImageFile] = React.useState<File | null>(null);
    const [formImageRemove, setFormImageRemove] = React.useState(false);
    const [formImageUrl, setFormImageUrl] = React.useState<string | null>(null);
    const [errors, setErrors] = React.useState<ValidationErrors>({});

    function buildFormData(): FormData {
        const data = new FormData();
        data.append('name', formName);
        data.append('sort_order', String(formSortOrder));
        data.append('is_active', formIsActive ? '1' : '0');
        if (editTarget) {
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
    const [submitting, setSubmitting] = React.useState(false);
    const [flash, setFlash] = React.useState<{ text: string; type: 'success' | 'error' } | null>(null);

    function notify(text: string, type: 'success' | 'error' = 'success') {
        setFlash({ text, type });
        setTimeout(() => setFlash(null), 4000);
    }

    function openCreate() {
        setFormName('');
        setFormSortOrder(categories.length > 0 ? Math.max(...categories.map((c) => c.sort_order)) + 1 : 0);
        setFormIsActive(true);
        setFormImageFile(null);
        setFormImageRemove(false);
        setFormImageUrl(null);
        setErrors({});
        setCreateOpen(true);
    }

    function openEdit(category: Category) {
        setFormName(category.name);
        setFormSortOrder(category.sort_order);
        setFormIsActive(category.is_active);
        setFormImageFile(null);
        setFormImageRemove(false);
        setFormImageUrl(category.image_url ?? null);
        setErrors({});
        setEditTarget(category);
    }

    async function handleCreate(e: React.FormEvent) {
        e.preventDefault();
        setSubmitting(true);
        setErrors({});

        const res = await apiRequest<Category>('POST', '/kategori', buildFormData());

        setSubmitting(false);

        if (res.ok && res.data) {
            setCategories((prev) => [...prev, { ...res.data!, menu_items_count: 0 }].sort((a, b) => a.sort_order - b.sort_order));
            setCreateOpen(false);
            notify('Kategori berhasil ditambahkan.');
        } else {
            setErrors(res.errors ?? {});
            notify(res.message, 'error');
        }
    }

    async function handleUpdate(e: React.FormEvent) {
        e.preventDefault();
        if (!editTarget) return;

        setSubmitting(true);
        setErrors({});

        // Gunakan POST dengan _method=PATCH untuk mendukung multipart/form-data di PHP.
        const res = await apiRequest<Category>('POST', `/kategori/${editTarget.id}`, buildFormData());

        setSubmitting(false);

        if (res.ok && res.data) {
            setCategories((prev) =>
                prev
                    .map((c) => (c.id === editTarget.id ? { ...res.data!, menu_items_count: c.menu_items_count } : c))
                    .sort((a, b) => a.sort_order - b.sort_order)
            );
            setEditTarget(null);
            notify('Kategori berhasil diperbarui.');
        } else {
            setErrors(res.errors ?? {});
            notify(res.message, 'error');
        }
    }

    async function handleToggleStatus(category: Category) {
        const res = await apiRequest<Category>('PATCH', `/kategori/${category.id}/status`);

        if (res.ok && res.data) {
            setCategories((prev) =>
                prev.map((c) => (c.id === category.id ? { ...c, is_active: res.data!.is_active } : c))
            );
            notify(`Status kategori ${category.name} diubah.`);
        } else {
            notify(res.message, 'error');
        }
    }

    async function handleDelete() {
        if (!deleteTarget) return;

        const res = await apiRequest('DELETE', `/kategori/${deleteTarget.id}`);

        if (res.ok) {
            setCategories((prev) => prev.filter((c) => c.id !== deleteTarget.id));
            setDeleteTarget(null);
            notify('Kategori berhasil dihapus.');
        } else {
            notify(res.message, 'error');
            setDeleteTarget(null);
        }
    }

    const filtered = categories.filter((c) => c.name.toLowerCase().includes(search.toLowerCase()));

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
                <div className="relative min-w-60 flex-1 max-w-sm">
                    <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-ink-2" />
                    <Input
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Cari kategori…"
                        className="pl-9"
                    />
                </div>
                <Button onClick={openCreate}>
                    <Plus className="h-4 w-4" />
                    Tambah Kategori
                </Button>
            </div>

            <section className="overflow-hidden rounded-card border border-line bg-surface shadow-1">
                <TableWrap>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead className="w-16">Urutan</TableHead>
                                <TableHead className="w-20">Gambar</TableHead>
                                <TableHead>Nama Kategori</TableHead>
                                <TableHead className="w-28 text-center">Jumlah Menu</TableHead>
                                <TableHead className="w-32">Status</TableHead>
                                <TableHead className="w-32 text-right">Aksi</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {filtered.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={6} className="py-8 text-center text-sm text-ink-2">
                                        Tidak ada kategori ditemukan.
                                    </TableCell>
                                </TableRow>
                            ) : (
                                filtered.map((cat) => (
                                    <TableRow key={cat.id}>
                                        <TableCell className="tabular text-xs font-semibold text-ink-2">
                                            {cat.sort_order}
                                        </TableCell>
                                        <TableCell>
                                            {cat.image_url ? (
                                                <img
                                                    src={cat.image_url}
                                                    alt={cat.name}
                                                    className="h-10 w-10 rounded object-cover"
                                                />
                                            ) : (
                                                <div className="flex h-10 w-10 items-center justify-center rounded bg-line/40 text-ink-2">
                                                    <ImageIcon className="h-4 w-4" />
                                                </div>
                                            )}
                                        </TableCell>
                                        <TableCell className="font-semibold text-ink">{cat.name}</TableCell>
                                        <TableCell className="tabular text-center text-xs">
                                            {cat.menu_items_count ?? 0}
                                        </TableCell>
                                        <TableCell>
                                            <button
                                                type="button"
                                                onClick={() => handleToggleStatus(cat)}
                                                className="group inline-flex items-center gap-2 cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary rounded-full"
                                                title="Klik untuk ubah status"
                                            >
                                                <Badge variant={cat.is_active ? 'success' : 'danger'}>
                                                    {cat.is_active ? 'Aktif' : 'Nonaktif'}
                                                </Badge>
                                            </button>
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <div className="inline-flex items-center gap-1">
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() => openEdit(cat)}
                                                    aria-label={`Ubah ${cat.name}`}
                                                >
                                                    <Pencil className="h-4 w-4" />
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() => setDeleteTarget(cat)}
                                                    disabled={(cat.menu_items_count ?? 0) > 0}
                                                    className="text-bad hover:bg-bad-bg hover:text-bad"
                                                    aria-label={`Hapus ${cat.name}`}
                                                    title={
                                                        (cat.menu_items_count ?? 0) > 0
                                                            ? 'Tidak dapat menghapus kategori yang masih memiliki menu'
                                                            : 'Hapus'
                                                    }
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

            {/* Modal Tambah */}
            <Dialog open={createOpen} onOpenChange={setCreateOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Tambah Kategori</DialogTitle>
                        <DialogDescription>
                            Kelompokkan menu makanan dan minuman pada sistem kasir.
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={handleCreate} className="flex flex-col gap-4">
                        <FormField label="Nama Kategori" error={firstError(errors, 'name')}>
                            <Input
                                value={formName}
                                onChange={(e) => setFormName(e.target.value)}
                                placeholder="Contoh: Kopi Spesial"
                                required
                                autoFocus
                            />
                        </FormField>
                        <FormField label="Urutan Tampil" error={firstError(errors, 'sort_order')} hint="Urutan lebih kecil tampil lebih awal">
                            <Input
                                type="number"
                                min={0}
                                max={65535}
                                value={formSortOrder}
                                onChange={(e) => setFormSortOrder(parseInt(e.target.value, 10) || 0)}
                            />
                        </FormField>
                        <ImageUpload
                            label="Gambar Kategori"
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
                                <span className="block text-xs font-semibold">Tampilkan di POS & Pelanggan</span>
                                <span className="text-[11px] text-ink-2">Kategori nonaktif tidak muncul di katalog</span>
                            </div>
                            <Switch checked={formIsActive} onCheckedChange={setFormIsActive} />
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setCreateOpen(false)}>
                                Batal
                            </Button>
                            <Button type="submit" disabled={submitting}>
                                {submitting && <Loader2 className="h-4 w-4 animate-spin" />}
                                Simpan Kategori
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Modal Edit */}
            <Dialog open={editTarget !== null} onOpenChange={(open) => !open && setEditTarget(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Ubah Kategori</DialogTitle>
                        <DialogDescription>Perbarui nama atau urutan tampilan kategori.</DialogDescription>
                    </DialogHeader>
                    <form onSubmit={handleUpdate} className="flex flex-col gap-4">
                        <FormField label="Nama Kategori" error={firstError(errors, 'name')}>
                            <Input
                                value={formName}
                                onChange={(e) => setFormName(e.target.value)}
                                required
                                autoFocus
                            />
                        </FormField>
                        <FormField label="Urutan Tampil" error={firstError(errors, 'sort_order')}>
                            <Input
                                type="number"
                                min={0}
                                max={65535}
                                value={formSortOrder}
                                onChange={(e) => setFormSortOrder(parseInt(e.target.value, 10) || 0)}
                            />
                        </FormField>
                        <ImageUpload
                            label="Gambar Kategori"
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
                                <span className="block text-xs font-semibold">Aktif</span>
                                <span className="text-[11px] text-ink-2">Kategori nonaktif disembunyikan</span>
                            </div>
                            <Switch checked={formIsActive} onCheckedChange={setFormIsActive} />
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setEditTarget(null)}>
                                Batal
                            </Button>
                            <Button type="submit" disabled={submitting}>
                                {submitting && <Loader2 className="h-4 w-4 animate-spin" />}
                                Simpan Perubahan
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Konfirmasi Hapus */}
            <AlertDialog open={deleteTarget !== null} onOpenChange={(open) => !open && setDeleteTarget(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Hapus Kategori {deleteTarget?.name}?</AlertDialogTitle>
                        <AlertDialogDescription>
                            Tindakan ini tidak dapat dibatalkan. Kategori hanya dapat dihapus jika tidak memiliki menu terhubung.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Batal</AlertDialogCancel>
                        <AlertDialogAction variant="destructive" onClick={handleDelete}>
                            Ya, Hapus
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </div>
    );
}