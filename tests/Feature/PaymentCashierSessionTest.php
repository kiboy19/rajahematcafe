<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\CashierSession;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentCashierSessionTest extends TestCase
{
    use RefreshDatabase;

    private User $kasir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->kasir = User::factory()->role(UserRole::Kasir)->create();
    }

    private function openShift(int $openingFloat = 100000): CashierSession
    {
        $this->actingAs($this->kasir)
            ->post('/sesi-kasir/buka', ['opening_float' => $openingFloat])
            ->assertRedirect('/pos');

        return CashierSession::query()->latest('id')->firstOrFail();
    }

    private function order(int $total = 46000): Order
    {
        return Order::factory()->create(['total' => $total, 'subtotal' => $total]);
    }

    public function test_kasir_membuka_sesi_dengan_modal_awal(): void
    {
        $session = $this->openShift(200000);

        $this->assertSame('open', $session->status);
        $this->assertSame(200000, $session->opening_float);
        $this->assertSame($this->kasir->id, $session->cashier_id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'cashier_session.opened']);
    }

    public function test_sesi_tidak_dapat_dibuka_dua_kali(): void
    {
        $this->openShift();

        $this->actingAs($this->kasir)
            ->post('/sesi-kasir/buka', ['opening_float' => 50000])
            ->assertSessionHasErrors('opening_float');

        $this->assertSame(1, CashierSession::query()->count());
    }

    public function test_pembayaran_tunai_menghitung_kembalian_dan_melunasi_pesanan(): void
    {
        $this->openShift();
        $order = $this->order(46000);

        $this->actingAs($this->kasir)
            ->post("/pesanan/{$order->id}/bayar", [
                'method' => 'cash',
                'amount' => 46000,
                'tendered' => 50000,
            ])
            ->assertRedirect("/pesanan/{$order->id}")
            ->assertSessionHas('success');

        $payment = Payment::query()->firstOrFail();
        $this->assertSame(46000, $payment->amount);
        $this->assertSame(50000, $payment->tendered);
        $this->assertSame(4000, $payment->change);
        $this->assertSame(PaymentStatus::Paid, $payment->status);
        $this->assertNotNull($payment->cashier_session_id);
        $this->assertNotNull($order->fresh()->paid_at);
        $this->assertTrue($order->fresh()->isFullyPaid());

        $this->actingAs($this->kasir)
            ->getJson("/pesanan/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.order.payments.0.amount', 46000)
            ->assertJsonPath('data.order.payments.0.change', 4000);
    }

    public function test_pembayaran_ditolak_tanpa_sesi_kasir_terbuka(): void
    {
        $order = $this->order();

        $this->actingAs($this->kasir)
            ->post("/pesanan/{$order->id}/bayar", ['method' => 'cash', 'amount' => 46000, 'tendered' => 46000])
            ->assertRedirect('/sesi-kasir')
            ->assertSessionHasErrors('session');

        $this->assertDatabaseCount('payments', 0);
        $this->assertNull($order->fresh()->paid_at);
    }

    public function test_nominal_melebihi_sisa_tagihan_ditolak(): void
    {
        $this->openShift();
        $order = $this->order(46000);

        $this->actingAs($this->kasir)
            ->post("/pesanan/{$order->id}/bayar", ['method' => 'cash', 'amount' => 50000, 'tendered' => 50000])
            ->assertSessionHasErrors('amount');

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_uang_tunai_kurang_dari_nominal_ditolak(): void
    {
        $this->openShift();
        $order = $this->order(46000);

        $this->actingAs($this->kasir)
            ->post("/pesanan/{$order->id}/bayar", ['method' => 'cash', 'amount' => 46000, 'tendered' => 40000])
            ->assertSessionHasErrors('tendered');

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_pembayaran_qris_wajib_referensi(): void
    {
        $this->openShift();
        $order = $this->order();

        $this->actingAs($this->kasir)
            ->post("/pesanan/{$order->id}/bayar", ['method' => 'qris', 'amount' => 46000])
            ->assertSessionHasErrors('reference');

        $this->actingAs($this->kasir)
            ->post("/pesanan/{$order->id}/bayar", ['method' => 'qris', 'amount' => 46000, 'reference' => 'QR-001'])
            ->assertRedirect("/pesanan/{$order->id}");

        $payment = Payment::query()->firstOrFail();
        $this->assertSame('QR-001', $payment->reference);
        $this->assertSame(0, $payment->change);
        $this->assertNull($payment->tendered);
    }

    public function test_pembayaran_terpisah_diizinkan_sampai_lunas(): void
    {
        $this->openShift();
        $order = $this->order(46000);

        $this->actingAs($this->kasir)
            ->post("/pesanan/{$order->id}/bayar", ['method' => 'cash', 'amount' => 20000, 'tendered' => 20000]);

        $this->assertNull($order->fresh()->paid_at);
        $this->assertSame(26000, $order->fresh()->remainingBalance());

        $this->actingAs($this->kasir)
            ->post("/pesanan/{$order->id}/bayar", ['method' => 'qris', 'amount' => 26000, 'reference' => 'QR-002']);

        $this->assertNotNull($order->fresh()->paid_at);
        $this->assertSame(0, $order->fresh()->remainingBalance());

        $this->actingAs($this->kasir)
            ->post("/pesanan/{$order->id}/bayar", ['method' => 'cash', 'amount' => 1000, 'tendered' => 1000])
            ->assertSessionHasErrors('amount');
    }

    public function test_tutup_kas_menghitung_ekspektasi_dan_selisih(): void
    {
        $this->openShift(100000);
        $order = $this->order(46000);

        $this->actingAs($this->kasir)
            ->post("/pesanan/{$order->id}/bayar", ['method' => 'cash', 'amount' => 46000, 'tendered' => 50000]);

        $this->actingAs($this->kasir)
            ->post('/sesi-kasir/tutup', ['counted_cash' => 146000, 'closing_note' => null])
            ->assertRedirect('/sesi-kasir');

        $session = CashierSession::query()->firstOrFail();
        $this->assertSame('closed', $session->status);
        $this->assertSame(146000, $session->expected_cash);
        $this->assertSame(146000, $session->counted_cash);
        $this->assertSame(0, $session->variance);
        $this->assertNotNull($session->closed_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'cashier_session.closed']);
    }

    public function test_pembayaran_non_tunai_tidak_menambah_uang_laci(): void
    {
        $this->openShift(100000);
        $order = $this->order(46000);

        $this->actingAs($this->kasir)
            ->post("/pesanan/{$order->id}/bayar", ['method' => 'qris', 'amount' => 46000, 'reference' => 'QR-003']);

        $this->assertSame(0, CashierSession::query()->firstOrFail()->cashSalesTotal());
        $this->assertSame(100000, CashierSession::query()->firstOrFail()->expectedCashTotal());
    }

    public function test_selisih_kas_wajib_disertai_catatan(): void
    {
        $this->openShift(100000);

        $this->actingAs($this->kasir)
            ->post('/sesi-kasir/tutup', ['counted_cash' => 95000])
            ->assertSessionHasErrors('closing_note');

        $this->assertSame('open', CashierSession::query()->firstOrFail()->status);

        $this->actingAs($this->kasir)
            ->post('/sesi-kasir/tutup', ['counted_cash' => 95000, 'closing_note' => 'Uang hilang saat tutup']);

        $session = CashierSession::query()->firstOrFail();
        $this->assertSame(-5000, $session->variance);
        $this->assertSame('Uang hilang saat tutup', $session->closing_note);
    }

    public function test_sesi_yang_sudah_ditutup_tidak_dapat_ditutup_lagi(): void
    {
        $this->openShift(100000);

        $this->actingAs($this->kasir)
            ->post('/sesi-kasir/tutup', ['counted_cash' => 100000]);

        $this->actingAs($this->kasir)
            ->post('/sesi-kasir/tutup', ['counted_cash' => 100000])
            ->assertSessionHasErrors('counted_cash');

        $this->assertSame(1, CashierSession::query()->count());
    }

    public function test_dapur_tidak_dapat_menerima_pembayaran(): void
    {
        $this->openShift();
        $order = $this->order();
        $dapur = User::factory()->role(UserRole::Dapur)->create();

        $this->actingAs($dapur)
            ->post("/pesanan/{$order->id}/bayar", ['method' => 'cash', 'amount' => 46000, 'tendered' => 46000])
            ->assertForbidden();

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_audit_log_mencatat_pembayaran_diterima(): void
    {
        $this->openShift();
        $order = $this->order();

        $this->actingAs($this->kasir)
            ->post("/pesanan/{$order->id}/bayar", ['method' => 'cash', 'amount' => 46000, 'tendered' => 46000]);

        $log = AuditLog::query()->where('action', 'payment.received')->firstOrFail();
        $this->assertSame(Payment::class, $log->subject_type);
        $this->assertSame($order->id, $log->after['order_id']);
        $this->assertSame($this->kasir->id, $log->actor_id);
    }

    public function test_halaman_struk_dapat_dibuka(): void
    {
        $this->openShift();
        $order = $this->order(46000);

        $this->actingAs($this->kasir)
            ->post("/pesanan/{$order->id}/bayar", ['method' => 'cash', 'amount' => 46000, 'tendered' => 50000]);

        // Halaman struk kini dirender React dari cangkang SPA.
        $this->actingAs($this->kasir)
            ->get("/pesanan/{$order->id}/struk")
            ->assertOk()
            ->assertSee('id="app"', false)
            ->assertSee('data-props=', false);

        // Data struk untuk canvas cetak diambil dari endpoint JSON yang sama.
        $this->actingAs($this->kasir)
            ->getJson("/pesanan/{$order->id}/struk")
            ->assertOk()
            ->assertJsonPath('data.number', $order->number)
            ->assertJsonPath('data.payments.0.amount', 46000)
            ->assertJsonPath('data.payments.0.change', 4000);
    }

    public function test_manager_dapat_melihat_laporan_harian_dan_kasir_dilarang(): void
    {
        $manager = User::factory()->role(UserRole::Manager)->create();

        $this->openShift();
        $order = $this->order(46000);
        $this->actingAs($this->kasir)->post("/pesanan/{$order->id}/bayar", [
            'method' => 'cash',
            'amount' => 46000,
            'tendered' => 50000,
        ]);

        $this->actingAs($manager)
            ->getJson('/laporan/harian')
            ->assertOk()
            ->assertJsonPath('data.grossSales', 46000)
            ->assertJsonPath('data.paymentsByMethod.0.total', 46000);

        $this->actingAs($this->kasir)
            ->get('/laporan/harian')
            ->assertForbidden();
    }
}
