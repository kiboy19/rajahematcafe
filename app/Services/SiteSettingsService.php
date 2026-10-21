<?php

namespace App\Services;

use App\Models\SiteSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class SiteSettingsService
{
    private const CACHE_KEY = 'site.settings.current';

    public function current(): SiteSetting
    {
        $cached = Cache::get(self::CACHE_KEY);

        if ($cached instanceof SiteSetting) {
            return $cached;
        }

        if ($cached !== null) {
            Cache::forget(self::CACHE_KEY);
        }

        // Tabel mungkin belum ada (mis. uji tanpa migrasi penuh atau saat deploy
        // bertahap); fallback ke default agar shell tidak pernah gagal render.
        if (! Schema::hasTable('site_settings')) {
            return $this->defaults();
        }

        return Cache::rememberForever(self::CACHE_KEY, function (): SiteSetting {
            return SiteSetting::query()->first() ?? $this->defaults();
        });
    }

    private function defaults(): SiteSetting
    {
        return new SiteSetting([
            'brand_name' => 'Rajahemat Cafe',
            'brand_tagline' => 'Ngopi Hemat, Rasa Nikmat',
        ]);
    }

    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public function toPayload(SiteSetting $settings): array
    {
        return [
            'brand_name' => $settings->brand_name,
            'brand_tagline' => $settings->brand_tagline,
            'logo_path' => $settings->logo_path,
            'favicon_path' => $settings->favicon_path,
            'logo_url' => $settings->logoUrl(),
            'favicon_url' => $settings->faviconUrl(),
            'description' => $settings->description,
            'address' => $settings->address,
            'phone' => $settings->phone,
            'whatsapp_number' => $settings->whatsapp_number,
            'whatsapp_link' => $settings->whatsappLink(),
            'email' => $settings->email,
            'instagram_url' => $settings->instagram_url,
            'tiktok_url' => $settings->tiktok_url,
            'facebook_url' => $settings->facebook_url,
            'google_maps_embed_url' => $settings->google_maps_embed_url,
            'opening_hours' => $settings->opening_hours,
            'footer_text' => $settings->footer_text,
            'updated_at' => $settings->updated_at?->toIso8601String(),
        ];
    }
}
