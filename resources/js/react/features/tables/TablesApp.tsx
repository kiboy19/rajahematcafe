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
import { Plus, Pencil, Trash2, Search, Loader2, QrCode, RefreshCw, Printer } from 'lucide-react';

export interface CafeTableItem {
    id: number;
    label: string;
    seats: number;
    is_active: boolean;
    qr_token?: string;
}

export function TablesApp({ initialTables = [] }: { initialTables?: CafeTableItem[] }) {
    const [tables, setTables] = React.useState<CafeTableItem[]>(initialTables);
    const [search, setSearch] = React.useState('');
    const [dialogTarget, setDialogTarget] = React.useState<CafeTableItem | 'create' | null>(null);
    const [deleteTarget, setDeleteTarget] = React.useState<CafeTableItem | null>(null);
    const [qrTarget, setQrTarget] = React.useState<CafeTableItem | null>(null);
    const [rotateTarget, setRotateTarget] = React.useState<CafeTableItem | null>(null);

    const [formLabel, setFormLabel] = React.useState('');
    const [formSeats, setFormSeats] = React.useState(4);
    const [formIsActive, setFormIsActive] = React.useState(true);
    const [errors, setErrors] = React.useState<ValidationErrors>({});
    const [submitting, setSubmitting] = React.useState(false);
    const [flash, setFlash] = React.useState<{ text: string; type: 'success' | 'error' } | null>(null);

    function notify(text: string, type: 'success' | 'error' = 'success') {
        setFlash({ text, type });
        setTimeout(() => setFlash(null), 4000);
    }

    function openCreate() {
        setFormLabel('');
        setFormSeats(4);
        setFormIsActive(true);
        setErrors({});
        setDialogTarget('create');
    }

    function openEdit(table: CafeTableItem) {
        setFormLabel(table.label);
        setFormSeats(table.seats);
        setFormIsActive(table.is_active);
        setErrors({});
        setDialogTarget(table);
    }

    async function handleSave(e: React.FormEvent) {
        e.preventDefault();
        if (!dialogTarget) return;

        setSubmitting(true);
        setErrors({});

        const isCreate = dialogTarget === 'create';
        const url = isCreate ? '/kelola-meja' : `/kelola-meja/${dialogTarget.id}`;
        const method = isCreate ? 'POST' : 'PATCH';

        const res = await apiRequest<CafeTableItem>(method, url, {
            label: formLabel,
            seats: formSeats,
            is_active: formIsActive,
        });

        setSubmitting(false);

        if (res.ok && res.data) {
            setTables((prev) =>
                isCreate
                    ? [...prev, res.data!].sort((a, b) => a.label.localeCompare(b.label, undefined, { numeric: true }))
                    : prev
                          .map((t) => (t.id === res.data!.id ? res.data! : t))
                          .sort((a, b) => a.label.localeCompare(b.label, undefined, { numeric: true }))
            );
            setDialogTarget(null);
            notify(isCreate ? 'Meja berhasil ditambahkan.' : 'Meja berhasil diperbarui.');
        } else {
            setErrors(res.errors ?? {});
            notify(res.message, 'error');
        }
    }

    async function handleToggleStatus(table: CafeTableItem) {
        const res = await apiRequest<CafeTableItem>('PATCH', `/kelola-meja/${table.id}/status`);

        if (res.ok && res.data) {
            setTables((prev) =>
                prev.map((t) => (t.id === table.id ? { ...t, is_active: res.data!.is_active } : t))
            );
            notify(`Status meja ${table.label} diubah.`);
        } else {
            notify(res.message, 'error');
        }
    }

    async function handleDelete() {
        if (!deleteTarget) return;

        const res = await apiRequest('DELETE', `/kelola-meja/${deleteTarget.id}`);

        if (res.ok) {
            setTables((prev) => prev.filter((t) => t.id !== deleteTarget.id));
            setDeleteTarget(null);
            notify('Meja berhasil dihapus.');
        } else {
            notify(res.message, 'error');
            setDeleteTarget(null);
        }
    }

    async function handleRotateQr() {
        if (!rotateTarget) return;

        const res = await apiRequest<CafeTableItem>('POST', `/meja/${rotateTarget.id}/qr-ulang`);

        if (res.ok && res.data) {
            setTables((prev) => prev.map((t) => (t.id === rotateTarget.id ? res.data! : t)));
            if (qrTarget?.id === rotateTarget.id) {
                setQrTarget(res.data);
            }
            notify(`QR meja ${rotateTarget.label} berhasil diganti. Cetak ulang QR.`);
            setRotateTarget(null);
        } else {
            notify(res.message, 'error');
            setRotateTarget(null);
        }
    }

    const filtered = tables.filter((t) => t.label.toLowerCase().includes(search.toLowerCase()));

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
                        placeholder="Cari label meja…"
                        className="pl-9"
                    />
                </div>
                <div className="flex items-center gap-2">
                    <Button variant="outline" asChild>
                        <a href="/meja/qr-cetak" target="_blank" rel="noopener noreferrer">
                            <Printer className="h-4 w-4" />
                            Cetak Kartu QR
                        </a>
                    </Button>
                    <Button onClick={openCreate}>
                        <Plus className="h-4 w-4" />
                        Tambah Meja
                    </Button>
                </div>
            </div>

            <section className="overflow-hidden rounded-card border border-line bg-surface shadow-1">
                <TableWrap>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead className="w-28">Label</TableHead>
                                <TableHead className="w-32">Kapasitas</TableHead>
                                <TableHead className="w-32">Status</TableHead>
                                <TableHead className="w-36 text-center">QR Meja</TableHead>
                                <TableHead className="text-right">Aksi</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {filtered.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={5} className="py-8 text-center text-sm text-ink-2">
                                        Tidak ada meja ditemukan.
                                    </TableCell>
                                </TableRow>
                            ) : (
                                filtered.map((table) => (
                                    <TableRow key={table.id}>
                                        <TableCell className="font-bold text-ink uppercase">
                                            Meja {table.label}
                                        </TableCell>
                                        <TableCell className="tabular text-sm text-ink-2">
                                            {table.seats} orang
                                        </TableCell>
                                        <TableCell>
                                            <button
                                                type="button"
                                                onClick={() => handleToggleStatus(table)}
                                                className="cursor-pointer rounded-full focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                                                title="Klik untuk ubah status meja"
                                            >
                                                <Badge variant={table.is_active ? 'success' : 'danger'}>
                                                    {table.is_active ? 'Aktif' : 'Nonaktif'}
                                                </Badge>
                                            </button>
                                        </TableCell>
                                        <TableCell className="text-center">
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() => setQrTarget(table)}
                                                className="gap-1 text-xs"
                                            >
                                                <QrCode className="h-3.5 w-3.5" />
                                                Lihat QR
                                            </Button>
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <div className="inline-flex items-center gap-1">
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() => openEdit(table)}
                                                    aria-label={`Ubah Meja ${table.label}`}
                                                >
                                                    <Pencil className="h-4 w-4" />
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() => setRotateTarget(table)}
                                                    aria-label={`Rotasi QR Meja ${table.label}`}
                                                    title="Terbitkan token QR baru"
                                                >
                                                    <RefreshCw className="h-4 w-4" />
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() => setDeleteTarget(table)}
                                                    className="text-bad hover:bg-bad-bg hover:text-bad"
                                                    aria-label={`Hapus Meja ${table.label}`}
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

            {/* Modal Tambah / Ubah Meja */}
            <Dialog open={dialogTarget !== null} onOpenChange={(open) => !open && setDialogTarget(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{dialogTarget === 'create' ? 'Tambah Meja' : 'Ubah Meja'}</DialogTitle>
                        <DialogDescription>
                            Label meja unik untuk identifikasi fisik dan pemesanan pelanggan via QR.
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={handleSave} className="flex flex-col gap-4">
                        <FormField label="Label Meja" error={firstError(errors, 'label')} hint="Contoh: 01, 02, VIP-1">
                            <Input
                                value={formLabel}
                                onChange={(e) => setFormLabel(e.target.value)}
                                required
                                autoFocus
                                maxLength={20}
                                className="uppercase"
                            />
                        </FormField>

                        <FormField label="Kapasitas Kursi (orang)" error={firstError(errors, 'seats')}>
                            <Input
                                type="number"
                                min={1}
                                max={50}
                                value={formSeats}
                                onChange={(e) => setFormSeats(parseInt(e.target.value, 10) || 1)}
                                required
                            />
                        </FormField>

                        <div className="flex items-center justify-between rounded-input border border-line p-3">
                            <div>
                                <span className="block text-xs font-semibold">Aktif & Dapat Digunakan</span>
                                <span className="text-[11px] text-ink-2">Meja nonaktif tidak muncul di POS atau pemesanan QR</span>
                            </div>
                            <Switch checked={formIsActive} onCheckedChange={setFormIsActive} />
                        </div>

                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setDialogTarget(null)}>
                                Batal
                            </Button>
                            <Button type="submit" disabled={submitting}>
                                {submitting && <Loader2 className="h-4 w-4 animate-spin" />}
                                Simpan Meja
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Modal Preview QR */}
            <Dialog open={qrTarget !== null} onOpenChange={(open) => !open && setQrTarget(null)}>
                <DialogContent className="max-w-sm text-center">
                    <DialogHeader>
                        <DialogTitle>QR Code Meja {qrTarget?.label}</DialogTitle>
                        <DialogDescription>
                            Pelanggan memindai QR ini untuk membuka menu dan memesan langsung dari meja.
                        </DialogDescription>
                    </DialogHeader>

                    {qrTarget && (
                        <div className="my-2 flex flex-col items-center gap-3">
                            <div className="rounded-card border border-line bg-white p-4 shadow-1">
                                <img
                                    src={`/meja/${qrTarget.id}/qr.svg`}
                                    alt={`QR Meja ${qrTarget.label}`}
                                    className="h-48 w-48"
                                />
                            </div>
                            <span className="text-xs text-ink-2">
                                Token: <code className="rounded bg-surface-2 px-1 py-0.5">{qrTarget.qr_token?.slice(0, 8)}…</code>
                            </span>
                        </div>
                    )}

                    <DialogFooter className="sm:justify-between">
                        <Button
                            type="button"
                            variant="destructive"
                            size="sm"
                            onClick={() => {
                                setRotateTarget(qrTarget);
                                setQrTarget(null);
                            }}
                        >
                            <RefreshCw className="h-3.5 w-3.5" />
                            Rotasi QR
                        </Button>
                        <Button type="button" variant="outline" size="sm" onClick={() => setQrTarget(null)}>
                            Tutup
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Konfirmasi Hapus */}
            <AlertDialog open={deleteTarget !== null} onOpenChange={(open) => !open && setDeleteTarget(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Hapus Meja {deleteTarget?.label}?</AlertDialogTitle>
                        <AlertDialogDescription>
                            Meja yang sudah memiliki riwayat pesanan tidak dapat dihapus. Nonaktifkan meja jika tidak lagi digunakan.
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

            {/* Konfirmasi Rotasi QR */}
            <AlertDialog open={rotateTarget !== null} onOpenChange={(open) => !open && setRotateTarget(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Rotasi QR Meja {rotateTarget?.label}?</AlertDialogTitle>
                        <AlertDialogDescription>
                            Token QR lama akan dibatalkan seketika dan QR fisik yang sudah ditempel harus diganti dengan hasil cetak baru.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Batal</AlertDialogCancel>
                        <AlertDialogAction variant="destructive" onClick={handleRotateQr}>
                            Terbitkan QR Baru
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </div>
    );
}
