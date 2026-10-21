<?php

namespace App\Payments\Status;

use App\Enums\GatewayResultToken;

/**
 * Matriks transisi internal status pembayaran.
 *
 * Status mapping dan precedence tidak digunakan untuk memutuskan transisi.
 * Validator ini adalah sumber kebenaran arah transisi dan syarat event.
 * `map()` menerima nama status internal dan, bila tersedia, konteks event
 * provider dalam `$isRecoveryAttempt` (lihat `validateWithEvidence()`).
 */
final class TransitionValidator
{
    /**
     * Validates a proposed internal status transition.
     *
     * The compact public contract is retained for callers that only have
     * current/proposed values. Event-specific requirements must use
     * validateWithEvidence() so a settlement/refund event cannot be inferred
     * from status alone.
     */
    public static function validate(string $current, string $proposed, bool $isRecoveryAttempt): TransitionResult
    {
        $from = self::status($current);
        $to = self::status($proposed);

        if ($from === null || $to === null) {
            return TransitionResult::stale('unknown_status', $current, $proposed);
        }

        if ($from === $to) {
            if ($from === InternalPaymentStatus::UnknownStatus) {
                // Note: unknown status harus selalu diverifikasi manual tidak boleh diidempotensi.
                return TransitionResult::stale('unknown_status', $from->value, $to->value);
            }

            // Note 1: idempotent replay is valid. Amount-increase replay is
            // represented by recoveryAttempt for this compact contract.
            return TransitionResult::allow(
                reason: $isRecoveryAttempt ? 'self_transition_amount_increase' : 'idempotent_replay',
                token: GatewayResultToken::DuplicateIgnored,
                isSelfTransition: true,
                isRecoveryAttempt: $isRecoveryAttempt,
                current: $from->value,
                proposed: $to->value,
            );
        }

        if ($from->isNonPaymentTerminal()) {
            // Note 2: failed/expired/canceled/denied can recover only with
            // provider settlement evidence.
            if ($isRecoveryAttempt && $to->isMoneyIn()) {
                return TransitionResult::allow(
                    reason: 'terminal_non_payment_recovered_with_settlement_evidence',
                    isRecoveryAttempt: true,
                    current: $from->value,
                    proposed: $to->value,
                );
            }

            return TransitionResult::stale('terminal_non_payment_requires_settlement_evidence', $from->value, $to->value);
        }

        return match (true) {
            // Note 3: pending -> settlement_pending only for capture + accept.
            $from === InternalPaymentStatus::Pending && $to === InternalPaymentStatus::SettlementPending
                => $isRecoveryAttempt
                    ? TransitionResult::allow('capture_accept', current: $from->value, proposed: $to->value)
                    : TransitionResult::stale('pending_to_settlement_pending_requires_capture_accept', $from->value, $to->value),

            // settlement_pending -> settled requires settlement event.
            // The compact contract uses recoveryAttempt as event evidence.
            $from === InternalPaymentStatus::SettlementPending && $to === InternalPaymentStatus::Settled
                => $isRecoveryAttempt
                    ? TransitionResult::allow('settlement_event', current: $from->value, proposed: $to->value)
                    : TransitionResult::stale('settlement_pending_to_settled_requires_settlement_event', $from->value, $to->value),

            // Note 5: settled -> refund states require refund events.
            $from === InternalPaymentStatus::Settled
                && in_array($to, [InternalPaymentStatus::PartiallyRefunded, InternalPaymentStatus::Refunded], true)
                => $isRecoveryAttempt
                    ? TransitionResult::allow('refund_event', current: $from->value, proposed: $to->value)
                    : TransitionResult::stale('refund_requires_refund_event', $from->value, $to->value),

            // Note 4: partial refund -> non-payment terminal is a refund
            // reversal followed by settlement reversal.
            $from === InternalPaymentStatus::PartiallyRefunded && $to->isNonPaymentTerminal()
                => TransitionResult::allow(
                    reason: 'refund_reversal_then_settlement_reversal',
                    sideEffects: ['refund_reversal', 'settlement_reversal'],
                    current: $from->value,
                    proposed: $to->value,
                ),

            // A full refund is terminal; chargeback is its own terminal event.
            $from === InternalPaymentStatus::Refunded || $from === InternalPaymentStatus::ChargedBack
                => TransitionResult::stale('terminal_status_cannot_transition', $from->value, $to->value),

            default => TransitionResult::stale('stale_transition', $from->value, $to->value),
        };
    }

    /**
     * Event-aware entry point. It avoids overloading the legacy bool with
     * meaning in new code while keeping the required public validate() API.
     */
    public static function validateWithEvidence(
        string $current,
        string $proposed,
        bool $isRecoveryAttempt,
        ?string $providerEvent = null,
        ?string $fraudStatus = null,
    ): TransitionResult {
        $result = self::validate($current, $proposed, $isRecoveryAttempt);
        if (! $result->allowed) {
            return $result;
        }

        $event = strtolower(trim((string) $providerEvent));
        $fraud = strtolower(trim((string) $fraudStatus));
        $from = self::status($current);
        $to = self::status($proposed);

        if ($from === InternalPaymentStatus::Pending && $to === InternalPaymentStatus::SettlementPending
            && ($event !== 'capture' || $fraud !== 'accept')) {
            return TransitionResult::stale('pending_to_settlement_pending_requires_capture_accept', $current, $proposed);
        }

        if ($from === InternalPaymentStatus::SettlementPending && $to === InternalPaymentStatus::Settled
            && $event !== 'settlement') {
            return TransitionResult::stale('settlement_pending_to_settled_requires_settlement_event', $current, $proposed);
        }

        if ($from === InternalPaymentStatus::Settled
            && in_array($to, [InternalPaymentStatus::PartiallyRefunded, InternalPaymentStatus::Refunded], true)
            && ! in_array($event, ['refund', 'partial_refund'], true)) {
            return TransitionResult::stale('refund_requires_refund_event', $current, $proposed);
        }

        return $result;
    }

    private static function status(string $value): ?InternalPaymentStatus
    {
        return InternalPaymentStatus::tryFrom(strtolower(trim($value)));
    }
}
