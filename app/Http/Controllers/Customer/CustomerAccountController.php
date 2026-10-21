<?php

namespace App\Http\Controllers\Customer;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerAccountController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $customer = $request->user('customer');

        $orders = $customer->orders()->withCount('items')->latest('id')->paginate(10);
        $transactions = $customer->walletTransactions()->latest('id')->limit(10)->get();
        $stats = $customer->orders()
            ->where('status', '!=', OrderStatus::Dibatalkan->value)
            ->selectRaw('COUNT(*) as orders_count, COALESCE(SUM(total), 0) as total_spent')
            ->first();

        return response()->json([
            'data' => [
                'customer' => $customer,
                'orders' => $orders->items(),
                'meta' => [
                    'current_page' => $orders->currentPage(),
                    'last_page' => $orders->lastPage(),
                    'total' => $orders->total(),
                ],
                'transactions' => $transactions,
                'stats' => $stats,
            ],
        ]);
    }
}
