<?php

namespace App\Payments\Environment;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Dilempar saat jalur pembuatan pembayaran harus fail-closed.
 * Dirender sebagai 503 `internal_error` (kontrak respons 2.3).
 */
final class PaymentGatewayUnavailableException extends RuntimeException
{
    public function render(): JsonResponse
    {
        return response()->json(FailClosedGuard::paymentCreationEnvelope(), 503);
    }
}
