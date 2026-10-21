<?php

namespace App\Http\Controllers;

use App\Enums\PageViewDevice;
use App\Models\PageView;
use App\Services\SeoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Analitik kunjungan halaman publik untuk owner/manager.
 * Semua angka dihitung dari tabel page_views tanpa pihak ketiga.
 */
class AnalyticsController extends Controller
{
    /** Rentang hari yang diizinkan pada parameter ?range=. */
    private const RANGES = [7, 30, 90];

    public function index(Request $request, SeoService $seoService): JsonResponse
    {
        abort_unless(
            $request->user()?->role
                && in_array($request->user()->role->value, ['owner', 'manager'], true)
                && $request->user()->is_active,
            403
        );

        $days = in_array((int) $request->query('range', 7), self::RANGES, true)
            ? (int) $request->query('range', 7)
            : 7;

        $start = now()->subDays($days)->startOfDay();
        $end = now()->endOfDay();
        $base = PageView::query()->between($start, $end);

        $total = (clone $base)->count();
        $unique = (clone $base)->distinct('ip_hash')->count('ip_hash');

        $byDevice = (clone $base)
            ->selectRaw('device, count(*) as total')
            ->groupBy('device')
            ->pluck('total', 'device');

        $topPages = (clone $base)
            ->selectRaw('path, count(*) as total, count(distinct ip_hash) as unique_visitors')
            ->groupBy('path')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        $topReferers = (clone $base)
            ->whereNotNull('referer')
            ->where('referer', '!=', '')
            ->selectRaw('referer, count(*) as total')
            ->groupBy('referer')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        $daily = (clone $base)
            ->selectRaw('date(viewed_at) as day, count(*) as total, count(distinct ip_hash) as unique_visitors')
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->keyBy(fn ($row) => Carbon::parse($row->day)->toDateString());

        $series = [];
        $cursor = $start->copy();

        while ($cursor->lte($end)) {
            $key = $cursor->toDateString();
            $row = $daily->get($key);

            $series[] = [
                'date' => $key,
                'views' => (int) ($row->total ?? 0),
                'unique' => (int) ($row->unique_visitors ?? 0),
            ];

            $cursor->addDay();
        }

        return response()->json([
            'data' => [
                'range_days' => $days,
                'public_paths' => $this->publicPaths(),
                'totals' => [
                    'views' => $total,
                    'unique_visitors' => $unique,
                    'views_per_visitor' => $unique > 0
                        ? round($total / $unique, 1)
                        : 0.0,
                ],
                'devices' => collect(PageViewDevice::cases())
                    ->map(fn (PageViewDevice $device): array => [
                        'device' => $device->value,
                        'label' => $device->label(),
                        'total' => (int) ($byDevice->get($device->value, 0)),
                    ])->all(),
                'top_pages' => $topPages->map(fn ($row): array => [
                    'path' => $row->path,
                    'views' => (int) $row->total,
                    'unique_visitors' => (int) $row->unique_visitors,
                ])->all(),
                'top_referers' => $topReferers->map(fn ($row): array => [
                    'referer' => $row->referer,
                    'views' => (int) $row->total,
                ])->all(),
                'series' => $series,
            ],
        ]);
    }

    /**
     * @return list<string>
     */
    private function publicPaths(): array
    {
        return ['/', '/daftar-menu', '/tentang-kami', '/kontak', '/blog'];
    }
}
