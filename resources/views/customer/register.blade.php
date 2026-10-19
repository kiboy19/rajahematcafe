<x-layouts.customer :title="'Daftar — Rajahemat Cafe'">
    <h1 class="font-display text-2xl font-bold">Buat akun</h1>
    <p class="text-sm text-ink-2 mt-1">
        Akun dipakai untuk memesan dari meja dan menyimpan saldo.
    </p>

    <form method="POST" action="{{ route('customer.register.store') }}" class="mt-5 bg-surface border border-line rounded-card shadow-1 p-5 flex flex-col gap-4">
        @csrf

        <div class="flex flex-col gap-1.5">
            <label for="name" class="text-xs font-semibold">Nama</label>
            <input id="name" name="name" type="text" autocomplete="name" required maxlength="120"
                   value="{{ old('name') }}"
                   class="h-11 rounded-input border border-line bg-bg px-3 text-sm">
        </div>

        <div class="flex flex-col gap-1.5">
            <label for="email" class="text-xs font-semibold">Email</label>
            <input id="email" name="email" type="email" autocomplete="email" required maxlength="190"
                   value="{{ old('email') }}"
                   class="h-11 rounded-input border border-line bg-bg px-3 text-sm">
        </div>

        <div class="flex flex-col gap-1.5">
            <label for="phone" class="text-xs font-semibold">Nomor HP / WhatsApp</label>
            <input id="phone" name="phone" type="tel" inputmode="tel" autocomplete="tel" required placeholder="08123456789"
                   value="{{ old('phone') }}"
                   class="h-11 rounded-input border border-line bg-bg px-3 text-sm">
        </div>

        <div class="flex flex-col gap-1.5">
            <label for="password" class="text-xs font-semibold">Kata sandi</label>
            <input id="password" name="password" type="password" autocomplete="new-password" required minlength="8"
                   class="h-11 rounded-input border border-line bg-bg px-3 text-sm">
            <span class="text-[11px] text-ink-2">Minimal 8 karakter.</span>
        </div>

        <div class="flex flex-col gap-1.5">
            <label for="password_confirmation" class="text-xs font-semibold">Ulangi kata sandi</label>
            <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required
                   class="h-11 rounded-input border border-line bg-bg px-3 text-sm">
        </div>

        <button type="submit" class="h-12 rounded-input bg-primary text-white font-semibold hover:bg-primary-hover">
            Daftar
        </button>
    </form>

    <p class="mt-4 text-sm text-ink-2 text-center">
        Sudah punya akun?
        <a href="{{ route('customer.login') }}" class="font-semibold text-primary underline">Masuk</a>
    </p>
</x-layouts.customer>
