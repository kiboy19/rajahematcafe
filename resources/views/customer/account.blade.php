@use('Illuminate\Support\Number')

<x-layouts.customer :title="'Akun Saya — Rajahemat Cafe'">
    <div class="flex flex-col gap-4">
        <section class="bg-surface border border-line rounded-card shadow-1 p-5">
            <div class="flex items-center gap-3">
                <span class="w-12 h-12 rounded-full bg-primary/10 text-primary flex items-center justify-center font-display font-bold text-lg">
                    {{ mb_substr($customer->name, 0, 1) }}
                </span>
                <div class="flex-1 min-w-0">
                    <h1 class="font-display font-bold text-lg truncate">{{ $customer->name }}</h1>
                    <p class="text-xs text-ink-2 truncate">{{ $customer->phone ?? '—' }} · {{ $customer->email }}</p>
                </div>
            </div>

            <div class="mt-4 grid grid-cols-2 gap-3 pt-4 border-t border-line">
                <div class="bg-surface-2 rounded-input p-3">
                    <span class="text-[11px] text-ink-2 block">Total Kunjungan</span>
                    <span class="tabular font-bold text-base text-ink">{{ $stats->orders_count ?? 0 }} pesanan</span>
                </div>
                <div class="bg-surface-2 rounded-input p-3">
                    <span class="text-[11px] text-ink-2 block">Total Belanja</span>
                    <span class="tabular font-bold text-base text-ink">{{ Number::currency((int) ($stats->total_spent ?? 0), 'IDR', 'id', precision: 0) }}</span>
                </div>
            </div>
        </section>

        <section class="bg-accent text-ink-on-dark rounded-card shadow-1 p-5">
            <p class="text-[11px] uppercase tracking-wider opacity-75 font-bold">Saldo Rajahemat</p>
            <p class="mt-1 text-3xl font-bold tabular">{{ Number::currency($customer->balance, 'IDR', 'id', precision: 0) }}</p>
            <p class="mt-2 text-xs opacity-80">Isi saldo tunai di kasir. Saldo dipakai untuk membayar pesanan di kasir.</p>
        </section>

        <section class="bg-surface border border-line rounded-card shadow-1 p-5">
            <div class="flex items-center justify-between mb-3">
                <h2 class="font-display font-bold text-base">Mutasi Saldo</h2>
                <span class="text-xs text-ink-2 tabular">{{ $transactions->count() }} terbaru</span>
            </div>

            @if ($transactions->isEmpty())
                <p class="text-sm text-ink-2 py-4 text-center">Belum ada mutasi saldo.</p>
            @else
                <ul class="flex flex-col divide-y divide-line">
                    @foreach ($transactions as $trx)
                        <li class="py-3 flex items-center justify-between gap-3 text-sm">
                            <div class="min-w-0">
                                <span class="font-semibold block">{{ $trx->type->label() }}</span>
                                <span class="text-[11px] text-ink-2 tabular">{{ $trx->created_at->translatedFormat('d M Y, H:i') }}</span>
                            </div>
                            <div class="text-right whitespace-nowrap">
                                <span class="tabular font-bold block {{ $trx->amount > 0 ? 'text-ok' : 'text-ink' }}">@if ($trx->amount > 0)+@else−@endif {{ Number::currency(abs($trx->amount), 'IDR', 'id', precision: 0) }}</span>
                                <span class="text-[11px] text-ink-2 tabular">Saldo {{ Number::currency($trx->balance_after, 'IDR', 'id', precision: 0) }}</span>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <section class="bg-surface border border-line rounded-card shadow-1 p-5">
            <div class="flex items-center justify-between mb-3">
                <h2 class="font-display font-bold text-base">Riwayat Pesanan</h2>
                <span class="text-xs text-ink-2 tabular">{{ $orders->total() }} total</span>
            </div>

            @if ($orders->isEmpty())
                <div class="text-center py-8 text-ink-2">
                    <p class="text-sm">Belum ada riwayat pesanan.</p>
                    <p class="text-xs mt-1">Sebutkan nomor HP Anda kepada kasir saat bertransaksi.</p>
                </div>
            @else
                <ul class="flex flex-col divide-y divide-line">
                    @foreach ($orders as $order)
                        <li class="py-3 flex items-center justify-between gap-3 text-sm">
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <span class="font-bold tabular text-ink">{{ $order->number }}</span>
                                    <span class="text-[11px] px-2 py-0.5 rounded-full font-semibold {{ $order->status->value === 'selesai' ? 'bg-ok-bg text-ok' : ($order->status->value === 'dibatalkan' ? 'bg-bad-bg text-bad' : 'bg-warn-bg text-warn') }}">
                                        {{ $order->status->label() }}
                                    </span>
                                </div>
                                <p class="text-xs text-ink-2 mt-0.5">
                                    {{ $order->placed_at?->translatedFormat('d M Y, H:i') ?? $order->created_at->translatedFormat('d M Y, H:i') }}
                                    @if ($order->table_label)
                                        · Meja {{ $order->table_label }}
                                    @endif
                                    · {{ $order->items_count }} item
                                </p>
                            </div>
                            <span class="tabular font-bold text-sm text-ink whitespace-nowrap">
                                {{ Number::currency((int) $order->total, 'IDR', 'id', precision: 0) }}
                            </span>
                        </li>
                    @endforeach
                </ul>

                <div class="mt-4">
                    {{ $orders->links() }}
                </div>
            @endif
        </section>

        {{-- Ponytail: Dompet saldo dan order QR meja aktif pada fase ekosistem (R1.1). --}}
        <section class="rounded-card border border-primary/20 bg-surface-2 p-4 text-xs text-ink-2">
            <strong class="font-semibold text-ink block mb-1">Menu via QR di Meja</strong>
            Pindai QR yang ditempel di meja Anda untuk memesan langsung dari HP — pesanan otomatis dikirim ke dapur.
        </section>
    </div>
</x-layouts.customer>
