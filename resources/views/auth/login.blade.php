<x-layouts.app :title="'Masuk Staf — Rajahemat Cafe'">
    <div class="min-h-[calc(100vh-3.5rem)] -m-6 bg-bg flex items-center justify-center px-4 py-10">
        <div class="w-full max-w-sm">
            <div class="text-center mb-6">
                <span class="w-12 h-12 rounded-full bg-accent text-ink-on-dark inline-flex items-center justify-center font-display font-bold text-lg">R</span>
                <h2 class="font-display text-2xl font-bold mt-3">Masuk Staf</h2>
                <p class="text-sm text-ink-2 mt-1">Gunakan akun yang diberikan manager.</p>
            </div>

            <form method="POST" action="{{ route('login.store') }}" class="bg-surface border border-line rounded-card shadow-1 p-5 flex flex-col gap-4">
                @csrf

                @error('email')
                    <p role="alert" class="text-xs font-semibold text-bad bg-bad-bg rounded-input px-3 py-2">{{ $message }}</p>
                @enderror

                <div class="flex flex-col gap-1.5">
                    <label for="email" class="text-xs font-semibold">Email</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                           class="h-11 px-3 rounded-input border border-line-strong bg-surface text-sm @error('email') border-bad @enderror">
                </div>

                <div class="flex flex-col gap-1.5">
                    <label for="password" class="text-xs font-semibold">Kata sandi</label>
                    <input id="password" name="password" type="password" required autocomplete="current-password"
                           class="h-11 px-3 rounded-input border border-line-strong bg-surface text-sm @error('password') border-bad @enderror">
                </div>

                <label class="flex items-center gap-2 text-xs text-ink-2">
                    <input type="checkbox" name="remember" value="1" class="w-4 h-4 accent-primary">
                    Ingat saya di perangkat ini
                </label>

                <button type="submit" class="h-12 rounded-input bg-primary hover:bg-primary-hover active:bg-primary-press text-white font-bold text-sm">
                    Masuk
                </button>
            </form>

            <p class="text-[11px] text-ink-2 text-center mt-4">
                Belum punya akses? Hubungi owner atau manager untuk dibuatkan akun.
            </p>
        </div>
    </div>
</x-layouts.app>
