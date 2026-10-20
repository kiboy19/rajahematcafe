<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\WalletTransactionType;
use App\Models\CashierSession;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WalletTest extends TestCase
{
    use RefreshDatabase;

    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cashier = User::factory()->role(UserRole::Kasir)->create();
    }

    private function openShift(): CashierSession
    {
        $this->actingAs($this->cashier)
            ->post('/sesi-kasir/buka', ['opening_float' => 100000])
            ->assertRedirect('/pos');

        return CashierSession::query()->latest('id')->firstOrFail();
    }

    public function test_kasir_dapat_topup_tunai_dan_sesi_menghitungnya_sebagai_kas(): void
    {
        $session = $this->openShift();
        $customer = Customer::factory()->create(['phone' => '081234567890']);

        $this->actingAs($this->cashier)
            ->post('/saldo/topup', ['phone' => '+62 812-3456-7890', 'amount' => 50000])
            ->assertRedirect('/saldo?hp=081234567890');

        $customer->refresh();
        $session->refresh();

        $this->assertSame(50000, $customer->balance);
        $this->assertSame(50000, app(WalletService::class)->ledgerBalance($customer));
        $this->assertSame(150000, $session->expectedCashTotal());
        $this->assertDatabaseHas('wallet_transactions', [
            'customer_id' => $customer->id,
            'type' => WalletTransactionType::TopUp->value,
            'amount' => 50000,
            'cashier_session_id' => $session->id,
        ]);
    }

    public function test_tanpa_sesi_topup_ditolak(): void
    {
        $customer = Customer::factory()->create(['phone' => '081234567890']);

        $this->actingAs($this->cashier)
            ->post('/saldo/topup', ['phone' => $customer->phone, 'amount' => 50000])
            ->assertRedirect('/saldo')
            ->assertSessionHasErrors('amount');

        $this->assertSame(0, $customer->fresh()->balance);
    }

    public function test_batas_topup_harian_ditolak_tanpa_mutasi(): void
    {
        config()->set('pos.wallet_daily_topup_limit', 60000);
        $session = $this->openShift();
        $customer = Customer::factory()->create(['phone' => '081234567890']);

        $wallet = app(WalletService::class);

        $wallet->credit($customer, WalletTransactionType::TopUp, 50000, $session, $this->cashier);

        $this->actingAs($this->cashier)
            ->from('/saldo?hp='.$customer->phone)
            ->post('/saldo/topup', ['phone' => $customer->phone, 'amount' => 20000])
            ->assertSessionHasErrors('amount');

        $this->assertSame(50000, $customer->fresh()->balance);
        $this->assertSame(1, WalletTransaction::query()->count());
    }

    public function test_pembayaran_saldo_mengurangi_saldo_dan_mengaitkan_pesanan(): void
    {
        $session = $this->openShift();
        $customer = Customer::factory()->create(['phone' => '081234567890']);

        $wallet = app(WalletService::class);
        $wallet->credit($customer, WalletTransactionType::TopUp, 60000, $session, $this->cashier);
        $order = Order::factory()->create(['total' => 46000, 'subtotal' => 46000]);

        $this->actingAs($this->cashier)
            ->post(route('orders.pay', $order), [
                'method' => 'wallet',
                'amount' => 46000,
                'customer_phone' => '+6281234567890',
            ])
            ->assertRedirect(route('orders.show', $order));

        $customer->refresh();
        $order->refresh();
        $this->assertSame(14000, $customer->balance);
        $this->assertSame($customer->id, $order->customer_id);
        $this->assertTrue($order->isFullyPaid());
        $this->assertDatabaseHas('wallet_transactions', [
            'customer_id' => $customer->id,
            'type' => WalletTransactionType::Payment->value,
            'amount' => -46000,
        ]);
        $this->assertSame(160000, $session->fresh()->expectedCashTotal());
    }

    public function test_saldo_tidak_cukup_tidak_membuat_pembayaran(): void
    {
        $session = $this->openShift();
        $customer = Customer::factory()->create(['phone' => '081234567890']);

        app(WalletService::class)->credit($customer, WalletTransactionType::TopUp, 10000, $session, $this->cashier);
        $order = Order::factory()->create(['total' => 46000, 'subtotal' => 46000]);

        $this->actingAs($this->cashier)
            ->from(route('orders.show', $order))
            ->post(route('orders.pay', $order), [
                'method' => 'wallet', 'amount' => 46000, 'customer_phone' => $customer->phone,
            ])
            ->assertSessionHasErrors('amount');

        $this->assertSame(10000, $customer->fresh()->balance);
        $this->assertSame(0, Payment::query()->count());
    }

    public function test_void_pembayaran_saldo_mengembalikan_saldo_dengan_ledger(): void
    {
        $session = $this->openShift();
        $customer = Customer::factory()->create(['phone' => '081234567890']);

        $wallet = app(WalletService::class);
        $wallet->credit($customer, WalletTransactionType::TopUp, 60000, $session, $this->cashier);
        $order = Order::factory()->create(['total' => 46000, 'subtotal' => 46000]);

        $this->actingAs($this->cashier)->post(route('orders.pay', $order), [
            'method' => 'wallet', 'amount' => 46000, 'customer_phone' => $customer->phone,
        ]);
        $payment = Payment::query()->firstOrFail();

        $owner = User::factory()->role(UserRole::Owner)->create();
        $this->actingAs($owner)
            ->delete(route('orders.pay.void', [$order, $payment]), ['reason' => 'Salah pesanan'])
            ->assertRedirect(route('orders.show', $order));

        $customer->refresh();
        $this->assertSame(60000, $customer->balance);
        $this->assertSame(60000, $wallet->ledgerBalance($customer));
        $this->assertDatabaseHas('wallet_transactions', [
            'customer_id' => $customer->id,
            'type' => WalletTransactionType::Refund->value,
            'amount' => 46000,
        ]);
        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'void']);
    }

    public function test_membayar_dengan_saldo_pelanggan_lain_ditolak(): void
    {
        $this->openShift();
        $linkedCustomer = Customer::factory()->create(['phone' => '081234567890']);
        $otherCustomer = Customer::factory()->create(['phone' => '081200009999']);
        $order = Order::factory()->create(['customer_id' => $linkedCustomer->id, 'total' => 10000, 'subtotal' => 10000]);

        $this->actingAs($this->cashier)
            ->from(route('orders.show', $order))
            ->post(route('orders.pay', $order), [
                'method' => 'wallet', 'amount' => 10000, 'customer_phone' => $otherCustomer->phone,
            ])
            ->assertSessionHasErrors('customer_phone');

        $this->assertSame(0, Payment::query()->count());
    }
}
