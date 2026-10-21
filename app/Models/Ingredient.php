<?php

namespace App\Models;

use Database\Factories\IngredientFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ingredient extends Model
{
    /** @use HasFactory<IngredientFactory> */
    use HasFactory;

    protected $fillable = [
        'name', 'unit', 'stock_qty_milli', 'min_stock_milli', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'stock_qty_milli' => 'integer',
            'min_stock_milli' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function stockLedgers(): HasMany
    {
        return $this->hasMany(StockLedger::class);
    }

    public function suppliers(): BelongsToMany
    {
        return $this->belongsToMany(Supplier::class)
            ->withPivot('last_unit_cost')
            ->withTimestamps();
    }

    public function isLowStock(): bool
    {
        return $this->stock_qty_milli <= $this->min_stock_milli;
    }
}
