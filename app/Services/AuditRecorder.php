<?php

namespace App\Services;

use App\Jobs\FlushAuditOutbox;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Transactional audit claim recorder. A claim is deliberately written to the
 * outbox on the default application connection, so business state and its
 * audit intent commit or roll back together.
 */
class AuditRecorder
{
    /**
     * Claim a provider event exactly once. Duplicate claims are absorbed and
     * return false; callers must not treat them as infrastructure failures.
     * This method does not open a transaction: it participates in the caller's
     * current transaction.
     *
     * @param  array<string, mixed>|null  $summary
     */
    public function claim(
        string $providerEventKey,
        string $token,
        string $digest,
        ?array $summary = null,
    ): bool {
        try {
            DB::table('gateway_audit_outbox')->insert([
                'provider_event_key' => $providerEventKey,
                'result_token' => $token,
                'payload_digest' => $digest,
                'summary' => $summary === null ? null : json_encode($summary, JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'relayed_at' => null,
            ]);
        } catch (UniqueConstraintViolationException) {
            return false;
        }

        DB::afterCommit(function (): void {
            try {
                FlushAuditOutbox::dispatch();
            } catch (Throwable) {
                // Scheduler is the durability backstop; queue availability is
                // an optimization and must never change a committed outcome.
            }
        });

        return true;
    }
}
