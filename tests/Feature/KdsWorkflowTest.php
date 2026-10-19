<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KdsWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function orderWithStatus(OrderStatus $status): Order
    {
        $order = Order::factory()->create(['status' => $status]);
        OrderItem::factory()->for($order)->create(['name' => 'Kopi Susu Aren', 'quantity' => 2]);

        return $order;
    }

    public function test_dapur_hanya_melihat_pesanan_aktif(): void
    {
        $baru = $this->orderWithStatus(OrderStatus::Baru);
        $selesai = $this->orderWithStatus(OrderStatus::Selesai);
        $dapur = User::factory()->role(UserRole::Dapur)->create();

        $this->actingAs($dapur)->get('/kds')->assertOk()
            ->assertSee($baru->number)
            ->assertDontSee($selesai->number);
    }

    public function test_dapur_dapat_memajukan_status_sesuai_alur(): void
    {
        $order = $this->orderWithStatus(OrderStatus::Baru);
        $dapur = User::factory()->role(UserRole::Dapur)->create();

        $this->actingAs($dapur)->patch("/kds/{$order->id}", ['status' => 'diproses'])->assertRedirect();
        $this->assertSame(OrderStatus::Diproses, $order->fresh()->status);

        $this->actingAs($dapur)->patch("/kds/{$order->id}", ['status' => 'siap']);
        $this->assertSame(OrderStatus::Siap, $order->fresh()->status);

        $this->actingAs($dapur)->patch("/kds/{$order->id}", ['status' => 'selesai']);
        $order->refresh();
        $this->assertSame(OrderStatus::Selesai, $order->status);
        $this->assertNotNull($order->closed_at);
    }

    public function test_lompat_status_ditolak(): void
    {
        $order = $this->orderWithStatus(OrderStatus::Baru);

        $this->actingAs(User::factory()->role(UserRole::Dapur)->create())
            ->patch("/kds/{$order->id}", ['status' => 'selesai'])
            ->assertSessionHasErrors('status');

        $this->assertSame(OrderStatus::Baru, $order->fresh()->status);
    }

    public function test_status_selesai_tidak_dapat_diubah_lagi(): void
    {
        $order = $this->orderWithStatus(OrderStatus::Selesai);

        $this->actingAs(User::factory()->role(UserRole::Dapur)->create())
            ->patch("/kds/{$order->id}", ['status' => 'diproses'])
            ->assertSessionHasErrors('status');

        $this->assertSame(OrderStatus::Selesai, $order->fresh()->status);
    }

    public function test_dapur_tidak_dapat_membatalkan_pesanan(): void
    {
        $order = $this->orderWithStatus(OrderStatus::Baru);

        $this->actingAs(User::factory()->role(UserRole::Dapur)->create())
            ->patch("/kds/{$order->id}", ['status' => 'dibatalkan'])
            ->assertSessionHasErrors('status');

        $this->assertSame(OrderStatus::Baru, $order->fresh()->status);
    }

    public function test_kasir_tidak_dapat_membuka_kds(): void
    {
        $this->actingAs(User::factory()->role(UserRole::Kasir)->create())
            ->get('/kds')
            ->assertForbidden();
    }

    public function test_dapur_tidak_dapat_membuka_pos(): void
    {
        $this->actingAs(User::factory()->role(UserRole::Dapur)->create())
            ->get('/pos')
            ->assertForbidden();
    }

    public function test_tamu_diarahkan_ke_login(): void
    {
        $this->get('/kds')->assertRedirect('/login');
    }
}
