import * as React from 'react';
import { apiRequest, firstError, type ValidationErrors } from '@/react/lib/api';
import { Button } from '@/react/components/ui/button';
import { Input, Select, Textarea } from '@/react/components/ui/input';
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
import { Plus, Pencil, Package, Search, Loader2, AlertTriangle, ArrowDownUp } from 'lucide-react';

export interface IngredientItem {
    id: number;
    name: string;
    unit: string;
    stock_qty_milli: number;
    min_stock_milli: number;
    is_active: boolean;
}

export interface LedgerItem {
    id: number;
    ingredient_id: number;
    type: string;
    delta_milli: number;
    balance_after_milli: number;
    note: string | null;
    occurred_at: string | null;
    ingredient?: { id: number; name: string; unit: string } | null;
}

export interface MovementType {
    value: string;
    label: string;
}

type IngredientForm = {
    name: string;
    unit: string;
    initial_stock: string;
    min_stock: string;
    is_active: boolean;
};

type MovementForm = {
    type: string;
    quantity: string;
    unit_cost: string;
    note: string;
};

const emptyIngredientForm: IngredientForm = {
    name: '',
    unit: 'kg',
    initial_stock: '0',
    min_stock: '0',
    is_active: true,
};

const emptyMovementForm: MovementForm = {
    type: 'in',
    quantity: '',
    unit_cost: '',
    note: '',
};

/** Milli-unit (1 unit = 1000) dibulatkan ke maksimal 3 desimal. */
export function formatQuantity(milli: number): string {
    return (milli / 1000).toLocaleString('id-ID', { maximumFractionDigits: 3 });
}

