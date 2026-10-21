<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerOrderController extends Controller
{
    /**
     * Status pesanan milik pelanggan sendiri. Pesanan milik pelanggan lain
     * dijawab 404 agar keberadaannya tidak bocor.
     */
    public function show(Request $request, Order $order): JsonResponse
    {
        if ($order->customer_id !== $request->user('customer')->id) {
            abort(404);
        }

        $order->load(['items', 'payments', 'voucher']);

        return response()->json([
            'data' => [
                'order' => $order,
                'refresh' => $order->status->isFinal() ? null : 20,
            ],
        ]);
    }
}
