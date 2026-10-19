<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="refresh" content="20">
    <title>{{ $title ?? 'Dapur — Rajahemat Cafe' }}</title>
    @vite('resources/css/app.css')
</head>
<body class="min-h-screen bg-kds-bg text-kds-ink font-sans antialiased">
    <header class="h-14 border-b border-kds-line px-5 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <span class="w-8 h-8 rounded-full bg-primary text-white flex items-center justify-center font-display font-bold text-sm">R</span>
            <div>
                <h1 class="font-bold text-base leading-none">Dapur · KDS</h1>
                <span class="text-[10px] uppercase tracking-widest text-kds-muted">Rajahemat Coffee &amp; Eatery</span>
            </div>
        </div>

        <div class="flex items-center gap-5 text-xs text-kds-muted">
            <span>Diperbarui {{ now()->format('H:i:s') }}</span>
            <span class="tabular">{{ auth()->user()->name }}</span>
            @unless (auth()->user()->role === \App\Enums\UserRole::Dapur)
                <a href="{{ route('dashboard') }}" class="underline hover:text-kds-ink">Dasbor</a>
            @endunless
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="underline hover:text-kds-ink">Keluar</button>
            </form>
        </div>
    </header>

    <main class="p-4">
        {{ $slot }}
    </main>
</body>
</html>
