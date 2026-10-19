<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Rajahemat Cafe' }}</title>
    @vite('resources/css/app.css')
</head>
<body class="min-h-screen bg-bg text-ink font-sans antialiased">
    <header class="sticky top-0 z-10 h-14 bg-surface border-b border-line px-4 flex items-center justify-between">
        <a href="{{ auth('customer')->check() ? route('customer.home') : route('customer.login') }}" class="flex items-center gap-3">
            <span class="w-8 h-8 rounded-full bg-accent text-ink-on-dark flex items-center justify-center font-display font-bold text-sm">R</span>
            <span class="leading-none">
                <span class="font-display font-bold text-base block">Rajahemat</span>
                <span class="text-[10px] uppercase tracking-wider text-ink-2">Coffee &amp; Eatery</span>
            </span>
        </a>

        @auth('customer')
            <form method="POST" action="{{ route('customer.logout') }}">
                @csrf
                <button type="submit" class="h-9 px-3 rounded-input border border-line text-xs font-semibold hover:bg-surface-2">
                    Keluar
                </button>
            </form>
        @endauth
    </header>

    <main class="mx-auto w-full max-w-[430px] px-4 py-5">
        @if (session('success'))
            <p role="status" class="mb-4 rounded-input border-l-4 border-l-ok border border-ok/20 bg-ok-bg px-3 py-2 text-sm">
                {{ session('success') }}
            </p>
        @endif

        @if ($errors->any())
            <div role="alert" class="mb-4 rounded-input border-l-4 border-l-bad border border-bad/20 bg-bad-bg px-3 py-2 text-sm">
                <ul class="flex flex-col gap-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{ $slot }}
    </main>
</body>
</html>
