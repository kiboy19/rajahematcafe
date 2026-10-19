<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\DB;

class TransactionNumberGenerator
{
    /**
     * Nomor pesanan berurutan per hari, format ORD-YYMMDD-####.
     * Mengevaluasi nomor terakhir hari ini di transaksi terkunci.
     * Konflik dijaga oleh unique index pada kolom `number`.
     */
    public function nextOrderNumber(): string
    {
        return (string) DB::transaction(function () {
            $prefix = 'ORD-'.now()->format('ymd').'-';

            $lastNumber = Order::query()
                ->whereNumberPrefix($prefix)
                ->orderByDesc('number')
                ->lockForUpdate()
                ->value('number');

            $sequence = $lastNumber === null
                ? 1
                : ((int) substr($lastNumber, -4)) + 1;

            return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
        });
    }
}
