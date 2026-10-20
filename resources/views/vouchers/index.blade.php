<x-layouts.app :title="'Voucher — Rajahemat Cafe'">
    <div class="max-w-5xl">
        <h2 class="font-display text-2xl font-bold">Voucher &amp; Kode Promo</h2>
        <p class="text-sm text-ink-2 mt-1">Voucher satu kali per transaksi (tidak bisa digabung dengan diskon manual). Kuota berlaku untuk checkout sukses saja.</p>

        <div class="mt-5 grid gap-5 lg:grid-cols-[1fr_1.4fr]">
            <form method="POST" action="{{ route('vouchers.store') }}" class="bg-surface border border-line rounded-card shadow-1 p-5 flex flex-col gap-3">
                @csrf
                <h3 class="text-xs font-bold uppercase tracking-wider">Buat Voucher</h3>
                <label class="flex flex-col gap-1 text-xs font-semibold">Kode
                    <input name="code" required maxlength="30" pattern="[A-Za-z0-9_-]+" value="{{ old('code') }}" class="h-10 px-3 rounded-input border border-line-strong bg-surface text-sm uppercase">
                    @error('code')<span class="text-bad">{{ $message }}</span>@enderror
                </label>
                <label class="flex flex-col gap-1 text-xs font-semibold">Nama promo
                    <input name="name" required maxlength="100" value="{{ old('name') }}" class="h-10 px-3 rounded-input border border-line-strong bg-surface text-sm">
                    @error('name')<span class="text-bad">{{ $message }}</span>@enderror
                </label>
                <div class="grid grid-cols-2 gap-3">
                    <label class="flex flex-col gap-1 text-xs font-semibold">Jenis
                        <select name="type" required class="h-10 px-2 rounded-input border border-line-strong bg-surface text-sm">
                            @foreach ($typeLabels as $value => $label)<option value="{{ $value }}" @selected(old('type', 'fixed') === $value)>{{ $label }}</option>@endforeach
                        </select>
                    </label>
                    <label class="flex flex-col gap-1 text-xs font-semibold">Nilai (Rp / %)
                        <input type="number" name="value" min="1" max="100000000" required value="{{ old('value') }}" class="h-10 px-3 rounded-input border border-line-strong bg-surface text-sm">
                        @error('value')<span class="text-bad">{{ $message }}</span>@enderror
                    </label>
                </div>
                <label class="flex flex-col gap-1 text-xs font-semibold">Batas maks. diskon (wajib untuk persen)
                    <input type="number" name="max_discount" min="1" value="{{ old('max_discount') }}" class="h-10 px-3 rounded-input border border-line-strong bg-surface text-sm">
                    @error('max_discount')<span class="text-bad">{{ $message }}</span>@enderror
                </label>
                <div class="grid grid-cols-2 gap-3">
                    <label class="flex flex-col gap-1 text-xs font-semibold">Minimal belanja (Rp)
                        <input type="number" name="min_subtotal" min="0" value="{{ old('min_subtotal', 0) }}" class="h-10 px-3 rounded-input border border-line-strong bg-surface text-sm">
                    </label>
                    <label class="flex flex-col gap-1 text-xs font-semibold">Kuota total (kosong = tanpa batas)
                        <input type="number" name="quota" min="1" value="{{ old('quota') }}" class="h-10 px-3 rounded-input border border-line-strong bg-surface text-sm">
                    </label>
                </div>
                <label class="flex flex-col gap-1 text-xs font-semibold">Maks. pemakaian per pelanggan
                    <input type="number" name="quota_per_customer" min="1" value="{{ old('quota_per_customer', 1) }}" class="h-10 px-3 rounded-input border border-line-strong bg-surface text-sm">
                </label>
                <div class="grid grid-cols-2 gap-3">
                    <label class="flex flex-col gap-1 text-xs font-semibold">Mulai berlaku (opsional)
                        <input type="date" name="starts_at" value="{{ old('starts_at') }}" class="h-10 px-3 rounded-input border border-line-strong bg-surface text-sm">
                    </label>
                    <label class="flex flex-col gap-1 text-xs font-semibold">Berakhir (opsional)
                        <input type="date" name="expires_at" value="{{ old('expires_at') }}" class="h-10 px-3 rounded-input border border-line-strong bg-surface text-sm">
                        @error('expires_at')<span class="text-bad">{{ $message }}</span>@enderror
                    </label>
                </div>
                <button class="h-11 rounded-input bg-primary hover:bg-primary-hover text-white font-bold text-sm">Buat Voucher</button>
            </form>

            <section class="bg-surface border border-line rounded-card shadow-1 overflow-hidden">
                <h3 class="px-4 py-4 text-xs font-bold uppercase tracking-wider">Voucher Terdaftar</h3>
                @if ($vouchers->isEmpty())
                    <p class="px-4 pb-4 text-sm text-ink-2">Belum ada voucher.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-surface-2 text-ink-2 text-[11px] uppercase tracking-wider"><tr><th class="text-left px-4 py-3">Kode / Nama</th><th class="text-left px-4 py-3">Diskon</th><th class="text-left px-4 py-3">Kuota</th><th class="text-left px-4 py-3">Status</th><th class="px-4 py-3"></th></tr></thead>
                            <tbody class="divide-y divide-line">
                                @foreach ($vouchers as $voucher)
                                    <tr>
                                        <td class="px-4 py-3"><strong>{{ $voucher->code }}</strong><span class="block text-xs text-ink-2">{{ $voucher->name }}</span></td>
                                        <td class="px-4 py-3 whitespace-nowrap">{{ $voucher->type->value === 'percent' ? $voucher->value.'% (maks Rp'.number_format($voucher->max_discount).')' : 'Rp'.number_format($voucher->value) }}<span class="block text-[11px] text-ink-2">Min Rp{{ number_format($voucher->min_subtotal) }}</span></td>
                                        <td class="px-4 py-3 tabular">{{ $voucher->used_count }} / {{ $voucher->quota ?? '∞' }}<span class="block text-[11px] text-ink-2">Pelanggan: {{ $voucher->quota_per_customer }}×</span></td>
                                        <td class="px-4 py-3"><span class="text-[11px] font-bold {{ $voucher->is_active ? 'text-ok' : 'text-bad' }}">{{ $voucher->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                                        <td class="px-4 py-3"><form method="POST" action="{{ route('vouchers.toggle', $voucher) }}">@csrf @method('PATCH')<button class="text-xs font-semibold text-primary hover:underline">{{ $voucher->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button></form></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        </div>
    </div>
</x-layouts.app>
