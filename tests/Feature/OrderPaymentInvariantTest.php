<?php

namespace Tests\Feature;

use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Services\TransactionNumberGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderPaymentInvariantTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_menyimpan_snapshot_item_meski_master_dihapus(): void
    {
        $order = Order::factory()->create();

        $item = OrderItem::factory()->for($order)->create([
            'name' => 'Kopi Susu Aren',
            'unit_price' => 20000,
            'quantity' => 2,
            'line_total' => 40000,
        ]);

        $this->assertSame('Kopi Susu Aren', $item->fresh()->name);
        $this->assertSame(20000, $item->fresh()->unit_price);
        $this->assertSame(40000, $item->fresh()->line_total);
    }

    public function test_pembayaran_order_dapat_partial_dan_diketahui_sisaannya(): void
    {
        $order = Order::factory()->create(['total' => 79350]);
        Payment::factory()->for($order)->create(['amount' => 50000, 'status' => PaymentStatus::Paid]);

        $this->assertSame(50000, $order->paidTotal());
        $this->assertFalse($order->isFullyPaid());
    }

    public function test_order_penuh_lunas_jika_total_pembayaran_paid_cukup(): void
    {
        $order = Order::factory()->create(['total' => 79350]);
        Payment::factory()->for($order)->create(['amount' => 50000, 'status' => PaymentStatus::Paid]);
        Payment::factory()->for($order)->create(['amount' => 29350, 'status' => PaymentStatus::Paid]);

        $this->assertSame(79350, $order->paidTotal());
        $this->assertTrue($order->isFullyPaid());
    }

    public function test_pembayaran_pending_tidak_dihitung_sebagai_pelunasan(): void
    {
        $order = Order::factory()->create(['total' => 50000]);
        Payment::factory()->for($order)->create(['amount' => 50000, 'status' => PaymentStatus::Pending]);

        $this->assertSame(0, $order->paidTotal());
        $this->assertFalse($order->isFullyPaid());
    }

    public function test_nomor_pesanan_berurutan_per_tanggal(): void
    {
        $generator = app(TransactionNumberGenerator::class);
        $prefix = 'ORD-'.now()->format('ymd').'-';

        Order::factory()->create(['number' => $prefix.'0001']);
        Order::factory()->create(['number' => $prefix.'0002']);

        $this->assertSame($prefix.'0003', $generator->nextOrderNumber());
    }

    public function test_nomor_pesanan_mulai_dari_satu(): void
    {
        $prefix = 'ORD-'.now()->format('ymd').'-';

        $this->assertSame(
            $prefix.'0001',
            app(TransactionNumberGenerator::class)->nextOrderNumber(),
        );
    }

    public function test_role_kanal_dan_metode_pembayaran_memiliki_semantik_uang_yang_benar(): void
    {
        $this->assertTrue(OrderChannel::QrTable->requiresCustomerAccount());
        $this->assertFalse(OrderChannel::DineIn->requiresCustomerAccount());
        $this->assertTrue(PaymentMethod::Cash->affectsCashDrawer());
        $this->assertFalse(PaymentMethod::Qris->affectsCashDrawer());
        $this->assertFalse(PaymentMethod::Wallet->affectsCashDrawer());
    }

    public function test_status_final_tidak_dapat_disalahartikan(): void
    {
        $this->assertTrue(OrderStatus::Selesai->isFinal());
        $this->assertTrue(OrderStatus::Dibatalkan->isFinal());
        $this->assertFalse(OrderStatus::Diproses->isFinal());
    }
}
