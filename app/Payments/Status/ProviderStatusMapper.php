<?php

namespace App\Payments\Status;

/**
 * Pemetaan status provider (Midtrans) ke status internal kanonis.
 *
 * Tabel ini harus lengkap (exhaustive): setiap kombinasi
 * `transaction_status` × `fraud_status` yang mungkin dikirim Midtrans
 * wajib punya baris di sini. Default adalah `UnknownStatus` rank 0.
 *
 * Ringkasan Midtrans (relevant untuk payment notification):
 * - capture (CC): fraud_status = accept|challenge|deny
 * - settlement (VA, e-wallet, QRIS): tanpa fraud_status
 * - pending: tanpa fraud_status
 * - deny: tanpa fraud_status
 * - cancel: tanpa fraud_status
 * - expire: tanpa fraud_status
 * - refund: tanpa fraud_status
 * - partial_refund: tanpa fraud_status
 * - chargeback: tanpa fraud_status
 *
 * Catatan presedensi (rank 1-10, 0=unknown):
 * 1 pending
 * 2 settlement_pending  (capture accept)
 * 3 settled
 * 4 partially_refunded
 * 5 failed
 * 6 expired
 * 7 canceled
 * 8 denied
 * 9 refunded
 * 10 charged_back
 *
 * Refund/reversal flags hanya true untuk event refund/partial_refund/chargeback.
 * Sinyal `moneyIn` true untuk status yang menandakan dana benar-benar masuk.
 */
final class ProviderStatusMapper
{
    /**
     * Tabel pemetaan kanonis.
     *
     * Setiap baris: transaction_status => fraud_status (string|null) => status internal.
     *
     * @var array<string, array<string|null, InternalPaymentStatus>>
     */
    private const TABLE = [
        'capture' => [
            'accept' => InternalPaymentStatus::SettlementPending,
            'challenge' => InternalPaymentStatus::Pending,
            'deny' => InternalPaymentStatus::Denied,
            null => InternalPaymentStatus::UnknownStatus,
        ],
        'settlement' => [
            null => InternalPaymentStatus::Settled,
        ],
        'pending' => [
            null => InternalPaymentStatus::Pending,
        ],
        'deny' => [
            null => InternalPaymentStatus::Denied,
        ],
        'cancel' => [
            null => InternalPaymentStatus::Canceled,
        ],
        'expire' => [
            null => InternalPaymentStatus::Expired,
        ],
        'refund' => [
            null => InternalPaymentStatus::Refunded,
        ],
        'partial_refund' => [
            null => InternalPaymentStatus::PartiallyRefunded,
        ],
        'chargeback' => [
            null => InternalPaymentStatus::ChargedBack,
        ],
    ];

    /**
     * Pemetaan satu notifikasi Midtrans ke status internal.
     *
     * @param  string  $transactionStatus  nilai `transaction_status` dari payload (case-insensitive)
     * @param  string|null  $fraudStatus   nilai `fraud_status` dari payload, atau null jika tidak ada (case-insensitive)
     * @return ProviderStatusMapping       status internal + metadata presedensi
     */
    public static function map(string $transactionStatus, ?string $fraudStatus): ProviderStatusMapping
    {
        $ts = strtolower(trim($transactionStatus));
        $fs = $fraudStatus !== null ? strtolower(trim($fraudStatus)) : null;

        $byTransaction = self::TABLE[$ts] ?? [];

        // fraud_status null/kosong berarti "tidak dikirim".
        $lookupFraud = ($fs === null || $fs === '') ? null : $fs;

        $status = $byTransaction[$lookupFraud] ?? $byTransaction[null] ?? null;

        if ($status === null) {
            // Tidak ada baris untuk transaction_status ini sama sekali.
            $status = InternalPaymentStatus::UnknownStatus;
        }

        return self::describe($status, $transactionStatus, $fraudStatus);
    }

    /**
     * Bungkus status internal dengan metadata sinyal turunan.
     */
    private static function describe(
        InternalPaymentStatus $status,
        string $transactionStatus,
        ?string $fraudStatus,
    ): ProviderStatusMapping {
        return new ProviderStatusMapping(
            status: $status,
            precedenceRank: $status->precedenceRank(),
            moneyIn: $status->isMoneyIn(),
            refundEvent: in_array($status, [InternalPaymentStatus::Refunded, InternalPaymentStatus::PartiallyRefunded], true),
            reversalEvent: $status === InternalPaymentStatus::ChargedBack,
            transactionStatus: $transactionStatus,
            fraudStatus: $fraudStatus,
        );
    }

    /**
     * Daftar semua transaction_status yang dikenal mapper (untuk testing/dokumentasi).
     *
     * @return array<string>
     */
    public static function knownTransactionStatuses(): array
    {
        return array_keys(self::TABLE);
    }

    /**
     * Semua fraud_status yang valid untuk suatu transaction_status.
     *
     * @param  string  $transactionStatus
     * @return array<int, string>
     */
    public static function knownFraudStatusesFor(string $transactionStatus): array
    {
        $ts = strtolower(trim($transactionStatus));
        $byTransaction = self::TABLE[$ts] ?? [];

        $keys = [];
        foreach ($byTransaction as $key => $_status) {
            if ($key !== null && $key !== '') {
                $keys[] = $key;
            }
        }

        return $keys;
    }
}
