<?php

namespace Tests\Unit;

use App\Services\MoneyCalculator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class MoneyCalculatorTest extends TestCase
{
    private MoneyCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calculator = new MoneyCalculator;
    }

    public function test_subtotal_adalah_jumlah_harga_kali_kuantitas(): void
    {
        $bill = $this->calculator->calculateBill([
            ['price' => 20000, 'quantity' => 2],
            ['price' => 35000, 'quantity' => 1],
        ], taxPercent: 0, servicePercent: 0);

        $this->assertSame(75000, $bill['subtotal']);
        $this->assertSame(75000, $bill['grand_total']);
    }

    public function test_pajak_dan_service_dihitung_dari_subtotal_setelah_diskon(): void
    {
        $bill = $this->calculator->calculateBill(
            [['price' => 50000, 'quantity' => 2]],
            discount: 10000,
            taxPercent: 10,
            servicePercent: 5,
        );

        $this->assertSame(100000, $bill['subtotal']);
        $this->assertSame(10000, $bill['discount']);
        $this->assertSame(90000, $bill['taxable_base']);
        $this->assertSame(9000, $bill['tax']);
        $this->assertSame(4500, $bill['service']);
        $this->assertSame(103500, $bill['grand_total']);
    }

    public function test_semua_nilai_adalah_integer(): void
    {
        $bill = $this->calculator->calculateBill(
            [['price' => 33333, 'quantity' => 3]],
            discount: 0,
            taxPercent: 10,
            servicePercent: 5,
        );

        foreach ($bill as $key => $value) {
            $this->assertIsInt($value, "Nilai {$key} harus integer.");
        }
    }

    public function test_pembulatan_pajak_menggunakan_setengah_ke_atas(): void
    {
        $bill = $this->calculator->calculateBill(
            [['price' => 15005, 'quantity' => 1]],
            taxPercent: 10,
            servicePercent: 0,
        );

        $this->assertSame(1501, $bill['tax']);
    }

    public function test_diskon_tidak_boleh_melebihi_subtotal(): void
    {
        $bill = $this->calculator->calculateBill(
            [['price' => 20000, 'quantity' => 1]],
            discount: 50000,
            taxPercent: 0,
            servicePercent: 0,
        );

        $this->assertSame(20000, $bill['discount']);
        $this->assertSame(0, $bill['taxable_base']);
        $this->assertSame(0, $bill['grand_total']);
    }

    public function test_input_negatif_ditolak(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->calculator->calculateBill([['price' => -1000, 'quantity' => 1]]);
    }

    public function test_kuantitas_nol_ditolak(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->calculator->calculateBill([['price' => 1000, 'quantity' => 0]]);
    }

    public function test_kembalian_tunai(): void
    {
        $result = $this->calculator->calculateCashChange(grandTotal: 79350, tendered: 100000);

        $this->assertSame(['paid' => 79350, 'change' => 20650, 'remaining' => 0], $result);
    }

    public function test_tunai_kurang_dihitung_sebagai_sisa(): void
    {
        $result = $this->calculator->calculateCashChange(grandTotal: 79350, tendered: 50000);

        $this->assertSame(['paid' => 50000, 'change' => 0, 'remaining' => 29350], $result);
    }
}
