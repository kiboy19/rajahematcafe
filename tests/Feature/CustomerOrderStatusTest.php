<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\MenuItem;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerOrderStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_open_own_order_tracker_and_refresh_is_enabled_while_active(): void
    {
        $customer = Customer::factory()->create();
        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'status' => OrderStatus::Diproses,
        ]);
        $item = MenuItem::factory()->create();
        $order->items()->create([
            'menu_item_id' => $item->id,
            'name' => $item->name,
            'unit_price' => $item->price,
            'quantity' => 1,
            'line_total' => $item->price,
        ]);

        $this->actingAs($customer, 'customer')
            ->getJson(route('customer.order.show', $order))
            ->assertOk()
            ->assertJsonPath('data.refresh', 20)
            ->assertJsonPath('data.order.number', $order->number)
            ->assertJsonPath('data.order.status', $order->status->value);
    }

    public function test_customer_cannot_see_another_customers_order_tracker(): void
    {
        $owner = Customer::factory()->create();
        $otherCustomer = Customer::factory()->create();
        $order = Order::factory()->create(['customer_id' => $otherCustomer->id]);

        $this->actingAs($owner, 'customer')
            ->get(route('customer.order.show', $order))
            ->assertNotFound();
    }

    public function test_final_order_tracker_does_not_auto_refresh(): void
    {
        $customer = Customer::factory()->create();
        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'status' => OrderStatus::Selesai,
        ]);

        $this->actingAs($customer, 'customer')
            ->getJson(route('customer.order.show', $order))
            ->assertOk()
            ->assertJsonPath('data.refresh', null)
            ->assertJsonPath('data.order.status', $order->status->value);
    }
}
