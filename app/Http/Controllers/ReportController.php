<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\WalletTransactionType;
use App\Models\CashierSession;
use App\Models\Order;
use App\Models\Payment;
use App\Models\WalletTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ReportController extends Controller
{
    public function daily(Request $request): JsonResponse
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

        $cashTopups = (int) WalletTransaction::query()
            ->where('type', WalletTransactionType::TopUp->value)
            ->whereBetween('created_at', [$date->copy()->startOfDay(), $date->copy()->endOfDay()])
            ->sum('amount');

        $sessions = CashierSession::query()
            ->with(['cashier', 'varianceVerifier'])
            ->whereDate('opened_at', $date)
            ->orderBy('opened_at')
            ->get();

        return response()->json([
            'data' => [
                'date' => $date->toDateString(),
                'orders' => $orders,
                'orderCount' => $orders->count(),
                'grossSales' => (int) $orders->sum('total'),
                'discountTotal' => (int) $orders->sum('discount'),
                'taxTotal' => (int) $orders->sum('tax'),
                'serviceTotal' => (int) $orders->sum('service'),
                'paymentsByMethod' => $paymentsByMethod,
                'cashPaid' => $cashPaid,
                'cashTopups' => $cashTopups,
                'sessions' => $sessions,
            ],
        ]);
    }
}
