<x-layouts.customer :title="'Masuk — Rajahemat Cafe'">
    <h1 class="font-display text-2xl font-bold">Masuk</h1>
    <p class="text-sm text-ink-2 mt-1">
        Gunakan email atau nomor HP yang terdaftar.
    </p>

    <form method="POST" action="{{ route('customer.login.store') }}" class="mt-5 bg-surface border border-line rounded-card shadow-1 p-5 flex flex-col gap-4">
        @csrf

        <div class="flex flex-col gap-1.5">
            <label for="identifier" class="text-xs font-semibold">Email atau nomor HP</label>
            <input id="identifier" name="identifier" type="text" inputmode="email" autocomplete="username" required
                   value="{{ old('identifier') }}"
                   class="h-11 rounded-input border border-line bg-bg px-3 text-sm">
        </div>

        <div class="flex flex-col gap-1.5">
            <label for="password" class="text-xs font-semibold">Kata sandi</label>
            <input id="password" name="password" type="password" autocomplete="current-password" required
                   class="h-11 rounded-input border border-line bg-bg px-3 text-sm">
        </div>

        <label class="flex items-center gap-2 text-xs text-ink-2">
            <input type="checkbox" name="remember" value="1" class="rounded border-line">
            Ingat saya di perangkat ini
        </label>

        <button type="submit" class="h-12 rounded-input bg-primary text-white font-semibold hover:bg-primary-hover">
            Masuk
        </button>
    </form>

    @if (config('pos.customer_registration_open'))
        <p class="mt-4 text-sm text-ink-2 text-center">
            Belum punya akun?
            <a href="{{ route('customer.register') }}" class="font-semibold text-primary underline">Daftar di sini</a>
        </p>
    @else
        <p class="mt-4 text-xs text-ink-2 text-center">
            Pendaftaran mandiri belum dibuka. Minta kasir membuatkan akun saat berkunjung.
        </p>
    @endif

    <p class="mt-6 text-center text-xs text-ink-2">
        <a href="{{ route('login') }}" class="underline">Masuk sebagai staf</a>
    </p>
</x-layouts.customer>
