<?php

namespace Tests\Feature\Payments\Environment;

use App\Payments\Environment\ConfigErrorLogOnce;
use App\Payments\Environment\EnvironmentClass;
use App\Payments\Environment\FailClosedGuard;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class PauseGateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        ConfigErrorLogOnce::reset();
    }

    protected function tearDown(): void
    {
        Artisan::call('midtrans:resume');
        ConfigErrorLogOnce::reset();
        parent::tearDown();
    }

    public function testPauseCommandCreatesPauseFile(): void
    {
        $this->artisan('midtrans:pause')->assertExitCode(0);

        $this->assertFileExists(storage_path('app/midtrans-paused'));
    }

    public function testResumeCommandRemovesPauseFile(): void
    {
        // First pause
        $this->artisan('midtrans:pause')->assertExitCode(0);
        $this->assertFileExists(storage_path('app/midtrans-paused'));

        $this->artisan('midtrans:resume')->assertExitCode(0);

        $this->assertFileDoesNotExist(storage_path('app/midtrans-paused'));
    }

    public function testIsPausedReturnsTrueWhenPauseFileExists(): void
    {
        // Directly test the method after running commands
        $this->artisan('midtrans:pause')->assertExitCode(0);
        $this->assertTrue(EnvironmentClass::isPaused());

        $this->artisan('midtrans:resume')->assertExitCode(0);
        $this->assertFalse(EnvironmentClass::isPaused());
    }

    public function testIsPausedReturnsTrueWhenConfigPaused(): void
    {
        Config::set('pos.midtrans.paused', true);
        $this->assertTrue(EnvironmentClass::isPaused());
        Config::set('pos.midtrans.paused', false);
        $this->assertFalse(EnvironmentClass::isPaused());
    }

    public function testMaintenanceRouteReturns503WhenPaused(): void
    {
        $this->artisan('midtrans:pause')->assertExitCode(0);

        $response = $this->getJson('/midtrans/maintenance');
        $response->assertStatus(503);
        $response->assertJson([
            'error' => 'provider_unavailable',
            'message' => 'Payment provider tidak tersedia.',
        ]);
    }

    public function testMaintenanceRouteReturnsOkWhenNotPaused(): void
    {
        $this->artisan('midtrans:resume')->assertExitCode(0);

        $response = $this->getJson('/midtrans/maintenance');
        $response->assertOk();
        $response->assertJson(['status' => 'operational']);
    }

    public function testWebhookReturns503WhenPaused(): void
    {
        $this->artisan('midtrans:pause')->assertExitCode(0);

        $response = $this->postJson('/midtrans/webhook', []);
        $response->assertStatus(503);
        $response->assertJson([
            'error' => 'provider_unavailable',
            'message' => 'Payment provider tidak tersedia.',
        ]);
    }

    public function testPaymentCreationReturns503WhenPaused(): void
    {
        $this->artisan('midtrans:pause')->assertExitCode(0);

        $this->assertTrue(FailClosedGuard::blocksPaymentCreation());
        $this->assertTrue(FailClosedGuard::blocksWebhook());
    }

    public function testPauseFileAlsoBlocksInLocalEnvironment(): void
    {
        // Local non-production passes env checks, but an active pause must still fail closed.
        Config::set('pos.midtrans.enabled', true);
        Config::set('pos.midtrans.is_production', false);
        Config::set('pos.midtrans.is_sandbox', false);
        Config::set('pos.midtrans.server_key', '');
        Config::set('pos.midtrans.payload_key', '');

        $this->artisan('midtrans:pause')->assertExitCode(0);

        $this->assertTrue(EnvironmentClass::isPaused());
        $this->assertTrue(FailClosedGuard::blocksPaymentCreation());
        $this->assertTrue(FailClosedGuard::blocksWebhook());
        $this->assertFalse(FailClosedGuard::allowsMockRoutes());
    }

    public function testPauseConfigFlagAlsoTriggersPaused(): void
    {
        Config::set('pos.midtrans.paused', true);
        $this->assertTrue(EnvironmentClass::isPaused());
    }
}