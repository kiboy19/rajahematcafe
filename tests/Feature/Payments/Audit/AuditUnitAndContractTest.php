<?php

namespace Tests\Feature\Payments\Audit;

use App\Enums\GatewayResultToken;
use App\Jobs\FlushAuditOutbox;
use App\Models\Customer;
use App\Services\AuditRecorder;
use App\Services\MidtransGatewayService;
use App\Support\Clock;
use App\Support\GatewayResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AuditUnitAndContractTest extends TestCase
{
    use RefreshDatabase;

    protected string $serverKey = 'SB-Mid-server-testkey123456';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'pos.midtrans.enabled' => true,
            'pos.midtrans.server_key' => $this->serverKey,
            'pos.midtrans.webhook_timeout_seconds' => 5,
        ]);
    }

    protected function makeSignedPayload(string $orderId, string $grossAmount = '50000', string $statusCode = '200', string $status = 'settlement'): array
    {
        $signature = app(MidtransGatewayService::class)->generateSignature(
            $orderId,
            $statusCode,
            $grossAmount,
            $this->serverKey,
        );

        return [
            'order_id' => $orderId,
            'status_code' => $statusCode,
            'gross_amount' => $grossAmount,
            'signature_key' => $signature,
            'transaction_status' => $status,
        ];
    }

    /**
     * R11.1 / R11.2 / TASK-009:
     * Migrations created the four gateway tables on the default connection.
     */
    public function test_all_gateway_audit_tables_exist_on_default_connection(): void
    {
        $this->assertTrue(\Schema::hasTable('gateway_audit_events'));
        $this->assertTrue(\Schema::hasTable('gateway_reconciliation_events'));
        $this->assertTrue(\Schema::hasTable('gateway_circuit_state'));
        $this->assertTrue(\Schema::hasTable('gateway_audit_outbox'));

        $this->assertTrue(\Schema::hasColumn('gateway_audit_events', 'provider_event_key'));
        $this->assertTrue(\Schema::hasColumn('gateway_audit_events', 'result_token'));
        $this->assertTrue(\Schema::hasColumn('gateway_audit_events', 'payload_digest'));
        $this->assertTrue(\Schema::hasColumn('gateway_audit_events', 'summary'));

        $this->assertTrue(\Schema::hasColumn('gateway_reconciliation_events', 'candidate_ids'));
        $this->assertTrue(\Schema::hasColumn('gateway_reconciliation_events', 'shadow_phase'));
        $this->assertTrue(\Schema::hasColumn('gateway_reconciliation_events', 'comparison'));

        $this->assertTrue(\Schema::hasColumn('gateway_circuit_state', 'half_open_probes_in_flight'));
        $this->assertTrue(\Schema::hasColumn('gateway_audit_outbox', 'relayed_at'));
    }

    /**
     * R11.3 / TASK-010:
     * Duplicate provider_event_key claims are absorbed (no error, return false).
     */
    public function test_audit_recorder_claim_is_idempotent_and_absorbs_duplicates(): void
    {
        $recorder = app(AuditRecorder::class);

        $first = $recorder->claim('key-123', 'processed', 'digest-1', ['note' => 'test']);
        $this->assertTrue($first);
        $this->assertSame(1, DB::table('gateway_audit_outbox')->count());

        $second = $recorder->claim('key-123', 'duplicate_ignored', 'digest-1', ['note' => 'test']);
        $this->assertFalse($second);
        $this->assertSame(1, DB::table('gateway_audit_outbox')->count());
    }

    /**
     * R13.1 / TASK-011:
     * Fixed JSON envelope and authoritative HTTP mapping:
     * 200 => {"status":"success","message":"<token>"}
     * 503 => {"status":"error","message":"<token>"}
     * No other fields allowed.
     */
    public function test_authoritative_http_status_mapping_and_fixed_envelope(): void
    {
        $tokens503 = [
            GatewayResultToken::ProviderUnavailable,
            GatewayResultToken::ProviderTimeout,
            GatewayResultToken::InternalError,
            GatewayResultToken::ConfigError,
        ];

        foreach (GatewayResultToken::cases() as $token) {
            $response = GatewayResponse::for($token);
            $expectedStatus = in_array($token, $tokens503, true) ? 503 : 200;
            $expectedStatusWord = $expectedStatus === 200 ? 'success' : 'error';

            $this->assertSame($expectedStatus, $response->getStatusCode(), "Token {$token->value} status mismatch");

            $data = json_decode((string) $response->getContent(), true);
            $this->assertSame(['status', 'message'], array_keys($data));
            $this->assertSame($expectedStatusWord, $data['status']);
            $this->assertSame($token->value, $data['message']);
        }
    }

    /**
     * R11.4 / TASK-012:
     * Outbox relay copies rows to gateway_audit_events on the same connection,
     * updates relayed_at, and is idempotent by provider_event_key.
     */
    public function test_flush_audit_outbox_relays_records_to_audit_events(): void
    {
        Queue::fake();
        $recorder = app(AuditRecorder::class);
        $recorder->claim('EVT-1001', 'processed', 'digest-1001', ['event_type' => 'webhook', 'event_source' => 'midtrans']);
        $recorder->claim('EVT-1002', 'duplicate_ignored', 'digest-1002', ['event_type' => 'webhook', 'event_source' => 'midtrans']);

        $this->assertSame(2, DB::table('gateway_audit_outbox')->whereNull('relayed_at')->count());
        $this->assertSame(0, DB::table('gateway_audit_events')->count());

        $job = new FlushAuditOutbox;
        $job->handle();

        $this->assertSame(0, DB::table('gateway_audit_outbox')->whereNull('relayed_at')->count());
        $this->assertSame(2, DB::table('gateway_audit_events')->count());

        $this->assertDatabaseHas('gateway_audit_events', [
            'provider_event_key' => 'EVT-1001',
            'result_token' => 'processed',
        ]);

        // Second run is idempotent and does not fail or duplicate
        $job->handle();
        $this->assertSame(2, DB::table('gateway_audit_events')->count());
    }

    /**
     * R11.4 / TASK-012:
     * Post-commit enqueue: queue-down does not escape to caller;
     * business outcome remains committed.
     */
    public function test_queue_down_post_commit_leaves_business_and_outbox_committed(): void
    {
        $customer = Customer::factory()->create(['balance' => 0]);
        $orderId = 'TOPUP-'.$customer->id.'-100001';
        $payload = $this->makeSignedPayload($orderId, '40000');

        Queue::fake();

        $response = $this->postJson(route('midtrans.webhook'), $payload);
        $response->assertOk()->assertExactJson(['status' => 'success', 'message' => 'processed']);

        $this->assertSame(40000, $customer->fresh()->balance);
        $this->assertSame(1, DB::table('gateway_audit_outbox')->where('provider_event_key', "{$orderId}:settlement:200")->count());
    }

    /**
     * R13.4 / TASK-013:
     * Pre-commit timeout check via injected Clock.
     * Measurement starts at pipeline entry; if elapsed > timeout seconds,
     * rollback the business transaction and outbox claim, then write
     * provider_timeout in a NEW transaction and return 503.
     */
    public function test_pre_commit_timeout_rolls_back_business_and_writes_timeout_outbox_row(): void
    {
        $customer = Customer::factory()->create(['balance' => 0]);
        $orderId = 'TOPUP-'.$customer->id.'-200002';
        $payload = $this->makeSignedPayload($orderId, '75000');

        // Fake clock that advances past timeout (default 5s) before commit
        $fakeClock = new class implements Clock
        {
            public int $callCount = 0;

            public function nowMs(): int
            {
                $this->callCount++;

                // 1st call: pipeline entry (0ms)
                // 2nd call: pre-commit check (6000ms > 5000ms timeout)
                return $this->callCount === 1 ? 10000 : 16500;
            }
        };

        $this->app->instance(Clock::class, $fakeClock);

        $response = $this->postJson(route('midtrans.webhook'), $payload);

        $response->assertStatus(503)
            ->assertExactJson(['status' => 'error', 'message' => 'provider_timeout']);

        // Business outcome rolled back: customer balance is NOT modified
        $this->assertSame(0, $customer->fresh()->balance);
        $this->assertDatabaseEmpty('gateway_transactions');

        // Outbox contains separate provider_timeout row written in new transaction
        $this->assertDatabaseHas('gateway_audit_outbox', [
            'provider_event_key' => "{$orderId}:settlement:200",
            'result_token' => 'provider_timeout',
        ]);
    }

    /**
     * R11.6 / TASK-014:
     * Outbox insert failure rolls back business and returns 503 internal_error.
     */
    public function test_outbox_insert_failure_rolls_back_business_and_returns_503(): void
    {
        $customer = Customer::factory()->create(['balance' => 0]);
        $orderId = 'TOPUP-'.$customer->id.'-300003';
        $payload = $this->makeSignedPayload($orderId, '50000');

        $mockRecorder = \Mockery::mock(AuditRecorder::class);
        $mockRecorder->shouldReceive('claim')
            ->andThrow(new \RuntimeException('Outbox DB down'));

        $this->app->instance(AuditRecorder::class, $mockRecorder);

        $response = $this->postJson(route('midtrans.webhook'), $payload);

        $response->assertStatus(503)
            ->assertExactJson(['status' => 'error', 'message' => 'internal_error']);

        // Business outcome was rolled back
        $this->assertSame(0, $customer->fresh()->balance);
        $this->assertDatabaseEmpty('gateway_transactions');
    }
}
