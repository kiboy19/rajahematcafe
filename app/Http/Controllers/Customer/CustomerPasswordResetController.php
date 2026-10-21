<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class CustomerPasswordResetController extends Controller
{
    public function request(): View
    {
        return view('spa');
    }

    public function email(Request $request): RedirectResponse|JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        $status = Password::broker('customers')->sendResetLink(
            $request->only('email')
        );

        // Email tak terdaftar tetap dijawab dengan pesan yang sama agar tidak bisa dipakai
        // menebak siapa saja yang punya akun di kafe ini.
        if ($request->expectsJson()) {
            if ($status === Password::RESET_THROTTLED) {
                return response()->json(['message' => __($status)], 422);
            }

            return response()->json(['message' => __(Password::RESET_LINK_SENT)]);
        }

        if ($status === Password::RESET_THROTTLED) {
            return back()->withErrors(['email' => __($status)]);
        }

        return back()->with('status', __(Password::RESET_LINK_SENT));
    }

    public function reset(Request $request, string $token): View
    {
        return view('spa');
    }

    public function update(Request $request): RedirectResponse|JsonResponse
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|confirmed|min:8',
        ]);

        $status = Password::broker('customers')->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($customer, $password) {
                $customer->password = $password;
                $customer->save();
            }
        );

        if ($request->expectsJson()) {
            return $status === Password::PASSWORD_RESET
                ? response()->json(['message' => __($status)])
                : response()->json(['message' => __($status)], 422);
        }

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('customer.login')->with('status', __($status))
            : back()->withErrors(['email' => [__($status)]]);
    }
}
