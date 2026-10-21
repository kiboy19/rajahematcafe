<?php

namespace App\Enums;

/**
 * Token hasil pemrosesan notifikasi gateway. Himpunan ini adalah superset
 * yang dipakai seluruh alur gateway; setiap token wajib dipetakan ke status
 * HTTP yang pasti lewat GatewayHttpStatus.
 */
enum GatewayResultToken: string
{
    case Processed = 'processed';
    case DuplicateIgnored = 'duplicate_ignored';
    case Rejected = 'rejected';
    case Repaired = 'repaired';
    case ProviderTimeout = 'provider_timeout';
    case ProviderUnavailable = 'provider_unavailable';
    case ProviderRejected = 'provider_rejected';
    case ProviderMalformed = 'provider_malformed';
    case SignatureInvalid = 'signature_invalid';
    case AmountMismatch = 'amount_mismatch';
    case CurrencyMismatch = 'currency_mismatch';
    case ContextMismatch = 'context_mismatch';
    case TypeMismatch = 'type_mismatch';
    case CustomerMismatch = 'customer_mismatch';
    case UnknownReference = 'unknown_reference';
    case UnknownStatus = 'unknown_status';
    case Overpayment = 'overpayment';
    case InternalError = 'internal_error';
    case BufferExpired = 'buffer_expired';
    case StaleTransition = 'stale_transition';
    case RefundExceedsSettled = 'refund_exceeds_settled';
    case RefundReversal = 'refund_reversal';
    case ChargedBack = 'charged_back';
    case ConfigError = 'config_error';

    /**
     * Tabel pemetaan status HTTP authoritative (TASK-011).
     *
     * 503 hanya untuk kegagalan infrastruktur/tak tertangani yang layak
     * diulang oleh provider: provider_unavailable, provider_timeout,
     * internal_error, config_error. Sisanya 200.
     */
    public function httpStatus(): int
    {
        return match ($this) {
            self::ProviderUnavailable,
            self::ProviderTimeout,
            self::InternalError,
            self::ConfigError => 503,
            default => 200,
        };
    }

    public function isRetryable(): bool
    {
        return $this->httpStatus() === 503;
    }

    /**
     * Kata status pada envelope JSON: 200 => success, 503 => error.
     */
    public function statusWord(): string
    {
        return $this->httpStatus() === 200 ? 'success' : 'error';
    }
}
