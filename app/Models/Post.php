<?php

namespace App\Models;

use App\Enums\PostCategory;
use App\Enums\PostStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Artikel blog kafe. Konten HTML berasal dari editor Tiptap dan sudah
 * disanitasi di server sebelum disimpan (lihat ContentSanitizer).
 */
class Post extends Model
{
    use HasFactory;

    /** @var list<string> */
    protected $appends = ['cover_image_url', 'read_time_minutes'];

    protected $fillable = [
        'author_id', 'title', 'slug', 'cover_image_path', 'category', 'excerpt',
        'content', 'status', 'published_at', 'views_count',
        'meta_title', 'meta_description',
    ];

    protected function casts(): array
    {
        return [
            'status' => PostStatus::class,
            'category' => PostCategory::class,
            'published_at' => 'datetime',
            'views_count' => 'integer',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    protected function coverImageUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->cover_image_path
            ? Storage::disk('public')->url($this->cover_image_path)
            : null);
    }

    /**
     * Estimasi waktu baca berdasarkan ~200 kata per menit pada teks HTML.
     */
    protected function readTimeMinutes(): Attribute
    {
        return Attribute::get(function (): int {
            $words = str_word_count(strip_tags((string) $this->content));

            return max(1, (int) ceil($words / 200));
        });
    }

    public function metaTitle(): string
    {
        return $this->meta_title ?: $this->title;
    }

    public function metaDescription(): string
    {
        return $this->meta_description
            ?: Str::limit($this->excerpt ?: strip_tags((string) $this->content), 155);
    }

    /**
     * Hanya artikel yang sudah terbit dan tanggal terbitnya sudah lewat.
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', PostStatus::Published)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }
}
