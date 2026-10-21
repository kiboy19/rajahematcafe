<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LayoutSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_layout_renders_navigation_icons(): void
    {
        $kasir = User::factory()->create(['role' => 'kasir']);

        $response = $this->actingAs($kasir)->get(route('pos.index'));

        $response->assertOk();
        // Cangkang SPA memuat aset dan data boot pengguna kasir
        $response->assertSee('id="app"', false);
        $response->assertSee('data-props=', false);
        $response->assertSee('pos.index');
    }

    public function test_customer_layout_renders_bottom_navigation(): void
    {
        $customer = Customer::factory()->create();

        $response = $this->actingAs($customer, 'customer')->get(route('customer.home'));

        $response->assertOk();
        // Cangkang SPA memuat identitas pelanggan
        $response->assertSee('id="app"', false);
        $response->assertSee('data-props=', false);
        $response->assertSee($customer->name);
    }
}
