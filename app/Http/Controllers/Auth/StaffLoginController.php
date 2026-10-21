<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\StaffLoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class StaffLoginController extends Controller
{
    /**
     * @throws ValidationException
     */
    public function store(StaffLoginRequest $request): RedirectResponse|JsonResponse
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

        if ($request->expectsJson()) {
            return response()->json([
                'data' => $this->userPayload($user),
                'message' => 'Selamat datang, '.$user->name.'.',
                'redirect' => route($this->landingRoute($user)),
            ]);
        }

        return redirect()->intended(route($this->landingRoute($user)));
    }

    public function destroy(Request $request): RedirectResponse|JsonResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Anda telah keluar.']);
        }

        return redirect()->route('login');
    }

    /**
     * Profil staf yang sedang login, dipakai SPA untuk memulihkan sesi.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return response()->json(['message' => 'Belum masuk.'], 401);
        }

        return response()->json(['data' => $this->userPayload($user)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role->value,
            'role_label' => $user->role->label(),
            'navigation' => $user->role->navigation(),
            'landing_route' => $this->landingRoute($user),
        ];
    }

    /**
     * Sampai POS/KDS terdaftar, semua role mendarat di dasbor.
     */
    private function landingRoute(User $user): string
    {
        return $user->role->navigation()[0]['route'] ?? 'dashboard';
    }
}
