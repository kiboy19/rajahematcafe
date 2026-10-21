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
 *
 * Top-up online dari gateway WAJIB memakai `reference` unik (mis. `midtrans:credit:{orderRef}:{eventKey}`)
 * sehingga sebuah event yang diputar ulang tidak pernah menulis baris ledger kedua.
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
        ?string $reference = null,
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

        return $this->post($customer, $type, $amount, $session, $cashier, $note, $payment, $reference);
    }

    /**
     * Kredit top-up online yang idempotent. `reference` bersifat wajib dan unik:
     * pengamatan kedua dengan referensi yang sama mengembalikan baris ledger yang
     * sudah ada tanpa menyentuh saldo. Operasi ini berjalan di dalam transaksi
     * bisnis pemanggil (tidak membuka transaksi sendiri) supaya efek bisnis,
     * ledger, dan audit commit bersama.
     */
    public function creditTopUp(
        Customer $customer,
        int $amount,
        string $reference,
        ?string $note = null,
    ): WalletTransaction {
        if ($reference === '') {
            throw ValidationException::withMessages(['reference' => 'Referensi top-up wajib diisi.']);
        }

        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Nominal saldo harus lebih dari nol.']);
        }

        $existing = WalletTransaction::query()->where('reference', $reference)->first();
        if ($existing !== null) {
            return $existing;
        }

        $this->assertWithinDailyLimit($customer, $amount);

        return $this->post($customer, WalletTransactionType::TopUp, $amount, null, null, $note, null, $reference);
    }

    /**
     * Balikkan kredit top-up berdasarkan `reference` kredit semula. Idempotent:
     * pemanggilan kedua untuk transaksi yang sudah dibalik mengembalikan baris
     * debit yang sudah ada tanpa mengubah saldo lagi.
     *
     * `$reversalKey` membedakan pembalikan parsial: satu kredit dapat dibalik
     * beberapa kali (mis. dua refund sebagian) bila setiap pembalikan membawa
     * kunci unik (id refund provider). Tanpa kunci, satu kredit hanya dapat
     * dibalik sekali.
     */
    public function reverseCredit(string $reference, ?string $note = null, ?int $amount = null, ?string $reversalKey = null): ?WalletTransaction
    {
        if ($reference === '') {
            throw ValidationException::withMessages(['reference' => 'Referensi pembalikan wajib diisi.']);
        }

        $reversalReference = 'reverse:'.$reference.($reversalKey !== null && $reversalKey !== '' ? ':'.$reversalKey : '');

        $existing = WalletTransaction::query()->where('reference', $reversalReference)->first();
        if ($existing !== null) {
            return $existing;
        }

        $original = WalletTransaction::query()->where('reference', $reference)->first();
        if ($original === null) {
            return null;
        }

        $origAmount = abs((int) $original->amount);
        $amount = $amount === null ? $origAmount : abs($amount);
        if ($amount <= 0 || $amount > $origAmount) {
            throw ValidationException::withMessages(['amount' => 'Nominal pembalikan harus antara nol dan nominal kredit semula.']);
        }
        $customer = $original->customer ?? Customer::query()->findOrFail($original->customer_id);

        return $this->post(
            $customer,
            WalletTransactionType::Refund,
            -$amount,
            null,
            null,
            $note ?? 'Pembalikan top-up (Ref: '.$reference.')',
            null,
            $reversalReference,
        );
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
        ?string $reference = null,
    ): WalletTransaction {
        // Jalur admin/kasir (reference null) mengelola transaksinya sendiri;
        // jalur gateway (reference terisi) berbagi transaksi bisnis pemanggil
        // sehingga efek bisnis dan ledger rollback bersama.
        if ($reference === null) {
            return DB::transaction(fn (): WalletTransaction => $this->write(
                $customer, $type, $amount, $session, $cashier, $note, $payment, null, true,
            ));
        }

        return $this->write($customer, $type, $amount, $session, $cashier, $note, $payment, $reference, false);
    }

    private function write(
        Customer $customer,
        WalletTransactionType $type,
        int $amount,
        ?CashierSession $session,
        ?User $cashier,
        ?string $note,
        ?Payment $payment,
        ?string $reference,
        bool $lockCustomer,
    ): WalletTransaction {
        if ($reference !== null) {
            $existing = WalletTransaction::query()->where('reference', $reference)->first();
            if ($existing !== null) {
                return $existing;
            }
        }

        // Kunci baris pelanggan: dua permintaan bersamaan tidak boleh memakai saldo yang sama.
        $locked = $lockCustomer
            ? Customer::query()->lockForUpdate()->findOrFail($customer->id)
            : Customer::query()->findOrFail($customer->id);

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
            'reference' => $reference,
        ]);

        $locked->update(['balance' => $balanceAfter]);
        $customer->setAttribute('balance', $balanceAfter);

        return $transaction;
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
