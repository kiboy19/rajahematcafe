<?php

namespace Tests\Feature;

use App\Models\CashierSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashierSessionVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_melihat_sesi_kasir_global_yang_dibuka_kasir_lain(): void
    {
        $kasir = $this->staff('kasir');
        $owner = $this->staff('owner');

        $session = $this->openSessionFor($kasir, 200000);

        $this->actingAs($owner)->getJson('/api/sesi-kasir')
            ->assertOk()
            ->assertJsonPath('data.session', null)
            ->assertJsonPath('data.active_session.id', $session->id)
            ->assertJsonPath('data.active_session.cashier.name', $kasir->name)
            ->assertJsonPath('data.can_close', false)
            ->assertJsonPath('data.can_force_close', true)
            ->assertJsonPath('data.can_open', false);

        $this->actingAs($owner)->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('data.session.id', $session->id)
            ->assertJsonPath('data.session.cashier.name', $kasir->name);
    }

    public function test_kasir_lain_tidak_dapat_membuka_sesi_saat_sesi_aktif(): void
    {
        $kasirA = $this->staff('kasir');
        $kasirB = $this->staff('kasir');

        $this->openSessionFor($kasirA, 100000);

        $this->actingAs($kasirB)->postJson('/api/sesi-kasir/buka', ['opening_float' => 50000])
            ->assertStatus(422);

        $this->assertSame(1, CashierSession::query()->where('status', 'open')->count());
    }

    public function test_manager_dapat_menutup_paksa_sesi_kasir(): void
    {
        $kasir = $this->staff('kasir');
        $manager = $this->staff('manager');

        $session = $this->openSessionFor($kasir, 100000);

        $this->actingAs($manager)
            ->postJson("/api/sesi-kasir/{$session->id}/tutup-paksa", [
                'counted_cash' => 100000,
                'closing_note' => 'Kasir pulang lebih awal',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'closed');

        $session->refresh();
        $this->assertSame('closed', $session->status);
        $this->assertSame($manager->id, $session->closed_by);
        $this->assertDatabaseHas('audit_logs', ['action' => 'cashier_session.force_closed']);
    }

    public function test_penutupan_paksa_wajib_alasan_dan_ditolak_untuk_kasir(): void
    {
        $kasir = $this->staff('kasir');
        $session = $this->openSessionFor($kasir, 100000);

        $this->actingAs($kasir)
            ->postJson("/api/sesi-kasir/{$session->id}/tutup-paksa", ['counted_cash' => 100000, 'closing_note' => 'x'])
            ->assertForbidden();

        $this->actingAs($this->staff('owner'))
            ->postJson("/api/sesi-kasir/{$session->id}/tutup-paksa", ['counted_cash' => 100000])
            ->assertStatus(422)
            ->assertJsonValidationErrors('closing_note');
    }

    private function staff(string $role): User
    {
        return User::factory()->create(['role' => $role, 'is_active' => true]);
    }

    private function openSessionFor(User $kasir, int $openingFloat): CashierSession
    {
        return CashierSession::query()->create([
            'cashier_id' => $kasir->id,
            'opening_float' => $openingFloat,
            'expected_cash' => $openingFloat,
            'status' => 'open',
            'opened_by' => $kasir->id,
            'opened_at' => now(),
        ]);
    }
}
