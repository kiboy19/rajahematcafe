<?php

namespace App\Http\Controllers;

use App\Models\CashierSession;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $orders = Order::query()
            ->withCount('items')
            ->latest('placed_at')
            ->paginate(20)
            ->withQueryString();

        return response()->json([
            'data' => $orders->items(),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ],
        ]);
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        $order->load(['items', 'payments.cashier', 'cashier', 'customer', 'voucher']);

        return response()->json([
            'data' => [
                'order' => $order,
                'openSession' => CashierSession::openFor($request->user()),
            ],
        ]);
    }

    public function receipt(Order $order): JsonResponse
    {
        $order->load(['items', 'payments.cashier', 'cashier', 'customer']);

        return response()->json(['data' => $order]);
    }
}
