<?php

namespace Database\Factories;

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseOrder>
 */
class PurchaseOrderFactory extends Factory
{
    protected $model = PurchaseOrder::class;

    private static int $sequence = 1;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'number' => 'PO-'.date('Ymd').'-'.str_pad(self::$sequence++, 4, '0', STR_PAD_LEFT),
            'supplier_id' => Supplier::factory(),
            'status' => PurchaseOrderStatus::Draft,
            'total' => 0,
            'note' => fake()->optional()->sentence(),
        ];
    }

    public function submitted(): static
    {
        return $this->state(fn (): array => [
            'status' => PurchaseOrderStatus::Submitted,
            'submitted_at' => now(),
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (): array => [
            'status' => PurchaseOrderStatus::Approved,
            'approved_at' => now(),
        ]);
    }

    public function received(): static
    {
        return $this->state(fn (): array => [
            'status' => PurchaseOrderStatus::Received,
            'received_at' => now(),
        ]);
    }
}
