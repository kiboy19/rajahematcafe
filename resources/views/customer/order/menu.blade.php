<x-layouts.customer :title="'Pesan Menu — ' . $tableLabel . ' · Rajahemat Cafe'">
    <div class="flex flex-col gap-4">
        {{-- Banner Meja & Saldo Pelanggan --}}
        <div class="rounded-card bg-accent text-ink-on-dark p-4 shadow-1 flex items-center justify-between">
            <div>
                <span class="text-[10px] uppercase font-bold tracking-wider opacity-75 block">Pemesanan di Meja</span>
                <span class="font-display text-xl font-bold block">{{ $tableLabel }}</span>
            </div>
            <div class="text-right">
                <span class="text-[10px] uppercase font-bold tracking-wider opacity-75 block">Saldo Anda</span>
                <span class="text-base font-bold tabular block">{{ \Illuminate\Support\Number::currency((int) $customer->balance, 'IDR', 'id', precision: 0) }}</span>
            </div>
        </div>

        {{-- Daftar Menu per Kategori --}}
        <div class="flex flex-col gap-5">
            @foreach ($categories as $category)
                @if ($category->menuItems->isNotEmpty())
                    <section class="bg-surface border border-line rounded-card shadow-1 p-4">
                        <h2 class="font-display text-sm font-bold text-ink uppercase tracking-wider mb-3">
                            {{ $category->name }}
                        </h2>

                        <div class="flex flex-col divide-y divide-line">
                            @foreach ($category->menuItems as $item)
                                <div class="py-3 first:pt-0 last:pb-0 flex items-center justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="font-semibold text-sm text-ink truncate">{{ $item->name }}</p>
                                        <p class="text-xs text-ink-2 tabular mt-0.5">
                                            {{ \Illuminate\Support\Number::currency($item->price, 'IDR', 'id', precision: 0) }}
                                        </p>
                                    </div>

                                    <form method="POST" action="{{ route('customer.order.cart.add') }}" class="shrink-0">
                                        @csrf
                                        <input type="hidden" name="menu_item_id" value="{{ $item->id }}">
                                        <input type="hidden" name="quantity" value="1">
                                        <button type="submit"
                                                class="h-9 px-3 rounded-input border border-line bg-surface hover:bg-surface-2 text-xs font-bold text-ink"
                                                aria-label="Tambah {{ $item->name }}">
                                            + Tambah
                                        </button>
                                    </form>
                                </div>
                            @endforeach
                        </div>
                    </section>
                @endif
            @endforeach
        </div>

        {{-- Keranjang & Checkout --}}
        <section class="bg-surface border-2 border-primary/20 rounded-card shadow-1 p-4">
            <div class="flex items-center justify-between border-b border-line pb-3 mb-3">
                <h3 class="font-display text-sm font-bold text-ink">Keranjang Pesanan ({{ count($lines) }})</h3>
                @if (! $cart->isEmpty())
                    <form method="POST" action="{{ route('customer.order.cart.clear') }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-xs text-bad hover:underline font-semibold">
                            Kosongkan
                        </button>
                    </form>
                @endif
            </div>

            @if ($cart->isEmpty())
                <p class="text-xs text-ink-2 py-4 text-center">
                    Keranjang masih kosong. Pilih menu di atas untuk mulai memesan.
                </p>
            @else
                {{-- List Item Keranjang --}}
                <div class="flex flex-col divide-y divide-line mb-4">
                    @foreach ($lines as $line)
                        <div class="py-2.5 flex items-center justify-between gap-3 text-xs">
                            <div class="min-w-0">
                                <p class="font-semibold text-ink truncate">{{ $line['menu_item']->name }}</p>
                                <p class="text-ink-2 tabular">
                                    {{ \Illuminate\Support\Number::currency($line['menu_item']->price, 'IDR', 'id', precision: 0) }} × {{ $line['quantity'] }}
                                </p>
                            </div>

                            <div class="flex items-center gap-2 shrink-0">
                                <span class="font-bold tabular text-ink">
                                    {{ \Illuminate\Support\Number::currency($line['line_total'], 'IDR', 'id', precision: 0) }}
                                </span>

                                <form method="POST" action="{{ route('customer.order.cart.update') }}" class="inline">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="menu_item_id" value="{{ $line['menu_item']->id }}">
                                    <input type="hidden" name="quantity" value="{{ $line['quantity'] - 1 }}">
                                    <button type="submit" aria-label="Kurangi {{ $line['menu_item']->name }}" class="w-9 h-9 rounded bg-surface-2 hover:bg-line border border-line text-ink font-bold flex items-center justify-center text-sm">
                                        −
                                    </button>
                                </form>

                                <span class="tabular font-semibold w-4 text-center">{{ $line['quantity'] }}</span>

                                <form method="POST" action="{{ route('customer.order.cart.update') }}" class="inline">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="menu_item_id" value="{{ $line['menu_item']->id }}">
                                    <input type="hidden" name="quantity" value="{{ $line['quantity'] + 1 }}">
                                    <button type="submit" aria-label="Tambah {{ $line['menu_item']->name }}" class="w-9 h-9 rounded bg-surface-2 hover:bg-line border border-line text-ink font-bold flex items-center justify-center text-sm">
                                        +
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Ringkasan Tagihan --}}
                <div class="bg-surface-2 rounded-input p-3 flex flex-col gap-1.5 text-xs text-ink-2 mb-4">
                    <div class="flex justify-between">
                        <span>Subtotal</span>
                        <span class="tabular font-semibold text-ink">{{ \Illuminate\Support\Number::currency($bill['subtotal'], 'IDR', 'id', precision: 0) }}</span>
                    </div>
                    <p class="text-[11px] text-ink-2">Voucher dipotong saat pesanan dikirim; gunakan satu voucher saja.</p>
                    @if ($bill['tax'] > 0)
                        <div class="flex justify-between">
                            <span>PB1 ({{ config('pos.tax_percent') }}%)</span>
                            <span class="tabular">{{ \Illuminate\Support\Number::currency($bill['tax'], 'IDR', 'id', precision: 0) }}</span>
                        </div>
                    @endif
                    @if ($bill['service'] > 0)
                        <div class="flex justify-between">
                            <span>Layanan ({{ config('pos.service_percent') }}%)</span>
                            <span class="tabular">{{ \Illuminate\Support\Number::currency($bill['service'], 'IDR', 'id', precision: 0) }}</span>
                        </div>
                    @endif
                    <div class="border-t border-line pt-2 mt-1 flex justify-between text-sm font-bold text-ink">
                        <span>TOTAL</span>
                        <span class="tabular">{{ \Illuminate\Support\Number::currency($bill['grand_total'], 'IDR', 'id', precision: 0) }}</span>
                    </div>
                    @error('voucher_code')
                        <p class="text-[11px] text-bad">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Form Checkout --}}
                <form method="POST" action="{{ route('customer.order.checkout') }}" class="flex flex-col gap-3">
                    @csrf

                    <label class="flex flex-col gap-1 text-xs font-semibold">
                        <span>Kode Voucher (opsional)</span>
                        <input type="text" name="voucher_code" maxlength="30" value="{{ old('voucher_code') }}"
                               class="h-10 px-3 rounded-input border border-line bg-surface text-xs uppercase"
                               placeholder="Contoh: HEMAT10K">
                    </label>

                    <label class="flex flex-col gap-1 text-xs font-semibold">
                        <span>Catatan Pesanan (opsional)</span>
                        <input type="text" name="note" maxlength="255"
                               class="h-10 px-3 rounded-input border border-line bg-surface text-xs"
                               placeholder="Contoh: Es sedikit, jangan terlalu manis">
                    </label>

                    @php
                        $canPayWithWallet = (int) $customer->balance >= $bill['grand_total'];
                    @endphp

                    <div class="p-3 rounded-input border {{ $canPayWithWallet ? 'border-ok/30 bg-ok-bg/30' : 'border-line bg-surface-2' }}">
                        <label class="flex items-start gap-2.5 cursor-pointer">
                            <input type="checkbox" name="pay_with_wallet" value="1"
                                   @disabled(! $canPayWithWallet)
                                   class="mt-0.5 rounded border-line text-primary focus:ring-primary">
                            <div class="text-xs">
                                <span class="font-bold block {{ $canPayWithWallet ? 'text-ink' : 'text-ink-2' }}">
                                    Bayar langsung pakai Saldo Rajahemat
                                </span>
                                @if ($canPayWithWallet)
                                    <span class="text-[11px] text-ink-2 block mt-0.5">
                                        Saldo Anda cukup (sisa setelah bayar: {{ \Illuminate\Support\Number::currency((int) $customer->balance - $bill['grand_total'], 'IDR', 'id', precision: 0) }}).
                                    </span>
                                @else
                                    <span class="text-[11px] text-bad block mt-0.5">
                                        Saldo Anda kurang (butuh {{ \Illuminate\Support\Number::currency($bill['grand_total'], 'IDR', 'id', precision: 0) }}, saldo {{ \Illuminate\Support\Number::currency((int) $customer->balance, 'IDR', 'id', precision: 0) }}). Bayar tunai/QRIS ke kasir saat selesai.
                                    </span>
                                @endif
                            </div>
                        </label>
                    </div>

                    <button type="submit"
                            class="h-11 rounded-input bg-primary hover:bg-primary-hover text-white font-bold text-sm shadow-1 transition flex items-center justify-center gap-2">
                        Kirim Pesanan ke Dapur
                    </button>
                    <p class="text-[11px] text-ink-2 text-center">
                        Pesanan langsung dikirim ke antrean dapur barista/koki.
                    </p>
                </form>
            @endif
        </section>
    </div>
</x-layouts.customer>
