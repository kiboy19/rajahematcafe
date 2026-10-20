<?php

namespace App\Services;

use App\Enums\WalletTransactionType;
use App\Models\CashierSession;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Satu-satunya jalur perubahan saldo pelanggan. Setiap perubahan menulis baris
 * ledger (tidak pernah dihapus) dan memperbarui saldo ter-cache pada baris pelanggan.
 */
class WalletService
{
    public function credit(
        Customer $customer,
        WalletTransactionType $type,
        int $amount,
        ?CashierSession $session = null,
        ?User $cashier = null,
        ?string $note = null,
        ?Payment $payment = null,
    ): WalletTransaction {
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Nominal saldo harus lebih dari nol.']);
        }

        if ($type !== WalletTransactionType::TopUp && $type !== WalletTransactionType::Refund) {
            throw ValidationException::withMessages(['amount' => 'Jenis transaksi saldo ini bukan kredit.']);
        }

        if ($type === WalletTransactionType::TopUp) {
            $this->assertWithinDailyLimit($customer, $amount);
        }

        return $this->post($customer, $type, $amount, $session, $cashier, $note, $payment);
    }

    /**
     * Debit saldo. Gagal (tanpa mengubah apa pun) bila saldo tidak cukup.
     */
    public function debit(
        Customer $customer,
        int $amount,
        Payment $payment,
        ?CashierSession $session = null,
        ?User $cashier = null,
    ): WalletTransaction {
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Nominal saldo harus lebih dari nol.']);
        }

        return $this->post($customer, WalletTransactionType::Payment, -$amount, $session, $cashier, null, $payment);
    }

    /**
     * Saldo menurut ledger. Harus sama dengan `customers.balance` (invariant).
     */
    public function ledgerBalance(Customer $customer): int
    {
        return (int) WalletTransaction::query()->where('customer_id', $customer->id)->sum('amount');
    }

    private function post(
        Customer $customer,
        WalletTransactionType $type,
        int $amount,
        ?CashierSession $session,
        ?User $cashier,
        ?string $note,
        ?Payment $payment,
    ): WalletTransaction {
        return DB::transaction(function () use ($customer, $type, $amount, $session, $cashier, $note, $payment): WalletTransaction {
            // Kunci baris pelanggan: dua permintaan bersamaan tidak boleh memakai saldo yang sama.
            $locked = Customer::query()->lockForUpdate()->findOrFail($customer->id);
            $balanceAfter = (int) $locked->balance + $amount;

            if ($balanceAfter < 0) {
                throw ValidationException::withMessages([
                    'amount' => 'Saldo tidak cukup. Sisa saldo: Rp'.number_format((int) $locked->balance, 0, ',', '.').'.',
                ]);
            }

            $transaction = WalletTransaction::query()->create([
                'customer_id' => $locked->id,
                'type' => $type,
                'amount' => $amount,
                'balance_after' => $balanceAfter,
                'cashier_session_id' => $session?->id,
                'cashier_id' => $cashier?->id,
                'payment_id' => $payment?->id,
                'note' => $note,
            ]);

            $locked->update(['balance' => $balanceAfter]);
            $customer->setAttribute('balance', $balanceAfter);

            return $transaction;
        });
    }

    /**
     * Batas harian melindungi dari salah ketik nominal di kasir (Gate 0 belum menetapkan angka final).
     */
    private function assertWithinDailyLimit(Customer $customer, int $amount): void
    {
        $limit = (int) config('pos.wallet_daily_topup_limit');

        if ($limit <= 0) {
            return;
        }

        $today = (int) WalletTransaction::query()
            ->where('customer_id', $customer->id)
            ->where('type', WalletTransactionType::TopUp->value)
            ->where('amount', '>', 0)
            ->whereBetween('created_at', [Carbon::today(), Carbon::today()->endOfDay()])
            ->sum('amount');

        if ($today + $amount > $limit) {
            throw ValidationException::withMessages([
                'amount' => 'Melebihi batas top-up harian (Rp'.number_format($limit, 0, ',', '.').'). Sudah top-up hari ini: Rp'.number_format($today, 0, ',', '.').'.',
            ]);
        }
    }
}
