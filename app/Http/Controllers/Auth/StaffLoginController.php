<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\StaffLoginRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class StaffLoginController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * @throws ValidationException
     */
    public function store(StaffLoginRequest $request): RedirectResponse
    {
        $request->ensureIsNotRateLimited();

        if (! Auth::attempt($request->only('email', 'password'), $request->boolean('remember'))) {
            $request->hitRateLimiter();

            throw ValidationException::withMessages([
                'email' => 'Email atau kata sandi tidak cocok.',
            ]);
        }

        $request->clearRateLimiter();
        $request->session()->regenerate();

        /** @var User $user */
        $user = $request->user();

        if (! $user->isActiveStaff()) {
            Auth::logout();
            $request->session()->invalidate();

            throw ValidationException::withMessages([
                'email' => 'Akun ini tidak aktif. Hubungi manager.',
            ]);
        }

        return redirect()->intended(route($this->landingRoute($user)));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    /**
     * Sampai POS/KDS terdaftar, semua role mendarat di dasbor.
     */
    private function landingRoute(User $user): string
    {
        return $user->role->navigation()[0]['route'] ?? 'dashboard';
    }
}
