<x-layouts.app :title="'Sesi Kasir — Rajahemat Cafe'">
    <div class="max-w-4xl">
        <h2 class="font-display text-2xl font-bold">Sesi Kasir</h2>
        <p class="text-sm text-ink-2 mt-1">Setiap kasir membuka dan menutup sesi masing-masing. Pembayaran hanya dapat diterima saat sesi terbuka.</p>

        @if ($session)
            <div class="mt-5 grid gap-4 md:grid-cols-3">
                <div class="bg-surface border border-line rounded-card shadow-1 p-4">
                    <p class="text-[10px] uppercase tracking-wider text-ink-2 font-bold">Modal awal</p>
                    <p class="mt-1 text-xl font-bold tabular">{{ \Illuminate\Support\Number::currency($session->opening_float, 'IDR', 'id', precision: 0) }}</p>
                </div>
                <div class="bg-surface border border-line rounded-card shadow-1 p-4">
                    <p class="text-[10px] uppercase tracking-wider text-ink-2 font-bold">Penjualan tunai tercatat</p>
                    <p class="mt-1 text-xl font-bold tabular">{{ \Illuminate\Support\Number::currency($cashSales, 'IDR', 'id', precision: 0) }}</p>
                    <p class="text-[11px] text-ink-2">{{ $paymentCount }} pembayaran lunas · non-tunai tidak dihitung</p>
                </div>
                <div class="bg-surface border border-line rounded-card shadow-1 p-4">
                    <p class="text-[10px] uppercase tracking-wider text-ink-2 font-bold">Top-up tunai masuk</p>
                    <p class="mt-1 text-xl font-bold tabular">{{ \Illuminate\Support\Number::currency($cashTopups, 'IDR', 'id', precision: 0) }}</p>
                    <p class="text-[11px] text-ink-2">Uang tunai pelanggan masuk laci</p>
                </div>
                <div class="bg-accent text-ink-on-dark rounded-card shadow-1 p-4 md:col-span-3">
                    <p class="text-[10px] uppercase tracking-wider opacity-75 font-bold">Estimasi total uang laci</p>
                    <p class="mt-1 text-2xl font-bold tabular">{{ \Illuminate\Support\Number::currency($session->expectedCashTotal(), 'IDR', 'id', precision: 0) }}</p>
                    <p class="text-[11px] opacity-80 mt-1">Modal awal ({{ \Illuminate\Support\Number::currency($session->opening_float, 'IDR', 'id', precision: 0) }}) + Penjualan tunai ({{ \Illuminate\Support\Number::currency($cashSales, 'IDR', 'id', precision: 0) }}) + Top-up tunai ({{ \Illuminate\Support\Number::currency($cashTopups, 'IDR', 'id', precision: 0) }})</p>
                </div>
            </div>

            <div class="mt-4 bg-surface border border-line rounded-card p-5">
                <p class="text-xs text-ink-2">Sesi dibuka {{ $session->opened_at->format('d M Y · H:i') }}.</p>
                <h3 class="font-bold text-sm mt-1">Tutup kas dan rekonsiliasi</h3>
                <p class="text-xs text-ink-2 mt-1">Hitung uang fisik di laci, lalu masukkan jumlahnya. Selisih akan dicatat untuk ditinjau manager.</p>

                <form method="POST" action="{{ route('cashier.session.close') }}" class="mt-4 grid gap-3 md:grid-cols-2">
                    @csrf
                    <label class="flex flex-col gap-1 text-xs font-semibold">
                        <span>Uang fisik dihitung (Rp)</span>
                        <input type="number" name="counted_cash" min="0" max="1000000000" required inputmode="numeric"
                               class="h-11 px-3 rounded-input border border-line-strong bg-surface tabular" placeholder="Contoh: 150000">
                        @error('counted_cash') <span class="text-bad">{{ $message }}</span> @enderror
                    </label>
                    <label class="flex flex-col gap-1 text-xs font-semibold">
                        <span>Catatan selisih (opsional)</span>
                        <input type="text" name="closing_note" maxlength="500"
                               class="h-11 px-3 rounded-input border border-line-strong bg-surface" placeholder="Alasan selisih, bila ada">
                    </label>
                    <div class="md:col-span-2">
                        <button type="submit" class="h-11 px-5 rounded-input bg-primary hover:bg-primary-hover text-white font-bold text-sm">
                            Tutup Sesi Kasir
                        </button>
                    </div>
                </form>
            </div>
        @else
            <div class="mt-5 bg-surface border border-line rounded-card shadow-1 p-5 max-w-xl">
                <div class="rounded-input bg-warn-bg text-warn p-3 text-xs mb-4">
                    Belum ada sesi terbuka. Buka sesi dan hitung modal awal sebelum menerima pembayaran.
                </div>

                <form method="POST" action="{{ route('cashier.session.open') }}" class="flex flex-col gap-3">
                    @csrf
                    <label class="flex flex-col gap-1 text-xs font-semibold">
                        <span>Modal awal di laci (Rp)</span>
                        <input type="number" name="opening_float" min="0" max="100000000" required inputmode="numeric"
                               class="h-11 px-3 rounded-input border border-line-strong bg-surface tabular" placeholder="Contoh: 200000">
                        @error('opening_float') <span class="text-bad">{{ $message }}</span> @enderror
                    </label>
                    <button type="submit" class="h-11 rounded-input bg-primary hover:bg-primary-hover text-white font-bold text-sm">
                        Buka Sesi Kasir
                    </button>
                </form>
            </div>
        @endif
    </div>
</x-layouts.app>
