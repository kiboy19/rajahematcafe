<?php

namespace App\Console\Commands;

use App\Enums\GatewayResultToken;
use App\Payments\Notification\PayloadCipher;
use App\Payments\Pipelines\ObservationPipeline;
use App\Support\Clock;
use DateTimeImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class ReplayGatewayNotificationBufferCommand extends Command
{
    protected $signature = 'midtrans:buffer-replay {--limit=100}';

    protected $description = 'Replay buffered Midtrans notifications after transaction creation';

    public function handle(ObservationPipeline $pipeline, Clock $clock): int
    {
        $now = new DateTimeImmutable('@'.intdiv($clock->nowMs(), 1000));
        $timeout = (int) Config::get('pos.midtrans.buffer_lock_timeout_seconds', 300);
        $rows = DB::table('gateway_notification_buffer')
            ->where('status', 'pending')
            ->where(function ($query) use ($now, $timeout): void {
                $query->whereNull('locked_at')->orWhere('locked_at', '<', $now->modify("-{$timeout} seconds"));
            })
            ->orderByRaw('provider_event_time IS NULL, provider_event_time ASC')
            ->orderByDesc('precedence')
            ->orderBy('id')
            ->limit(max(1, (int) $this->option('limit')))
            ->get();

        $cipher = new PayloadCipher((string) Config::get('pos.midtrans.payload_key'));
        foreach ($rows as $row) {
            $token = Str::uuid()->toString();
            $claimed = DB::table('gateway_notification_buffer')
                ->where('id', $row->id)
                ->where('status', 'pending')
                ->where(function ($query) use ($now, $timeout): void {
                    $query->whereNull('locked_at')->orWhere('locked_at', '<', $now->modify("-{$timeout} seconds"));
                })
                ->update(['status' => 'processing', 'lock_token' => $token, 'locked_at' => $now, 'updated_at' => $now]);
            if ($claimed !== 1) {
                continue;
            }

            try {
                $outcome = $pipeline->observe($cipher->decrypt((string) $row->encrypted_payload), 'application/json');
                $status = $outcome->token === GatewayResultToken::Processed ? 'processed' : 'failed';
                $updatedAt = new DateTimeImmutable('@'.intdiv($clock->nowMs(), 1000));
                DB::table('gateway_notification_buffer')->where('id', $row->id)->where('lock_token', $token)->update([
                    'status' => $status,
                    'retry_count' => DB::raw('retry_count + 1'),
                    'updated_at' => $updatedAt,
                ]);
            } catch (Throwable) {
                $updatedAt = new DateTimeImmutable('@'.intdiv($clock->nowMs(), 1000));
                DB::table('gateway_notification_buffer')->where('id', $row->id)->where('lock_token', $token)->update([
                    'status' => 'failed',
                    'retry_count' => DB::raw('retry_count + 1'),
                    'updated_at' => $updatedAt,
                ]);
            }
        }

        return self::SUCCESS;
    }
}
