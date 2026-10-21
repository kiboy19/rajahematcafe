<?php

namespace App\Payments\Status;

use App\Enums\GatewayResultToken;

/**
 * Hasil evaluasi transisi status pembayaran oleh TransitionValidator.
 *
 * Memuat:
 * - allowed: apakah usulan transisi diizinkan dieksekusi
 * - isStale: apakah transisi ditolak karena stale (out-of-order)
 * - isSelfTransition: apakah proposed == current
 * - isRecoveryAttempt: apakah ini percobaan pemulihan dari status terminal non-payment
 * - sideEffects: daftar efek samping yang wajib dipicu (misal: refund_reversal, settlement_reversal)
 * - reason: penjelasan singkat alasan penerimaan / penolakan
 * - token: GatewayResultToken kanonis yang sesuai (Processed, DuplicateIgnored, StaleTransition, dll.)
 */
final readonly class TransitionResult
{
    /**
     * @param  array<string>  $sideEffects
     */
    public function __construct(
        public bool $allowed,
        public bool $isStale,
        public bool $isSelfTransition,
        public bool $isRecoveryAttempt,
        public array $sideEffects,
        public string $reason,
        public GatewayResultToken $token,
        public ?string $currentStatus = null,
        public ?string $proposedStatus = null,
    ) {}

    public static function allow(
        string $reason = 'transition_allowed',
        GatewayResultToken $token = GatewayResultToken::Processed,
        array $sideEffects = [],
        bool $isSelfTransition = false,
        bool $isRecoveryAttempt = false,
        ?string $current = null,
        ?string $proposed = null,
    ): self {
        return new self(
            allowed: true,
            isStale: false,
            isSelfTransition: $isSelfTransition,
            isRecoveryAttempt: $isRecoveryAttempt,
            sideEffects: $sideEffects,
            reason: $reason,
            token: $token,
            currentStatus: $current,
            proposedStatus: $proposed,
        );
    }

    public static function stale(
        string $reason = 'stale_transition',
        ?string $current = null,
        ?string $proposed = null,
    ): self {
        return new self(
            allowed: false,
            isStale: true,
            isSelfTransition: false,
            isRecoveryAttempt: false,
            sideEffects: [],
            reason: $reason,
            token: GatewayResultToken::StaleTransition,
            currentStatus: $current,
            proposedStatus: $proposed,
        );
    }

    public static function reject(
        string $reason,
        GatewayResultToken $token = GatewayResultToken::Rejected,
        ?string $current = null,
        ?string $proposed = null,
    ): self {
        return new self(
            allowed: false,
            isStale: false,
            isSelfTransition: false,
            isRecoveryAttempt: false,
            sideEffects: [],
            reason: $reason,
            token: $token,
            currentStatus: $current,
            proposedStatus: $proposed,
        );
    }
}
