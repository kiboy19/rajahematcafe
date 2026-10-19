<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\CashierSession;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function daily(Request $request): View
    {
        $date = $request->date('tanggal') ?? Carbon::today();

        $orders = Order::query()
            ->whereDate('placed_at', $date)
            ->whereNot('status', OrderStatus::Dibatalkan)
            ->get(['id', 'number', 'channel', 'status', 'total', 'subtotal', 'discount', 'tax', 'service']);

        $paymentsByMethod = Payment::query()
            ->where('status', PaymentStatus::Paid->value)
            ->whereDate('paid_at', $date)
            ->selectRaw('method, SUM(amount) as total, COUNT(*) as jumlah')
            ->groupBy('method')
            ->get();

        $cashPaid = (int) $paymentsByMethod
            ->firstWhere('method', PaymentMethod::Cash->value)
            ?->total;

        $sessions = CashierSession::query()
            ->with(['cashier', 'varianceVerifier'])
            ->whereDate('opened_at', $date)
            ->orderBy('opened_at')
            ->get();

        return view('reports.daily', [
            'date' => $date,
            'orders' => $orders,
            'orderCount' => $orders->count(),
            'grossSales' => (int) $orders->sum('total'),
            'discountTotal' => (int) $orders->sum('discount'),
            'taxTotal' => (int) $orders->sum('tax'),
            'serviceTotal' => (int) $orders->sum('service'),
            'paymentsByMethod' => $paymentsByMethod,
            'cashPaid' => $cashPaid,
            'sessions' => $sessions,
        ]);
    }
}
