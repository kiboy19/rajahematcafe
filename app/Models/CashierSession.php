<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\WalletTransactionType;
use App\Services\CashierSessionService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashierSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'cashier_id', 'opening_float', 'expected_cash', 'counted_cash', 'variance',
        'status', 'closing_note', 'opened_by', 'closed_by', 'variance_verified_by', 'variance_verified_at',
        'opened_at', 'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'variance_verified_at' => 'datetime',
            'opening_float' => 'integer',
            'expected_cash' => 'integer',
            'counted_cash' => 'integer',
            'variance' => 'integer',
        ];
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function varianceVerifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'variance_verified_by');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    /**
     * Sesi terbuka milik kasir ini, bila ada. Satu kasir hanya boleh punya satu sesi terbuka.
     */
    public static function openFor(?User $user): ?self
    {
        if (! $user) {
            return null;
        }

        return self::query()
            ->where('cashier_id', $user->id)
            ->where('status', 'open')
            ->latest('opened_at')
            ->first();
    }

    /**
     * Sesi kasir aktif global (hanya boleh ada satu per toko).
     */
    public static function active(): ?self
    {
        return self::query()
            ->where('status', 'open')
            ->with('cashier')
            ->latest('opened_at')
            ->first();
    }

    /**
     * Total uang tunai yang seharusnya ada di laci dari pembayaran tunai
     * yang tercatat pada sesi ini. Saldo/QRIS/EDC tidak pernah masuk laci.
     */
    public function cashSalesTotal(): int
    {
        return (int) $this->payments()
            ->where('method', PaymentMethod::Cash->value)
            ->where('status', PaymentStatus::Paid->value)
            ->sum('amount');
    }

    /**
     * Top-up tunai yang diterima di sesi ini. Uang fisik masuk laci, jadi ikut dihitung saat tutup kas.
     */
    public function cashTopupsTotal(): int
    {
        return (int) WalletTransaction::query()
            ->where('cashier_session_id', $this->id)
            ->where('type', WalletTransactionType::TopUp->value)
            ->sum('amount');
    }

    public function expectedCashTotal(): int
    {
        return app(CashierSessionService::class)->calculateExpectedCash(
            openingFloat: $this->opening_float,
            cashSales: $this->cashSalesTotal(),
            cashTopups: $this->cashTopupsTotal(),
        );
    }
}