export function InventoryApp({
    initialIngredients = [],
    initialLedgers = [],
    movementTypes = [],
}: {
    initialIngredients?: IngredientItem[];
    initialLedgers?: LedgerItem[];
    movementTypes?: MovementType[];
}) {
    const [ingredients, setIngredients] = React.useState<IngredientItem[]>(initialIngredients);
    const [ledgers, setLedgers] = React.useState<LedgerItem[]>(initialLedgers);
    const [search, setSearch] = React.useState('');
    const [onlyLowStock, setOnlyLowStock] = React.useState(false);
    const [dialogTarget, setDialogTarget] = React.useState<IngredientItem | 'create' | null>(null);
    const [movementTarget, setMovementTarget] = React.useState<IngredientItem | null>(null);

    const [ingredientForm, setIngredientForm] = React.useState<IngredientForm>(emptyIngredientForm);
    const [movementForm, setMovementForm] = React.useState<MovementForm>(emptyMovementForm);
    const [errors, setErrors] = React.useState<ValidationErrors>({});
    const [submitting, setSubmitting] = React.useState(false);
    const [flash, setFlash] = React.useState<{ text: string; type: 'success' | 'error' } | null>(null);

    function notify(text: string, type: 'success' | 'error' = 'success') {
        setFlash({ text, type });
        setTimeout(() => setFlash(null), 5000);
    }

    function openCreate() {
        setIngredientForm(emptyIngredientForm);
        setErrors({});
        setDialogTarget('create');
    }

    function openEdit(ingredient: IngredientItem) {
        setIngredientForm({
            name: ingredient.name,
            unit: ingredient.unit,
            initial_stock: '0',
            min_stock: formatQuantity(ingredient.min_stock_milli),
            is_active: ingredient.is_active,
        });
        setErrors({});
        setDialogTarget(ingredient);
    }

    function openMovement(ingredient: IngredientItem) {
        setMovementForm({ ...emptyMovementForm, quantity: '' });
        setErrors({});
        setMovementTarget(ingredient);
    }

    async function saveIngredient(event: React.FormEvent) {
        event.preventDefault();
        if (!dialogTarget) return;

        setSubmitting(true);
        setErrors({});

        const isCreate = dialogTarget === 'create';
        const payload = {
            name: ingredientForm.name,
            unit: ingredientForm.unit,
            min_stock: Number(ingredientForm.min_stock) || 0,
            is_active: ingredientForm.is_active,
            ...(isCreate ? { initial_stock: Number(ingredientForm.initial_stock) || 0 } : {}),
        };

        const res = await apiRequest<IngredientItem>(
            isCreate ? 'POST' : 'PATCH',
            isCreate ? '/inventory/bahan' : `/inventory/bahan/${dialogTarget.id}`,
            payload
        );

        setSubmitting(false);

        if (res.ok && res.data) {
            const fresh = res.data;
            setIngredients((prev) => {
                const next = isCreate
                    ? [...prev, fresh]
                    : prev.map((item) => (item.id === fresh.id ? fresh : item));
                return next.sort((a, b) => a.name.localeCompare(b.name));
            });
            setDialogTarget(null);
            notify(isCreate ? 'Bahan berhasil ditambahkan.' : 'Bahan berhasil diperbarui.');
        } else {
            setErrors(res.errors ?? {});
            notify(res.message, 'error');
        }
    }

    async function saveMovement(event: React.FormEvent) {
        event.preventDefault();
        if (!movementTarget) return;

        setSubmitting(true);
        setErrors({});

        const payload: Record<string, unknown> = {
            type: movementForm.type,
            quantity: Number(movementForm.quantity) || 0,
            note: movementForm.note || null,
        };

        if (movementForm.unit_cost !== '') {
            payload.unit_cost = Number(movementForm.unit_cost);
        }

        const res = await apiRequest<IngredientItem>('POST', `/inventory/bahan/${movementTarget.id}/pergerakan`, payload);

        setSubmitting(false);

        if (res.ok && res.data) {
            const fresh = res.data;
            setIngredients((prev) => prev.map((item) => (item.id === fresh.id ? fresh : item)));
            setMovementTarget(null);
            notify(res.message ?? 'Pergerakan stok tercatat.');
            await refreshLedgers();
        } else {
            setErrors(res.errors ?? {});
            notify(res.message, 'error');
        }
    }

    async function refreshLedgers() {
        const res = await apiRequest<{ ingredients: IngredientItem[]; ledgers: LedgerItem[] }>('GET', '/inventory');
        if (res.ok && res.data) {
            setLedgers(res.data.ledgers);
            setIngredients(res.data.ingredients);
        }
    }

    async function toggleStatus(ingredient: IngredientItem) {
        const res = await apiRequest<IngredientItem>('PATCH', `/inventory/bahan/${ingredient.id}/status`);

        if (res.ok && res.data) {
            setIngredients((prev) => prev.map((item) => (item.id === ingredient.id ? res.data! : item)));
            notify(`Status bahan ${ingredient.name} diubah.`);
        } else {
            notify(res.message, 'error');
        }
    }

    const filtered = ingredients.filter((ingredient) => {
        const matchSearch = ingredient.name.toLowerCase().includes(search.toLowerCase());
        const matchLow = !onlyLowStock || ingredient.stock_qty_milli <= ingredient.min_stock_milli;
        return matchSearch && matchLow;
    });

    const lowStockCount = ingredients.filter((i) => i.stock_qty_milli <= i.min_stock_milli).length;

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

            {lowStockCount > 0 && (
                <div role="status" className="flex items-start gap-3 rounded-input border border-warn/30 bg-warn-bg p-3 text-sm text-warn">
                    <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" />
                    <div>
                        <p className="font-bold">{lowStockCount} bahan mencapai atau di bawah stok minimum.</p>
                        <p className="text-xs">Segera buat purchase order agar operasional tidak terhenti.</p>
                    </div>
                </div>
            )}

            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="relative min-w-60 max-w-sm flex-1">
                    <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-ink-2" />
                    <Input
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Cari bahan baku…"
                        className="pl-9"
                    />
                </div>
                <div className="flex items-center gap-2">
                    <Button variant={onlyLowStock ? 'default' : 'outline'} size="sm" onClick={() => setOnlyLowStock((value) => !value)}>
                        <AlertTriangle className="h-4 w-4" />
                        Stok Menipis
                    </Button>
                    <Button onClick={openCreate}>
                        <Plus className="h-4 w-4" />
                        Tambah Bahan
                    </Button>
                </div>
            </div>

            <section className="overflow-hidden rounded-card border border-line bg-surface shadow-1">
                <div className="flex items-center justify-between border-b border-line px-4 py-3">
                    <h3 className="flex items-center gap-2 text-xs font-bold uppercase tracking-wider">
                        <Package className="h-4 w-4 text-primary" />
                        Bahan Terdaftar
                    </h3>
                    <span className="text-xs text-ink-2">{filtered.length} dari {ingredients.length} bahan</span>
                </div>
                <TableWrap>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Bahan</TableHead>
                                <TableHead className="text-right">Saldo</TableHead>
                                <TableHead className="text-right">Minimum</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead className="text-right">Aksi</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {filtered.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={5} className="py-8 text-center text-sm text-ink-2">
                                        Tidak ada bahan ditemukan.
                                    </TableCell>
                                </TableRow>
                            ) : (
                                filtered.map((ingredient) => {
                                    const isLow = ingredient.stock_qty_milli <= ingredient.min_stock_milli;

                                    return (
                                        <TableRow key={ingredient.id}>
                                            <TableCell>
                                                <span className="block font-semibold text-ink">{ingredient.name}</span>
                                                <span className="text-[11px] text-ink-2">Satuan: {ingredient.unit}</span>
                                            </TableCell>
                                            <TableCell className={`tabular text-right font-bold ${isLow ? 'text-bad' : 'text-ink'}`}>
                                                {formatQuantity(ingredient.stock_qty_milli)}
                                                <span className="ml-1 text-[11px] font-normal text-ink-2">{ingredient.unit}</span>
                                            </TableCell>
                                            <TableCell className="tabular text-right text-ink-2">
                                                {formatQuantity(ingredient.min_stock_milli)}
                                            </TableCell>
                                            <TableCell>
                                                <button
                                                    type="button"
                                                    onClick={() => toggleStatus(ingredient)}
                                                    className="cursor-pointer rounded-full focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                                                    title="Klik untuk ubah status bahan"
                                                >
                                                    <Badge variant={ingredient.is_active ? 'success' : 'danger'}>
                                                        {ingredient.is_active ? 'Aktif' : 'Nonaktif'}
                                                    </Badge>
                                                </button>
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <div className="inline-flex items-center gap-1">
                                                    <Button variant="outline" size="sm" onClick={() => openMovement(ingredient)} className="gap-1 text-xs">
                                                        <ArrowDownUp className="h-3.5 w-3.5" />
                                                        Catat Stok
                                                    </Button>
                                                    <Button variant="ghost" size="sm" onClick={() => openEdit(ingredient)} aria-label={`Ubah ${ingredient.name}`}>
                                                        <Pencil className="h-4 w-4" />
                                                    </Button>
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    );
                                })
                            )}
                        </TableBody>
                    </Table>
                </TableWrap>
            </section>

            <section className="overflow-hidden rounded-card border border-line bg-surface shadow-1">
                <div className="border-b border-line px-4 py-3">
                    <h3 className="text-xs font-bold uppercase tracking-wider">Pergerakan Terakhir</h3>
                </div>
                <TableWrap>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead className="w-32">Waktu</TableHead>
                                <TableHead>Bahan</TableHead>
                                <TableHead className="w-32">Tipe</TableHead>
                                <TableHead className="w-28 text-right">Delta</TableHead>
                                <TableHead className="w-28 text-right">Saldo</TableHead>
                                <TableHead>Catatan</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {ledgers.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={6} className="py-8 text-center text-sm text-ink-2">
                                        Belum ada pergerakan stok.
                                    </TableCell>
                                </TableRow>
                            ) : (
                                ledgers.map((ledger) => (
                                    <TableRow key={ledger.id}>
                                        <TableCell className="whitespace-nowrap text-xs text-ink-2">
                                            {ledger.occurred_at ? new Date(ledger.occurred_at).toLocaleString('id-ID', { dateStyle: 'short', timeStyle: 'short' }) : '—'}
                                        </TableCell>
                                        <TableCell className="text-sm">{ledger.ingredient?.name ?? '—'}</TableCell>
                                        <TableCell className="text-xs">{ledger.type}</TableCell>
                                        <TableCell className={`tabular text-right font-semibold ${ledger.delta_milli < 0 ? 'text-bad' : 'text-ok'}`}>
                                            {ledger.delta_milli > 0 ? '+' : ''}
                                            {formatQuantity(ledger.delta_milli)}
                                        </TableCell>
                                        <TableCell className="tabular text-right">{formatQuantity(ledger.balance_after_milli)}</TableCell>
                                        <TableCell className="text-xs text-ink-2">{ledger.note ?? '—'}</TableCell>
                                    </TableRow>
                                ))
                            )}
                        </TableBody>
                    </Table>
                </TableWrap>
            </section>

            {/* Modal Tambah / Ubah Bahan */}
            <Dialog open={dialogTarget !== null} onOpenChange={(open) => !open && setDialogTarget(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{dialogTarget === 'create' ? 'Tambah Bahan Baku' : 'Ubah Bahan Baku'}</DialogTitle>
                        <DialogDescription>
                            Stok disimpan dalam satuan dasar (1 unit = 1000 milli) agar tidak ada pembulatan floating point.
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={saveIngredient} className="flex flex-col gap-4">
                        <FormField label="Nama Bahan" error={firstError(errors, 'name')}>
                            <Input
                                value={ingredientForm.name}
                                onChange={(e) => setIngredientForm({ ...ingredientForm, name: e.target.value })}
                                required
                                autoFocus
                                maxLength={100}
                                placeholder="Contoh: Biji Kopi Arabika"
                            />
                        </FormField>

                        <FormField label="Satuan" error={firstError(errors, 'unit')} hint="kg, liter, pcs, pack">
                            <Input
                                value={ingredientForm.unit}
                                onChange={(e) => setIngredientForm({ ...ingredientForm, unit: e.target.value })}
                                required
                                maxLength={20}
                            />
                        </FormField>

                        <div className="grid grid-cols-2 gap-3">
                            {dialogTarget === 'create' && (
                                <FormField label="Stok Awal" error={firstError(errors, 'initial_stock')}>
                                    <Input
                                        type="number"
                                        min={0}
                                        step={0.001}
                                        value={ingredientForm.initial_stock}
                                        onChange={(e) => setIngredientForm({ ...ingredientForm, initial_stock: e.target.value })}
                                    />
                                </FormField>
                            )}
                            <FormField label="Stok Minimum" error={firstError(errors, 'min_stock')}>
                                <Input
                                    type="number"
                                    min={0}
                                    step={0.001}
                                    value={ingredientForm.min_stock}
                                    onChange={(e) => setIngredientForm({ ...ingredientForm, min_stock: e.target.value })}
                                />
                            </FormField>
                        </div>

                        <div className="flex items-center justify-between rounded-input border border-line p-3">
                            <span className="text-xs font-semibold">Bahan Aktif</span>
                            <Switch
                                checked={ingredientForm.is_active}
                                onCheckedChange={(value) => setIngredientForm({ ...ingredientForm, is_active: value })}
                            />
                        </div>

                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setDialogTarget(null)}>
                                Batal
                            </Button>
                            <Button type="submit" disabled={submitting}>
                                {submitting && <Loader2 className="h-4 w-4 animate-spin" />}
                                Simpan Bahan
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Modal Catat Pergerakan */}
            <Dialog open={movementTarget !== null} onOpenChange={(open) => !open && setMovementTarget(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Catat Pergerakan Stok</DialogTitle>
                        <DialogDescription>
                            {movementTarget?.name} — saldo saat ini {movementTarget ? formatQuantity(movementTarget.stock_qty_milli) : 0} {movementTarget?.unit}
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={saveMovement} className="flex flex-col gap-4">
                        <FormField label="Jenis Pergerakan" error={firstError(errors, 'type')} hint="Opname mengisi saldo fisik hasil hitung, bukan selisih.">
                            <Select
                                value={movementForm.type}
                                onChange={(e) => setMovementForm({ ...movementForm, type: e.target.value })}
                                required
                            >
                                {movementTypes.map((type) => (
                                    <option key={type.value} value={type.value}>
                                        {type.label}
                                    </option>
                                ))}
                            </Select>
                        </FormField>

                        <FormField
                            label={movementForm.type === 'opname' ? `Stok Fisik (${movementTarget?.unit ?? 'unit'})` : `Jumlah (${movementTarget?.unit ?? 'unit'})`}
                            error={firstError(errors, 'quantity')}
                        >
                            <Input
                                type="number"
                                min={0}
                                max={1000000}
                                step={0.001}
                                value={movementForm.quantity}
                                onChange={(e) => setMovementForm({ ...movementForm, quantity: e.target.value })}
                                required
                                autoFocus
                            />
                        </FormField>

                        <FormField label="Harga Satuan (opsional)" error={firstError(errors, 'unit_cost')}>
                            <Input
                                type="number"
                                min={0}
                                step={1}
                                value={movementForm.unit_cost}
                                onChange={(e) => setMovementForm({ ...movementForm, unit_cost: e.target.value })}
                                placeholder="Rupiah"
                            />
                        </FormField>

                        <FormField label="Catatan" error={firstError(errors, 'note')}>
                            <Textarea
                                value={movementForm.note}
                                onChange={(e) => setMovementForm({ ...movementForm, note: e.target.value })}
                                maxLength={255}
                                placeholder="Contoh: Penerimaan dari supplier"
                            />
                        </FormField>

                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setMovementTarget(null)}>
                                Batal
                            </Button>
                            <Button type="submit" disabled={submitting}>
                                {submitting && <Loader2 className="h-4 w-4 animate-spin" />}
                                Catat Pergerakan
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </div>
    );
}
