import * as React from 'react';
import { apiRequest, firstError, type ValidationErrors } from '@/react/lib/api';
import { formatRupiah } from '@/react/lib/utils';
import { Plus, Search, Save, Loader2, FileText, CheckCircle, Package, X } from 'lucide-react';
import { Button, Input, Select, FormField, Badge, Table, TableWrap, TableHeader, TableBody, TableRow, TableHead, TableCell, Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter, AlertDialog, AlertDialogContent, AlertDialogHeader, AlertDialogTitle, AlertDialogDescription, AlertDialogFooter, AlertDialogAction, AlertDialogCancel } from '../../spa/ui-primitives';

export interface PurchaseOrderItemPayload {
    id?: number;
    ingredient_id: number;
    ingredient_name?: string | null;
    ingredient_unit?: string | null;
    quantity_milli: number;
    unit_cost: number;
    line_total: number;
}

export interface PurchaseOrder {
    id: number;
    number: string;
    status: string;
    status_label: string;
    supplier_id: number;
    supplier_name: string | null;
    total: number;
    note: string | null;
    expected_at: string | null;
    submitted_at: string | null;
    approved_at: string | null;
    received_at: string | null;
    created_at: string | null;
    creator_name: string | null;
    approver_name: string | null;
    receiver_name: string | null;
    items: PurchaseOrderItemPayload[];
}

export interface SupplierOption {
    id: number;
    name: string;
}

export interface IngredientOption {
    id: number;
    name: string;
    unit: string;
}

type FormItem = {
    ingredient_id: string;
    quantity: string;
    unit_cost: string;
};

export const statusVariant: Record<string, 'default' | 'success' | 'warning' | 'danger' | 'outline'> = {
    draft: 'outline',
    submitted: 'warning',
    approved: 'default',
    received: 'success',
    cancelled: 'danger',
};

export function formatMillis(milli: number): string {
    return (milli / 1000).toLocaleString('id-ID', { maximumFractionDigits: 3 });
}

function emptyRow(): FormItem {
    return { ingredient_id: '', quantity: '1', unit_cost: '0' };
}

