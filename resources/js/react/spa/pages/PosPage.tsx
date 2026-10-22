import { useMemo, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { deleteJson, patchJson, postJson } from '../api';
import { mutate, useFlash, useResource } from '../hooks';
import { Button, EmptyState, ErrorBox, Field, inputClass, Loading, Money, Panel, selectClass } from '../ui';

interface MenuItemDto {
    id: number;
    name: string;
    price: number;
    is_available: boolean;
    category_id: number;
    category?: { id: number; name: string } | null;
}

interface CategoryDto {
    id: number;
    name: string;
}

interface TableDto {
    id: number;
    label: string;
    is_active: boolean;
}

interface CartLine {
    menu_item: MenuItemDto;
    quantity: number;
    line_total: number;
    note: string | null;
}

interface Bill {
    subtotal: number;
    tax: number;
    service: number;
    grand_total: number;
}

interface PosData {
    categories: CategoryDto[];
    menuItems: MenuItemDto[];
    tables: TableDto[];
    lines: CartLine[];
    bill: Bill;
    selectedTable: string;
    selectedChannel: 'dine_in' | 'takeaway';
}

export function PosPage() {
    const navigate = useNavigate();
    const { data, loading, error, reload } = useResource<PosData>('/api/pos');
    const { message, flashSuccess, flashError } = useFlash();

    const [categoryFilter, setCategoryFilter] = useState<number | null>(null);
    const [search, setSearch] = useState('');
    const [channel, setChannel] = useState<'dine_in' | 'takeaway'>('dine_in');
    const [tableLabel, setTableLabel] = useState('');
    const [voucherCode, setVoucherCode] = useState('');
    const [discount, setDiscount] = useState('');
    const [note, setNote] = useState('');
    const [busy, setBusy] = useState(false);
    const [override, setOverride] = useState<{ lines: CartLine[]; bill: Bill | null } | null>(null);
    const [cartError, setCartError] = useState<{ message: string; retry: () => void } | null>(null);

    const menuItems = data?.menuItems ?? [];
    const categories = data?.categories ?? [];
    const tables = data?.tables ?? [];
    const lines = override?.lines ?? data?.lines ?? [];
    const bill = override?.bill ?? data?.bill;

    const context = useMemo(
        () => ({ channel, table_label: tableLabel || undefined }),
        [channel, tableLabel]
    );

    const filtered = useMemo(() => {
        const term = search.trim().toLowerCase();
        return menuItems.filter((item) => {
            if (categoryFilter !== null && item.category_id !== categoryFilter) {
                return false;
            }

            return term === '' || item.name.toLowerCase().includes(term);
        });
    }, [menuItems, categoryFilter, search]);

    if (loading) {
        return <Loading label="Menyiapkan POS..." />;
    }

    if (error || !data) {
        return <ErrorBox message={error ?? 'Gagal memuat data POS.'} />;
    }

    const applyResult = (lines: CartLine[], bill: Bill) => {
        setData(lines, bill);
    };

    function setData(nextLines: CartLine[], nextBill: Bill) {
        setOverride({ lines: nextLines, bill: nextBill });
        reload();
    }

    const addToCart = async (item: MenuItemDto) => {
        // Optimistic: tampilkan baris langsung, lalu rekonsiliasi dengan server.
        const existing = lines.find((line) => line.menu_item.id === item.id);
        setOverride({
            lines: existing
                ? lines.map((line) => line.menu_item.id === item.id
                    ? { ...line, quantity: line.quantity + 1, line_total: line.line_total + item.price }
                    : line)
                : [...lines, { menu_item: item, quantity: 1, line_total: item.price, note: null }],
            bill: null,
        });
        setBusy(true);
        setCartError(null);
        const result = await postJson<{ lines: CartLine[]; bill: Bill }>('/api/pos/keranjang', {
            menu_item_id: item.id,
            quantity: 1,
            ...context,
        });
        setBusy(false);

        if (!result.ok) {
            setOverride(null);
            setCartError({ message: result.message, retry: () => addToCart(item) });
            return;
        }

        if (result.data) {
            applyResult(result.data.lines, result.data.bill);
        }
        flashSuccess(`${item.name} ditambahkan ke keranjang.`);
    };

    const changeQuantity = async (line: CartLine, quantity: number) => {
        // Optimistic: ubah kuantitas dulu, buang baris bila kuantitas 0.
        setOverride({
            lines: quantity < 1
                ? lines.filter((row) => row.menu_item.id !== line.menu_item.id)
                : lines.map((row) => row.menu_item.id === line.menu_item.id ? { ...row, quantity } : row),
            bill: null,
        });
        setBusy(true);
        setCartError(null);
        const request = quantity < 1
            ? patchJson<{ lines: CartLine[]; bill: Bill }>('/api/pos/keranjang', { menu_item_id: line.menu_item.id, quantity: 0, ...context })
            : patchJson<{ lines: CartLine[]; bill: Bill }>('/api/pos/keranjang', { menu_item_id: line.menu_item.id, quantity, ...context });
        const result = await request;
        setBusy(false);

        if (!result.ok) {
            setOverride(null);
            setCartError({ message: result.message, retry: () => changeQuantity(line, quantity) });
            return;
        }

        if (result.data) {
            applyResult(result.data.lines, result.data.bill);
        }
    };

    const clearCart = async () => {
        setBusy(true);
        const result = await deleteJson<{ lines: CartLine[]; bill: Bill }>('/api/pos/keranjang');
        setBusy(false);

        if (!result.ok) {
            flashError(result.message);
            return;
        }

        if (result.data) {
            applyResult(result.data.lines, result.data.bill);
        }
        flashSuccess('Keranjang dikosongkan.');
    };

    const checkout = async () => {
        if (lines.length === 0) {
            flashError('Keranjang masih kosong.');
            return;
        }

        // Validasi sisi klien agar kasir menerima pesan yang jelas, bukan 422 mentah.
        const resolvedTable = (tableLabel || data.selectedTable || '').trim();
        if (channel === 'dine_in' && resolvedTable === '') {
            flashError('Pilih meja terlebih dahulu untuk pesanan dine-in.');
            return;
        }

        if (voucherCode.trim() !== '' && discount !== '' && Number(discount) > 0) {
            flashError('Gunakan salah satu: kode voucher atau diskon manual.');
            return;
        }

        setBusy(true);
        const result = await postJson<{ order_id: number; order_number: string }>('/api/pos/checkout', {
            channel,
            table_label: channel === 'dine_in' ? resolvedTable : undefined,
            voucher_code: voucherCode.trim() || undefined,
            discount: discount === '' ? undefined : Number(discount),
            note: note.trim() || undefined,
        });
        setBusy(false);

        if (!result.ok) {
            flashError(result.message);
            return;
        }

        flashSuccess('Pesanan dikirim ke dapur.');
        navigate(`/pesanan/${result.data?.order_id}`);
    };

    return (
        <div className="flex flex-col lg:flex-row gap-5 pb-20 lg:pb-0">
            {/* Katalog Menu */}
            <section className="flex-1 flex flex-col gap-4 min-w-0">
                {cartError && (
                    <div role="alert" className="flex items-center justify-between rounded-card border border-bad/30 bg-bad-bg p-3 text-xs text-bad">
                        <span>{cartError.message}</span>
                        <button type="button" className="underline font-bold" onClick={cartError.retry}>Coba lagi</button>
                    </div>
                )}
                <div className="flex gap-2">
                    <input
                        type="search"
                        className={inputClass}
                        placeholder="Cari nama menu..."
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        aria-label="Cari nama menu"
                    />
                </div>

                <div className="flex gap-2 overflow-x-auto pb-1">
                    <button
                        className={`h-11 px-4 rounded-full text-xs font-semibold whitespace-nowrap ${categoryFilter === null ? 'bg-primary text-white' : 'border border-line bg-surface text-ink-2 hover:border-line-strong'}`}
                        onClick={() => setCategoryFilter(null)}
                        aria-pressed={categoryFilter === null}
                    >
                        Semua ({menuItems.length})
                    </button>
                    {categories.map((category) => (
                        <button
                            key={category.id}
                            className={`h-11 px-4 rounded-full text-xs font-semibold whitespace-nowrap ${categoryFilter === category.id ? 'bg-primary text-white' : 'border border-line bg-surface text-ink-2 hover:border-line-strong'}`}
                            onClick={() => setCategoryFilter(category.id)}
                            aria-pressed={categoryFilter === category.id}
                        >
                            {category.name}
                        </button>
                    ))}
                </div>

                {filtered.length === 0 ? (
                    <EmptyState title="Menu tidak ditemukan." hint="Coba kata kunci lain atau pilih kategori Semua." />
                ) : (
                    <div className="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-3">
                        {filtered.map((item) => (
                            <button
                                key={item.id}
                                type="button"
                                onClick={() => item.is_available && addToCart(item)}
                                disabled={busy || !item.is_available}
                                aria-label={`Tambah ${item.name} ke keranjang`}
                                className={`text-left bg-surface border border-line rounded-card p-3 shadow-1 flex flex-col justify-between hover:border-primary transition-colors ${!item.is_available ? 'opacity-55 cursor-not-allowed' : ''}`}
                            >
                                <div>
                                    <div className="aspect-4/3 rounded-input bg-thumb flex items-center justify-center text-ink-2/60 text-xs mb-2.5 font-display font-semibold">
                                        {item.category?.name ?? 'Menu'}
                                    </div>
                                    <h3 className="font-semibold text-sm leading-snug line-clamp-2">{item.name}</h3>
                                </div>

                                <div className="mt-3 pt-2 border-t border-line flex items-baseline justify-between">
                                    <Money value={item.price} className="font-bold text-sm" />
                                    {item.is_available ? (
                                        <span className="text-xs font-bold text-primary">+ Tambah</span>
                                    ) : (
                                        <span className="text-[10px] font-bold text-bad uppercase tracking-wider">Habis</span>
                                    )}
                                </div>
                            </button>
                        ))}
                    </div>
                )}
            </section>

            {/* Sidebar Tagihan */}
            <aside id="pos-bill" className="w-full lg:w-96 shrink-0 lg:sticky lg:top-20 lg:self-start lg:max-h-[calc(100vh-6rem)] lg:overflow-y-auto">
                <Panel className="p-5 flex flex-col gap-4">
                    <div className="flex items-center justify-between pb-3 border-b border-line">
                        <div>
                            <h2 className="font-display font-bold text-base leading-none">Tagihan Pesanan</h2>
                            <span className="text-xs text-ink-2">Dine-in / Takeaway kasir</span>
                        </div>
                        <button className="text-xs text-ink-2 hover:text-bad disabled:opacity-40" onClick={clearCart} disabled={busy || lines.length === 0}>
                            Kosongkan
                        </button>
                    </div>

                    <div className="grid grid-cols-2 gap-2">
                        <Field label="Tipe">
                            <select
                                className={selectClass}
                                value={channel}
                                onChange={(event) => setChannel(event.target.value as 'dine_in' | 'takeaway')}
                            >
                                <option value="dine_in">Dine-in (Meja)</option>
                                <option value="takeaway">Takeaway</option>
                            </select>
                        </Field>

                        <Field label="Meja">
                            <select
                                className={selectClass}
                                value={tableLabel || data.selectedTable}
                                onChange={(event) => setTableLabel(event.target.value)}
                                disabled={channel === 'takeaway'}
                            >
                                <option value="">Pilih meja</option>
                                {tables.map((table) => (
                                    <option key={table.id} value={table.label}>Meja {table.label}</option>
                                ))}
                            </select>
                        </Field>
                    </div>

                    <div className="grid grid-cols-2 gap-2">
                        <Field label="Voucher (opsional)">
                            <input className={`${inputClass} uppercase`} placeholder="KODE" maxLength={30} value={voucherCode} onChange={(event) => setVoucherCode(event.target.value)} />
                        </Field>
                        <Field label="Diskon Manual (Rp)">
                            <input className={inputClass} type="number" min={0} placeholder="0" value={discount} onChange={(event) => setDiscount(event.target.value)} />
                        </Field>
                    </div>

                    <Field label="Catatan Pesanan (opsional)">
                        <input className={inputClass} placeholder="Misal: jangan pakai sedotan" maxLength={255} value={note} onChange={(event) => setNote(event.target.value)} />
                    </Field>

                    <div className="flex flex-col divide-y divide-line max-h-72 overflow-y-auto">
                        {lines.length === 0 ? (
                            <div className="py-8 text-center text-xs text-ink-2">Pilih menu di sebelah kiri untuk menambah ke tagihan.</div>
                        ) : (
                            lines.map((line) => (
                                <div key={line.menu_item.id} className="py-2.5 flex items-start justify-between gap-3 text-xs">
                                    <div className="flex-1">
                                        <span className="font-semibold text-sm block leading-tight">{line.menu_item.name}</span>
                                        <span className="text-ink-2 tabular"><Money value={line.menu_item.price} /> × {line.quantity}</span>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <button className="w-11 h-11 rounded-full border border-line hover:bg-surface-2 font-bold" onClick={() => changeQuantity(line, line.quantity - 1)} disabled={busy} aria-label="Kurangi kuantitas">−</button>
                                        <span className="w-6 text-center font-bold text-sm tabular">{line.quantity}</span>
                                        <button className="w-11 h-11 rounded-full border border-line hover:bg-surface-2 font-bold" onClick={() => changeQuantity(line, line.quantity + 1)} disabled={busy} aria-label="Tambah kuantitas">+</button>
                                        <Money value={line.line_total} className="font-bold min-w-16 text-right" />
                                    </div>
                                </div>
                            ))
                        )}
                    </div>

                    {bill && (
                        <div className="pt-4 border-t border-line flex flex-col gap-1 text-xs text-ink-2">
                            <div className="flex justify-between"><span>Subtotal</span><Money value={bill.subtotal} className="font-semibold text-ink" /></div>
                            <p className="text-[11px]">Belum termasuk diskon manual / voucher (dihitung saat pesanan dikirim).</p>
                            <div className="flex justify-between"><span>PB1</span><Money value={bill.tax} className="font-semibold text-ink" /></div>
                            <div className="flex justify-between"><span>Layanan</span><Money value={bill.service} className="font-semibold text-ink" /></div>
                            <div className="flex justify-between items-baseline pt-2 border-t border-line-strong text-ink font-bold">
                                <span className="text-xs uppercase tracking-wider text-ink-2">Total</span>
                                <Money value={bill.grand_total} className="text-xl text-primary" />
                            </div>
                        </div>
                    )}

                    <Button className="w-full h-12" onClick={checkout} disabled={busy || lines.length === 0}>
                        Kirim ke Dapur (F8)
                    </Button>
                </Panel>
            </aside>

            {/* Bar total mobile: total selalu pegang + lompat ke panel tagihan. */}
            <div className="fixed inset-x-0 bottom-0 z-30 border-t border-line bg-surface p-3 flex items-center justify-between gap-3 lg:hidden">
                <div>
                    <span className="block text-[10px] font-bold uppercase tracking-wider text-ink-2">Total</span>
                    <Money value={bill?.grand_total ?? 0} className="font-bold text-base text-primary tabular" />
                </div>
                <a
                    href="#pos-bill"
                    className="inline-flex h-11 items-center rounded-input bg-primary px-4 text-sm font-bold text-white hover:bg-primary-hover"
                >
                    Lihat Tagihan
                </a>
            </div>
        </div>
    );
}