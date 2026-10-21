<?php

namespace Tests\Feature\Payments\Provider;

use App\Enums\GatewayResultToken;
use App\Payments\Midtrans\CircuitBreaker;
use App\Payments\Midtrans\CircuitState;
use App\Payments\Midtrans\HttpMidtransClient;
use App\Payments\Midtrans\ProviderErrorType;
use App\Payments\Midtrans\SnapRequest;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request as HttpRequest;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\FakeClock;
use Tests\TestCase;

class ProviderClientErrorClassificationTest extends TestCase
{
    use RefreshDatabase;

    private FakeClock $clock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clock = new FakeClock;
        DB::table('gateway_circuit_state')->where('id', 1)->update([
            'state' => CircuitState::Closed->value,
            'failure_count' => 0,
            'window_started_at_ms' => 0,
            'half_open_probes_in_flight' => 0,
            'opened_at_ms' => 0,
            'version' => 0,
        ]);
    }

    /** @return array{0: HttpMidtransClient, 1: CircuitBreaker, 2: list<array<string, mixed>>} */
    private function makeClient(array $queue, int $threshold = 5): array
    {
        $container = [];
        $history = Middleware::history($container);
        $stack = HandlerStack::create(new MockHandler($queue));
        $stack->push($history);

        $breaker = new CircuitBreaker($this->clock, $threshold, 60, 2);

        $client = new HttpMidtransClient(
            'SB-Mid-server-test',
            'https://app.sandbox.midtrans.com/snap/v1',
            3000,
            10000,
            $this->clock,
            $breaker,
            new Client(['handler' => $stack]),
        );

        return [$client, $breaker, array_values($container)];
    }

    private static function jsonResponse(int $status, array $body): Response
    {
        return new Response($status, ['Content-Type' => 'application/json'], (string) json_encode($body));
    }

    private static function snapRequest(string $orderId): SnapRequest
    {
        return new SnapRequest(midtransOrderId: $orderId, grossAmount: 50000, idempotencyKey: 'idem-'.$orderId);
    }

    public function test_error_type_enum_is_exhaustive(): void
    {
        $this->assertSame([
            'provider_timeout',
            'provider_rejected',
            'provider_unavailable',
            'provider_malformed',
        ], array_map(fn (ProviderErrorType $t) => $t->value, ProviderErrorType::cases()));
    }

    public function test_connect_timeout_is_classified_and_retried_once_with_same_body(): void
    {
        $connect = static fn () => new ConnectException('cURL error 28', new HttpRequest('POST', 'https://app.sandbox.midtrans.com/snap/v1/transactions'));

        [$client, , $container] = $this->makeClient([
            $connect,
            self::jsonResponse(200, ['token' => 'snap-tok-retried', 'redirect_url' => 'https://example.com']),
        ]);

        $response = $client->createSnapTransaction(self::snapRequest('ORDER-TIMEOUT'));

        $this->assertTrue($response->successful);
        $this->assertSame('snap-tok-retried', $response->token);
        $this->assertCount(2, $container);
        $this->assertSame(
            (string) $container[0]['request']->getBody(),
            (string) $container[1]['request']->getBody(),
        );
        $this->assertStringContainsString('ORDER-TIMEOUT', (string) $container[1]['request']->getBody());
    }

    public function test_http_5xx_is_unavailable_and_retried_once(): void
    {
        [$client, , $container] = $this->makeClient([
            self::jsonResponse(500, ['message' => 'internal']),
            self::jsonResponse(200, ['token' => 'snap-tok-after-500']),
        ]);

        $response = $client->createSnapTransaction(self::snapRequest('ORDER-500'));

        $this->assertTrue($response->successful);
        $this->assertSame('snap-tok-after-500', $response->token);
        $this->assertCount(2, $container);
    }

    /**
     * @dataProvider retryableStatusProvider
     */
    public function test_retryable_4xx_status_is_unavailable_and_retried_once(int $status): void
    {
        [$client, , $container] = $this->makeClient([
            self::jsonResponse($status, ['message' => 'retry me']),
            self::jsonResponse(200, ['token' => 'snap-tok-after-'.$status]),
        ]);

        $response = $client->createSnapTransaction(self::snapRequest('ORDER-'.$status));

        $this->assertTrue($response->successful);
        $this->assertCount(2, $container);
    }

    public static function retryableStatusProvider(): array
    {
        return [[408], [425], [429]];
    }

    public function test_retry_exhausted_returns_unavailable_after_exactly_two_attempts(): void
    {
        [$client, $breaker, $container] = $this->makeClient([
            self::jsonResponse(503, ['message' => 'down']),
            self::jsonResponse(503, ['message' => 'down']),
        ]);

        $response = $client->createSnapTransaction(self::snapRequest('ORDER-DOWN'));

        $this->assertFalse($response->successful);
        $this->assertSame(GatewayResultToken::ProviderUnavailable, $response->errorType);
        $this->assertCount(2, $container);
        // Breaker records exactly one outcome per logical operation.
        $this->assertSame(1, (int) DB::table('gateway_circuit_state')->where('id', 1)->value('failure_count'));
    }

    /**
     * @dataProvider nonRetryableStatusProvider
     */
    public function test_non_retryable_4xx_is_rejected_without_retry(int $status): void
    {
        [$client, , $container] = $this->makeClient([
            self::jsonResponse($status, ['message' => 'nope']),
        ]);

        $response = $client->createSnapTransaction(self::snapRequest('ORDER-REJECTED'));

        $this->assertFalse($response->successful);
        $this->assertSame(GatewayResultToken::ProviderRejected, $response->errorType);
        $this->assertCount(1, $container);
    }

    public static function nonRetryableStatusProvider(): array
    {
        return [[400], [401], [403], [404], [422]];
    }

    public function test_malformed_json_is_classified_without_retry(): void
    {
        [$client, , $container] = $this->makeClient([
            new Response(200, ['Content-Type' => 'application/json'], 'not valid json'),
        ]);

        $response = $client->createSnapTransaction(self::snapRequest('ORDER-MALFORMED'));

        $this->assertFalse($response->successful);
        $this->assertSame(GatewayResultToken::ProviderMalformed, $response->errorType);
        $this->assertCount(1, $container);
    }

    public function test_circuit_opens_at_threshold_then_rejects_without_calling_provider(): void
    {
        [$client, $breaker, $container] = $this->makeClient(
            array_fill(0, 5, self::jsonResponse(500, ['message' => 'down'])),
            threshold: 5,
        );

        for ($i = 1; $i <= 5; $i++) {
            $response = $client->createSnapTransaction(self::snapRequest('ORDER-FAIL-'.$i));
            $this->assertFalse($response->successful);
        }

        $this->assertSame(CircuitState::Open, $breaker->currentState());
        $this->assertCount(10, $container, 'each failing operation retries once');

        $response = $client->createSnapTransaction(self::snapRequest('ORDER-BLOCKED'));

        $this->assertFalse($response->successful);
        $this->assertSame(GatewayResultToken::ProviderUnavailable, $response->errorType);
        $this->assertSame('circuit_open', $response->message);
        $this->assertCount(10, $container, 'open circuit never reaches the provider');
    }

    public function test_half_open_success_closes_circuit(): void
    {
        [$client, $breaker] = $this->makeClient([
            ...array_fill(0, 5, self::jsonResponse(500, ['message' => 'down'])),
            self::jsonResponse(200, ['token' => 'snap-tok-half-open']),
        ]);

        for ($i = 1; $i <= 5; $i++) {
            $client->createSnapTransaction(self::snapRequest('ORDER-FAIL-'.$i));
        }

        $this->assertSame(CircuitState::Open, $breaker->currentState());

        $this->clock->advance(61_000);

        $response = $client->createSnapTransaction(self::snapRequest('ORDER-HALF-OK'));

        $this->assertTrue($response->successful);
        $this->assertSame(CircuitState::Closed, $breaker->currentState());
    }

    public function test_half_open_failure_reopens_circuit(): void
    {
        [$client, $breaker] = $this->makeClient([
            ...array_fill(0, 5, self::jsonResponse(500, ['message' => 'down'])),
            self::jsonResponse(500, ['message' => 'down']),
        ]);

        for ($i = 1; $i <= 5; $i++) {
            $client->createSnapTransaction(self::snapRequest('ORDER-FAIL-'.$i));
        }

        $this->clock->advance(61_000);

        $response = $client->createSnapTransaction(self::snapRequest('ORDER-HALF-FAIL'));

        $this->assertFalse($response->successful);
        $this->assertSame(CircuitState::Open, $breaker->currentState());
    }
}
