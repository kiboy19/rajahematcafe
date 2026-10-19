<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CafeTable extends Model
{
    use HasFactory;

    protected $fillable = ['label', 'seats', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
