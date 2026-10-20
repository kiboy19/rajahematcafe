<x-layouts.app :title="'Top-Up Saldo — Rajahemat Cafe'">
    <div class="max-w-4xl">
        <h2 class="font-display text-2xl font-bold">Top-Up Saldo Pelanggan</h2>
        <p class="text-sm text-ink-2 mt-1">Uang tunai dari top-up masuk ke laci kas dan dihitung saat tutup sesi. Minta salinan nama pemilik saldo sebelum memasukkan uang.</p>

        @if (! $session)
            <div class="mt-5 rounded-input bg-warn-bg text-warn p-3 text-xs">
                Sesi kasir belum dibuka. Buka sesi kasir terlebih dahulu —
                <a href="{{ route('cashier.session') }}" class="font-bold underline">buka sesi kasir</a>.
            </div>
        @endif

        <div class="mt-5 grid gap-5 md:grid-cols-2">
            <div class="bg-surface border border-line rounded-card shadow-1 p-5">
                <h3 class="text-xs font-bold uppercase tracking-wider mb-3">Cari Pelanggan</h3>

                <form method="GET" action="{{ route('wallet.index') }}" class="flex gap-2">
                    <input type="tel" name="hp" maxlength="30" inputmode="numeric" required
                           value="{{ old('hp', $phone) }}"
                           class="h-11 flex-1 px-3 rounded-input border border-line-strong bg-surface text-sm tabular"
                           placeholder="Nomor HP pelanggan">
                    <button type="submit" class="h-11 px-4 rounded-input bg-primary hover:bg-primary-hover text-white font-bold text-sm">
                        Cari
                    </button>
                </form>

                @if ($phone !== '')
                    @if ($customer)
                        <div class="mt-4 rounded-input bg-surface-2 p-4">
                            <p class="font-bold text-sm">{{ $customer->name }}</p>
                            <p class="text-[11px] text-ink-2 tabular">{{ $customer->phone }}</p>
                            <p class="mt-2 text-xs text-ink-2">Saldo saat ini</p>
                            <p class="text-xl font-bold tabular text-ink">{{ \Illuminate\Support\Number::currency($customer->balance, 'IDR', 'id', precision: 0) }}</p>
                            <p class="mt-2 text-[11px] text-ink-2 tabular">Top-up hari ini: {{ \Illuminate\Support\Number::currency($todayTopups, 'IDR', 'id', precision: 0) }}</p>
                        </div>

                        <form method="POST" action="{{ route('wallet.topup') }}" class="mt-4 flex flex-col gap-3">
                            @csrf
                            <input type="hidden" name="phone" value="{{ $customer->phone }}">

                            <label class="flex flex-col gap-1 text-xs font-semibold">
                                <span>Nominal top-up (Rp)</span>
                                <input type="number" name="amount" min="{{ $minTopup }}" max="100000000" required inputmode="numeric"
                                       class="h-11 px-3 rounded-input border border-line-strong bg-surface text-sm tabular"
                                       placeholder="Minimal {{ \Illuminate\Support\Number::currency($minTopup, 'IDR', 'id', precision: 0) }}">
                                @error('amount') <span class="text-bad">{{ $message }}</span> @enderror
                            </label>

                            <label class="flex flex-col gap-1 text-xs font-semibold">
                                <span>Catatan (opsional)</span>
                                <input type="text" name="note" maxlength="255"
                                       class="h-11 px-3 rounded-input border border-line-strong bg-surface text-sm"
                                       placeholder="mis. dilayani di meja kasir">
                            </label>

                            <button type="submit" class="h-11 rounded-input bg-primary hover:bg-primary-hover text-white font-bold text-sm"
                                    @disabled(! $session)>
                                Terima Tunai & Tambah Saldo
                            </button>
                            <span class="text-[11px] text-ink-2">Batas top-up harian per pelanggan: {{ \Illuminate\Support\Number::currency($dailyLimit, 'IDR', 'id', precision: 0) }}.</span>
                        </form>
                    @else
                        <p class="mt-4 text-xs text-bad">Pelanggan dengan nomor {{ $phone }} tidak ditemukan.</p>
                    @endif
                @endif
            </div>

            <div class="bg-surface border border-line rounded-card shadow-1 p-5">
                <h3 class="text-xs font-bold uppercase tracking-wider mb-3">Top-Up Sesi Ini</h3>
                @if ($recentTopups->isEmpty())
                    <p class="text-xs text-ink-2">Belum ada top-up pada sesi kasir terbuka.</p>
                @else
                    <ul class="flex flex-col divide-y divide-line text-sm">
                        @foreach ($recentTopups as $topup)
                            <li class="py-2 flex items-center justify-between gap-3">
                                <div class="min-w-0">
                                    <span class="font-semibold block truncate">{{ $topup->customer->name }}</span>
                                    <span class="text-[11px] text-ink-2 tabular">{{ $topup->created_at->format('H:i') }} · {{ $topup->customer->phone }}</span>
                                </div>
                                <span class="tabular font-bold text-sm whitespace-nowrap">+ {{ \Illuminate\Support\Number::currency($topup->amount, 'IDR', 'id', precision: 0) }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
</x-layouts.app>
