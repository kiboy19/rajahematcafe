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
        $filters = $request->validate([
            'status' => ['nullable', 'string', 'max:40'],
            'metode' => ['nullable', 'string', 'max:20'],
            'dari' => ['nullable', 'date'],
            'sampai' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'between:10,100'],
        ]);

        $orders = Order::query()
            ->withCount('items')
            ->withSum(['payments as paid_total' => fn ($query) => $query->where('status', 'paid')], 'amount')
            ->with(['cashier', 'customer'])
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['metode'] ?? null, fn ($query, $method) => $query->whereHas(
                'payments',
                fn ($payment) => $payment->where('method', $method)->where('status', 'paid')
            ))
            ->when($filters['dari'] ?? null, fn ($query, $from) => $query->whereDate('placed_at', '>=', $from))
            ->when($filters['sampai'] ?? null, fn ($query, $to) => $query->whereDate('placed_at', '<=', $to))
            ->latest('placed_at')
            ->paginate((int) ($filters['per_page'] ?? 20))
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
