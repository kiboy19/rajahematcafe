<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Konfigurasi tunggal pajak & service charge. Tarif disimpan dalam basis poin
 * (1% = 100 bps) agar perhitungan tetap integer tanpa floating point.
 */
class BillingSetting extends Model
{
    protected $fillable = [
        'tax_name', 'tax_bps', 'tax_enabled',
        'service_name', 'service_bps', 'service_enabled',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'tax_bps' => 'integer',
            'service_bps' => 'integer',
            'tax_enabled' => 'boolean',
            'service_enabled' => 'boolean',
        ];
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
