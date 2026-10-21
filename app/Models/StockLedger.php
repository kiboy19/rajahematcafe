<?php

namespace App\Models;

use App\Enums\StockMovementType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class StockLedger extends Model
{
    use HasFactory;

    protected $fillable = [
        'ingredient_id', 'type', 'delta_milli', 'balance_after_milli',
        'unit_cost', 'total_cost', 'reference_type', 'reference_id',
        'note', 'recorded_by', 'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'delta_milli' => 'integer',
            'balance_after_milli' => 'integer',
            'unit_cost' => 'integer',
            'total_cost' => 'integer',
            'occurred_at' => 'datetime',
            'type' => StockMovementType::class,
        ];
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
