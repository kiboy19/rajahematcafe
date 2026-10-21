<?php

namespace App\Providers;

use App\Payments\Environment\EnvironmentClass;
use App\Payments\Midtrans\HttpMidtransClient;
use App\Payments\Midtrans\MidtransClient;
use App\Payments\Support\SettlementBarrier;
use App\Support\Clock;
use App\Support\SystemClock;
use Illuminate\Support\ServiceProvider;
use Tests\Support\FakeMidtransClient;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(Clock::class, SystemClock::class);
        $this->app->singleton(SettlementBarrier::class);

        $this->app->bind(MidtransClient::class, function () {
            $env = app(EnvironmentClass::class);
            if ($env->isSandbox() && (bool) env('POS_MIDTRANS_SANDBOX_ENABLED', false)) {
                $serverKey = (string) env('MIDTRANS_SANDBOX_SERVER_KEY', '');
                if ($serverKey !== '') {
                    return new HttpMidtransClient(
                        $serverKey,
                        'https://app.sandbox.midtrans.com/snap/v1',
                        (int) config('pos.midtrans.connect_timeout_ms', 3000),
                        (int) config('pos.midtrans.read_timeout_ms', 10000),
                        app(Clock::class),
                    );
                }
            }

            return app(FakeMidtransClient::class);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
