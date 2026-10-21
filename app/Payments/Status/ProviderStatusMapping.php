<?php

namespace App\Payments\Status;

/**
 * Hasil pemetaan satu notifikasi gateway ke status internal.
 *
 * Immutable value object: memuat status internal kanonis, peringkat
 * presedensi (buffer/migrasi), serta sinyal turunan yang dipakai lapisan
 * efek bisnis (apakah dana masuk, apakah ini refund/reversal).
 */
final readonly class ProviderStatusMapping
{
    /**
     * @param  array{transaction_status?: string, fraud_status?: string|null}  $raw
     */
    public function __construct(
        public InternalPaymentStatus $status,
        public int $precedenceRank,
        public bool $moneyIn,
        public bool $refundEvent,
        public bool $reversalEvent,
        public string $transactionStatus,
        public ?string $fraudStatus,
        public array $raw = [],
    ) {}

    public function isKnown(): bool
    {
        return $this->status !== InternalPaymentStatus::UnknownStatus;
    }

    public function isRefundEvent(): bool
    {
        return $this->refundEvent;
    }

    public function isReversalEvent(): bool
    {
        return $this->reversalEvent;
    }

    public function isMoneyIn(): bool
    {
        return $this->moneyIn;
    }

    public function requiresManualReview(): bool
    {
        return ! $this->isKnown();
    }
}
