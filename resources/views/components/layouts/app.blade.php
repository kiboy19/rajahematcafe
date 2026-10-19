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
    <div class="min-h-screen flex flex-col">
        <header class="h-14 bg-surface border-b border-line px-5 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <span class="w-8 h-8 rounded-full bg-accent text-ink-on-dark flex items-center justify-center font-display font-bold text-sm">R</span>
                <div>
                    <h1 class="font-display font-bold text-base leading-none">Rajahemat</h1>
                    <span class="text-[10px] uppercase tracking-wider text-ink-2">Coffee &amp; Eatery</span>
                </div>
            </div>

            @auth
                <div class="flex items-center gap-4 text-xs">
                    <div class="text-right">
                        <span class="font-semibold block text-sm">{{ auth()->user()->name }}</span>
                        <span class="text-ink-2">{{ auth()->user()->role->label() }}</span>
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="h-8 px-3 rounded-input border border-line hover:bg-surface-2 font-semibold">
                            Keluar
                        </button>
                    </form>
                </div>
            @endauth
        </header>

        <div class="flex-1 flex">
            @auth
                @php
                    $nav = auth()->user()->role->navigation();
                @endphp
                @if (count($nav) > 0)
                    <aside class="w-56 bg-surface border-r border-line p-4 flex flex-col gap-1">
                        @foreach ($nav as $item)
                            <a href="{{ route($item['route']) }}"
                               class="px-3 py-2 rounded-input text-sm font-semibold flex items-center gap-2 {{ request()->routeIs($item['route']) ? 'bg-primary text-white' : 'text-ink-2 hover:bg-surface-2 hover:text-ink' }}">
                                <span>{{ $item['label'] }}</span>
                            </a>
                        @endforeach
                    </aside>
                @endif
            @endauth

            @if (session('success'))
                <div role="status" class="fixed bottom-5 right-5 z-20 max-w-sm rounded-input border border-ok/20 border-l-4 border-l-ok bg-surface p-3 text-sm shadow-2">
                    {{ session('success') }}
                </div>
            @endif

            @if ($errors->any())
                <div role="alert" class="fixed bottom-5 right-5 z-20 max-w-sm rounded-input border border-bad/20 border-l-4 border-l-bad bg-surface p-3 text-sm shadow-2">
                    {{ $errors->first() }}
                </div>
            @endif

            <main class="flex-1 p-6">
                {{ $slot }}
            </main>
        </div>
    </div>
</body>
</html>
