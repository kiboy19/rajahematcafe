<x-layouts.app :title="'QR Meja — Rajahemat Cafe'">
    <div class="max-w-4xl">
        <h2 class="font-display text-2xl font-bold">QR Meja Pelanggan</h2>
        <p class="text-sm text-ink-2 mt-1">
            Cetak URL berikut menjadi QR code untuk tiap meja. Pelanggan yang memindai akan diminta masuk
            (akun pelanggan) sebelum dapat memesan, dan pesanan langsung masuk ke antrean dapur.
        </p>

        <div class="mt-5 bg-surface border border-line rounded-card shadow-1 overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-surface-2 text-ink-2 text-[11px] uppercase tracking-wider">
                    <tr>
                        <th class="text-left px-4 py-3">Meja</th>
                        <th class="text-left px-4 py-3">Status</th>
                        <th class="text-left px-4 py-3">URL QR</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @foreach ($tables as $table)
                        <tr>
                            <td class="px-4 py-3 font-semibold">{{ $table->label }}</td>
                            <td class="px-4 py-3">
                                <span class="text-[11px] font-bold uppercase {{ $table->is_active ? 'text-ok' : 'text-bad' }}">
                                    {{ $table->is_active ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <code class="text-[11px] break-all">{{ route('customer.table.scan', $table->qr_token) }}</code>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <p class="mt-4 text-xs text-ink-2">
            Meja nonaktif tidak dapat memesan (QR-nya ditolak). Ganti token bila QR lama bocor/dicuri.
        </p>
    </div>
</x-layouts.app>
