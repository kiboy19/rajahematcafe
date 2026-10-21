<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\WalletTopUp;
use App\Services\Payments\PaymentInitiationException;
use App\Services\Payments\PaymentInitiationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PaymentInitiationController extends Controller
{
    public function order(Request $request, Order $order, PaymentInitiationService $payments): JsonResponse
    {
        $customer = $request->user('customer');
        abort_unless($customer !== null && (int) $order->customer_id === (int) $customer->id, 404);

        return $this->initiate(fn (): array => $payments->initiateForOrder($order));
    }

    public function topUp(Request $request, WalletTopUp $topUp, PaymentInitiationService $payments): JsonResponse
    {
        $customer = $request->user('customer');
        abort_unless($customer !== null && (int) $topUp->customer_id === (int) $customer->id, 404);

        return $this->initiate(fn (): array => $payments->initiateForTopUp($topUp));
    }

    /** @param callable():array<string,mixed> $callback */
    private function initiate(callable $callback): JsonResponse
    {
        try {
            return response()->json(['data' => $callback()]);
        } catch (PaymentInitiationException $exception) {
            return response()->json([
                'error' => $exception->error,
                'message' => $exception->getMessage(),
            ], $exception->status);
        }
    }
}
