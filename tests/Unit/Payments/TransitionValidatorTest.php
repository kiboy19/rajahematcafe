<?php

namespace Tests\Unit\Payments;

use App\Enums\GatewayResultToken;
use App\Payments\Status\InternalPaymentStatus;
use App\Payments\Status\TransitionValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TransitionValidatorTest extends TestCase
{
    /**
     * All 11x11 = 121 pairs of From/To statuses.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function allStatusPairsProvider(): array
    {
        $cases = InternalPaymentStatus::cases();
        $pairs = [];

        foreach ($cases as $from) {
            foreach ($cases as $to) {
                $label = "{$from->value} -> {$to->value}";
                $pairs[$label] = [$from->value, $to->value];
            }
        }

        return $pairs;
    }

    #[DataProvider('allStatusPairsProvider')]
    public function test_every_pair_without_recovery_fails_unless_explicitly_allowed(string $from, string $to): void
    {
        $result = TransitionValidator::validate($from, $to, isRecoveryAttempt: false);

        if ($from === $to && $from !== 'unknown_status') {
            // Note 1: idempotent replay is allowed.
            $this->assertTrue($result->allowed);
            $this->assertSame(GatewayResultToken::DuplicateIgnored, $result->token);
            $this->assertTrue($result->isSelfTransition);

            return;
        }

        if ($from === 'partially_refunded' && in_array($to, ['failed', 'expired', 'canceled', 'denied'], true)) {
            // Note 4: refund reversal then settlement reversal.
            $this->assertTrue($result->allowed);
            $this->assertSame(['refund_reversal', 'settlement_reversal'], $result->sideEffects);

            return;
        }

        // All other pairs without recovery/events must be rejected as stale.
        $this->assertFalse($result->allowed, "Expected {$from} -> {$to} without recovery to be disallowed");
        $this->assertTrue($result->isStale, "Expected {$from} -> {$to} to be marked stale");
        $this->assertSame(GatewayResultToken::StaleTransition, $result->token);
    }

    /**
     * Note 1: self-transition behavior.
     */
    public function test_note_1_self_transition_on_idempotent_replay_and_amount_increase(): void
    {
        // Idempotent replay
        $replay = TransitionValidator::validate('settled', 'settled', isRecoveryAttempt: false);
        $this->assertTrue($replay->allowed);
        $this->assertTrue($replay->isSelfTransition);
        $this->assertFalse($replay->isRecoveryAttempt);
        $this->assertSame('idempotent_replay', $replay->reason);
        $this->assertSame(GatewayResultToken::DuplicateIgnored, $replay->token);

        // Amount increase
        $amountInc = TransitionValidator::validate('settled', 'settled', isRecoveryAttempt: true);
        $this->assertTrue($amountInc->allowed);
        $this->assertTrue($amountInc->isSelfTransition);
        $this->assertTrue($amountInc->isRecoveryAttempt);
        $this->assertSame('self_transition_amount_increase', $amountInc->reason);
        $this->assertSame(GatewayResultToken::DuplicateIgnored, $amountInc->token);
    }

    /**
     * Note 2: terminal non-payment recovery.
     */
    public function test_note_2_terminal_non_payment_recovery_only_with_provider_settlement_evidence(): void
    {
        $terminalNonPayments = ['failed', 'expired', 'canceled', 'denied'];
        $moneyInStatuses = ['settled', 'partially_refunded', 'refunded', 'charged_back'];

        foreach ($terminalNonPayments as $terminal) {
            foreach ($moneyInStatuses as $moneyIn) {
                // Without recovery attempt, must be rejected as stale.
                $noEvidence = TransitionValidator::validate($terminal, $moneyIn, isRecoveryAttempt: false);
                $this->assertFalse($noEvidence->allowed);
                $this->assertTrue($noEvidence->isStale);
                $this->assertSame(GatewayResultToken::StaleTransition, $noEvidence->token);

                // With recovery attempt, must be allowed.
                $withEvidence = TransitionValidator::validate($terminal, $moneyIn, isRecoveryAttempt: true);
                $this->assertTrue($withEvidence->allowed, "Failed for {$terminal} -> {$moneyIn}");
                $this->assertTrue($withEvidence->isRecoveryAttempt);
                $this->assertSame(
                    'terminal_non_payment_recovered_with_settlement_evidence',
                    $withEvidence->reason,
                );
            }

            // Cannot recover into a non-money-in status (e.g. pending).
            $invalidTarget = TransitionValidator::validate($terminal, 'pending', isRecoveryAttempt: true);
            $this->assertFalse($invalidTarget->allowed);
            $this->assertTrue($invalidTarget->isStale);
        }
    }

    /**
     * Note 3: pending -> settlement_pending.
     */
    public function test_note_3_pending_to_settlement_pending_only_on_capture_accept(): void
    {
        // Compact API: without recovery/evidence flag it is rejected.
        $compactNoEvidence = TransitionValidator::validate('pending', 'settlement_pending', isRecoveryAttempt: false);
        $this->assertFalse($compactNoEvidence->allowed);
        $this->assertTrue($compactNoEvidence->isStale);

        $compactAllowed = TransitionValidator::validate('pending', 'settlement_pending', isRecoveryAttempt: true);
        $this->assertTrue($compactAllowed->allowed);
        $this->assertSame('capture_accept', $compactAllowed->reason);

        // Evidence-aware API: requires capture + accept specifically.
        $validEvidence = TransitionValidator::validateWithEvidence(
            'pending',
            'settlement_pending',
            isRecoveryAttempt: true,
            providerEvent: 'capture',
            fraudStatus: 'accept',
        );
        $this->assertTrue($validEvidence->allowed);

        // Bad fraud status (challenge/deny/null) rejected as stale.
        foreach (['challenge', 'deny', null, ''] as $badFraud) {
            $invalid = TransitionValidator::validateWithEvidence(
                'pending',
                'settlement_pending',
                isRecoveryAttempt: true,
                providerEvent: 'capture',
                fraudStatus: $badFraud,
            );
            $this->assertFalse($invalid->allowed);
            $this->assertTrue($invalid->isStale);
        }

        // Wrong event (e.g. settlement) rejected for this specific edge.
        $wrongEvent = TransitionValidator::validateWithEvidence(
            'pending',
            'settlement_pending',
            isRecoveryAttempt: true,
            providerEvent: 'settlement',
            fraudStatus: 'accept',
        );
        $this->assertFalse($wrongEvent->allowed);
    }

    /**
     * Note 3 part b: settlement_pending -> settled only on settlement event.
     */
    public function test_settlement_pending_to_settled_only_on_settlement_event(): void
    {
        $compactNo = TransitionValidator::validate('settlement_pending', 'settled', isRecoveryAttempt: false);
        $this->assertFalse($compactNo->allowed);
        $this->assertTrue($compactNo->isStale);

        $compactYes = TransitionValidator::validate('settlement_pending', 'settled', isRecoveryAttempt: true);
        $this->assertTrue($compactYes->allowed);
        $this->assertSame('settlement_event', $compactYes->reason);

        $withEvidence = TransitionValidator::validateWithEvidence(
            'settlement_pending',
            'settled',
            isRecoveryAttempt: true,
            providerEvent: 'settlement',
        );
        $this->assertTrue($withEvidence->allowed);

        // Wrong provider event fails.
        $wrong = TransitionValidator::validateWithEvidence(
            'settlement_pending',
            'settled',
            isRecoveryAttempt: true,
            providerEvent: 'capture',
        );
        $this->assertFalse($wrong->allowed);
        $this->assertTrue($wrong->isStale);
    }

    /**
     * Note 5: settled -> partially_refunded / refunded only on refund events.
     */
    public function test_note_5_settled_to_refund_states_only_on_refund_events(): void
    {
        foreach (['partially_refunded', 'refunded'] as $target) {
            $compactNo = TransitionValidator::validate('settled', $target, isRecoveryAttempt: false);
            $this->assertFalse($compactNo->allowed);
            $this->assertTrue($compactNo->isStale);

            $compactYes = TransitionValidator::validate('settled', $target, isRecoveryAttempt: true);
            $this->assertTrue($compactYes->allowed);
            $this->assertSame('refund_event', $compactYes->reason);

            foreach (['refund', 'partial_refund'] as $event) {
                $withEvent = TransitionValidator::validateWithEvidence(
                    'settled',
                    $target,
                    isRecoveryAttempt: true,
                    providerEvent: $event,
                );
                $this->assertTrue($withEvent->allowed, "Settled -> {$target} with event {$event}");
            }

            // Wrong event rejected.
            $badEvent = TransitionValidator::validateWithEvidence(
                'settled',
                $target,
                isRecoveryAttempt: true,
                providerEvent: 'settlement',
            );
            $this->assertFalse($badEvent->allowed);
            $this->assertTrue($badEvent->isStale);
        }
    }

    /**
     * Note 4: partially_refunded -> failed/expired/canceled/denied
     * triggers refund reversal then settlement reversal.
     */
    public function test_note_4_partially_refunded_to_non_payment_terminal_triggers_reversals(): void
    {
        $nonPaymentTerminals = ['failed', 'expired', 'canceled', 'denied'];

        foreach ($nonPaymentTerminals as $target) {
            $result = TransitionValidator::validate('partially_refunded', $target, isRecoveryAttempt: false);

            $this->assertTrue($result->allowed, "Expected partially_refunded -> {$target} to be allowed");
            $this->assertSame('refund_reversal_then_settlement_reversal', $result->reason);
            $this->assertSame(['refund_reversal', 'settlement_reversal'], $result->sideEffects);
            $this->assertSame(GatewayResultToken::Processed, $result->token);
        }
    }

    /**
     * Note 6: fully refunded or charged-back transactions are strictly terminal.
     */
    public function test_note_6_terminal_refunded_and_charged_back_cannot_transition(): void
    {
        $targets = array_map(fn (InternalPaymentStatus $s) => $s->value, InternalPaymentStatus::cases());

        foreach (['refunded', 'charged_back'] as $terminal) {
            foreach ($targets as $target) {
                if ($target === $terminal) {
                    continue; // Self-transition tested separately.
                }

                $attempt = TransitionValidator::validate($terminal, $target, isRecoveryAttempt: true);
                $this->assertFalse($attempt->allowed);
                $this->assertTrue($attempt->isStale);
                $this->assertSame(GatewayResultToken::StaleTransition, $attempt->token);
            }
        }
    }

    public function test_unknown_status_inputs_are_rejected(): void
    {
        $res = TransitionValidator::validate('nonsense', 'settled', false);
        $this->assertFalse($res->allowed);
        $this->assertTrue($res->isStale);
        $this->assertSame('unknown_status', $res->reason);

        $res2 = TransitionValidator::validate('settled', 'nonsense', false);
        $this->assertFalse($res2->allowed);
        $this->assertTrue($res2->isStale);
    }
}
