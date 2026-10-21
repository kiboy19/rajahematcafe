<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/**
 * Job yang memindahkan baris outbox ke audit_events (relay).
 * Idempotent via UNIQUE provider_event_key pada audit_events.
 * Dijalankan di default queue/connection aplikasi.
 */
final class FlushAuditOutbox implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 60, 300];

    public function handle(): void
    {
        $driver = DB::getDriverName();

        DB::transaction(function () use ($driver): void {
            $query = DB::table('gateway_audit_outbox')
                ->whereNull('relayed_at')
                ->orderBy('id')
                ->limit(500);

            // SQLite doesn't support FOR UPDATE; use it only for MySQL/PostgreSQL
            if ($driver === 'mysql' || $driver === 'pgsql') {
                $query->lockForUpdate();
            }

            $rows = $query->get();

            foreach ($rows as $row) {
                $summary = is_string($row->summary)
                    ? json_decode($row->summary, true)
                    : $row->summary;

                DB::table('gateway_audit_events')->insertOrIgnore([
                    'provider_event_key' => $row->provider_event_key,
                    'provider_event_time' => $row->created_at,
                    'event_type' => $summary['event_type'] ?? 'webhook',
                    'event_source' => $summary['event_source'] ?? 'midtrans',
                    'result_token' => $row->result_token,
                    'payload_digest' => $row->payload_digest,
                    'summary' => $row->summary,
                    'created_at' => $row->created_at,
                    'updated_at' => now(),
                ]);
            }

            if ($rows->isNotEmpty()) {
                DB::table('gateway_audit_outbox')
                    ->whereIn('id', $rows->pluck('id')->all())
                    ->update(['relayed_at' => now()]);
            }
        });
    }
}
