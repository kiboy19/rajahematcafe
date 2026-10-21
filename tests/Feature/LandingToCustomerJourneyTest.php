<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\CafeTable;
use App\Models\Customer;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingToCustomerJourneyTest extends TestCase
{
    use RefreshDatabase;

    public function test_alur_lengkap_tamu_ke_landing_daftar_lalu_pesan_meja(): void
    {
        $this->withoutVite();

        // 1. Tamu membuka landing page publik.
        $this->get('/')->assertOk();

        // 2. Tamu klik CTA 'Pesan Sekarang' yang mengarah ke /daftar?return_to=%2Fmeja.
        $this->get('/daftar?return_to=%2Fmeja')->assertOk();

        // 3. Tamu mendaftar menjadi pelanggan.
        $this->post('/daftar', [
            'name' => 'Faris Pratama',
            'email' => 'faris@contoh.test',
            'phone' => '081234567899',
            'password' => 'kopi-enak-123',
            'password_confirmation' => 'kopi-enak-123',
        ])->assertRedirect('/meja');

        $customer = Customer::query()->where('email', 'faris@contoh.test')->firstOrFail();
        $this->assertAuthenticatedAs($customer, 'customer');

        // 4. Meja dipilih (simulasi pindaian QR meja 02).
        $table = CafeTable::factory()->create(['label' => 'Meja 02']);
        $menuItem = MenuItem::factory()->create(['name' => 'Caramel Latte', 'price' => 24000]);

        $this->actingAs($customer, 'customer')->get('/m/'.$table->qr_token)->assertRedirect(route('customer.order.menu'));

        // 5. Tambah item ke keranjang belanja.
        $this->actingAs($customer, 'customer')
            ->postJson('/meja/keranjang', [
                'menu_item_id' => $menuItem->id,
                'quantity' => 2,
            ])
            ->assertOk();

        // 6. Checkout pesanan meja (status awal: menunggu pembayaran).
        $checkout = $this->actingAs($customer, 'customer')
            ->postJson('/meja/pesan')
            ->assertCreated()
            ->assertJsonPath('data.paid', false);

        $orderId = (int) $checkout->json('data.order_id');
        $order = Order::query()->findOrFail($orderId);

        $this->assertSame(OrderStatus::MenungguPembayaran, $order->status);
        $this->assertSame($customer->id, $order->customer_id);

        // 7. Pesanan belum lunas disaring keluar dari layar KDS dapur.
        $cook = User::factory()->role(UserRole::Dapur)->create();
        $this->actingAs($cook)
            ->getJson('/api/kds')
            ->assertOk()
            ->assertJsonMissing(['id' => $orderId]);
    }
}
