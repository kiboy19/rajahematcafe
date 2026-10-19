<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\CashierSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CashierSessionService
{
    /**
     * Hitung ekspektasi uang kas fisik di laci saat tutup kas.
     *
     * expected_cash = opening_float + cash_sales + cash_topups - cash_refunds - cash_withdrawals.
     * Pembayaran saldo, QRIS, dan EDC tidak pernah masuk laci.
     */
    public function calculateExpectedCash(
        int $openingFloat,
        int $cashSales,
        int $cashRefunds = 0,
        int $cashTopups = 0,
        int $cashWithdrawals = 0,
    ): int {
        if ($openingFloat < 0 || $cashSales < 0 || $cashRefunds < 0 || $cashTopups < 0 || $cashWithdrawals < 0) {
            throw new InvalidArgumentException('Semua komponen uang kas harus non-negatif.');
        }

        return $openingFloat + $cashSales + $cashTopups - $cashRefunds - $cashWithdrawals;
    }

    /**
     * Selisih kas fisik vs catatan sistem. Negatif = kurang, positif = lebih.
     */
    public function calculateVariance(int $expectedCash, int $countedCash): int
    {
        if ($expectedCash < 0 || $countedCash < 0) {
            throw new InvalidArgumentException('Ekspektasi dan hitungan fisik harus non-negatif.');
        }

        return $countedCash - $expectedCash;
    }

    /**
     * Tutup sesi: bekukan ekspektasi, catat hitungan fisik dan selisihnya.
     */
    public function close(CashierSession $session, int $countedCash, ?string $note, ?User $closedBy = null): CashierSession
    {
        if (! $session->isOpen()) {
            throw new InvalidArgumentException('Sesi kasir ini sudah ditutup.');
        }

        return DB::transaction(function () use ($session, $countedCash, $note, $closedBy): CashierSession {
            $expected = $this->calculateExpectedCash(
                openingFloat: $session->opening_float,
                cashSales: $session->cashSalesTotal(),
            );

            $variance = $this->calculateVariance($expected, $countedCash);

            $session->update([
                'expected_cash' => $expected,
                'counted_cash' => $countedCash,
                'variance' => $variance,
                'status' => 'closed',
                'closing_note' => $note,
                'closed_by' => $closedBy?->id ?? auth()->id(),
                'closed_at' => now(),
            ]);

            AuditLog::record('cashier_session.closed', $session, after: [
                'expected_cash' => $expected,
                'counted_cash' => $countedCash,
                'variance' => $variance,
            ], reason: $note, actor: $closedBy);

            return $session;
        });
    }
}
