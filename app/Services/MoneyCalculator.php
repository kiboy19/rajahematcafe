<?php

namespace App\Services;

use InvalidArgumentException;

class MoneyCalculator
{
    /**
     * Hitung rincian pesanan. Semua angka adalah integer Rupiah.
     * Tidak ada floating-point pada hasil kalkulasi.
     *
     * @param  list<array{price: int, quantity: int}>  $items
     * @return array{
     *     subtotal: int,
     *     discount: int,
     *     taxable_base: int,
     *     tax: int,
     *     service: int,
     *     grand_total: int
     * }
     */
    public function calculateBill(
        array $items,
        int $discount = 0,
        int $taxPercent = 10,
        int $servicePercent = 5,
        ?int $taxBps = null,
        ?int $serviceBps = null,
    ): array {
        if ($discount < 0 || $taxPercent < 0 || $servicePercent < 0) {
            throw new InvalidArgumentException('Diskon, pajak, dan biaya layanan tidak boleh negatif.');
        }

        if (($taxBps !== null && $taxBps < 0) || ($serviceBps !== null && $serviceBps < 0)) {
            throw new InvalidArgumentException('Basis poin pajak dan biaya layanan tidak boleh negatif.');
        }

        $subtotal = 0;
        foreach ($items as $item) {
            if ($item['price'] < 0 || $item['quantity'] < 1) {
                throw new InvalidArgumentException('Harga harus non-negatif dan kuantitas minimal 1.');
            }
            $subtotal += $item['price'] * $item['quantity'];
        }

        // Diskon tidak boleh melebihi subtotal
        $discount = min($discount, $subtotal);
        $taxableBase = $subtotal - $discount;

        // Hitung pajak & service charge: prioritaskan bps (basis poin 1% = 100) jika ada,
        // fallback ke percent. Pembulatan ke integer Rupiah terdekat.
        $tax = $taxBps !== null
            ? (int) round(($taxableBase * $taxBps) / 10000)
            : (int) round(($taxableBase * $taxPercent) / 100);

        $service = $serviceBps !== null
            ? (int) round(($taxableBase * $serviceBps) / 10000)
            : (int) round(($taxableBase * $servicePercent) / 100);

        $grandTotal = $taxableBase + $tax + $service;

        return [
            'subtotal' => $subtotal,
            'discount' => $discount,
            'taxable_base' => $taxableBase,
            'tax' => $tax,
            'service' => $service,
            'grand_total' => $grandTotal,
        ];
    }

    /**
     * Hitung uang kembalian untuk pembayaran tunai.
     *
     * @return array{paid: int, change: int, remaining: int}
     */
    public function calculateCashChange(int $grandTotal, int $tendered): array
    {
        if ($grandTotal < 0 || $tendered < 0) {
            throw new InvalidArgumentException('Nominal total dan uang diterima harus non-negatif.');
        }

        if ($tendered >= $grandTotal) {
            return [
                'paid' => $grandTotal,
                'change' => $tendered - $grandTotal,
                'remaining' => 0,
            ];
        }

        return [
            'paid' => $tendered,
            'change' => 0,
            'remaining' => $grandTotal - $tendered,
        ];
    }
}
