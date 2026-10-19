<?php

namespace Tests\Unit;

use App\Services\CashierSessionService;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

class CashReconciliationTest extends TestCase
{
    private CashierSessionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new CashierSessionService;
    }

    public function test_ekspektasi_kas_adalah_modal_ditambah_penjualan_tunai(): void
    {
        $this->assertSame(200000, $this->service->calculateExpectedCash(200000, 0));
        $this->assertSame(73000, $this->service->calculateExpectedCash(30000, 43000));
    }

    // Top-up, penarikan, dan refund tunai menyusul rilis wallet (R1.1)
    #[TestWith([20000, 0, 0, 100000])]
    #[TestWith([0, 30000, 0, 150000])]
    #[TestWith([0, 0, 50000, 70000])]
    public function test_komponen_rilis_wallet_mempengaruhi_ekspektasi(
        int $cashRefunds,
        int $cashTopups,
        int $cashWithdrawals,
        int $expectedCash,
    ): void {
        $this->assertSame(
            $expectedCash,
            $this->service->calculateExpectedCash(50000, 70000, $cashRefunds, $cashTopups, $cashWithdrawals),
        );
    }

    public function test_selisih_adalah_hitungan_fisik_dikurangi_ekspektasi(): void
    {
        $this->assertSame(0, $this->service->calculateVariance(73000, 73000));
        $this->assertSame(-5000, $this->service->calculateVariance(73000, 68000));
        $this->assertSame(2000, $this->service->calculateVariance(73000, 75000));
    }

    public function test_tolak_komponen_negatif(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->calculateExpectedCash(-1, 0);
    }

    public function test_tolak_hitungan_fisik_negatif(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->calculateVariance(0, -1);
    }
}
