<x-layouts.app :title="'Pesanan '.$order->number.' — Rajahemat Cafe'">
    <div class="max-w-3xl">
        <a href="{{ route('orders.index') }}" class="text-xs font-semibold text-primary hover:underline">← Kembali ke pesanan</a>

        <div class="mt-4 bg-surface border border-line rounded-card shadow-1 p-5">
            <div class="flex items-start justify-between gap-4 border-b border-line pb-4">
                <div>
                    <p class="text-[10px] uppercase tracking-widest text-ink-2">Pesanan dibuat</p>
                    <h2 class="font-display text-xl font-bold">{{ $order->number }}</h2>
                    <p class="text-xs text-ink-2 mt-1">{{ $order->placed_at?->format('d M Y · H:i') }} · {{ $order->channel->label() }}{{ $order->table_label ? ' · Meja '.$order->table_label : '' }}</p>
                </div>
                <span class="rounded-full px-3 py-1 text-[10px] font-bold uppercase tracking-wider
                    {{ match ($order->status) {
                        \App\Enums\OrderStatus::Baru => 'bg-info-bg text-info',
                        \App\Enums\OrderStatus::Diproses => 'bg-warn-bg text-warn',
                        \App\Enums\OrderStatus::Siap => 'bg-ok-bg text-ok',
                        \App\Enums\OrderStatus::Dibatalkan => 'bg-bad-bg text-bad',
                        default => 'bg-surface-2 text-ink-2',
                    } }}">
                    {{ $order->status->label() }}
                </span>
            </div>

            <div class="py-4 flex flex-col gap-3">
                @foreach ($order->items as $item)
                    <div class="flex justify-between gap-4 text-sm">
                        <div>
                            <span class="font-semibold">{{ $item->quantity }} × {{ $item->name }}</span>
                            @if ($item->note)
                                <span class="block text-xs text-ink-2">Catatan: {{ $item->note }}</span>
                            @endif
                        </div>
                        <span class="tabular font-semibold">{{ \Illuminate\Support\Number::currency($item->line_total, 'IDR', 'id', precision: 0) }}</span>
                    </div>
                @endforeach
                @if ($order->note)
                    <p class="text-xs text-ink-2 border-t border-line pt-3">Catatan: {{ $order->note }}</p>
                @endif
            </div>

            <div class="border-t border-line pt-3 flex flex-col gap-1 text-xs text-ink-2">
                <div class="flex justify-between"><span>Subtotal</span><span class="tabular">{{ \Illuminate\Support\Number::currency($order->subtotal, 'IDR', 'id', precision: 0) }}</span></div>
                @if ($order->discount > 0)
                    <div class="flex justify-between text-ok"><span>Diskon{{ $order->voucher ? ' · '.$order->voucher->code : '' }}</span><span class="tabular">−{{ \Illuminate\Support\Number::currency($order->discount, 'IDR', 'id', precision: 0) }}</span></div>
                @endif
                <div class="flex justify-between"><span>PB1 ({{ $order->tax_percent }}%)</span><span class="tabular">{{ \Illuminate\Support\Number::currency($order->tax, 'IDR', 'id', precision: 0) }}</span></div>
                <div class="flex justify-between"><span>Layanan ({{ $order->service_percent }}%)</span><span class="tabular">{{ \Illuminate\Support\Number::currency($order->service, 'IDR', 'id', precision: 0) }}</span></div>
                <div class="flex justify-between font-bold text-ink text-base border-t border-line mt-2 pt-2"><span>Total</span><span class="tabular">{{ \Illuminate\Support\Number::currency($order->total, 'IDR', 'id', precision: 0) }}</span></div>
            </div>

            <div class="mt-4 border-t border-line pt-4">
                <div class="flex items-center justify-between gap-2 mb-2">
                    <h3 class="text-xs font-bold uppercase tracking-wider">Pembayaran</h3>
                    <a href="{{ route('orders.receipt', $order) }}" target="_blank" rel="noopener" class="text-xs font-semibold text-primary hover:underline">Lihat / cetak struk</a>
                </div>
                @forelse ($order->payments as $payment)
                    <div class="flex justify-between text-xs py-1">
                        <div>
                            <span class="font-semibold">{{ $payment->method->label() }} · {{ $payment->status->label() }}</span>
                            @if ($payment->reference)
                                <span class="block text-[11px] text-ink-2">Ref: {{ $payment->reference }}</span>
                            @endif
                            @if ($payment->change > 0)
                                <span class="block text-[11px] text-ink-2">Tunai diterima {{ \Illuminate\Support\Number::currency($payment->tendered ?? 0, 'IDR', 'id', precision: 0) }} · kembali {{ \Illuminate\Support\Number::currency($payment->change, 'IDR', 'id', precision: 0) }}</span>
                            @endif
                        </div>
                        <span class="tabular font-semibold {{ $payment->status === \App\Enums\PaymentStatus::Void ? 'line-through text-ink-2' : '' }}">{{ \Illuminate\Support\Number::currency($payment->amount, 'IDR', 'id', precision: 0) }}</span>
                    </div>
                    @if ($payment->status === \App\Enums\PaymentStatus::Paid && in_array(auth()->user()->role, [\App\Enums\UserRole::Owner, \App\Enums\UserRole::Manager], true))
                        <form method="POST" action="{{ route('orders.pay.void', [$order, $payment]) }}" class="flex items-center gap-2 mb-2">
                            @csrf
                            @method('DELETE')
                            <input type="text" name="reason" required minlength="5" maxlength="255" placeholder="Alasan pembatalan (min. 5 karakter)"
                                   class="h-8 flex-1 px-2 rounded-input border border-line-strong bg-surface text-[11px]">
                            <button type="submit" class="h-8 px-2 rounded-input border border-bad/40 text-bad text-[11px] font-bold hover:bg-bad-bg">
                                Batalkan
                            </button>
                        </form>
                        @error('reason') <p class="text-[11px] text-bad mb-2">{{ $message }}</p> @enderror
                    @endif
                @empty
                    <p class="text-xs text-ink-2">Belum ada pembayaran.</p>
                @endforelse
                <div class="flex justify-between text-xs mt-2 pt-2 border-t border-line font-bold">
                    <span>Terbayar</span><span class="tabular">{{ \Illuminate\Support\Number::currency($order->paidTotal(), 'IDR', 'id', precision: 0) }}</span>
                </div>
            </div>

            @if ($order->status !== \App\Enums\OrderStatus::Dibatalkan)
                <div class="mt-5 border-t border-line pt-5">
                    @if ($order->remainingBalance() > 0)
                        <h3 class="text-xs font-bold uppercase tracking-wider mb-3">Terima Pembayaran</h3>

                        @if ($openSession)
                            <form method="POST" action="{{ route('orders.pay', $order) }}" class="flex flex-col gap-3">
                                @csrf
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <label class="flex flex-col gap-1 text-xs font-semibold">
                                        <span>Metode</span>
                                        <select name="method" id="pay-method" class="h-11 px-3 rounded-input border border-line-strong bg-surface text-sm font-normal">
                                            <option value="cash">Tunai</option>
                                            <option value="qris">QRIS (manual)</option>
                                            <option value="edc">Kartu / EDC (manual)</option>
                                            <option value="wallet">Saldo Pelanggan</option>
                                        </select>
                                    </label>

                                    <label class="flex flex-col gap-1 text-xs font-semibold">
                                        <span>Nominal dibayar (Rp)</span>
                                        <input type="number" name="amount" min="1" max="{{ $order->remainingBalance() }}" required inputmode="numeric"
                                               value="{{ $order->remainingBalance() }}"
                                               class="h-11 px-3 rounded-input border border-line-strong bg-surface text-sm tabular">
                                        @error('amount') <span class="text-bad">{{ $message }}</span> @enderror
                                    </label>
                                </div>

                                <div class="grid gap-3 sm:grid-cols-2">
                                    <label class="flex flex-col gap-1 text-xs font-semibold">
                                        <span>Uang tunai diterima (Rp)</span>
                                        <input type="number" name="tendered" min="0" inputmode="numeric"
                                               class="h-11 px-3 rounded-input border border-line-strong bg-surface text-sm tabular"
                                               placeholder="Untuk tunai: nominal uang dari pelanggan">
                                        @error('tendered') <span class="text-bad">{{ $message }}</span> @enderror
                                    </label>

                                    <label class="flex flex-col gap-1 text-xs font-semibold">
                                        <span>Referensi (QRIS/EDC)</span>
                                        <input type="text" name="reference" maxlength="100"
                                               class="h-11 px-3 rounded-input border border-line-strong bg-surface text-sm"
                                               placeholder="Nomor referensi dari mesin/merchant">
                                        @error('reference') <span class="text-bad">{{ $message }}</span> @enderror
                                    </label>

                                    <label class="flex flex-col gap-1 text-xs font-semibold sm:col-span-2">
                                        <span>Nomor HP Pelanggan (khusus Saldo)</span>
                                        <input type="tel" name="customer_phone" maxlength="30"
                                               value="{{ old('customer_phone', $order->customer?->phone) }}"
                                               class="h-11 px-3 rounded-input border border-line-strong bg-surface text-sm"
                                               placeholder="081234567890 (diperlukan jika bayar pakai saldo)">
                                        @error('customer_phone') <span class="text-bad">{{ $message }}</span> @enderror
                                    </label>
                                </div>

                                <div class="flex items-center gap-3">
                                    <button type="submit" class="h-12 px-6 rounded-input bg-primary hover:bg-primary-hover text-white font-bold text-sm">
                                        Simpan Pembayaran
                                    </button>
                                    <span class="text-xs text-ink-2">Sisa tagihan: <span class="tabular font-bold text-ink">{{ \Illuminate\Support\Number::currency($order->remainingBalance(), 'IDR', 'id', precision: 0) }}</span></span>
                                </div>
                            </form>
                        @else
                            <div class="rounded-input bg-warn-bg text-warn p-3 text-xs">
                                Sesi kasir Anda belum dibuka, jadi pembayaran tidak dapat diterima.
                                <a href="{{ route('cashier.session') }}" class="font-bold underline">Buka sesi kasir</a>.
                            </div>
                        @endif
                    @else
                        <div class="rounded-input bg-ok-bg text-ok p-3 text-xs font-semibold">
                            Pesanan sudah lunas.
                        </div>
                    @endif

                    @error('session') <p class="mt-2 text-xs text-bad">{{ $message }}</p> @enderror
                </div>
            @endif
        </div>
    </div>
</x-layouts.app>
