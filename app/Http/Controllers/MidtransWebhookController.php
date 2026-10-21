<?php

namespace App\Http\Controllers;

use App\Services\MidtransGatewayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MidtransWebhookController extends Controller
{
    /**
     * Endpoint notifikasi Midtrans. Wajib verifikasi signature dan idempotent:
     * replay dengan payload sama tidak menambah saldo/pembayaran kedua kali.
     */
    public function __invoke(Request $request, MidtransGatewayService $gateway): JsonResponse
    {
        if (! config('pos.midtrans.enabled')) {
            return response()->json(['message' => 'Gateway tidak aktif.'], 503);
        }

        try {
            $gateway->handleNotification($request->all());
        } catch (ValidationException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'errors' => $exception->errors(),
            ], 403);
        }

        return response()->json(['status' => 'ok']);
    }
}
