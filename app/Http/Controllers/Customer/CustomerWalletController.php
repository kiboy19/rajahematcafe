<?php

namespace App\Http\Controllers\Customer;

use App\Enums\WalletTransactionType;
use App\Http\Controllers\Controller;
use App\Payments\Environment\EnvironmentClass;
use App\Payments\Environment\FailClosedGuard;
use App\Services\MidtransGatewayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerWalletController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $customer = $request->user('customer');

        $todayTopups = (int) $customer->walletTransactions()
            ->where('type', WalletTransactionType::TopUp->value)
            ->where('amount', '>', 0)
            ->whereDate('created_at', today())
            ->sum('amount');

        return response()->json([
            'data' => [
                'customer' => $customer,
                'minTopup' => (int) config('pos.wallet_min_topup'),
                'dailyLimit' => (int) config('pos.wallet_daily_topup_limit'),
                'todayTopups' => $todayTopups,
                'recentTopups' => $customer->walletTransactions()
                    ->where('type', WalletTransactionType::TopUp->value)
                    ->latest('id')
                    ->limit(10)
                    ->get(),
                'transactions' => $customer->walletTransactions()->latest('id')->limit(10)->get(),
                'midtransEnabled' => EnvironmentClass::isEnabled()
                    && EnvironmentClass::serverKey() !== ''
                    && ! EnvironmentClass::isFailClosed(),
            ],
        ]);
    }

    public function topUp(Request $request, MidtransGatewayService $gateway): JsonResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'integer', 'min:'.config('pos.wallet_min_topup'), 'max:100000000'],
        ]);

        $customer = $request->user('customer');
        $checkout = $gateway->createCustomerTopUp($customer, (int) $validated['amount']);

        return response()->json([
            'data' => $checkout,
            'message' => $checkout['mode'] === 'mock'
                ? 'Top-up dibuat dalam mode simulasi karena Midtrans belum dikonfigurasi.'
                : 'Silakan selesaikan pembayaran melalui Midtrans.',
        ], 201);
    }

    public function simulateTopUp(Request $request, MidtransGatewayService $gateway): JsonResponse
    {
        abort_unless(FailClosedGuard::allowsMockRoutes(), 404);

        $validated = $request->validate(['order_id' => ['required', 'string', 'max:64']]);
        $transaction = $gateway->simulateSettlement(
            $request->user('customer'),
            $validated['order_id'],
        );

        return response()->json([
            'data' => ['balance' => $request->user('customer')->fresh()->balance, 'transaction' => $transaction],
            'message' => 'Top-up simulasi berhasil masuk ke saldo.',
        ]);
    }
}
