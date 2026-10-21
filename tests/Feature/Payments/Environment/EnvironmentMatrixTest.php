<?php

namespace Tests\Feature\Payments\Environment;

use App\Payments\Environment\ConfigErrorLogOnce;
use App\Payments\Environment\EnvironmentClass;
use App\Payments\Environment\FailClosedGuard;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EnvironmentMatrixTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        ConfigErrorLogOnce::reset();
    }

    protected function tearDown(): void
    {
        foreach (['APP_ENV', 'PHPUNIT_RUNNING', 'PEST_RUNNING', 'CI'] as $key) {
            unset($_ENV[$key], $_SERVER[$key]);
            putenv($key);
        }
        ConfigErrorLogOnce::reset();
        parent::tearDown();
    }

    /**
     * @return array<string, mixed>
     */
    protected function matrixFixture(): array
    {
        return require dirname(__DIR__, 4).'/tests/Fixtures/Payments/environment-matrix.php';
    }

    /**
     * @param  array<string, string>  $env
     * @param  array<string, mixed>  $config
     */
    protected function applyEnvironment(array $env, array $config): void
    {
        foreach (['PHPUNIT_RUNNING', 'PEST_RUNNING', 'CI'] as $flag) {
            unset($_ENV[$flag], $_SERVER[$flag]);
            putenv($flag);
        }

        foreach ($env as $key => $value) {
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
            putenv("{$key}={$value}");
        }

        Config::set('app.env', $env['APP_ENV'] ?? 'local');

        $base = $this->validMidtransConfig();
        Config::set('pos.midtrans', array_merge($base, $config));
    }

    /**
     * @return array<string, mixed>
     */
    protected function validMidtransConfig(): array
    {
        return [
            'enabled' => false,
            'is_production' => false,
            'is_sandbox' => false,
            'paused' => false,
            'new_stack_enabled' => false,
            'shadow_mode_enabled' => false,
            'legacy_fallback_enabled' => false,
            'server_key' => 'SB-Mid-server-valid',
            'server_key_rotation' => [],
            'payload_key' => 'payload-key-valid',
            'webhook_timeout_seconds' => 10,
            'max_payload_bytes' => 65536,
            'settlement_retry_limit' => 3,
            'poll_threshold_seconds' => 300,
            'poll_lock_timeout_seconds' => 30,
            'status_request_budget_ms' => 800,
            'reconciliation_interval_minutes' => 60,
            'reconcile_rate_per_minute' => 10,
            'buffer_ttl_minutes' => 1440,
            'buffer_lock_timeout_seconds' => 300,
            'payload_retention_days' => 730,
            'circuit_failure_threshold' => 5,
            'circuit_window_seconds' => 60,
            'circuit_half_open_probes' => 2,
            'poll_customer_rate_per_minute' => 30,
            'poll_global_rate_per_minute' => 200,
            'overpayment_cap_percent' => 0.02,
        ];
    }

    /**
     * @param  array<string, string>  $env
     * @param  array<string, mixed>  $config
     */
    #[DataProvider('matrixProvider')]
    public function testEnvironmentMatrix(
        string $label,
        array $env,
        array $config,
        string $expectedTier,
        bool $isProduction,
        bool $failClosed,
        bool $allowsMock,
    ): void {
        $this->applyEnvironment($env, $config);

        $this->assertSame($expectedTier, EnvironmentClass::tier(), "{$label}: tier");
        $this->assertSame($isProduction, EnvironmentClass::isProduction(), "{$label}: is_production");
        $this->assertSame($failClosed, EnvironmentClass::isFailClosed(), "{$label}: isFailClosed");
        $this->assertSame($failClosed, FailClosedGuard::blocksPaymentCreation(), "{$label}: blocksPaymentCreation");
        $this->assertSame($failClosed, FailClosedGuard::blocksWebhook(), "{$label}: blocksWebhook");
        $this->assertSame($failClosed, FailClosedGuard::blocksMockRoutes(), "{$label}: blocksMockRoutes");
        $this->assertSame($allowsMock, FailClosedGuard::allowsMockRoutes(), "{$label}: allowsMockRoutes");

        // Create a fresh logger per "process" as the ticket specifies.
        ConfigErrorLogOnce::reset();
    }

    /**
     * @return array<string, array{0:string, 1:array<string,string>, 2:array<string,mixed>, 3:string, 4:bool, 5:bool, 6:bool}>
     */
    public static function matrixProvider(): array
    {
        $fixture = require dirname(__DIR__, 4).'/tests/Fixtures/Payments/environment-matrix.php';

        $out = [];
        foreach ($fixture['matrix'] as $row) {
            $out[$row['label']] = [
                $row['label'],
                $row['env'],
                $row['config'],
                $row['tier'],
                $row['is_production'],
                $row['fail_closed'],
                $row['allows_mock'],
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    #[DataProvider('invalidConfigProvider')]
    public function testInvalidConfigIsDetected(string $label, array $config, string $expectedErrorKey): void
    {
        $this->applyEnvironment(['APP_ENV' => 'production'], array_merge($config, [
            'enabled' => true,
            'is_production' => true,
        ]));

        $errors = EnvironmentClass::configErrors();
        $this->assertArrayHasKey($expectedErrorKey, $errors, "{$label}: expected error for {$expectedErrorKey}");
        $this->assertTrue(EnvironmentClass::isFailClosed(), "{$label}: invalid config must fail closed");
        $this->assertTrue(FailClosedGuard::blocksPaymentCreation(), "{$label}: payment creation must be blocked");
        $this->assertTrue(FailClosedGuard::blocksWebhook(), "{$label}: webhook must be blocked");
        $this->assertSame(404, $this->mockRouteStatusFor($label), "{$label}: mock routes must be 404 when fail-closed");
    }

    private function mockRouteStatusFor(string $label): int
    {
        // Fail-closed means simulation/mock routes return 404.
        return FailClosedGuard::allowsMockRoutes() ? 200 : 404;
    }

    /**
     * @return array<string, array{0:string, 1:array<string,mixed>, 2:string}>
     */
    public static function invalidConfigProvider(): array
    {
        $fixture = require dirname(__DIR__, 4).'/tests/Fixtures/Payments/environment-matrix.php';

        $out = [];
        foreach ($fixture['invalid_config'] as $row) {
            $out[$row['label']] = [$row['label'], $row['config'], $row['expected_error_key']];
        }

        return $out;
    }

    public function testConfigErrorsMissingRequiredKeysInProduction(): void
    {
        $this->applyEnvironment(['APP_ENV' => 'production'], [
            'enabled' => true,
            'is_production' => true,
            'is_sandbox' => false,
            'server_key' => '',
            'payload_key' => '',
        ]);

        $errors = EnvironmentClass::configErrors();
        $this->assertArrayHasKey('server_key', $errors);
        $this->assertArrayHasKey('payload_key', $errors);
        $this->assertTrue(EnvironmentClass::isFailClosed());
    }

    public function testConfigErrorLogOnceEmitsExactlyOncePerLogger(): void
    {
        Log::spy();

        $errors = ['webhook_timeout_seconds' => 'harus di antara 5 dan 30'];

        ConfigErrorLogOnce::emit($errors);
        ConfigErrorLogOnce::emit($errors); // duplicate — must not log again
        ConfigErrorLogOnce::emit($errors); // duplicate — must not log again

        Log::shouldHaveReceived('error')->once();
    }

    public function testConfigErrorLogOnceEmitsPerFingerprint(): void
    {
        Log::spy();

        ConfigErrorLogOnce::emit(['key1' => 'error1']);
        ConfigErrorLogOnce::emit(['key2' => 'error2']);

        Log::shouldHaveReceived('error')->twice();
    }

    public function testFingerprintMatchesConfigErrorsShape(): void
    {
        $this->applyEnvironment(['APP_ENV' => 'production'], [
            'enabled' => true,
            'is_production' => true,
            'webhook_timeout_seconds' => 999,
        ]);

        $errors = EnvironmentClass::configErrors();
        $fingerprint = hash('sha256', serialize($errors));

        Log::spy();
        ConfigErrorLogOnce::emit($errors);
        ConfigErrorLogOnce::emit($errors);

        Log::shouldHaveReceived('error')->withArgs(function (string $message, array $context) use ($fingerprint, $errors): bool {
            return $context['fingerprint'] === $fingerprint && $context['keys'] === array_keys($errors);
        })->once();
    }

    public function testMockRoutes404WhenFailClosed(): void
    {
        $this->applyEnvironment(['APP_ENV' => 'production'], [
            'enabled' => false,
            'is_production' => true,
        ]);

        $this->assertTrue(EnvironmentClass::isFailClosed());
        $this->assertTrue(FailClosedGuard::blocksMockRoutes());
        $this->assertFalse(FailClosedGuard::allowsMockRoutes());
    }

    public function testMockRoutesAllowedInLocal(): void
    {
        $this->applyEnvironment(['APP_ENV' => 'local'], [
            'enabled' => false,
            'is_production' => false,
            'is_sandbox' => false,
        ]);

        $this->assertFalse(EnvironmentClass::isFailClosed());
        $this->assertFalse(FailClosedGuard::blocksMockRoutes());
        $this->assertTrue(FailClosedGuard::allowsMockRoutes());
    }

    public function testMockRoutesNotAllowedInStaging(): void
    {
        $this->applyEnvironment(['APP_ENV' => 'staging'], [
            'enabled' => false,
            'is_production' => false,
        ]);

        $this->assertFalse(EnvironmentClass::isFailClosed());
        $this->assertFalse(FailClosedGuard::allowsMockRoutes());
    }
}
