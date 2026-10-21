<?php

namespace App\Payments\Support;

use Closure;

final class SettlementBarrier
{
    private ?Closure $barrier = null;

    public function set(callable $barrier): void
    {
        $this->barrier = Closure::fromCallable($barrier);
    }

    public function clear(): void
    {
        $this->barrier = null;
    }

    public function run(): void
    {
        ($this->barrier)?->__invoke();
    }
}
