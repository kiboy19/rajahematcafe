<?php

namespace Tests\Feature;

use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\CafeTable;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosOrderTest extends TestCase
{
    use RefreshDatabase;

    private User $kasir;

    private MenuItem $kopi;

    private CafeTable $meja;

    protected function setUp(): void
    {
        parent::setUp();

        $this->kasir = User::factory()->role(UserRole::Kasir)->create();
        $this->kopi = MenuItem::factory()->create(['name' => 'Kopi Susu Aren', 'price' => 20000]);
        $this->meja = CafeTable::factory()->create(['label' => '04']);
    }

    public function test_kasir_dapat_melihat_halaman_pos(): void
    {
        $this->withoutVite()->actingAs($this->kasir)
            ->getJson('/pos')
            ->assertOk()
            ->assertJsonPath('data.menuItems.0.name', 'Kopi Susu Aren')
            ->assertJsonPath('data.lines', []);
    }

    public function test_halaman_pos_merender_cangkang_spa(): void
    {
        $this->withoutVite()->actingAs($this->kasir)
            ->get('/pos')
            ->assertOk()
            ->assertSee('id="app"', false)
            ->assertSee('data-props=', false)
            ->assertDontSee('Tagihan Pesanan');
    }

    public function test_kasir_dapat_menambah_item_ke_keranjang(): void
    {
        $this->actingAs($this->kasir)
            ->post('/pos/keranjang', [
                'menu_item_id' => $this->kopi->id,
                'quantity' => 2,
                'channel' => 'dine_in',
                'table_label' => '04',
            ])
            ->assertRedirect('/pos')
            ->assertSessionHas('pos.cart');

        $this->actingAs($this->kasir)
            ->getJson('/pos')
            ->assertOk()
            ->assertJsonPath('data.lines.0.menu_item.name', 'Kopi Susu Aren')
            ->assertJsonPath('data.lines.0.quantity', 2);
    }

    public function test_checkout_membuat_order_dan_order_item_snapshot(): void
    {
        // Isi keranjang
        $this->actingAs($this->kasir)
            ->post('/pos/keranjang', [
                'menu_item_id' => $this->kopi->id,
                'quantity' => 2,
                'channel' => 'dine_in',
                'table_label' => '04',
            ]);

        $response = $this->actingAs($this->kasir)->withoutVite()
            ->post('/pos/checkout', [
                'channel' => 'dine_in',
                'table_label' => '04',
                'note' => 'Less sugar',
            ]);

        $order = Order::query()->first();
        $this->assertNotNull($order);
        $this->assertSame(OrderStatus::Baru, $order->status);
        $this->assertSame(OrderChannel::DineIn, $order->channel);
        $this->assertSame('04', $order->table_label);
        $this->assertSame(40000, $order->subtotal);

        // PB1 10% = 4000, Service 5% = 2000, Total = 46000
        $this->assertSame(4000, $order->tax);
        $this->assertSame(2000, $order->service);
        $this->assertSame(46000, $order->total);

        $item = $order->items->first();
        $this->assertSame('Kopi Susu Aren', $item->name);
        $this->assertSame(20000, $item->unit_price);
        $this->assertSame(2, $item->quantity);
        $this->assertSame(40000, $item->line_total);

        $response->assertRedirect("/pesanan/{$order->id}");
        $response->assertSessionMissing('pos.cart');
        $this->assertCount(1, $order->items);
        $this->assertCount(0, $order->payments);
    }

    public function test_menu_habis_tidak_dapat_dicheckout(): void
    {
        $habis = MenuItem::factory()->unavailable()->create(['name' => 'Croissant Habis']);

        $this->actingAs($this->kasir)
            ->post('/pos/keranjang', [
                'menu_item_id' => $habis->id,
                'quantity' => 1,
                'channel' => 'takeaway',
            ])
            ->assertSessionHasErrors('menu_item_id');
    }

    public function test_checkout_gagal_jika_keranjang_kosong(): void
    {
        $this->actingAs($this->kasir)
            ->post('/pos/checkout', ['channel' => 'takeaway'])
            ->assertSessionHasErrors('cart');
    }

    public function test_keranjang_dapat_dikosongkan(): void
    {
        $this->actingAs($this->kasir)
            ->post('/pos/keranjang', [
                'menu_item_id' => $this->kopi->id,
                'quantity' => 1,
                'channel' => 'takeaway',
            ]);

        $this->actingAs($this->kasir)
            ->deleteJson('/pos/keranjang')
            ->assertOk()
            ->assertJsonPath('data.lines', []);
    }
}
