<?php

namespace App\Http\Controllers;

use App\Enums\WalletTransactionType;
use App\Models\AuditLog;
use App\Models\CashierSession;
use App\Models\Customer;
use App\Models\WalletTransaction;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WalletController extends Controller
{
    /**
     * Loket top-up tunai: kasir mencari pelanggan lewat nomor HP lalu menerima uangnya.
     */
    public function index(Request $request): JsonResponse
    {
        $phone = Customer::normalizePhone((string) $request->query('hp', ''));
        $customer = $phone === '' ? null : Customer::query()->where('phone', $phone)->first();

        $session = CashierSession::openFor($request->user());
        $active = CashierSession::active();
        $active?->load('cashier');

        return response()->json([
            'data' => [
                'phone' => $phone,
                'customer' => $customer,
                'session' => $session,
                'activeSession' => $active ? $active->only(['id', 'status', 'opened_at', 'opening_float']) + [
                    'cashier' => $active->cashier?->only(['id', 'name']),
                ] : null,
                'minTopup' => (int) config('pos.wallet_min_topup'),
                'dailyLimit' => (int) config('pos.wallet_daily_topup_limit'),
                'todayTopups' => $customer
                    ? (int) WalletTransaction::query()
                        ->where('customer_id', $customer->id)
                        ->where('type', WalletTransactionType::TopUp->value)
                        ->whereDate('created_at', today())
                        ->sum('amount')
                    : 0,
                'recentTopups' => $session
                    ? WalletTransaction::query()
                        ->with('customer')
                        ->where('cashier_session_id', $session->id)
                        ->where('type', WalletTransactionType::TopUp->value)
                        ->latest('id')
                        ->limit(10)
                        ->get()
                    : collect(),
            ],
        ]);
    }

    public function store(Request $request, WalletService $wallet): RedirectResponse|JsonResponse
    {
        $session = CashierSession::openFor($request->user());

        if (! $session) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Buka sesi kasir terlebih dahulu: uang top-up masuk ke laci kas.'], 422);
            }

            return redirect()->route('wallet.index')->withErrors([
                'amount' => 'Buka sesi kasir terlebih dahulu: uang top-up masuk ke laci kas.',
            ]);
        }

        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:30'],
            'amount' => ['required', 'integer', 'min:'.config('pos.wallet_min_topup'), 'max:100000000'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $phone = Customer::normalizePhone($validated['phone']);
        $customer = Customer::query()->where('phone', $phone)->first();

        if (! $customer) {
            throw ValidationException::withMessages([
                'phone' => 'Pelanggan dengan nomor '.$phone.' tidak ditemukan. Minta pelanggan mendaftar terlebih dahulu.',
            ]);
        }

        $transaction = DB::transaction(function () use ($customer, $session, $validated, $request, $wallet): WalletTransaction {
            $transaction = $wallet->credit(
                customer: $customer,
                type: WalletTransactionType::TopUp,
                amount: (int) $validated['amount'],
                session: $session,
                cashier: $request->user(),
                note: $validated['note'] ?? null,
            );

            AuditLog::record('wallet.topped_up', $transaction, after: [
                'customer_id' => $customer->id,
                'amount' => $transaction->amount,
                'balance_after' => $transaction->balance_after,
                'cashier_session_id' => $session->id,
            ], actor: $request->user());

            return $transaction;
        });

        $message = 'Top-up Rp'.number_format($transaction->amount, 0, ',', '.').' untuk '.$customer->name
            .'. Saldo sekarang Rp'.number_format($transaction->balance_after, 0, ',', '.').'.';

        if ($request->expectsJson()) {
            return response()->json([
                'data' => $transaction,
                'message' => $message,
            ], 201);
        }

        return redirect()->route('wallet.index', ['hp' => $customer->phone])->with('success', $message);
    }
}
