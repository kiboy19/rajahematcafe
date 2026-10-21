<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\CustomerLoginRequest;
use App\Http\Requests\CustomerRegisterRequest;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CustomerAuthController extends Controller
{
    public function showLogin(): View
    {
        return view('spa');
    }

    public function showRegister(Request $request): View
    {
        abort_unless(config('pos.customer_registration_open'), 404);

        if ($request->filled('return_to')) {
            $returnTo = (string) $request->string('return_to');

            if (str_starts_with($returnTo, '/') && ! str_starts_with($returnTo, '//')) {
                $request->session()->put('url.intended', $returnTo);
            }
        }

        return view('spa');
    }

    public function register(CustomerRegisterRequest $request): RedirectResponse|JsonResponse
    {
        abort_unless(config('pos.customer_registration_open'), 404);

        $customer = Customer::query()->create($request->safe()->only(['name', 'email', 'phone', 'password']));

        Auth::guard('customer')->login($customer);
        $request->session()->regenerate();

        if (config('pos.require_customer_email_verification')) {
            $customer->sendEmailVerificationNotification();
        }

        $intended = $request->session()->pull('url.intended');

        if ($request->expectsJson()) {
            return response()->json([
                'data' => $this->customerPayload($customer),
                'intended' => $intended ?: route('customer.home'),
                'message' => 'Akun berhasil dibuat. Selamat datang di Rajahemat Cafe, '.$customer->name.'!',
            ], 201);
        }

        return redirect()->to($intended ?: route('customer.home'))
            ->with('success', 'Akun berhasil dibuat. Selamat datang di Rajahemat Cafe, '.$customer->name.'!');
    }

    /**
     * @throws ValidationException
     */
    public function login(CustomerLoginRequest $request): RedirectResponse|JsonResponse
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

        $customer = $request->user('customer');

        if ($request->expectsJson()) {
            return response()->json([
                'data' => $this->customerPayload($customer),
                'message' => 'Selamat datang kembali, '.$customer->name.'.',
            ]);
        }

        return redirect()->intended(route('customer.home'));
    }

    public function logout(Request $request): RedirectResponse|JsonResponse
    {
        Auth::guard('customer')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Anda telah keluar.']);
        }

        return redirect()->route('customer.login');
    }

    public function me(Request $request): JsonResponse
    {
        $customer = $request->user('customer');

        if (! $customer) {
            return response()->json(['message' => 'Belum masuk.'], 401);
        }

        return response()->json(['data' => $this->customerPayload($customer)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function customerPayload(Customer $customer): array
    {
        return [
            'id' => $customer->id,
            'name' => $customer->name,
            'email' => $customer->email,
            'phone' => $customer->phone,
            'is_active' => $customer->is_active,
            'balance' => $customer->balance,
            'email_verified_at' => $customer->email_verified_at?->toIso8601String(),
        ];
    }
}
