<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Voucher;
use App\Models\VoucherUsage;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Validasi & klaim voucher. Diskon dihitung dari master voucher di server;
 * kode/nominal dari klien tidak pernah dipercaya.
 */
class VoucherService
{
    /**
     * Periksa apakah voucher boleh dipakai untuk subtotal & pelanggan ini.
     * Tidak memotong kuota — hanya pratinjau sebelum pesanan dibuat.
     *
     * @throws ValidationException
     */
    public function resolve(string $code, int $subtotal, ?Customer $customer = null): Voucher
    {
        $normalized = mb_strtoupper(trim($code));

        return $this->assertUsable(
            Voucher::query()->where('code', $normalized)->first(),
            $subtotal,
            $customer,
        );
    }

    /**
     * Klaim voucher untuk pesanan yang sudah tersimpan: kuota dipotong di dalam
     * transaksi dengan row lock agar dua request bersamaan tidak menembus kuota.
     *
     * @throws ValidationException
     */
    public function redeem(Voucher $voucher, int $orderId, int $subtotal, ?Customer $customer, int $discountAmount): VoucherUsage
    {
        return DB::transaction(function () use ($voucher, $orderId, $subtotal, $customer, $discountAmount): VoucherUsage {
            /** @var Voucher $locked */
            $locked = Voucher::query()->lockForUpdate()->findOrFail($voucher->id);

            $this->assertUsable($locked, $subtotal, $customer);

            // Klaim atomik: kondisi memeriksa kuota lagi, sehingga dua checkout bersamaan
            // pada kuota terakhir hanya satu yang berhasil.
            $quota = $locked->quota;

            if ($quota !== null) {
                $claimed = Voucher::query()
                    ->whereKey($locked->id)
                    ->where('used_count', '<', $quota)
                    ->increment('used_count');

                if ($claimed === 0) {
                    throw ValidationException::withMessages(['voucher_code' => 'Kuota voucher ini baru saja habis.']);
                }
            } else {
                $locked->increment('used_count');
            }

            return VoucherUsage::query()->create([
                'voucher_id' => $locked->id,
                'order_id' => $orderId,
                'customer_id' => $customer?->id,
                'discount_amount' => $discountAmount,
            ]);
        });
    }

    /**
     * Kembalikan kuota bila pesanan dibatalkan.
     */
    public function release(int $orderId): void
    {
        DB::transaction(function () use ($orderId): void {
            $usage = VoucherUsage::query()->where('order_id', $orderId)->first();

            if (! $usage) {
                return;
            }

            Voucher::query()->whereKey($usage->voucher_id)->where('used_count', '>', 0)->decrement('used_count');
            $usage->delete();
        });
    }

    /**
     * @throws ValidationException
     */
    private function assertUsable(?Voucher $voucher, int $subtotal, ?Customer $customer): Voucher
    {
        if (! $voucher || ! $voucher->is_active) {
            throw ValidationException::withMessages(['voucher_code' => 'Kode voucher tidak ditemukan atau tidak aktif.']);
        }

        if ($voucher->starts_at?->isFuture()) {
            throw ValidationException::withMessages(['voucher_code' => 'Voucher ini belum mulai berlaku.']);
        }

        if ($voucher->expires_at?->isPast()) {
            throw ValidationException::withMessages(['voucher_code' => 'Voucher ini sudah kedaluwarsa.']);
        }

        if ($voucher->codeIsExhausted()) {
            throw ValidationException::withMessages(['voucher_code' => 'Kuota voucher ini sudah habis.']);
        }

        if ($subtotal < $voucher->min_subtotal) {
            throw ValidationException::withMessages([
                'voucher_code' => 'Minimal belanja untuk voucher ini Rp'.number_format($voucher->min_subtotal, 0, ',', '.').'.',
            ]);
        }

        if ($customer && VoucherUsage::query()
            ->where('voucher_id', $voucher->id)
            ->where('customer_id', $customer->id)
            ->count() >= $voucher->quota_per_customer) {
            throw ValidationException::withMessages([
                'voucher_code' => 'Anda sudah memakai voucher ini '.$voucher->quota_per_customer.'× (batas maksimal).',
            ]);
        }

        return $voucher;
    }
}
