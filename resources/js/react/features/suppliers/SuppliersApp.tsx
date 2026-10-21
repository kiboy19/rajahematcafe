import * as React from 'react';
import { apiRequest, firstError, type ValidationErrors } from '@/react/lib/api';
import { Button } from '@/react/components/ui/button';
import { Input, Textarea, Select } from '@/react/components/ui/input';
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
import { Plus, Pencil, Trash2, Search, Loader2, Building2 } from 'lucide-react';

export interface SupplierItem {
    id: number;
    name: string;
    contact_person: string | null;
    phone: string | null;
    email: string | null;
    address: string | null;
    is_active: boolean;
    ingredients_count: number;
    purchase_orders_count: number;
    ingredient_ids?: number[];
}

export interface IngredientSimple {
    id: number;
    name: string;
    unit: string;
}

type SupplierForm = {
    name: string;
    contact_person: string;
    phone: string;
    email: string;
    address: string;
    is_active: boolean;
    ingredient_ids: number[];
};

const emptySupplierForm: SupplierForm = {
    name: '',
    contact_person: '',
    phone: '',
    email: '',
    address: '',
    is_active: true,
    ingredient_ids: [],
};

export function SuppliersApp({
    initialSuppliers = [],
    initialIngredients = [],
}: {
    initialSuppliers?: SupplierItem[];
    initialIngredients?: IngredientSimple[];
}) {
    const [suppliers, setSuppliers] = React.useState<SupplierItem[]>(initialSuppliers);
    const [ingredients] = React.useState<IngredientSimple[]>(initialIngredients);
    const [search, setSearch] = React.useState('');

    const [dialogTarget, setDialogTarget] = React.useState<SupplierItem | 'create' | null>(null);
    const [deleteTarget, setDeleteTarget] = React.useState<SupplierItem | null>(null);
    const [form, setForm] = React.useState<SupplierForm>(emptySupplierForm);
    const [errors, setErrors] = React.useState<ValidationErrors>({});
    const [submitting, setSubmitting] = React.useState(false);
    const [flash, setFlash] = React.useState<{ text: string; type: 'success' | 'error' } | null>(null);

    function notify(text: string, type: 'success' | 'error' = 'success') {
        setFlash({ text, type });
        setTimeout(() => setFlash(null), 4000);
    }

    function openCreate() {
        setForm(emptySupplierForm);
        setErrors({});
        setDialogTarget('create');
    }

    function openEdit(supplier: SupplierItem) {
        setForm({
            name: supplier.name,
            contact_person: supplier.contact_person ?? '',
            phone: supplier.phone ?? '',
            email: supplier.email ?? '',
            address: supplier.address ?? '',
            is_active: supplier.is_active,
            ingredient_ids: supplier.ingredient_ids ?? [],
        });
        setErrors({});
        setDialogTarget(supplier);
    }

    async function saveSupplier(event: React.FormEvent) {
        event.preventDefault();
        if (!dialogTarget) return;

        setSubmitting(true);
        setErrors({});

        const isCreate = dialogTarget === 'create';
        const url = isCreate ? '/supplier' : `/supplier/${dialogTarget.id}`;

        const payload = {
            name: form.name,
            contact_person: form.contact_person,
            phone: form.phone,
            email: form.email,
            address: form.address,
            is_active: form.is_active,
            ingredients: form.ingredient_ids,
        };

        const res = await apiRequest<SupplierItem>(isCreate ? 'POST' : 'PATCH', url, payload);

        setSubmitting(false);

        if (res.ok && res.data) {
            setSuppliers((prev) =>
                isCreate
                    ? [...prev, res.data!].sort((a, b) => a.name.localeCompare(b.name))
                    : prev
                          .map((s) => (s.id === res.data!.id ? res.data! : s))
                          .sort((a, b) => a.name.localeCompare(b.name))
            );
            setDialogTarget(null);
            notify(isCreate ? 'Supplier berhasil ditambahkan.' : 'Supplier berhasil diperbarui.');
        } else {
            setErrors(res.errors ?? {});
            notify(res.message, 'error');
        }
    }

    async function toggleStatus(supplier: SupplierItem) {
        const res = await apiRequest<SupplierItem>('PATCH', `/supplier/${supplier.id}/status`);

        if (res.ok && res.data) {
            setSuppliers((prev) =>
                prev.map((s) => (s.id === supplier.id ? { ...s, is_active: res.data!.is_active } : s))
            );
            notify(`Status supplier ${supplier.name} diubah.`);
        } else {
            notify(res.message, 'error');
        }
    }

    async function deleteSupplier() {
        if (!deleteTarget) return;

        const res = await apiRequest('DELETE', `/supplier/${deleteTarget.id}`);

        if (res.ok) {
            setSuppliers((prev) => prev.filter((s) => s.id !== deleteTarget.id));
            setDeleteTarget(null);
            notify('Supplier berhasil dihapus.');
        } else {
            notify(res.message, 'error');
            setDeleteTarget(null);
        }
    }

    const filtered = suppliers.filter((s) => s.name.toLowerCase().includes(search.toLowerCase()));

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
                        placeholder="Cari supplier…"
                        className="pl-9"
                    />
                </div>
                <Button onClick={openCreate}>
                    <Plus className="h-4 w-4" />
                    Tambah Supplier
                </Button>
            </div>

            <section className="overflow-hidden rounded-card border border-line bg-surface shadow-1">
                <TableWrap>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Nama & Alamat</TableHead>
                                <TableHead>Kontak</TableHead>
                                <TableHead className="text-center">PO</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead className="text-right">Aksi</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {filtered.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={5} className="py-8 text-center text-sm text-ink-2">
                                        Tidak ada supplier ditemukan.
                                    </TableCell>
                                </TableRow>
                            ) : (
                                filtered.map((supplier) => (
                                    <TableRow key={supplier.id}>
                                        <TableCell>
                                            <span className="block font-semibold text-ink">{supplier.name}</span>
                                            {supplier.address && (
                                                <span className="text-[11px] text-ink-2">{supplier.address}</span>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-xs">
                                            {supplier.contact_person && (
                                                <span className="block font-medium">{supplier.contact_person}</span>
                                            )}
                                            {supplier.phone && <span className="text-ink-2">{supplier.phone}</span>}
                                            {supplier.email && <span className="text-ink-2 block">{supplier.email}</span>}
                                        </TableCell>
                                        <TableCell className="tabular text-center text-sm">{supplier.purchase_orders_count}</TableCell>
                                        <TableCell>
                                            <button
                                                type="button"
                                                onClick={() => toggleStatus(supplier)}
                                                className="cursor-pointer rounded-full focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                                                title="Klik untuk ubah status"
                                            >
                                                <Badge variant={supplier.is_active ? 'success' : 'danger'}>
                                                    {supplier.is_active ? 'Aktif' : 'Nonaktif'}
                                                </Badge>
                                            </button>
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <div className="inline-flex items-center gap-1">
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() => openEdit(supplier)}
                                                    aria-label={`Ubah ${supplier.name}`}
                                                >
                                                    <Pencil className="h-4 w-4" />
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() => setDeleteTarget(supplier)}
                                                    disabled={supplier.purchase_orders_count > 0}
                                                    className="text-bad hover:bg-bad-bg hover:text-bad"
                                                    aria-label={`Hapus ${supplier.name}`}
                                                    title={supplier.purchase_orders_count > 0 ? 'Tidak dapat menghapus supplier dengan riwayat PO' : 'Hapus'}
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

            {/* Modal Tambah / Ubah Supplier */}
            <Dialog open={dialogTarget !== null} onOpenChange={(open) => !open && setDialogTarget(null)}>
                <DialogContent className="max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>{dialogTarget === 'create' ? 'Tambah Supplier' : 'Ubah Supplier'}</DialogTitle>
                        <DialogDescription>
                            Supplier yang disimpan bisa dipilih saat membuat Purchase Order. Ingredients terhubung untuk keperluan preferensi harga.
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={saveSupplier} className="flex flex-col gap-4">
                        <FormField label="Nama PT / Usaha" error={firstError(errors, 'name')}>
                            <Input
                                value={form.name}
                                onChange={(e) => setForm({ ...form, name: e.target.value })}
                                required
                                autoFocus
                                maxLength={120}
                                placeholder="Contoh: PT Sumber Kopi Sejahtera"
                            />
                        </FormField>

                        <div className="grid grid-cols-2 gap-3">
                            <FormField label="Kontak Person" error={firstError(errors, 'contact_person')}>
                                <Input
                                    value={form.contact_person}
                                    onChange={(e) => setForm({ ...form, contact_person: e.target.value })}
                                    maxLength={80}
                                />
                            </FormField>
                            <FormField label="Telepon / WA" error={firstError(errors, 'phone')}>
                                <Input
                                    value={form.phone}
                                    onChange={(e) => setForm({ ...form, phone: e.target.value })}
                                    maxLength={30}
                                />
                            </FormField>
                        </div>

                        <FormField label="Email" error={firstError(errors, 'email')}>
                            <Input
                                type="email"
                                value={form.email}
                                onChange={(e) => setForm({ ...form, email: e.target.value })}
                                maxLength={120}
                            />
                        </FormField>

                        <FormField label="Alamat" error={firstError(errors, 'address')}>
                            <Textarea
                                value={form.address}
                                onChange={(e) => setForm({ ...form, address: e.target.value })}
                                rows={2}
                            />
                        </FormField>

                        <FormField label="Bahan Baku Terkait (opsional)" hint="Pilih bahan yang disediakan supplier ini untuk keperluan PO">
                            <div className="rounded-input border border-line bg-surface p-2 max-h-40 overflow-auto">
                                {ingredients.map((ingredient) => (
                                    <label key={ingredient.id} className="flex items-center gap-2 block py-1 px-2 hover:bg-surface-2 rounded-input">
                                        <input
                                            type="checkbox"
                                            checked={form.ingredient_ids.includes(ingredient.id)}
                                            onChange={(e) =>
                                                setForm({
                                                    ...form,
                                                    ingredient_ids: e.target.checked
                                                        ? [...form.ingredient_ids, ingredient.id]
                                                        : form.ingredient_ids.filter((id) => id !== ingredient.id),
                                                })
                                            }
                                            className="h-4 w-4 rounded border-line text-primary focus:ring-primary"
                                        />
                                        <span className="text-sm">{ingredient.name} ({ingredient.unit})</span>
                                    </label>
                                ))}
                            </div>
                        </FormField>

                        <div className="flex items-center justify-between rounded-input border border-line p-3">
                            <span className="text-xs font-semibold">Aktif</span>
                            <Switch
                                checked={form.is_active}
                                onCheckedChange={(value) => setForm({ ...form, is_active: value })}
                            />
                        </div>

                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setDialogTarget(null)}>
                                Batal
                            </Button>
                            <Button type="submit" disabled={submitting}>
                                {submitting && <Loader2 className="h-4 w-4 animate-spin" />}
                                Simpan Supplier
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Konfirmasi Hapus */}
            <AlertDialog open={deleteTarget !== null} onOpenChange={(open) => !open && setDeleteTarget(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Hapus Supplier {deleteTarget?.name}?</AlertDialogTitle>
                        <AlertDialogDescription>
                            Supplier yang memiliki riwayat Purchase Order tidak dapat dihapus. Nonaktifkan sebagai gantinya.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Batal</AlertDialogCancel>
                        <AlertDialogAction variant="destructive" onClick={deleteSupplier}>
                            Ya, Hapus
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </div>
    );
}