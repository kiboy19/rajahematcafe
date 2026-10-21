<?php

namespace App\Support;

final class SystemClock implements Clock
{
    public function nowMs(): int
    {
        return (int) floor(microtime(true) * 1000);
    }
}
