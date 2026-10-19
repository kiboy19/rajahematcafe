<?php

namespace App\Http\Controllers;

use App\Models\CashierSession;
use App\Models\Order;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function index(): View
    {
        $orders = Order::query()
            ->withCount('items')
            ->latest('placed_at')
            ->paginate(20);

        return view('orders.index', ['orders' => $orders]);
    }

    public function show(Order $order): View
    {
        $order->load(['items', 'payments.cashier', 'cashier', 'customer']);

        return view('orders.show', [
            'order' => $order,
            'openSession' => CashierSession::openFor(request()->user()),
        ]);
    }

    public function receipt(Order $order): View
    {
        $order->load(['items', 'payments.cashier', 'cashier']);

        return view('orders.receipt', [
            'order' => $order,
        ]);
    }
}
