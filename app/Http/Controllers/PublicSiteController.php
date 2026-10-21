<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Post;
use App\Services\SiteSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Data untuk halaman publik (landing, katalog menu, tentang, kontak).
 * Tidak memerlukan login sehingga aman dipakai crawler dan tamu.
 */
class PublicSiteController extends Controller
{
    public function __construct(private readonly SiteSettingsService $siteSettings) {}

    /**
     * Isi landing page: branding, menu unggulan, kategori, dan artikel terbaru.
     */
    public function home(): JsonResponse
    {
        $minutes = (int) config('pos.public_cache_minutes', 5);
        $cacheKey = 'public.home.'.md5(serialize([
            $this->siteSettings->current()?->updated_at?->timestamp,
            MenuItem::query()->max('updated_at'),
            Category::query()->max('updated_at'),
            Post::query()->max('updated_at'),
        ]));

        $payload = cache()->remember($cacheKey, now()->addMinutes($minutes), function (): array {
            $settings = $this->siteSettings->current();

            return [
                'branding' => $this->siteSettings->toPayload($settings),
                'featured_menu_items' => MenuItem::query()
                    ->available()
                    ->with('category:id,name')
                    ->orderBy('sort_order')
                    ->orderBy('name')
                    ->limit(8)
                    ->get()
                    ->map(fn (MenuItem $item): array => $this->menuCard($item))
                    ->all(),
                'categories' => Category::query()
                    ->where('is_active', true)
                    ->withCount(['menuItems' => fn ($query) => $query->where('is_available', true)])
                    ->orderBy('sort_order')
                    ->orderBy('name')
                    ->get(['id', 'name', 'sort_order', 'image_path'])
                    ->toArray(),
                'posts' => Post::query()
                    ->published()
                    ->orderByDesc('published_at')
                    ->limit(3)
                    ->get()
                    ->map(fn (Post $post): array => $this->postCard($post))
                    ->all(),
            ];
        });

        return response()->json(['data' => $payload]);
    }

    /**
     * Katalog menu publik dikelompokkan per kategori aktif.
     */
    public function menu(Request $request): JsonResponse
    {
        $items = MenuItem::query()
            ->available()
            ->with('category:id,name,sort_order')
            ->whereHas('category', fn ($query) => $query->where('is_active', true))
            ->when($request->filled('category'), fn ($query) => $query->where('category_id', (int) $request->input('category')))
            ->when($request->filled('search'), function ($query) use ($request): void {
                $query->where('name', 'like', '%'.$request->string('search').'%');
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->sortBy(fn (MenuItem $item): string => sprintf(
                '%04d-%04d-%s',
                $item->category?->sort_order ?? 0,
                $item->sort_order,
                $item->name
            ))
            ->map(fn (MenuItem $item): array => $this->menuCard($item));

        $grouped = $items
            ->groupBy(fn (array $item): string => (string) $item['category_name'])
            ->map(fn ($group, string $name): array => [
                'category' => $name,
                'items' => $group->values()->all(),
            ])
            ->values();

        return response()->json([
            'data' => [
                'branding' => $this->siteSettings->toPayload($this->siteSettings->current()),
                'groups' => $grouped,
                'total' => $items->count(),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function menuCard(MenuItem $item): array
    {
        return [
            'id' => $item->id,
            'name' => $item->name,
            'price' => $item->price,
            'image_url' => $item->image_url,
            'category_id' => $item->category_id,
            'category_name' => $item->category?->name,
            'is_available' => $item->is_available,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function postCard(Post $post): array
    {
        return [
            'id' => $post->id,
            'title' => $post->title,
            'slug' => $post->slug,
            'excerpt' => $post->excerpt,
            'category_label' => $post->category?->label(),
            'cover_image_url' => $post->cover_image_url,
            'published_at' => $post->published_at?->toIso8601String(),
        ];
    }
}
