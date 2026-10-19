<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'method' => PaymentMethod::Cash,
            'status' => PaymentStatus::Paid,
            'amount' => 50000,
            'tendered' => 50000,
            'change' => 0,
            'paid_at' => now(),
        ];
    }
}
