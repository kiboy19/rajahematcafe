<?php

namespace Tests\Unit\Payments;

use App\Payments\Status\InternalPaymentStatus;
use App\Payments\Status\ProviderStatusMapper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ProviderStatusMapperTest extends TestCase
{
    /**
     * Full expected matrix: transaction_status => fraud_status => internal status.
     *
     * @return array<string, array<string|null, InternalPaymentStatus>>
     */
    public static function expectedMatrix(): array
    {
        return [
            'capture' => [
                'accept' => InternalPaymentStatus::SettlementPending,
                'challenge' => InternalPaymentStatus::Pending,
                'deny' => InternalPaymentStatus::Denied,
                null => InternalPaymentStatus::UnknownStatus,
            ],
            'settlement' => [null => InternalPaymentStatus::Settled],
            'pending' => [null => InternalPaymentStatus::Pending],
            'deny' => [null => InternalPaymentStatus::Denied],
            'cancel' => [null => InternalPaymentStatus::Canceled],
            'expire' => [null => InternalPaymentStatus::Expired],
            'refund' => [null => InternalPaymentStatus::Refunded],
            'partial_refund' => [null => InternalPaymentStatus::PartiallyRefunded],
            'chargeback' => [null => InternalPaymentStatus::ChargedBack],
        ];
    }

    /**
     * Every known transaction_status × fraud_status pair, including absent/null.
     */
    public static function mappingProvider(): array
    {
        $rows = [];
        foreach (self::expectedMatrix() as $transactionStatus => $fraudCases) {
            foreach ($fraudCases as $fraudStatus => $expected) {
                $label = $transactionStatus.'/'.($fraudStatus ?? 'null');
                $rows[$label] = [$transactionStatus, $fraudStatus, $expected];
            }
        }

        return $rows;
    }

    #[DataProvider('mappingProvider')]
    public function test_every_transaction_and_fraud_status_maps_to_expected_internal_status(
        string $transactionStatus,
        ?string $fraudStatus,
        InternalPaymentStatus $expected,
    ): void {
        $mapping = ProviderStatusMapper::map($transactionStatus, $fraudStatus);

        $this->assertSame($expected, $mapping->status, "{$transactionStatus}/{$fraudStatus}");
        $this->assertSame($expected->precedenceRank(), $mapping->precedenceRank);
        $this->assertSame($transactionStatus, $mapping->transactionStatus);
        $this->assertSame($fraudStatus, $mapping->fraudStatus);
    }

    public function test_unknown_transaction_status_defaults_to_unknown_status_rank_zero(): void
    {
        $mapping = ProviderStatusMapper::map('mystery_status', null);

        $this->assertSame(InternalPaymentStatus::UnknownStatus, $mapping->status);
        $this->assertFalse($mapping->isKnown());
        $this->assertTrue($mapping->requiresManualReview());
        $this->assertSame(0, $mapping->precedenceRank);
        $this->assertFalse($mapping->isMoneyIn());
    }

    public function test_unknown_fraud_status_for_capture_falls_back_to_unknown(): void
    {
        $mapping = ProviderStatusMapper::map('capture', 'bogus');

        $this->assertSame(InternalPaymentStatus::UnknownStatus, $mapping->status);
        $this->assertSame(0, $mapping->precedenceRank);
    }

    public function test_mapping_is_case_insensitive_and_trims(): void
    {
        $this->assertSame(
            InternalPaymentStatus::Settled,
            ProviderStatusMapper::map('  SETTLEMENT ', null)->status,
        );
        $this->assertSame(
            InternalPaymentStatus::SettlementPending,
            ProviderStatusMapper::map('Capture', 'ACCEPT')->status,
        );
    }

    public function test_absent_and_empty_fraud_status_are_equivalent_to_null(): void
    {
        $this->assertSame(InternalPaymentStatus::Settled, ProviderStatusMapper::map('settlement', null)->status);
        $this->assertSame(InternalPaymentStatus::Settled, ProviderStatusMapper::map('settlement', '')->status);
    }

    public function test_money_in_flags(): void
    {
        $this->assertTrue(ProviderStatusMapper::map('settlement', null)->isMoneyIn());
        $this->assertTrue(ProviderStatusMapper::map('partial_refund', null)->isMoneyIn());
        $this->assertFalse(ProviderStatusMapper::map('capture', 'accept')->isMoneyIn());
        $this->assertFalse(ProviderStatusMapper::map('pending', null)->isMoneyIn());
        $this->assertFalse(ProviderStatusMapper::map('expire', null)->isMoneyIn());
    }

    public function test_refund_and_reversal_event_flags(): void
    {
        $this->assertTrue(ProviderStatusMapper::map('refund', null)->isRefundEvent());
        $this->assertTrue(ProviderStatusMapper::map('partial_refund', null)->isRefundEvent());
        $this->assertFalse(ProviderStatusMapper::map('refund', null)->isReversalEvent());
        $this->assertTrue(ProviderStatusMapper::map('chargeback', null)->isReversalEvent());
        $this->assertFalse(ProviderStatusMapper::map('settlement', null)->isRefundEvent());
        $this->assertFalse(ProviderStatusMapper::map('settlement', null)->isReversalEvent());
    }

    public function test_precedence_ranks_are_unique_and_bounded(): void
    {
        $ranks = [];
        foreach (InternalPaymentStatus::cases() as $status) {
            $rank = $status->precedenceRank();
            $this->assertGreaterThanOrEqual(0, $rank);
            $this->assertLessThanOrEqual(10, $rank);

            if ($status === InternalPaymentStatus::UnknownStatus) {
                $this->assertSame(0, $rank);

                continue;
            }

            $this->assertArrayNotHasKey($rank, $ranks, 'Duplicate precedence rank '.$rank);
            $ranks[$rank] = $status;
        }

        ksort($ranks);
        $this->assertSame(range(1, 10), array_keys($ranks));
    }

    public function test_precedence_order_is_stable_for_buffer_and_migration(): void
    {
        $ordered = InternalPaymentStatus::cases();
        usort($ordered, fn (InternalPaymentStatus $a, InternalPaymentStatus $b) => $a->precedenceRank() <=> $b->precedenceRank());

        $this->assertSame([
            InternalPaymentStatus::UnknownStatus,
            InternalPaymentStatus::Pending,
            InternalPaymentStatus::SettlementPending,
            InternalPaymentStatus::Settled,
            InternalPaymentStatus::PartiallyRefunded,
            InternalPaymentStatus::Failed,
            InternalPaymentStatus::Expired,
            InternalPaymentStatus::Canceled,
            InternalPaymentStatus::Denied,
            InternalPaymentStatus::Refunded,
            InternalPaymentStatus::ChargedBack,
        ], $ordered);
    }

    public function test_known_mappings_cover_every_declared_transaction_status(): void
    {
        $this->assertSame(
            ['capture', 'settlement', 'pending', 'deny', 'cancel', 'expire', 'refund', 'partial_refund', 'chargeback'],
            ProviderStatusMapper::knownTransactionStatuses(),
        );
        $this->assertSame(['accept', 'challenge', 'deny'], ProviderStatusMapper::knownFraudStatusesFor('capture'));
        $this->assertSame([], ProviderStatusMapper::knownFraudStatusesFor('settlement'));
    }

    public function test_derived_status_predicates(): void
    {
        $this->assertTrue(InternalPaymentStatus::Failed->isNonPaymentTerminal());
        $this->assertTrue(InternalPaymentStatus::Expired->isNonPaymentTerminal());
        $this->assertTrue(InternalPaymentStatus::Canceled->isNonPaymentTerminal());
        $this->assertTrue(InternalPaymentStatus::Denied->isNonPaymentTerminal());
        $this->assertFalse(InternalPaymentStatus::Settled->isNonPaymentTerminal());
        $this->assertTrue(InternalPaymentStatus::Refunded->isTerminal());
        $this->assertTrue(InternalPaymentStatus::ChargedBack->isTerminal());
        $this->assertFalse(InternalPaymentStatus::PartiallyRefunded->isTerminal());
        $this->assertTrue(InternalPaymentStatus::Settled->isMoneyIn());
        $this->assertFalse(InternalPaymentStatus::Pending->isMoneyIn());
    }
}
