<?php

namespace Database\Factories;

use App\Models\CashierSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashierSession>
 */
class CashierSessionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'cashier_id' => User::factory(),
            'opening_float' => 100000,
            'expected_cash' => 100000,
            'counted_cash' => null,
            'variance' => null,
            'status' => 'open',
            'opened_by' => null,
            'opened_at' => now(),
        ];
    }

    public function closed(int $countedCash = 100000): static
    {
        return $this->state(fn (array $attrs) => [
            'status' => 'closed',
            'counted_cash' => $countedCash,
            'expected_cash' => $attrs['opening_float'],
            'variance' => $countedCash - $attrs['opening_float'],
            'closed_at' => now(),
        ]);
    }
}
