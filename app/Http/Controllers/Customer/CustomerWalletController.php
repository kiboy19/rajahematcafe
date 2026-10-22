<?php

namespace App\Http\Controllers\Customer;

use App\Enums\WalletTransactionType;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Payments\Environment\EnvironmentClass;
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

        /** @var Customer $customer */
        $customer = $request->user('customer');
        abort_unless($customer instanceof Customer, 401);
        $checkout = $gateway->createCustomerTopUp($customer, (int) $validated['amount']);

        return response()->json([
            'data' => $checkout,
            'message' => 'Silakan selesaikan pembayaran melalui Midtrans.',
        ], 201);
    }
}
