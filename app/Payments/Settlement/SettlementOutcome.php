<?php

namespace App\Payments\Settlement;

use App\Payments\Support\Clock;
use App\Payments\Support\SettlementResult;

final class SettlementOutcome
{
    public function __construct(
        public readonly SettlementResult $result,
        public readonly string $newSettledAmount,
        public readonly ?string $expectedBefore,
        public readonly int $attempts,
    ) {
    }

    public function isSuccess(): bool
    {
        return $this->result->isSuccess();
    }
}