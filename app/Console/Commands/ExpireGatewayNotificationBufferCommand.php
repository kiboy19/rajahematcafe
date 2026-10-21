<?php

namespace App\Console\Commands;

use App\Support\Clock;
use DateTimeImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class ExpireGatewayNotificationBufferCommand extends Command
{
    protected $signature = 'midtrans:buffer-expire';

    protected $description = 'Expire old buffered Midtrans notifications without deleting them';

    public function handle(Clock $clock): int
    {
        $now = new DateTimeImmutable('@'.intdiv($clock->nowMs(), 1000));
        $ttlMinutes = (int) Config::get('pos.midtrans.buffer_ttl_minutes', 1440);
        $cutoff = $now->modify("-{$ttlMinutes} minutes");

        $updated = DB::table('gateway_notification_buffer')
            ->where('status', 'pending')
            ->where('created_at', '<', $cutoff)
            ->update([
                'status' => 'expired',
                'updated_at' => $now,
            ]);

        $this->info("Expired {$updated} buffered notification(s).");

        return self::SUCCESS;
    }
}
