<?php

namespace App\Services;

use App\Models\BillingSetting;
use Illuminate\Support\Facades\Cache;

class BillingSettingsService
{
    private const CACHE_KEY = 'billing-settings.current';

    public function current(): BillingSetting
    {
        $cached = Cache::get(self::CACHE_KEY);

        if ($cached instanceof BillingSetting) {
            return $cached;
        }

        if ($cached !== null) {
            Cache::forget(self::CACHE_KEY);
        }

        return Cache::rememberForever(self::CACHE_KEY, function (): BillingSetting {
            return BillingSetting::query()->first() ?? new BillingSetting([
                'tax_name' => 'PB1',
                'tax_bps' => (int) round((float) config('pos.tax_percent', 10) * 100),
                'tax_enabled' => true,
                'service_name' => 'Service Charge',
                'service_bps' => (int) round((float) config('pos.service_percent', 5) * 100),
                'service_enabled' => true,
            ]);
        });
    }

    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /** @return array{taxBps: int, serviceBps: int} */
    public function rates(): array
    {
        $settings = $this->current();

        return [
            'taxBps' => $settings->tax_enabled ? $settings->tax_bps : 0,
            'serviceBps' => $settings->service_enabled ? $settings->service_bps : 0,
        ];
    }
}
