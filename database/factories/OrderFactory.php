<?php

namespace Database\Factories;

use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    public function definition(): array
    {
        $subtotal = fake()->numberBetween(1, 20) * 5000;

        return [
            'number' => 'ORD-'.now()->format('ymd').'-'.fake()->unique()->numerify('####'),
            'channel' => OrderChannel::DineIn,
            'status' => OrderStatus::Baru,
            'table_label' => (string) fake()->numberBetween(1, 12),
            'subtotal' => $subtotal,
            'discount' => 0,
            'tax' => 0,
            'service' => 0,
            'total' => $subtotal,
            'tax_percent' => 0,
            'service_percent' => 0,
            'placed_at' => now(),
        ];
    }

    public function takeaway(): static
    {
        return $this->state(fn (array $attributes): array => [
            'channel' => OrderChannel::Takeaway,
            'table_label' => null,
        ]);
    }
}
