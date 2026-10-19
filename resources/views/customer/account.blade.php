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
            <strong class="font-semibold text-ink block mb-1">Fitur Berikutnya (R1.1)</strong>
            Saldo dompet cafe, pengisian tunai di kasir, dan pemesanan langsung dari meja via QR akan aktif pada rilis berikutnya.
        </section>
    </div>
</x-layouts.customer>
