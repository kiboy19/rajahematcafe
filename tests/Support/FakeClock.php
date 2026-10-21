<?php

namespace Tests\Support;

use App\Support\Clock;

final class FakeClock implements Clock
{
    public function __construct(
        private int $currentMs = 1700000000000,
    ) {}

    public function nowMs(): int
    {
        return $this->currentMs;
    }

    public function advance(int $ms): void
    {
        $this->currentMs += $ms;
    }

    public function set(int $ms): void
    {
        $this->currentMs = $ms;
    }
}
