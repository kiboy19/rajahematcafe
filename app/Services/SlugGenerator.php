<?php

namespace App\Services;

use App\Models\Post;
use Illuminate\Support\Str;

/**
 * Membuat slug unik dari judul. Bila slug sudah dipakai artikel lain,
 * tambahkan sufiks numerik sampai bebas.
 */
class SlugGenerator
{
    public function forPost(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title);

        if ($base === '') {
            $base = 'artikel';
        }

        $base = Str::limit($base, 200, '');
        $slug = $base;
        $suffix = 2;

        while ($this->exists($slug, $ignoreId)) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    private function exists(string $slug, ?int $ignoreId): bool
    {
        return Post::query()
            ->where('slug', $slug)
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();
    }
}
