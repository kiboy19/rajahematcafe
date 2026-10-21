<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoint blog publik. Tidak memerlukan login staf maupun pelanggan.
 */
class PublicBlogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $posts = Post::query()
            ->published()
            ->when($request->filled('category'), fn ($query) => $query->where('category', $request->string('category')))
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = '%'.$request->string('search').'%';
                $query->where(fn ($nested) => $nested->where('title', 'like', $search)->orWhere('excerpt', 'like', $search));
            })
            ->orderByDesc('published_at')
            ->paginate(9)
            ->through(fn (Post $post): array => $this->card($post));

        return response()->json($posts);
    }

    public function show(string $slug): JsonResponse
    {
        $post = Post::query()->published()->where('slug', $slug)->firstOrFail();

        $post->increment('views_count');

        $related = Post::query()
            ->published()
            ->whereKeyNot($post->getKey())
            ->when($post->category, fn ($query) => $query->orderByRaw('category = ? desc', [$post->category->value]))
            ->orderByDesc('published_at')
            ->limit(3)
            ->get()
            ->map(fn (Post $item): array => $this->card($item))
            ->all();

        return response()->json([
            'data' => [
                ...$this->card($post),
                'content' => $post->content,
                'author' => $post->author?->only(['id', 'name']),
                'meta_title' => $post->metaTitle(),
                'meta_description' => $post->metaDescription(),
            ],
            'related' => $related,
        ]);
    }

    /**
     * Bentuk ringkas untuk kartu daftar artikel (tanpa isi HTML penuh).
     *
     * @return array<string, mixed>
     */
    private function card(Post $post): array
    {
        return [
            'id' => $post->id,
            'title' => $post->title,
            'slug' => $post->slug,
            'excerpt' => $post->excerpt,
            'category' => $post->category?->value,
            'category_label' => $post->category?->label(),
            'cover_image_url' => $post->cover_image_url,
            'published_at' => $post->published_at?->toIso8601String(),
            'views_count' => $post->views_count,
            'read_time_minutes' => $post->read_time_minutes,
        ];
    }
}
