<?php

namespace Database\Factories;

use App\Enums\PageViewDevice;
use App\Models\PageView;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PageView>
 */
class PageViewFactory extends Factory
{
    protected $model = PageView::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'page_type' => 'home',
            'path' => '/',
            'page_title' => 'Beranda',
            'ip_hash' => hash('sha256', fake()->ipv4().fake()->uuid()),
            'user_agent' => fake()->userAgent(),
            'device' => PageViewDevice::Desktop->value,
            'referer' => null,
            'viewed_at' => now(),
        ];
    }

    public function mobile(): static
    {
        return $this->state(fn (): array => ['device' => PageViewDevice::Mobile->value]);
    }
}
