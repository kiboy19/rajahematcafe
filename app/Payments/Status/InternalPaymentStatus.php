<?php

namespace App\Payments\Status;

/**
 * Status internal kanonis hasil normalisasi notifikasi gateway.
 *
 * Himpunan ini tidak sama dengan status pasien/order; ia hanya merekam
 * keadaan uang di gateway. Nilai `UnknownStatus` adalah default fail-safe
 * untuk status provider yang tidak dikenal (rank 0, tanpa efek uang).
 */
enum InternalPaymentStatus: string
{
    case Pending = 'pending';
    case SettlementPending = 'settlement_pending';
    case Settled = 'settled';
    case PartiallyRefunded = 'partially_refunded';
    case Refunded = 'refunded';
    case Failed = 'failed';
    case Expired = 'expired';
    case Canceled = 'canceled';
    case Denied = 'denied';
    case ChargedBack = 'charged_back';
    case UnknownStatus = 'unknown_status';

    /**
     * Peringkat presedensi 1-10 (unknown_status = 0).
     *
     * Peringkat HANYA dipakai untuk (a) mengurutkan event di buffer
     * replika/migrasi dan (b) memilih pemenang saat memigrasikan data lama
     * yang berisi beberapa status untuk satu referensi. Peringkat TIDAK
     * dipakai untuk memutuskan transisi — keputusan itu milik
     * TransitionValidator, karena arah "lebih tinggi menang" salah untuk
     * refund (refunded rank 9 -> failed rank 6 bukan regresi).
     *
     * 1 pending -> 2 settlement_pending -> 3 settled adalah alur maju normal.
     * 4-9 merekam episode refund, kegagalan, atau pembalikan dana yang
     * menimpa status terminal. 10 charged_back menutup transaksi.
     */
    public function precedenceRank(): int
    {
        return match ($this) {
            self::UnknownStatus => 0,
            self::Pending => 1,
            self::SettlementPending => 2,
            self::Settled => 3,
            self::PartiallyRefunded => 4,
            self::Failed => 5,
            self::Expired => 6,
            self::Canceled => 7,
            self::Denied => 8,
            self::Refunded => 9,
            self::ChargedBack => 10,
        };
    }

    /**
     * Status yang tidak punya jalur keluar sama sekali.
     */
    public function isTerminal(): bool
    {
        return match ($this) {
            self::Refunded, self::ChargedBack => true,
            default => false,
        };
    }

    /**
     * Status yang merekam kegagalan/batal sebelum dana pernah masuk.
     * Recovery dari status ini wajib disertai bukti settlement provider.
     */
    public function isNonPaymentTerminal(): bool
    {
        return match ($this) {
            self::Failed, self::Expired, self::Canceled, self::Denied => true,
            default => false,
        };
    }

    /**
     * Status yang menandakan dana pernah benar-benar masuk.
     */
    public function isMoneyIn(): bool
    {
        return match ($this) {
            self::Settled, self::PartiallyRefunded, self::Refunded, self::ChargedBack => true,
            default => false,
        };
    }
}
