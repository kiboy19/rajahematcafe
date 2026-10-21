<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\CafeTable;
use App\Models\User;
use App\Services\QrCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QrCodePrintTest extends TestCase
{
    use RefreshDatabase;

    public function test_svg_qr_valid_dan_ukuran_modul_sesuai_permintaan(): void
    {
        $svg = app(QrCodeService::class)->svg('https://rajahemat.test/m/abc');

        $this->assertStringStartsWith('<?xml', trim($svg));
        $this->assertStringContainsString('<svg', $svg);
        $this->assertStringContainsString('width="280"', $svg);
        // Backend SVG menggabungkan semua modul menjadi satu path; pastikan modul
        // benar-benar berisi data, bukan SVG kosong/placeholder.
        $this->assertStringContainsString('fill-rule="evenodd"', $svg);
        $this->assertGreaterThan(1000, strlen($svg));
    }

    public function test_svg_qr_berbeda_untuk_url_berbeda(): void
    {
        $service = app(QrCodeService::class);

        $this->assertNotSame($service->svg('https://a.test'), $service->svg('https://b.test'));
    }

    public function test_gambar_qr_meja_dikirim_sebagai_svg(): void
    {
        $owner = User::factory()->role(UserRole::Owner)->create();
        $table = CafeTable::factory()->create(['label' => 'Meja 01']);

        $response = $this->actingAs($owner)->get(route('tables.qr.image', $table));

        $response->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml');

        $this->assertStringContainsString('<svg', $response->getContent());
    }

    public function test_lembar_cetak_menampilkan_setiap_meja_dengan_gambar_qrnya(): void
    {
        $owner = User::factory()->role(UserRole::Owner)->create();
        $tableA = CafeTable::factory()->create(['label' => 'Meja 01']);
        $tableB = CafeTable::factory()->create(['label' => 'Meja 02']);

        // Halaman cetak kini dirender React dari cangkang SPA.
        $this->actingAs($owner)
            ->get(route('tables.qr.print'))
            ->assertOk()
            ->assertSee('id="app"', false)
            ->assertSee('data-props=', false);

        // Data kartu QR diambil dari endpoint JSON, termasuk URL gambar SVG per meja.
        $this->actingAs($owner)
            ->getJson(route('api.tables.qr.print'))
            ->assertOk()
            ->assertJsonPath('data.tables.0.label', $tableA->label)
            ->assertJsonPath('data.tables.1.label', $tableB->label)
            ->assertJsonPath('data.tables.0.scan_url', route('customer.table.scan', $tableA->qr_token))
            ->assertJsonPath('data.tables.1.scan_url', route('customer.table.scan', $tableB->qr_token));
    }

    public function test_kasir_dan_dapur_dilarang_mencetak_qr(): void
    {
        $table = CafeTable::factory()->create();

        $this->actingAs(User::factory()->role(UserRole::Kasir)->create())
            ->get(route('tables.qr.print'))
            ->assertForbidden();

        $this->actingAs(User::factory()->role(UserRole::Dapur)->create())
            ->get(route('tables.qr.image', $table))
            ->assertForbidden();
    }

    public function test_tamu_tidak_dapat_membuka_gambar_qr_staf(): void
    {
        $table = CafeTable::factory()->create();

        $this->get(route('tables.qr.image', $table))->assertRedirect(route('login'));
        $this->get(route('tables.qr.print'))->assertRedirect(route('login'));
    }

    public function test_gambar_qr_mengikuti_token_setelah_rotasi(): void
    {
        $owner = User::factory()->role(UserRole::Owner)->create();
        $table = CafeTable::factory()->create();
        $oldSvg = $this->actingAs($owner)->get(route('tables.qr.image', $table))->getContent();

        $this->actingAs($owner)->post(route('tables.qr.rotate', $table));

        $newSvg = $this->actingAs($owner)->get(route('tables.qr.image', $table->fresh()))->getContent();

        $this->assertNotSame($oldSvg, $newSvg);
    }

    public function test_halaman_qr_pelanggan_tetap_menolak_token_lama_setelah_dicetak(): void
    {
        $table = CafeTable::factory()->create();
        $oldToken = $table->qr_token;
        $table->rotateQrToken();

        $this->get(route('customer.table.scan', $oldToken))->assertNotFound();
        $this->get(route('customer.table.scan', $table->fresh()->qr_token))
            ->assertRedirect(route('customer.order.menu'));
    }
}
