<?php

namespace App\Support;

/**
 * Abstraksi jam agar logika timeout webhook dapat diuji tanpa sleep.
 * Semua waktu diukur dalam milidetik epoch (UTC).
 */
interface Clock
{
    public function nowMs(): int;
}
