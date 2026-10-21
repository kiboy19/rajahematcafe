<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReplayGatewayNotificationBufferCommand extends Command
{
    protected $signature = 'midtrans:buffer-replay {--limit=100}';

    protected $description = 'Replay buffered Midtrans notifications after transaction creation';

    public function handle(): int
    {
        $rows = DB::table('gateway_notification_buffer')
            ->where('status', 'pending')
            ->orderBy('id')
            ->limit((int) $this->option('limit'))
            ->get();

        $this->info('Buffered notifications ready for replay: '.$rows->count());

        return self::SUCCESS;
    }
}