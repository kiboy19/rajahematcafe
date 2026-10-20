<x-layouts.kds :title="'Dapur — Rajahemat Cafe'">
    @if ($orders->isEmpty())
        <div class="rounded-card border border-kds-line bg-kds-card p-12 text-center">
            <p class="text-lg font-bold">Tidak ada pesanan aktif.</p>
            <p class="text-sm text-kds-muted mt-1">Layar ini menyegarkan sendiri setiap 20 detik.</p>
        </div>
    @else
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
            @foreach ($orders as $order)
                @php
                    $minutes = (int) $order->placed_at?->diffInMinutes(now());
                    $ageClass = $minutes >= 20 ? 'border-bad' : ($minutes >= 10 ? 'border-warn' : 'border-kds-line');
                @endphp

                <article class="rounded-card border {{ $ageClass }} bg-kds-card flex flex-col">
                    <header class="p-4 border-b border-kds-line flex items-start justify-between gap-3">
                        <div>
                            <p class="text-2xl font-bold leading-none">
                                {{ $order->table_label ? 'Meja '.$order->table_label : 'Bungkus' }}
                            </p>
                            <p class="text-xs text-kds-muted mt-1 font-mono">{{ $order->number }}</p>
                        </div>
                        <div class="text-right">
                            <span class="inline-block text-[10px] font-bold uppercase tracking-wider px-2 py-1 rounded-full
                                {{ match ($order->status) {
                                    \App\Enums\OrderStatus::Baru => 'bg-info-bg text-info',
                                    \App\Enums\OrderStatus::Diproses => 'bg-warn-bg text-warn',
                                    \App\Enums\OrderStatus::Siap => 'bg-ok-bg text-ok',
                                    default => 'bg-surface-2 text-ink-2',
                                } }}">
                                {{ $order->status->label() }}
                            </span>
                            <p class="text-xs mt-1 {{ $minutes >= 10 ? 'text-warn font-bold' : 'text-kds-muted' }}">
                                {{ $minutes }} menit
                            </p>
                        </div>
                    </header>

                    <ul class="p-4 flex flex-col gap-3 flex-1">
                        @foreach ($order->items as $item)
                            <li class="flex gap-3">
                                <span class="min-w-8 h-8 rounded-input bg-primary text-white font-bold text-sm flex items-center justify-center tabular">
                                    {{ $item->quantity }}
                                </span>
                                <div>
                                    <p class="text-lg font-semibold leading-tight">{{ $item->name }}</p>
                                    @if ($item->note)
                                        <p class="text-sm text-amber-300">Catatan: {{ $item->note }}</p>
                                    @endif
                                </div>
                            </li>
                        @endforeach

                        @if ($order->note)
                            <li class="text-sm text-amber-300 border-t border-kds-line pt-3">Catatan pesanan: {{ $order->note }}</li>
                        @endif
                    </ul>

                    <footer class="p-4 pt-0 flex gap-2">
                        @php
                            $next = match ($order->status) {
                                \App\Enums\OrderStatus::Baru => \App\Enums\OrderStatus::Diproses,
                                \App\Enums\OrderStatus::Diproses => \App\Enums\OrderStatus::Siap,
                                \App\Enums\OrderStatus::Siap => \App\Enums\OrderStatus::Selesai,
                                default => null,
                            };
                        @endphp

                        @if ($next)
                            <form method="POST" action="{{ route('kds.status', $order) }}" class="flex-1">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="status" value="{{ $next->value }}">
                                <button type="submit"
                                        class="w-full min-h-14 rounded-input font-bold text-base {{ $next === \App\Enums\OrderStatus::Selesai ? 'bg-ok text-white hover:bg-ok/90' : 'bg-primary text-white hover:bg-primary-hover' }}">
                                    {{ match ($next) {
                                        \App\Enums\OrderStatus::Diproses => 'Mulai Masak',
                                        \App\Enums\OrderStatus::Siap => 'Tandai Siap',
                                        \App\Enums\OrderStatus::Selesai => 'Selesai & Serahkan',
                                    } }}
                                </button>
                            </form>
                        @endif
                    </footer>
                </article>
            @endforeach
        </div>
    @endif
</x-layouts.kds>
