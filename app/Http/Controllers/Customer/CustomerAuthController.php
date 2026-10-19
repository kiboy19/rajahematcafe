<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\CustomerLoginRequest;
use App\Http\Requests\CustomerRegisterRequest;
use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CustomerAuthController extends Controller
{
    public function showLogin(): View
    {
        return view('customer.login');
    }

    public function showRegister(): View
    {
        abort_unless(config('pos.customer_registration_open'), 404);

        return view('customer.register');
    }

    public function register(CustomerRegisterRequest $request): RedirectResponse
    {
        abort_unless(config('pos.customer_registration_open'), 404);

        $customer = Customer::query()->create($request->safe()->only(['name', 'email', 'phone', 'password']));

        Auth::guard('customer')->login($customer);
        $request->session()->regenerate();

        return redirect()->route('customer.home')
            ->with('success', 'Akun berhasil dibuat. Selamat datang di Rajahemat Cafe, '.$customer->name.'!');
    }

    /**
     * @throws ValidationException
     */
    public function login(CustomerLoginRequest $request): RedirectResponse
    {
        $request->ensureIsNotRateLimited();

        $identifier = trim((string) $request->string('identifier'));
        $column = str_contains($identifier, '@') ? 'email' : 'phone';

        $credentials = [
            $column => $column === 'email' ? mb_strtolower($identifier) : Customer::normalizePhone($identifier),
            'password' => (string) $request->string('password'),
            'is_active' => true,
        ];

        if (! Auth::guard('customer')->attempt($credentials, $request->boolean('remember'))) {
            $request->hitRateLimiter();

            throw ValidationException::withMessages([
                'identifier' => 'Email/nomor HP atau kata sandi tidak cocok.',
            ]);
        }

        $request->clearRateLimiter();
        $request->session()->regenerate();

        return redirect()->intended(route('customer.home'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('customer')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('customer.login');
    }
}
