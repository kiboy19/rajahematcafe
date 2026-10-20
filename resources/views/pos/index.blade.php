<x-layouts.app :title="'POS Kasir — Rajahemat Cafe'">
    <div class="-m-6 flex flex-col lg:flex-row min-h-[calc(100vh-3.5rem)]">
        <!-- Katalog Menu Kiri -->
        <section class="flex-1 p-5 flex flex-col gap-4 overflow-y-auto">
            <!-- Search & Kategori -->
            <div class="flex flex-col sm:flex-row gap-3 items-stretch sm:items-center justify-between">
                <form method="GET" action="{{ route('pos.index') }}" class="flex-1 flex gap-2">
                    @if ($selectedCategoryId)
                        <input type="hidden" name="kategori" value="{{ $selectedCategoryId }}">
                    @endif
                    <input type="search" name="q" value="{{ $search }}" placeholder="Cari nama menu..."
                           class="flex-1 h-10 px-3 rounded-input border border-line-strong bg-surface text-sm">
                    <button type="submit" class="h-10 px-4 rounded-input border border-line bg-surface hover:bg-surface-2 font-semibold text-xs">
                        Cari
                    </button>
                    @if ($search)
                        <a href="{{ route('pos.index', array_filter(['kategori' => $selectedCategoryId])) }}"
                           class="h-10 px-3 rounded-input border border-line bg-surface hover:bg-surface-2 flex items-center text-xs font-semibold">
                            Reset
                        </a>
                    @endif
                </form>

                <div class="text-xs text-ink-2 flex items-center gap-2">
                    <span class="inline-block w-2 h-2 rounded-full bg-ok"></span>
                    <span>Kasir Siap · Shift Terbuka</span>
                </div>
            </div>

            <!-- Tab Kategori -->
            <div class="flex gap-2 overflow-x-auto pb-1">
                <a href="{{ route('pos.index', array_filter(['q' => $search])) }}"
                   class="h-8 px-3.5 rounded-full text-xs font-semibold flex items-center whitespace-nowrap {{ ! $selectedCategoryId ? 'bg-primary text-white' : 'border border-line bg-surface text-ink-2 hover:border-line-strong' }}">
                    Semua ({{ $menuItems->count() }})
                </a>
                @foreach ($categories as $cat)
                    <a href="{{ route('pos.index', array_filter(['kategori' => $cat->id, 'q' => $search])) }}"
                       class="h-8 px-3.5 rounded-full text-xs font-semibold flex items-center whitespace-nowrap {{ $selectedCategoryId === $cat->id ? 'bg-primary text-white' : 'border border-line bg-surface text-ink-2 hover:border-line-strong' }}">
                        {{ $cat->name }}
                    </a>
                @endforeach
            </div>

            <!-- Grid Menu -->
            @if ($menuItems->isEmpty())
                <div class="bg-surface border border-line rounded-card p-10 text-center text-ink-2">
                    <p class="font-semibold text-sm">Menu tidak ditemukan.</p>
                    <p class="text-xs mt-1">Coba kata kunci lain atau pilih kategori Semua.</p>
                </div>
            @else
                <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-3">
                    @foreach ($menuItems as $item)
                        <form method="POST" action="{{ route('pos.cart.store') }}"
                              class="bg-surface border border-line rounded-card p-3 shadow-1 flex flex-col justify-between hover:border-primary transition-colors text-left {{ ! $item->is_available ? 'opacity-55' : '' }}">
                            @csrf
                            <input type="hidden" name="menu_item_id" value="{{ $item->id }}">
                            <input type="hidden" name="table_label" value="{{ $selectedTable }}">
                            <input type="hidden" name="channel" value="{{ $selectedChannel }}">

                            <div>
                                <div class="aspect-4/3 rounded-input bg-thumb flex items-center justify-center text-ink-2/60 text-xs mb-2.5 font-display font-semibold">
                                    {{ $item->category?->name ?? 'Menu' }}
                                </div>
                                <h3 class="font-semibold text-sm leading-snug line-clamp-2">{{ $item->name }}</h3>
                            </div>

                            <div class="mt-3 pt-2 border-t border-line flex items-baseline justify-between">
                                <span class="font-bold text-sm tabular">{{ \Illuminate\Support\Number::currency($item->price, 'IDR', 'id', precision: 0) }}</span>
                                @if ($item->is_available)
                                    <button type="submit" class="text-xs font-bold text-primary hover:underline">
                                        + Tambah
                                    </button>
                                @else
                                    <span class="text-[10px] font-bold text-bad uppercase tracking-wider">Habis</span>
                                @endif
                            </div>
                        </form>
                    @endforeach
                </div>
            @endif
        </section>

        <!-- Sidebar Tagihan Kanan -->
        <aside class="w-full lg:w-96 bg-surface border-t lg:border-t-0 lg:border-l border-line p-5 flex flex-col justify-between">
            <div>
                <!-- Header Meja & Kanal -->
                <div class="flex items-center justify-between pb-3 border-b border-line">
                    <div>
                        <h2 class="font-display font-bold text-base leading-none">Tagihan Pesanan</h2>
                        <span class="text-xs text-ink-2">Dine-in / Takeaway kasir</span>
                    </div>

                    <form method="POST" action="{{ route('pos.cart.destroy') }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-xs text-ink-2 hover:text-bad" title="Kosongkan keranjang" {{ empty($lines) ? 'disabled' : '' }}>
                            Kosongkan
                        </button>
                    </form>
                </div>

                <!-- Form Konteks Meja & Kanal (selalu sinkron dengan keranjang) -->
                <form id="checkout-form" method="POST" action="{{ route('pos.checkout') }}" class="mt-3 flex flex-col gap-3">
                    @csrf
                    <div class="grid grid-cols-2 gap-2">
                        <label class="flex flex-col gap-1 text-xs font-semibold">
                            <span>Tipe</span>
                            <select name="channel" id="pos-channel" class="h-9 px-2 rounded-input border border-line-strong bg-surface text-xs font-normal">
                                <option value="dine_in" {{ $selectedChannel === 'dine_in' ? 'selected' : '' }}>Dine-in (Meja)</option>
                                <option value="takeaway" {{ $selectedChannel === 'takeaway' ? 'selected' : '' }}>Takeaway</option>
                            </select>
                        </label>

                        <label class="flex flex-col gap-1 text-xs font-semibold">
                            <span>Meja</span>
                            <select name="table_label" id="pos-table" class="h-9 px-2 rounded-input border border-line-strong bg-surface text-xs font-normal">
                                @foreach ($tables as $t)
                                    <option value="{{ $t->label }}" {{ $selectedTable === $t->label ? 'selected' : '' }}>Meja {{ $t->label }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <label class="flex flex-col gap-1 text-xs font-semibold">
                            <span>Voucher (opsional)</span>
                            <input type="text" name="voucher_code" placeholder="KODE" maxlength="30"
                                   class="h-8 px-2 rounded-input border border-line-strong bg-surface text-xs font-normal uppercase">
                        </label>
                        <label class="flex flex-col gap-1 text-xs font-semibold">
                            <span>Diskon Manual (Rp)</span>
                            <input type="number" name="discount" min="0" placeholder="0"
                                   class="h-8 px-2 rounded-input border border-line-strong bg-surface text-xs font-normal">
                        </label>
                    </div>

                    <label class="flex flex-col gap-1 text-xs font-semibold">
                        <span>Catatan Pesanan (opsional)</span>
                        <input type="text" name="note" placeholder="Misal: jangan pakai sedotan" maxlength="255"
                               class="h-8 px-2 rounded-input border border-line-strong bg-surface text-xs font-normal">
                    </label>
                </form>

                <!-- Daftar Item Keranjang -->
                <div class="mt-4 flex flex-col divide-y divide-line max-h-72 overflow-y-auto">
                    @forelse ($lines as $line)
                        <div class="py-2.5 flex items-start justify-between gap-3 text-xs">
                            <div class="flex-1">
                                <span class="font-semibold text-sm block leading-tight">{{ $line['menu_item']->name }}</span>
                                <span class="text-ink-2 tabular">{{ \Illuminate\Support\Number::currency($line['menu_item']->price, 'IDR', 'id', precision: 0) }} × {{ $line['quantity'] }}</span>
                            </div>

                            <div class="flex items-center gap-2">
                                <form method="POST" action="{{ route('pos.cart.update') }}" class="inline-flex items-center border border-line rounded-full overflow-hidden">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="menu_item_id" value="{{ $line['menu_item']->id }}">
                                    <input type="hidden" name="table_label" value="{{ $selectedTable }}">
                                    <input type="hidden" name="channel" value="{{ $selectedChannel }}">
                                    <input type="hidden" name="quantity" value="{{ $line['quantity'] - 1 }}">
                                    <button type="submit" class="w-6 h-6 hover:bg-surface-2 font-bold leading-none">−</button>
                                </form>

                                <span class="w-5 text-center font-bold tabular">{{ $line['quantity'] }}</span>

                                <form method="POST" action="{{ route('pos.cart.update') }}" class="inline-flex items-center border border-line rounded-full overflow-hidden">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="menu_item_id" value="{{ $line['menu_item']->id }}">
                                    <input type="hidden" name="table_label" value="{{ $selectedTable }}">
                                    <input type="hidden" name="channel" value="{{ $selectedChannel }}">
                                    <input type="hidden" name="quantity" value="{{ $line['quantity'] + 1 }}">
                                    <button type="submit" class="w-6 h-6 hover:bg-surface-2 font-bold leading-none">+</button>
                                </form>

                                <span class="font-bold tabular min-w-16 text-right">
                                    {{ \Illuminate\Support\Number::currency($line['line_total'], 'IDR', 'id', precision: 0) }}
                                </span>
                            </div>
                        </div>
                    @empty
                        <div class="py-8 text-center text-xs text-ink-2">
                            Pilih menu di sebelah kiri untuk menambah ke tagihan.
                        </div>
                    @endforelse
                </div>
            </div>

            <!-- Ringkasan & Tombol Kirim -->
            <div class="mt-4 pt-4 border-t border-line">
                <div class="flex flex-col gap-1 text-xs text-ink-2">
                    <div class="flex justify-between">
                        <span>Subtotal</span>
                        <span class="tabular font-semibold text-ink">{{ \Illuminate\Support\Number::currency($bill['subtotal'], 'IDR', 'id', precision: 0) }}</span>
                    </div>
                    <p class="text-[11px] text-ink-2">Belum termasuk diskon manual / voucher (dihitung saat pesanan dikirim).</p>
                    <div class="flex justify-between">
                        <span>PB1 ({{ config('pos.tax_percent') }}%)</span>
                        <span class="tabular font-semibold text-ink">{{ \Illuminate\Support\Number::currency($bill['tax'], 'IDR', 'id', precision: 0) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Layanan ({{ config('pos.service_percent') }}%)</span>
                        <span class="tabular font-semibold text-ink">{{ \Illuminate\Support\Number::currency($bill['service'], 'IDR', 'id', precision: 0) }}</span>
                    </div>
                    <div class="flex justify-between items-baseline pt-2 border-t border-line-strong text-ink font-bold">
                        <span class="text-xs uppercase tracking-wider text-ink-2">Total</span>
                        <span class="text-xl tabular text-primary">{{ \Illuminate\Support\Number::currency($bill['grand_total'], 'IDR', 'id', precision: 0) }}</span>
                    </div>
                </div>

                <button type="submit" form="checkout-form"
                        {{ empty($lines) ? 'disabled' : '' }}
                        class="w-full mt-3 h-12 rounded-input bg-primary hover:bg-primary-hover active:bg-primary-press text-white font-bold text-sm shadow-1 disabled:opacity-40 disabled:cursor-not-allowed">
                    Kirim ke Dapur (F8)
                </button>
            </div>
        </aside>
    </div>
</x-layouts.app>
