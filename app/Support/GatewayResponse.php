<?php

namespace App\Support;

use App\Enums\GatewayResultToken;
use Illuminate\Http\JsonResponse;

/**
 * Envelope JSON tetap untuk endpoint webhook gateway.
 *
 * 200 => {"status":"success","message":"<token>"}
 * 503 => {"status":"error","message":"<token>"}
 *
 * Tidak boleh ada field lain (`code`, `errors`, dsb.) di body.
 */
final class GatewayResponse
{
    public static function for(GatewayResultToken $token): JsonResponse
    {
        return response()->json([
            'status' => $token->statusWord(),
            'message' => $token->value,
        ], $token->httpStatus());
    }
}
