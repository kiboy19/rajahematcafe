<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\User;
use App\Services\VoucherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CancelOrderTest extends TestCase
{
    use RefreshDatabase;

    private function orderedOrder(): Order
    {
        $order = Order::factory()->create(['status' => OrderStatus::Baru]);
        OrderItem::factory()->for($order)->create(['name' => 'Kopi Susu Aren', 'quantity' => 2]);

        return $order;
    }

    public function test_dapur_tidak_boleh_membatalkan_pesanan(): void
    {
        $order = $this->orderedOrder();
        $dapur = User::factory()->role(UserRole::Dapur)->create();

        $this->actingAs($dapur)
            ->patch("/kds/{$order->id}", ['status' => OrderStatus::Dibatalkan->value])
            ->assertSessionHasErrors('status');

        $this->assertSame(OrderStatus::Baru, $order->fresh()->status);
    }

    public function test_manager_membatalkan_pesanan_aktif_dengan_alasan_dan_voucher_dirilis(): void
    {
        $order = $this->orderedOrder();
        $voucherService = $this->mock(VoucherService::class);
        $voucherService->shouldReceive('release')->once()->with($order->id);
        $this->app->instance(VoucherService::class, $voucherService);

        $manager = User::factory()->role(UserRole::Manager)->create();

        $this->actingAs($manager)
            ->patch("/kds/{$order->id}", ['status' => OrderStatus::Dibatalkan->value, 'reason' => 'Pesanan salah meja'])
            ->assertSessionHasNoErrors();

        $order->refresh();
        $this->assertSame(OrderStatus::Dibatalkan, $order->status);
        $this->assertNull($order->closed_at);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'order.cancelled',
            'subject_id' => $order->id,
            'actor_id' => $manager->id,
        ]);
    }

    public function test_pesanan_yang_sudah_dibayar_tidak_dapat_dibatalkan_lewat_kds(): void
    {
        $order = $this->orderedOrder();
        Payment::factory()->for($order)->create([
            'method' => PaymentMethod::Cash,
            'status' => PaymentStatus::Paid,
            'amount' => 20000,
        ]);
        $order->update(['paid_at' => now()]);

        $manager = User::factory()->role(UserRole::Manager)->create();

        $this->actingAs($manager)
            ->patch("/kds/{$order->id}", ['status' => OrderStatus::Dibatalkan->value, 'reason' => 'Pelanggan berubah pikiran'])
            ->assertSessionHasErrors('status');

        $this->assertSame(OrderStatus::Baru, $order->fresh()->status);
    }

    public function test_pembatalan_wajib_menyertakan_alasan(): void
    {
        $order = $this->orderedOrder();
        $manager = User::factory()->role(UserRole::Manager)->create();

        $this->actingAs($manager)
            ->patch("/kds/{$order->id}", ['status' => OrderStatus::Dibatalkan->value])
            ->assertSessionHasErrors('reason');

        $this->assertSame(OrderStatus::Baru, $order->fresh()->status);
    }

    public function test_dasbor_tidak_lagi_menampilkan_peringatan_modul_belum_tersedia(): void
    {
        $owner = User::factory()->role(UserRole::Owner)->create();

        $this->actingAs($owner)
            ->get('/dashboard')
            ->assertOk()
            ->assertDontSee('Modul transaksi belum tersedia');
    }
}
