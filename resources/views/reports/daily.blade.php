<x-layouts.app :title="'Laporan Harian — Rajahemat Cafe'">
    <div class="max-w-5xl">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h2 class="font-display text-2xl font-bold">Laporan Harian</h2>
                <p class="text-sm text-ink-2 mt-1">Ringkasan pesanan, pembayaran, dan rekonsiliasi kas per hari.</p>
            </div>

            <form method="GET" class="flex items-end gap-2">
                <label class="flex flex-col gap-1 text-xs font-semibold">
                    <span>Tanggal</span>
                    <input type="date" name="tanggal" value="{{ $date->format('Y-m-d') }}"
                           class="h-10 px-3 rounded-input border border-line-strong bg-surface text-sm font-normal">
                </label>
                <button type="submit" class="h-10 px-4 rounded-input bg-primary hover:bg-primary-hover text-white text-xs font-bold">Tampilkan</button>
            </form>
        </div>

        <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="bg-surface border border-line rounded-card shadow-1 p-4">
                <p class="text-[10px] uppercase tracking-wider text-ink-2 font-bold">Pesanan (tanpa batal)</p>
                <p class="mt-1 text-xl font-bold tabular">{{ $orderCount }}</p>
            </div>
            <div class="bg-surface border border-line rounded-card shadow-1 p-4">
                <p class="text-[10px] uppercase tracking-wider text-ink-2 font-bold">Penjualan bruto</p>
                <p class="mt-1 text-xl font-bold tabular">{{ \Illuminate\Support\Number::currency($grossSales, 'IDR', 'id', precision: 0) }}</p>
                <p class="text-[11px] text-ink-2">termasuk PB1 &amp; layanan</p>
            </div>
            <div class="bg-surface border border-line rounded-card shadow-1 p-4">
                <p class="text-[10px] uppercase tracking-wider text-ink-2 font-bold">PB1 terkumpul</p>
                <p class="mt-1 text-xl font-bold tabular">{{ \Illuminate\Support\Number::currency($taxTotal, 'IDR', 'id', precision: 0) }}</p>
                <p class="text-[11px] text-ink-2">layanan {{ \Illuminate\Support\Number::currency($serviceTotal, 'IDR', 'id', precision: 0) }}</p>
            </div>
            <div class="bg-surface border border-line rounded-card shadow-1 p-4">
                <p class="text-[10px] uppercase tracking-wider text-ink-2 font-bold">Diskon diberikan</p>
                <p class="mt-1 text-xl font-bold tabular">{{ \Illuminate\Support\Number::currency($discountTotal, 'IDR', 'id', precision: 0) }}</p>
            </div>
        </div>

        <div class="mt-5 grid gap-4 lg:grid-cols-2">
            <div class="bg-surface border border-line rounded-card shadow-1 overflow-hidden">
                <h3 class="px-4 py-3 border-b border-line text-xs font-bold uppercase tracking-wider">Pembayaran per metode</h3>
                @if ($paymentsByMethod->isEmpty())
                    <p class="p-4 text-xs text-ink-2">Belum ada pembayaran pada tanggal ini.</p>
                @else
                    <table class="w-full text-sm">
                        <tbody class="divide-y divide-line">
                            @foreach ($paymentsByMethod as $row)
                                <tr>
                                    <td class="px-4 py-3">{{ $row->method->label() }}</td>
                                    <td class="px-4 py-3 text-right text-ink-2 text-xs">{{ $row->jumlah }} transaksi</td>
                                    <td class="px-4 py-3 text-right tabular font-bold">{{ \Illuminate\Support\Number::currency((int) $row->total, 'IDR', 'id', precision: 0) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <p class="px-4 py-3 border-t border-line text-[11px] text-ink-2">
                        Hanya tunai yang menambah uang fisik laci ({{ \Illuminate\Support\Number::currency($cashPaid, 'IDR', 'id', precision: 0) }} pada tanggal ini).
                    </p>
                @endif
            </div>

            <div class="bg-surface border border-line rounded-card shadow-1 overflow-hidden">
                <h3 class="px-4 py-3 border-b border-line text-xs font-bold uppercase tracking-wider">Rekonsiliasi sesi kasir</h3>
                @if ($sessions->isEmpty())
                    <p class="p-4 text-xs text-ink-2">Tidak ada sesi kasir pada tanggal ini.</p>
                @else
                    <table class="w-full text-sm">
                        <thead class="bg-surface-2 text-[11px] uppercase tracking-wider text-ink-2">
                            <tr>
                                <th class="text-left font-bold px-4 py-2">Kasir</th>
                                <th class="text-right font-bold px-4 py-2">Seharusnya</th>
                                <th class="text-right font-bold px-4 py-2">Dihitung</th>
                                <th class="text-right font-bold px-4 py-2">Selisih</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($sessions as $s)
                                <tr>
                                    <td class="px-4 py-3 text-xs">
                                        {{ $s->cashier?->name ?? '—' }}
                                        <span class="block text-[10px] uppercase tracking-wider {{ $s->status === 'open' ? 'text-warn' : 'text-ink-2' }}">{{ $s->status === 'open' ? 'Masih terbuka' : 'Tutup' }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-right tabular">{{ \Illuminate\Support\Number::currency($s->expected_cash, 'IDR', 'id', precision: 0) }}</td>
                                    <td class="px-4 py-3 text-right tabular">{{ $s->counted_cash === null ? '—' : \Illuminate\Support\Number::currency($s->counted_cash, 'IDR', 'id', precision: 0) }}</td>
                                    <td class="px-4 py-3 text-right tabular font-bold {{ ($s->variance ?? 0) === 0 ? 'text-ok' : 'text-bad' }}">
                                        {{ $s->variance === null ? '—' : \Illuminate\Support\Number::currency($s->variance, 'IDR', 'id', precision: 0) }}
                                    </td>
                                </tr>
                                @if ($s->status === 'closed' && (int) $s->variance !== 0)
                                    <tr>
                                        <td colspan="4" class="px-4 pb-3 text-[11px]">
                                            @if ($s->variance_verified_at)
                                                <span class="text-ok font-semibold">Diverifikasi {{ $s->varianceVerifier?->name }} · {{ $s->variance_verified_at->format('d M H:i') }}</span>
                                            @elseif ($s->cashier_id === auth()->id())
                                                <span class="text-warn">Menunggu verifikasi manager (kasir tidak dapat memverifikasi sesinya sendiri).</span>
                                            @else
                                                <form method="POST" action="{{ route('cashier.session.verify', $s) }}" class="flex items-center gap-2 mt-1">
                                                    @csrf
                                                    <input type="text" name="approval_note" required minlength="5" maxlength="255" placeholder="Catatan verifikasi selisih"
                                                           class="h-8 flex-1 px-2 rounded-input border border-line-strong bg-surface text-[11px]">
                                                    <button type="submit" class="h-8 px-2 rounded-input bg-primary text-white text-[11px] font-bold">Verifikasi</button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>

        <p class="mt-4 text-[11px] text-ink-2">
            Catatan: laporan ini belum memuat saldo/wallet dan gateway; keduanya menyusul pada rilis R1.1 dan R1.2.
        </p>
    </div>
</x-layouts.app>
