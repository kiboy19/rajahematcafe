<?php

namespace App\Models;

use App\Enums\PageViewDevice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Satu kunjungan halaman publik. Tidak menyimpan IP mentah — hanya hash
 * sha256(ip + app key) agar metrik unik tetap bisa dihitung tanpa data pribadi.
 */
class PageView extends Model
{
    use HasFactory;

    protected $fillable = [
        'page_type', 'path', 'page_title', 'ip_hash', 'user_agent', 'device', 'referer', 'viewed_at',
    ];

    protected function casts(): array
    {
        return [
            'device' => PageViewDevice::class,
            'viewed_at' => 'datetime',
        ];
    }

    public function scopeBetween(Builder $query, \DateTimeInterface $start, \DateTimeInterface $end): Builder
    {
        return $query->whereBetween('viewed_at', [$start, $end]);
    }
}
