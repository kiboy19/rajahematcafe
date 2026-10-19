<x-layouts.app :title="'Pesanan — Rajahemat Cafe'">
    <div class="max-w-5xl">
        <div class="flex items-end justify-between gap-4">
            <div>
                <h2 class="font-display text-2xl font-bold">Pesanan</h2>
                <p class="text-sm text-ink-2 mt-1">Riwayat pesanan terbaru. Gunakan POS untuk membuat pesanan baru.</p>
            </div>
            <a href="{{ route('pos.index') }}" class="h-10 px-4 rounded-input bg-primary hover:bg-primary-hover text-white text-xs font-bold flex items-center">
                + Pesanan Baru
            </a>
        </div>

        @if ($orders->isEmpty())
            <div class="mt-6 bg-surface border border-line rounded-card p-10 text-center">
                <p class="font-semibold text-sm">Belum ada pesanan.</p>
                <p class="text-xs text-ink-2 mt-1">Pesanan pertama akan muncul di sini setelah dikirim dari POS.</p>
            </div>
        @else
            <div class="mt-5 bg-surface border border-line rounded-card shadow-1 overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-surface-2 text-[11px] uppercase tracking-wider text-ink-2">
                        <tr>
                            <th class="text-left font-bold px-4 py-2.5">Nomor</th>
                            <th class="text-left font-bold px-4 py-2.5">Waktu</th>
                            <th class="text-left font-bold px-4 py-2.5">Kanal</th>
                            <th class="text-left font-bold px-4 py-2.5">Status</th>
                            <th class="text-right font-bold px-4 py-2.5">Item</th>
                            <th class="text-right font-bold px-4 py-2.5">Total</th>
                            <th class="px-4 py-2.5"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($orders as $order)
                            <tr class="hover:bg-surface-2/50">
                                <td class="px-4 py-3 font-mono text-xs">{{ $order->number }}</td>
                                <td class="px-4 py-3 text-ink-2 text-xs">{{ $order->placed_at?->format('d/m H:i') ?? '—' }}</td>
                                <td class="px-4 py-3 text-xs">{{ $order->channel->label() }}{{ $order->table_label ? ' · Meja '.$order->table_label : '' }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-block rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider
                                        {{ match ($order->status) {
                                            \App\Enums\OrderStatus::Baru => 'bg-info-bg text-info',
                                            \App\Enums\OrderStatus::Diproses => 'bg-warn-bg text-warn',
                                            \App\Enums\OrderStatus::Siap => 'bg-ok-bg text-ok',
                                            \App\Enums\OrderStatus::Dibatalkan => 'bg-bad-bg text-bad',
                                            default => 'bg-surface-2 text-ink-2',
                                        } }}">
                                        {{ $order->status->label() }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right tabular">{{ $order->items_count }}</td>
                                <td class="px-4 py-3 text-right tabular font-bold">{{ \Illuminate\Support\Number::currency($order->total, 'IDR', 'id', precision: 0) }}</td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('orders.show', $order) }}" class="text-xs font-bold text-primary hover:underline">Detail</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $orders->links() }}</div>
        @endif
    </div>
</x-layouts.app>
