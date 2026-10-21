<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CafeTable;
use App\Models\Customer;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QrTokenRotateTest extends TestCase
{
    use RefreshDatabase;

    private function user(array $attributes): User
    {
        return User::factory()->create($attributes);
    }

    public function test_owner_can_rotate_qr_token_and_old_token_becomes_invalid(): void
    {
        $owner = $this->user(['role' => 'owner']);
        $table = CafeTable::factory()->create(['label' => 'Meja 01', 'is_active' => true]);
        $oldToken = $table->qr_token;
        $oldUrl = route('customer.table.scan', $oldToken);

        // Pemutaran token
        $this->actingAs($owner)
            ->post(route('tables.qr.rotate', $table))
            ->assertRedirect(route('tables.qr'))
            ->assertSessionHas('success');

        $table->refresh();
        $this->assertNotSame($oldToken, $table->qr_token);

        // Token lama 404
        $this->get($oldUrl)->assertNotFound();

        // Token baru berfungsi lagi
        $this->get(route('customer.table.scan', $table->qr_token))
            ->assertRedirect(route('customer.order.menu'));

        // Audit log: token disimpan sebagai sidik jari, bukan token mentah.
        $log = AuditLog::query()->where('action', 'table.qr_rotated')->firstOrFail();
        $this->assertSame($table->id, $log->subject_id);
        $this->assertSame($owner->id, $log->actor_id);
        $this->assertSame(hash('sha256', $oldToken), $log->before['token_fingerprint']);
        $this->assertSame(hash('sha256', $table->qr_token), $log->after['token_fingerprint']);
        $this->assertStringNotContainsString($oldToken, json_encode($log->before).json_encode($log->after));
    }

    public function test_kasir_cannot_access_qr_rotate(): void
    {
        $kasir = $this->user(['role' => 'kasir']);
        $table = CafeTable::factory()->create();

        $this->actingAs($kasir)
            ->post(route('tables.qr.rotate', $table))
            ->assertForbidden();
    }

    public function test_dapur_cannot_access_qr_rotate(): void
    {
        $dapur = $this->user(['role' => 'dapur']);
        $table = CafeTable::factory()->create();

        $this->actingAs($dapur)
            ->post(route('tables.qr.rotate', $table))
            ->assertForbidden();
    }

    public function test_guest_cannot_access_qr_rotate(): void
    {
        $table = CafeTable::factory()->create();

        $this->post(route('tables.qr.rotate', $table))
            ->assertRedirect(route('login'));
    }

    public function test_new_token_allows_qr_ordering_flow(): void
    {
        $owner = $this->user(['role' => 'owner']);
        $customer = Customer::factory()->create();
        $item = MenuItem::factory()->create(['price' => 20000, 'is_available' => true]);
        $table = CafeTable::factory()->create(['label' => 'Meja 05', 'is_active' => true]);
        $oldToken = $table->qr_token;

        // Rotate
        $this->actingAs($owner)
            ->post(route('tables.qr.rotate', $table));
        $table->refresh();
        $newToken = $table->qr_token;
        $this->assertNotSame($oldToken, $newToken);

        // Scan baru → login pelanggan → menu → pesan
        $this->get(route('customer.table.scan', $newToken))
            ->assertRedirect(route('customer.order.menu'));

        $this->actingAs($customer, 'customer')
            ->getJson(route('customer.order.menu'))
            ->assertOk()
            ->assertJsonPath('data.categories.0.menu_items.0.name', $item->name);

        // Pesan via QR
        $this->actingAs($customer, 'customer')
            ->post(route('customer.order.cart.add'), ['menu_item_id' => $item->id]);

        $this->actingAs($customer, 'customer')
            ->post(route('customer.order.checkout'))
            ->assertRedirect();

        $order = Order::query()->firstOrFail();
        $this->assertSame($table->label, $order->table_label);
        $this->assertSame($customer->id, $order->customer_id);
    }
}
