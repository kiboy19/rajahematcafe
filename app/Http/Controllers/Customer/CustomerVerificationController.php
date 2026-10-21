<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerVerificationController extends Controller
{
    public function notice(): View
    {
        return view('spa');
    }

    public function send(Request $request): RedirectResponse|JsonResponse
    {
        $customer = $request->user('customer');

        if ($customer->hasVerifiedEmail()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Email Anda sudah terverifikasi.', 'data' => ['verified' => true]]);
            }

            return redirect()->route('customer.home');
        }

        $customer->sendEmailVerificationNotification();

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Tautan verifikasi telah dikirim ke email Anda.']);
        }

        return back()->with('success', 'Tautan verifikasi telah dikirim ke email Anda.');
    }

    /**
     * Tautan bertanda tangan menahan token yang ditebak; id + hash memastikan tautan
     * milik akun lain tidak bisa dipakai untuk memverifikasi akun ini.
     */
    public function verify(Request $request, int $id, string $hash): RedirectResponse|JsonResponse
    {
        $customer = $request->user('customer');

        if ($customer->getKey() !== $id || ! hash_equals($hash, sha1((string) $customer->getEmailForVerification()))) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Tautan verifikasi tidak valid.'], 403);
            }

            abort(403);
        }

        if (! $customer->hasVerifiedEmail()) {
            $customer->markEmailAsVerified();
        }

        if ($request->expectsJson()) {
            return response()->json([
                'data' => ['verified' => true],
                'message' => 'Email berhasil diverifikasi.',
            ]);
        }

        return redirect()->route('customer.home')->with('success', 'Email berhasil diverifikasi.');
    }
}