export function PurchaseOrdersApp({
    initialOrders = [],
    initialSuppliers = [],
    initialIngredients = [],
    initialCreateOpen = false,
}: {
    initialOrders?: PurchaseOrder[];
    initialSuppliers?: SupplierOption[];
    initialIngredients?: IngredientOption[];
    initialCreateOpen?: boolean;
}) {
    const [orders, setOrders] = React.useState<PurchaseOrder[]>(initialOrders);
    const suppliers = initialSuppliers;
    const ingredients = initialIngredients;

    const [search, setSearch] = React.useState('');
    const [statusFilter, setStatusFilter] = React.useState('all');

    const [createOpen, setCreateOpen] = React.useState(initialCreateOpen);
    const [detailTarget, setDetailTarget] = React.useState<PurchaseOrder | null>(null);
    const [receiveTarget, setReceiveTarget] = React.useState<PurchaseOrder | null>(null);

    const [formSupplier, setFormSupplier] = React.useState('');
    const [formExpected, setFormExpected] = React.useState('');
    const [formNote, setFormNote] = React.useState('');
    const [formItems, setFormItems] = React.useState<FormItem[]>([emptyRow()]);

    const [errors, setErrors] = React.useState<ValidationErrors>({});
    const [submitting, setSubmitting] = React.useState(false);
    const [flash, setFlash] = React.useState<{ text: string; type: 'success' | 'error' } | null>(null);

    function notify(text: string, type: 'success' | 'error' = 'success') {
        setFlash({ text, type });
        setTimeout(() => setFlash(null), 5000);
    }

    function openCreate() {
        setFormSupplier('');
        setFormExpected(new Date(Date.now() + 3 * 86400000).toISOString().slice(0, 10));
        setFormNote('');
        setFormItems([emptyRow()]);
        setErrors({});
        setCreateOpen(true);
    }

    function setItem(index: number, patch: Partial<FormItem>) {
        setFormItems((prev) => prev.map((row, i) => (i === index ? { ...row, ...patch } : row)));
    }

    function lineTotal(item: FormItem): number {
        const qty = parseFloat(item.quantity) || 0;
        const cost = parseFloat(item.unit_cost) || 0;
        return Math.round(qty * cost);
    }

    const grandTotal = formItems.reduce((total, item) => total + lineTotal(item), 0);

    async function createPurchaseOrder(event: React.FormEvent) {
        event.preventDefault();

        setSubmitting(true);
        setErrors({});

        const payload = {
            supplier_id: Number(formSupplier) || null,
            expected_at: formExpected || null,
            note: formNote || null,
            items: formItems.map((row) => ({
                ingredient_id: Number(row.ingredient_id) || null,
                quantity: Number(row.quantity) || 0,
                unit_cost: Number(row.unit_cost) || 0,
            })),
        };

        const res = await apiRequest<PurchaseOrder>('POST', '/purchase-order', payload);

        setSubmitting(false);

        if (res.ok && res.data) {
            setOrders((prev) => [res.data!, ...prev]);
            setCreateOpen(false);
            notify(res.message ?? 'Purchase Order dibuat sebagai draf.');
        } else {
            setErrors(res.errors ?? {});
            notify(res.message, 'error');
        }
    }

    async function openDetail(order: PurchaseOrder) {
        const res = await apiRequest<PurchaseOrder>('GET', `/purchase-order/${order.id}`);

        if (res.ok && res.data) {
            setDetailTarget(res.data);
        } else {
            notify(res.message, 'error');
        }
    }

    async function transition(order: PurchaseOrder, action: 'submit' | 'approve' | 'receive') {
        setSubmitting(true);
        const res = await apiRequest<PurchaseOrder>('POST', `/purchase-order/${order.id}/${action}`);
        setSubmitting(false);

        if (res.ok && res.data) {
            const fresh = res.data;
            setOrders((prev) => prev.map((o) => (o.id === fresh.id ? fresh : o)));
            setDetailTarget((current) => (current?.id === fresh.id ? fresh : current));
            notify(res.message ?? 'Status PO diperbarui.');
        } else {
            notify(res.message, 'error');
        }
    }

    const filtered = orders.filter((order) => {
        const haystack = `${order.number} ${order.supplier_name ?? ''}`.toLowerCase();
        const matchSearch = haystack.includes(search.toLowerCase());
        const matchStatus = statusFilter === 'all' || order.status === statusFilter;
        return matchSearch && matchStatus;
    });

    return (
        <div className="flex flex-col gap-5">
            {flash && (
                <div
                    role="alert"
                    className={`rounded-input border p-3 text-sm font-medium ${
                        flash.type === 'success' ? 'border-ok/30 bg-ok-bg text-ok' : 'border-bad/30 bg-bad-bg text-bad'
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
                        placeholder="Cari nomor PO atau supplier…"
                        className="pl-9"
                    />
                </div>
                <div className="flex items-center gap-2">
                    <Select
                        value={statusFilter}
                        onChange={(e) => setStatusFilter(e.target.value)}
                        className="h-9 w-auto text-xs"
                        aria-label="Filter status PO"
                    >
                        <option value="all">Semua status</option>
                        <option value="draft">Draf</option>
                        <option value="submitted">Diajukan</option>
                        <option value="approved">Disetujui</option>
                        <option value="received">Diterima</option>
                        <option value="cancelled">Dibatalkan</option>
                    </Select>
                    <Button onClick={openCreate}>
                        <Plus className="h-4 w-4" />
                        Buat PO
                    </Button>
                </div>
            </div>

            <section className="overflow-hidden rounded-card border border-line bg-surface shadow-1">
                <TableWrap>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Nomor</TableHead>
                                <TableHead>Supplier</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead className="text-right">Total</TableHead>
                                <TableHead>Dibuat</TableHead>
                                <TableHead className="text-right">Aksi</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {filtered.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={6} className="py-8 text-center text-sm text-ink-2">
                                        Tidak ada Purchase Order ditemukan.
                                    </TableCell>
                                </TableRow>
                            ) : (
                                filtered.map((order) => (
                                    <TableRow key={order.id}>
                                        <TableCell className="font-bold">
                                            <button
                                                type="button"
                                                className="text-primary hover:underline"
                                                onClick={() => openDetail(order)}
                                            >
                                                {order.number}
                                            </button>
                                        </TableCell>
                                        <TableCell className="text-sm">{order.supplier_name ?? '—'}</TableCell>
                                        <TableCell>
                                            <Badge variant={statusVariant[order.status] ?? 'outline'}>
                                                {order.status_label}
                                            </Badge>
                                        </TableCell>
                                        <TableCell className="tabular text-right font-semibold">
                                            {formatRupiah(order.total)}
                                        </TableCell>
                                        <TableCell className="text-xs text-ink-2">
                                            {order.created_at ? new Date(order.created_at).toLocaleDateString('id-ID') : '—'}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() => openDetail(order)}
                                                className="gap-1 text-xs"
                                            >
                                                <FileText className="h-3.5 w-3.5" />
                                                Detail
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                ))
                            )}
                        </TableBody>
                    </Table>
                </TableWrap>
            </section>

            {/* Modal Buat PO */}
            <Dialog open={createOpen} onOpenChange={setCreateOpen}>
                <DialogContent className="max-h-[90vh] max-w-3xl overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>Buat Purchase Order Baru</DialogTitle>
                        <DialogDescription>
                            Isi item bahan yang akan dibeli. Total baris dihitung otomatis oleh UI; total final tetap dihitung di server.
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={createPurchaseOrder} className="flex flex-col gap-4">
                        <div className="grid gap-3 sm:grid-cols-3">
                            <FormField label="Supplier" error={firstError(errors, 'supplier_id')}>
                                <Select value={formSupplier} onChange={(e) => setFormSupplier(e.target.value)} required>
                                    <option value="">Pilih supplier…</option>
                                    {suppliers.map((supplier) => (
                                        <option key={supplier.id} value={supplier.id}>
                                            {supplier.name}
                                        </option>
                                    ))}
                                </Select>
                            </FormField>

                            <FormField label="Tanggal Diharapkan" error={firstError(errors, 'expected_at')}>
                                <Input type="date" value={formExpected} onChange={(e) => setFormExpected(e.target.value)} />
                            </FormField>

                            <FormField label="Catatan" error={firstError(errors, 'note')}>
                                <Input
                                    value={formNote}
                                    onChange={(e) => setFormNote(e.target.value)}
                                    maxLength={255}
                                    placeholder="Opsional"
                                />
                            </FormField>
                        </div>

                        <div className="border-t border-line pt-4">
                            <div className="mb-3 flex items-center justify-between">
                                <h3 className="text-xs font-bold uppercase tracking-wider">Item PO</h3>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() => setFormItems((prev) => [...prev, emptyRow()])}
                                >
                                    <Plus className="h-3.5 w-3.5" />
                                    Tambah Baris
                                </Button>
                            </div>

                            {firstError(errors, 'items') && (
                                <p role="alert" className="mb-2 text-xs font-medium text-bad">
                                    {firstError(errors, 'items')}
                                </p>
                            )}

                            <div className="flex flex-col gap-2">
                                {formItems.map((item, index) => (
                                    <div
                                        key={index}
                                        className="grid grid-cols-1 gap-2 rounded-input border border-line p-2 sm:grid-cols-[1fr_7rem_8rem_9rem_2.5rem] sm:items-center"
                                    >
                                        <Select
                                            value={item.ingredient_id}
                                            onChange={(e) => setItem(index, { ingredient_id: e.target.value })}
                                            required
                                            aria-label={`Bahan baris ${index + 1}`}
                                        >
                                            <option value="">Pilih bahan…</option>
                                            {ingredients.map((ingredient) => (
                                                <option key={ingredient.id} value={ingredient.id}>
                                                    {ingredient.name} ({ingredient.unit})
                                                </option>
                                            ))}
                                        </Select>
                                        <Input
                                            type="number"
                                            min={0.001}
                                            max={1000000}
                                            step={0.001}
                                            value={item.quantity}
                                            onChange={(e) => setItem(index, { quantity: e.target.value })}
                                            required
                                            aria-label={`Jumlah baris ${index + 1}`}
                                        />
                                        <Input
                                            type="number"
                                            min={0}
                                            max={1000000000}
                                            step={1}
                                            value={item.unit_cost}
                                            onChange={(e) => setItem(index, { unit_cost: e.target.value })}
                                            required
                                            aria-label={`Harga satuan baris ${index + 1}`}
                                        />
                                        <span className="tabular text-right text-sm font-semibold">
                                            {formatRupiah(lineTotal(item))}
                                        </span>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => setFormItems((prev) => prev.filter((_, i) => i !== index))}
                                            disabled={formItems.length === 1}
                                            className="text-bad hover:bg-bad-bg hover:text-bad"
                                            aria-label={`Hapus baris ${index + 1}`}
                                        >
                                            <X className="h-4 w-4" />
                                        </Button>
                                    </div>
                                ))}
                            </div>

                            <div className="mt-3 flex justify-end gap-2 text-sm">
                                <span className="text-ink-2">Grand Total:</span>
                                <span className="tabular font-bold">{formatRupiah(grandTotal)}</span>
                            </div>
                        </div>

                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setCreateOpen(false)}>
                                Batal
                            </Button>
                            <Button type="submit" disabled={submitting}>
                                {submitting && <Loader2 className="h-4 w-4 animate-spin" />}
                                <Save className="h-4 w-4" />
                                Simpan PO (Draf)
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Modal Detail PO */}
            <Dialog open={detailTarget !== null} onOpenChange={(open) => !open && setDetailTarget(null)}>
                <DialogContent className="max-h-[90vh] max-w-3xl overflow-y-auto">
                    {detailTarget && (
                        <>
                            <DialogHeader>
                                <DialogTitle>{detailTarget.number}</DialogTitle>
                                <DialogDescription className="flex flex-wrap items-center gap-2">
                                    <span>{detailTarget.supplier_name ?? '—'}</span>
                                    <Badge variant={statusVariant[detailTarget.status] ?? 'outline'}>
                                        {detailTarget.status_label}
                                    </Badge>
                                </DialogDescription>
                            </DialogHeader>

                            <div className="overflow-hidden rounded-card border border-line">
                                <TableWrap>
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead>Bahan</TableHead>
                                                <TableHead className="text-right">Jumlah</TableHead>
                                                <TableHead className="text-right">Harga Satuan</TableHead>
                                                <TableHead className="text-right">Subtotal</TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {detailTarget.items.map((item) => (
                                                <TableRow key={item.id ?? item.ingredient_id}>
                                                    <TableCell>
                                                        {item.ingredient_name ?? '—'}
                                                        <span className="ml-1 text-xs text-ink-2">({item.ingredient_unit ?? 'unit'})</span>
                                                    </TableCell>
                                                    <TableCell className="tabular text-right">
                                                        {formatMillis(item.quantity_milli)}
                                                    </TableCell>
                                                    <TableCell className="tabular text-right">
                                                        {formatRupiah(item.unit_cost)}
                                                    </TableCell>
                                                    <TableCell className="tabular text-right">
                                                        {formatRupiah(item.line_total)}
                                                    </TableCell>
                                                </TableRow>
                                            ))}
                                        </TableBody>
                                    </Table>
                                </TableWrap>
                                <div className="flex items-center justify-end gap-2 border-t border-line bg-surface-2 px-4 py-3 text-sm">
                                    <span className="font-bold">Total:</span>
                                    <span className="tabular font-bold">{formatRupiah(detailTarget.total)}</span>
                                </div>
                            </div>

                            <div className="grid gap-2 rounded-input border border-line p-3 text-xs sm:grid-cols-2">
                                <div className="flex justify-between">
                                    <span className="text-ink-2">Dibuat oleh</span>
                                    <span>{detailTarget.creator_name ?? '—'}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-ink-2">Disetujui oleh</span>
                                    <span>{detailTarget.approver_name ?? '—'}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-ink-2">Diajukan</span>
                                    <span>
                                        {detailTarget.submitted_at
                                            ? new Date(detailTarget.submitted_at).toLocaleString('id-ID')
                                            : '—'}
                                    </span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-ink-2">Diterima</span>
                                    <span>
                                        {detailTarget.received_at
                                            ? new Date(detailTarget.received_at).toLocaleString('id-ID')
                                            : '—'}
                                    </span>
                                </div>
                                {detailTarget.expected_at && (
                                    <div className="flex justify-between">
                                        <span className="text-ink-2">Estimasi tiba</span>
                                        <span>{new Date(detailTarget.expected_at).toLocaleDateString('id-ID')}</span>
                                    </div>
                                )}
                                {detailTarget.note && (
                                    <div className="border-t border-line pt-2 text-ink-2 sm:col-span-2">
                                        {detailTarget.note}
                                    </div>
                                )}
                            </div>

                            <DialogFooter className="sm:justify-between">
                                <div className="flex flex-col gap-2">
                                    {detailTarget.status === 'draft' && (
                                        <Button type="button" onClick={() => transition(detailTarget, 'submit')} disabled={submitting}>
                                            <FileText className="h-4 w-4" />
                                            Ajukan untuk Persetujuan
                                        </Button>
                                    )}
                                    {detailTarget.status === 'submitted' && (
                                        <Button type="button" onClick={() => transition(detailTarget, 'approve')} disabled={submitting}>
                                            <CheckCircle className="h-4 w-4" />
                                            Setujui PO
                                        </Button>
                                    )}
                                    {detailTarget.status === 'approved' && (
                                        <Button type="button" onClick={() => setReceiveTarget(detailTarget)} disabled={submitting}>
                                            <Package className="h-4 w-4" />
                                            Terima Barang &amp; Tambah Stok
                                        </Button>
                                    )}
                                    {detailTarget.status === 'received' && (
                                        <div className="rounded-input border border-ok/30 bg-ok-bg p-2 text-xs font-semibold text-ok">
                                            PO sudah diterima dan stok sudah diperbarui.
                                        </div>
                                    )}
                                </div>
                                <Button type="button" variant="outline" onClick={() => setDetailTarget(null)}>
                                    Tutup
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </DialogContent>
            </Dialog>

            {/* Konfirmasi Terima PO */}
            <AlertDialog open={receiveTarget !== null} onOpenChange={(open) => !open && setReceiveTarget(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Terima PO {receiveTarget?.number}?</AlertDialogTitle>
                        <AlertDialogDescription>
                            Seluruh item pada PO ini ditambahkan ke stok sesuai qty pesanan. Penerimaan bersifat idempoten — submit ulang tidak menggandakan stok.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Batal</AlertDialogCancel>
                        <AlertDialogAction
                            onClick={() => {
                                if (receiveTarget) {
                                    void transition(receiveTarget, 'receive');
                                }
                                setReceiveTarget(null);
                            }}
                        >
                            Terima &amp; Tambah Stok
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </div>
    );
}