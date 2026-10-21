<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCustomerEmailIsVerifiedIfRequired
{
    /**
     * Memesan di meja hanya diwajibkan verifikasi email setelah diaktifkan
     * lewat POS_REQUIRE_CUSTOMER_EMAIL_VERIFICATION (Gate 0 SMTP).
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (config('pos.require_customer_email_verification')) {
            $user = $request->user('customer');

            if ($user instanceof MustVerifyEmail && ! $user->hasVerifiedEmail()) {
                return $request->expectsJson()
                    ? abort(403, 'Alamat email Anda belum diverifikasi.')
                    : redirect()->route('customer.verification.notice');
            }
        }

        return $next($request);
    }
}
