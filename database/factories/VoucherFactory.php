<?php

namespace Database\Factories;

use App\Enums\VoucherType;
use App\Models\Voucher;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Voucher>
 */
class VoucherFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => mb_strtoupper(fake()->unique()->bothify('PROMO-####')),
            'name' => 'Promo '.fake()->words(2, true),
            'type' => VoucherType::Fixed,
            'value' => 5000,
            'max_discount' => null,
            'min_subtotal' => 0,
            'quota' => null,
            'used_count' => 0,
            'quota_per_customer' => 1,
            'starts_at' => null,
            'expires_at' => null,
            'is_active' => true,
        ];
    }

    public function percent(int $percent = 10, int $maxDiscount = 20000): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => VoucherType::Percent,
            'value' => $percent,
            'max_discount' => $maxDiscount,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'expires_at' => now()->subDay(),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }
}
