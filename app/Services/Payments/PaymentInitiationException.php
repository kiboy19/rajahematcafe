<?php

namespace App\Services\Payments;

use RuntimeException;

/**
 * Kegagalan inisiasi pembayaran yang sudah dipetakan ke kontrak HTTP
 * (R8.4/R8.5): `error` + `message` + status.
 */
final class PaymentInitiationException extends RuntimeException
{
    private function __construct(
        public readonly string $error,
        public readonly int $status,
        string $message,
    ) {
        parent::__construct($message);
    }

    /** 503 — fail-closed / config tidak valid / infrastruktur. */
    public static function internalError(): self
    {
        return new self('internal_error', 503, 'Payment gateway tidak tersedia.');
    }

    /** 503 — provider gagal/timeout/menolak. */
    public static function providerUnavailable(): self
    {
        return new self('provider_unavailable', 503, 'Payment provider tidak tersedia.');
    }

    /** 422 — entitas tidak payable. */
    public static function unprocessable(string $reason): self
    {
        return new self($reason, 422, 'Entitas ini tidak dapat dibayar saat ini.');
    }

    /** 409 — inisiasi sebelumnya belum menghasilkan token. */
    public static function conflict(): self
    {
        return new self('duplicate_ignored', 409, 'Pembayaran untuk entitas ini sudah dibuat tanpa token Snap.');
    }
}
