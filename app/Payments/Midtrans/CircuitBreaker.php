<?php

namespace App\Payments\Midtrans;

use App\Support\Clock;
use Illuminate\Support\Facades\DB;

/**
 * Circuit breaker backed solely by the gateway_circuit_state table.
 * All transitions use compare-and-set (CAS) updates with an integer version counter.
 * Time comparisons use the injected Clock (never Carbon::now()).
 */
final class CircuitBreaker
{
    public function __construct(
        private readonly Clock $clock,
        private readonly int $failureThreshold,
        private readonly int $windowSeconds,
        private readonly int $halfOpenProbes,
    ) {}

    public function recordSuccess(): bool
    {
        return DB::transaction(function () {
            $row = DB::table('gateway_circuit_state')->where('id', 1)->first();
            if (! $row) {
                return false;
            }

            if ($row->state === CircuitState::HalfOpen->value) {
                // Half-open success -> closed
                return $this->cas(
                    $row->version,
                    CircuitState::HalfOpen->value,
                    CircuitState::Closed->value,
                    0,
                    0,
                    0,
                    0,
                );
            }

            if ($row->state === CircuitState::Closed->value) {
                // Reset failure count on success in closed state
                return $this->cas(
                    $row->version,
                    CircuitState::Closed->value,
                    CircuitState::Closed->value,
                    0,
                    $row->window_started_at_ms,
                    0,
                    $row->opened_at_ms,
                );
            }

            return false;
        });
    }

    public function recordFailure(): bool
    {
        return DB::transaction(function () {
            $row = DB::table('gateway_circuit_state')->where('id', 1)->first();
            if (! $row) {
                return false;
            }

            if ($row->state === CircuitState::HalfOpen->value) {
                // Half-open failure -> open
                return $this->cas(
                    $row->version,
                    CircuitState::HalfOpen->value,
                    CircuitState::Open->value,
                    $row->failure_count + 1,
                    $row->window_started_at_ms,
                    0,
                    $this->clock->nowMs(),
                );
            }

            if ($row->state === CircuitState::Closed->value) {
                $newCount = $row->failure_count + 1;
                $newWindowStart = $row->window_started_at_ms === 0
                    ? $this->clock->nowMs()
                    : $row->window_started_at_ms;

                // Check if window expired
                if ($this->clock->nowMs() - $newWindowStart > $this->windowSeconds * 1000) {
                    $newCount = 1;
                    $newWindowStart = $this->clock->nowMs();
                }

                if ($newCount >= $this->failureThreshold) {
                    // Open the circuit
                    return $this->cas(
                        $row->version,
                        CircuitState::Closed->value,
                        CircuitState::Open->value,
                        $newCount,
                        $newWindowStart,
                        0,
                        $this->clock->nowMs(),
                    );
                }

                // Stay closed, increment count
                return $this->cas(
                    $row->version,
                    CircuitState::Closed->value,
                    CircuitState::Closed->value,
                    $newCount,
                    $newWindowStart,
                    0,
                    $row->opened_at_ms,
                );
            }

            return false;
        });
    }

    /**
     * Try to admit a probe in half-open state.
     * Returns true if admitted (caller should proceed with the request).
     * If the circuit is closed, always returns true.
     * If the circuit is open, returns false unless window expired (then transitions to half-open).
     */
    public function tryProbe(): bool
    {
        return DB::transaction(function () {
            $row = DB::table('gateway_circuit_state')->where('id', 1)->first();
            if (! $row) {
                return true;
            }

            $now = $this->clock->nowMs();

            return match ($row->state) {
                CircuitState::Closed->value => true,
                CircuitState::Open->value => $this->maybeTransitionToHalfOpen($row, $now),
                CircuitState::HalfOpen->value => $this->admitProbe($row, $now),
                default => false,
            };
        });
    }

    private function maybeTransitionToHalfOpen($row, int $now): bool
    {
        if ($row->opened_at_ms === 0 || $now - $row->opened_at_ms < $this->windowSeconds * 1000) {
            return false;
        }

        // Window expired, transition to half-open
        $cas = $this->cas(
            $row->version,
            CircuitState::Open->value,
            CircuitState::HalfOpen->value,
            0,
            0,
            1,
            0,
        );

        return $cas;
    }

    private function admitProbe($row, int $now): bool
    {
        if ($row->half_open_probes_in_flight >= $this->halfOpenProbes) {
            return false;
        }

        return $this->cas(
            $row->version,
            CircuitState::HalfOpen->value,
            CircuitState::HalfOpen->value,
            $row->failure_count,
            $row->window_started_at_ms,
            $row->half_open_probes_in_flight + 1,
            $row->opened_at_ms,
        );
    }

    public function releaseProbe(bool $success): void
    {
        DB::transaction(function () use ($success) {
            $row = DB::table('gateway_circuit_state')->where('id', 1)->first();
            if (! $row || $row->state !== CircuitState::HalfOpen->value) {
                return;
            }

            $newProbes = max(0, $row->half_open_probes_in_flight - 1);

            $this->cas(
                $row->version,
                CircuitState::HalfOpen->value,
                $success ? CircuitState::Closed->value : CircuitState::HalfOpen->value,
                $success ? 0 : $row->failure_count,
                $success ? 0 : $row->window_started_at_ms,
                $newProbes,
                $success ? 0 : $row->opened_at_ms,
            );
        });
    }

    public function currentState(): CircuitState
    {
        $row = DB::table('gateway_circuit_state')->where('id', 1)->first();
        if (! $row) {
            return CircuitState::Closed;
        }

        return CircuitState::from($row->state);
    }

    /**
     * Compare-and-set update for circuit state.
     * Returns true if the update was applied.
     */
    private function cas(
        int $expectedVersion,
        string $expectedState,
        string $newState,
        int $newFailureCount,
        int $newWindowStartMs,
        int $newProbes,
        int $newOpenedAtMs,
    ): bool {
        $updated = DB::table('gateway_circuit_state')
            ->where('id', 1)
            ->where('version', $expectedVersion)
            ->where('state', $expectedState)
            ->update([
                'state' => $newState,
                'failure_count' => $newFailureCount,
                'window_started_at_ms' => $newWindowStartMs,
                'half_open_probes_in_flight' => $newProbes,
                'opened_at_ms' => $newOpenedAtMs,
                'version' => $expectedVersion + 1,
            ]);

        return $updated > 0;
    }
}
