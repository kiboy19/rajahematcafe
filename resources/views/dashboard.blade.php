<x-layouts.app :title="'Dasbor — Rajahemat Cafe'">
    <div class="max-w-3xl">
        <p class="text-[10.5px] uppercase tracking-widest font-bold text-primary">Fase 1 · Fondasi</p>
        <h2 class="font-display text-2xl font-bold mt-1">Halo, {{ auth()->user()->name }}.</h2>
        <p class="text-sm text-ink-2 mt-1">
            Anda masuk sebagai <span class="font-semibold text-ink">{{ auth()->user()->role->label() }}</span>.
            Repo, autentikasi staf, dan shell layout sudah berjalan. POS, KDS, dan fitur transaksi menyusul per fase.
        </p>

        @php
            $nav = auth()->user()->role->navigation();
        @endphp

        @if (count($nav) > 1)
            <div class="mt-6 grid gap-3 sm:grid-cols-2">
                @foreach ($nav as $item)
                    <a href="{{ route($item['route']) }}"
                       class="bg-surface border border-line rounded-card shadow-1 p-4 hover:border-primary transition-colors">
                        <span class="block text-sm font-bold">{{ $item['label'] }}</span>
                        <span class="block text-xs text-ink-2 mt-1 font-mono">{{ $item['route'] }}</span>
                    </a>
                @endforeach
            </div>
        @endif

        <div class="mt-6 bg-warn-bg text-warn border border-warn/20 rounded-card p-4 text-xs">
            <span class="font-bold block mb-1">Modul transaksi belum tersedia</span>
            Jangan gunakan aplikasi ini untuk transaksi pelanggan sebelum Fase 2–4 selesai dan diuji di perangkat nyata.
        </div>
    </div>
</x-layouts.app>
