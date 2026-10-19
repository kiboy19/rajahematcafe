<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\CashierSession;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HardeningSecurityTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $manager;

    private User $kasir;

    private User $dapur;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->role(UserRole::Owner)->create();
        $this->manager = User::factory()->role(UserRole::Manager)->create();
        $this->kasir = User::factory()->role(UserRole::Kasir)->create();
        $this->dapur = User::factory()->role(UserRole::Dapur)->create();
    }

    public function test_header_keamanan_dikirim_pada_setiap_respons(): void
    {
        $response = $this->get('/login');

        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_manager_dapat_membatalkan_pembayaran_dengan_alasan(): void
    {
        $order = Order::factory()->create(['total' => 20000, 'subtotal' => 20000, 'paid_at' => now()]);
        $payment = Payment::factory()->for($order)->create([
            'amount' => 20000,
            'status' => PaymentStatus::Paid,
        ]);

        $this->actingAs($this->manager)
            ->delete("/pesanan/{$order->id}/bayar/{$payment->id}", [
                'reason' => 'Salah input nominal kasir',
            ])
            ->assertRedirect("/pesanan/{$order->id}")
            ->assertSessionHas('success');

        $this->assertSame(PaymentStatus::Void, $payment->fresh()->status);
        $this->assertNull($order->fresh()->paid_at);
        $this->assertSame(20000, $order->fresh()->remainingBalance());

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'payment.voided',
            'subject_id' => $payment->id,
            'reason' => 'Salah input nominal kasir',
            'actor_id' => $this->manager->id,
        ]);
    }

    public function test_kasir_dan_dapur_dilarang_membatalkan_pembayaran(): void
    {
        $order = Order::factory()->create(['total' => 20000]);
        $payment = Payment::factory()->for($order)->create(['status' => PaymentStatus::Paid]);

        $this->actingAs($this->kasir)
            ->delete("/pesanan/{$order->id}/bayar/{$payment->id}", ['reason' => 'Kasir coba void'])
            ->assertForbidden();

        $this->actingAs($this->dapur)
            ->delete("/pesanan/{$order->id}/bayar/{$payment->id}", ['reason' => 'Dapur coba void'])
            ->assertForbidden();

        $this->assertSame(PaymentStatus::Paid, $payment->fresh()->status);
    }

    public function test_pembatalan_pembayaran_wajib_alasan_minimal_5_karakter(): void
    {
        $order = Order::factory()->create(['total' => 20000]);
        $payment = Payment::factory()->for($order)->create(['status' => PaymentStatus::Paid]);

        $this->actingAs($this->manager)
            ->delete("/pesanan/{$order->id}/bayar/{$payment->id}", ['reason' => 't'])
            ->assertSessionHasErrors('reason');

        $this->assertSame(PaymentStatus::Paid, $payment->fresh()->status);
    }

    public function test_manager_dapat_memverifikasi_selisih_sesi_kasir(): void
    {
        $session = CashierSession::factory()->closed(95000)->create([
            'cashier_id' => $this->kasir->id,
            'opening_float' => 100000,
            'variance' => -5000,
            'closing_note' => 'Kurang 5000 kembalian salah',
        ]);

        $this->actingAs($this->manager)
            ->post("/sesi-kasir/{$session->id}/verifikasi-selisih", [
                'approval_note' => 'Disetujui dipotong kas operasional',
            ])
            ->assertRedirect();

        $session->refresh();
        $this->assertSame($this->manager->id, $session->variance_verified_by);
        $this->assertNotNull($session->variance_verified_at);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'cashier_session.variance_verified',
            'actor_id' => $this->manager->id,
            'reason' => 'Disetujui dipotong kas operasional',
        ]);
    }

    public function test_kasir_dilarang_memverifikasi_selisih_sesinya_sendiri(): void
    {
        $session = CashierSession::factory()->closed(95000)->create([
            'cashier_id' => $this->kasir->id,
            'opening_float' => 100000,
            'variance' => -5000,
        ]);

        $this->actingAs($this->kasir)
            ->post("/sesi-kasir/{$session->id}/verifikasi-selisih", [
                'approval_note' => 'Saya verifikasi sendiri',
            ])
            ->assertForbidden();

        $this->assertNull($session->fresh()->variance_verified_at);
    }
}
