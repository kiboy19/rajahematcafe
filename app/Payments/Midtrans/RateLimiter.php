<?php

namespace App\Payments\Midtrans;

use App\Support\Clock;
use Illuminate\Support\Facades\DB;

final class RateLimiter
{
    public function __construct(
        private readonly Clock $clock,
    ) {}

    public function increment(string $bucketKey): int
    {
        $windowStart = intdiv($this->clock->nowMs(), 60_000);

        DB::statement(
            'INSERT INTO gateway_rate_windows (bucket_key, window_start, count) VALUES (?, ?, 1) ON CONFLICT(bucket_key, window_start) DO UPDATE SET count = count + 1',
            [$bucketKey, $windowStart],
        );

        $this->prune($windowStart - 1);

        return (int) DB::table('gateway_rate_windows')
            ->where('bucket_key', $bucketKey)
            ->where('window_start', $windowStart)
            ->value('count');
    }

    public function prune(int $cutoffWindow): int
    {
        return DB::table('gateway_rate_windows')
            ->where('window_start', '<', $cutoffWindow)
            ->delete();
    }
}
