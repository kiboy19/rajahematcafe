import * as React from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { Clock, Eye, Search, RotateCcw } from 'lucide-react';
import { Badge, Button, Input } from '../ui-primitives';
import { PublicBlogLayout } from './PublicPages';

export interface BlogCard {
    id: number;
    title: string;
    slug: string;
    excerpt?: string | null;
    category?: string | null;
    category_label?: string | null;
    cover_image_url?: string | null;
    published_at?: string | null;
    views_count: number;
    read_time_minutes: number;
}

const CATEGORY_FILTERS = [
    { value: '', label: 'Semua' },
    { value: 'berita', label: 'Berita' },
    { value: 'tips-kopi', label: 'Tips Kopi' },
    { value: 'event', label: 'Event' },
    { value: 'promo', label: 'Promo' },
    { value: 'umum', label: 'Umum' },
];

function formatDate(value?: string | null): string {
    if (!value) return '';
    return new Date(value).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
}

/** Daftar artikel blog publik dengan filter kategori, pencarian, dan pagination. */
export function BlogListPage() {
    const [posts, setPosts] = React.useState<BlogCard[]>([]);
    const [loading, setLoading] = React.useState(true);
    const [error, setError] = React.useState<string | null>(null);
    const [params, setParams] = useSearchParams();
    const page = Number(params.get('page') ?? 1);
    const category = params.get('kategori') ?? '';
    const search = params.get('cari') ?? '';
    const [searchInput, setSearchInput] = React.useState(search);
    const [pagination, setPagination] = React.useState<{ current: number; last: number; total: number }>({
        current: 1,
        last: 1,
        total: 0,
    });

    React.useEffect(() => {
        const timer = window.setTimeout(() => {
            const next = new URLSearchParams(params);
            if (searchInput) {
                next.set('cari', searchInput);
            } else {
                next.delete('cari');
            }
            next.delete('page');
            if (searchInput !== search) {
                setParams(next, { replace: true });
            }
        }, 200);

        return () => window.clearTimeout(timer);
    }, [searchInput, search, params, setParams]);

    const loadPosts = React.useCallback(() => {
        let active = true;
        setLoading(true);
        setError(null);

        const query = new URLSearchParams();
        query.set('page', String(page));
        if (category) query.set('category', category);
        if (search) query.set('search', search);

        fetch(`/api/publik/blog?${query.toString()}`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        })
            .then((res) => {
                if (!res.ok) throw new Error('HTTP ' + res.status);
                return res.json();
            })
            .then((json) => {
                if (!active) return;
                setPosts(json.data ?? []);
                setPagination({
                    current: json.current_page ?? 1,
                    last: json.last_page ?? 1,
                    total: json.total ?? 0,
                });
                setLoading(false);
            })
            .catch(() => {
                if (!active) return;
                setError('Gagal memuat artikel.');
                setLoading(false);
            });

        return () => {
            active = false;
        };
    }, [page, category, search]);

    React.useEffect(() => {
        return loadPosts();
    }, [loadPosts]);

    function updateFilter(key: string, value: string) {
        const next = new URLSearchParams(params);
        if (value) {
            next.set(key, value);
        } else {
            next.delete(key);
        }
        next.delete('page');
        setParams(next);
    }

    return (
        <PublicBlogLayout>
            <div className="mx-auto max-w-6xl px-4 py-12">
                <div className="motion-fade-up">
                    <p className="font-semibold uppercase tracking-wider text-primary">Cerita & wawasan</p>
                    <h1 className="mt-2 font-display text-4xl font-bold">Blog Rajahemat</h1>
                    <p className="mt-2 text-sm text-ink-2">Cerita, berita, dan tips seputar kopi serta kegiatan kedai kami.</p>
                </div>

                <div className="mt-8 flex flex-wrap items-center gap-3">
                    <div className="relative min-w-56 flex-1 max-w-sm">
                        <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-ink-2" aria-hidden="true" />
                        <Input
                            value={searchInput}
                            onChange={(event) => setSearchInput(event.target.value)}
                            placeholder="Cari artikel…"
                            className="pl-9"
                            aria-label="Cari artikel"
                        />
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {CATEGORY_FILTERS.map((item) => {
                            const isActive = category === item.value;
                            return (
                                <button
                                    key={item.value}
                                    type="button"
                                    onClick={() => updateFilter('kategori', item.value)}
                                    className={`rounded-full px-4 py-2 text-xs font-bold transition-colors ${
                                        isActive
                                            ? 'bg-surface-strong text-primary'
                                            : 'border border-line bg-surface text-ink-2 hover:bg-surface-2'
                                    }`}
                                >
                                    {item.label}
                                </button>
                            );
                        })}
                    </div>
                </div>

                {error ? (
                    <div role="alert" className="mt-8 flex items-center justify-between rounded-card border border-bad/20 bg-bad-bg p-4 text-sm text-bad">
                        <span>{error}</span>
                        <Button variant="outline" size="sm" onClick={loadPosts}>
                            <RotateCcw className="h-4 w-4" aria-hidden="true" />
                            Coba lagi
                        </Button>
                    </div>
                ) : null}

                {loading ? (
                    <div className="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        {[1, 2, 3, 4, 5, 6].map((i) => (
                            <div key={i} className="flex flex-col overflow-hidden rounded-card border border-line bg-surface shadow-1">
                                <div className="aspect-[16/10] w-full animate-pulse bg-surface-2" />
                                <div className="flex flex-1 flex-col gap-3 p-5">
                                    <div className="h-4 w-20 animate-pulse rounded bg-surface-2" />
                                    <div className="h-5 w-3/4 animate-pulse rounded bg-surface-2" />
                                    <div className="h-10 w-full animate-pulse rounded bg-surface-2" />
                                </div>
                            </div>
                        ))}
                    </div>
                ) : posts.length === 0 ? (
                    <div className="py-24 text-center">
                        <h2 className="font-display text-2xl font-bold">Tidak ditemukan</h2>
                        <p className="mt-2 text-sm text-ink-2">Belum ada artikel pada kriteria ini.</p>
                        <Button
                            variant="outline"
                            className="mt-5"
                            onClick={() => {
                                setSearchInput('');
                                setParams({});
                            }}
                        >
                            Reset filter
                        </Button>
                    </div>
                ) : (
                    <div className="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        {posts.map((post) => (
                            <article
                                key={post.id}
                                className="group flex flex-col overflow-hidden rounded-card border border-line bg-surface shadow-1 transition-all duration-200 hover:-translate-y-1 hover:shadow-2"
                            >
                                <Link to={`/blog/${post.slug}`} className="block aspect-[16/10] overflow-hidden bg-thumb">
                                    {post.cover_image_url ? (
                                        <img
                                            src={post.cover_image_url}
                                            alt={post.title}
                                            loading="lazy"
                                            className="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105"
                                        />
                                    ) : null}
                                </Link>
                                <div className="flex flex-1 flex-col gap-2 p-5">
                                    {post.category_label && (
                                        <Badge variant="outline" className="w-fit border-primary/20 text-primary">
                                            {post.category_label}
                                        </Badge>
                                    )}
                                    <h2 className="font-display text-lg font-bold leading-tight line-clamp-2">
                                        <Link to={`/blog/${post.slug}`} className="hover:text-primary transition-colors">
                                            {post.title}
                                        </Link>
                                    </h2>
                                    {post.excerpt && <p className="text-xs leading-relaxed text-ink-2 line-clamp-2">{post.excerpt}</p>}
                                    <div className="mt-auto flex items-center gap-4 pt-3 text-[11px] font-medium text-ink-2">
                                        <span className="inline-flex items-center gap-1">
                                            <Clock className="h-3 w-3" aria-hidden="true" />
                                            {post.read_time_minutes} mnt baca
                                        </span>
                                        <span className="inline-flex items-center gap-1">
                                            <Eye className="h-3 w-3" aria-hidden="true" />
                                            {post.views_count}
                                        </span>
                                        <span className="ml-auto">{formatDate(post.published_at)}</span>
                                    </div>
                                </div>
                            </article>
                        ))}
                    </div>
                )}

                {pagination.last > 1 && (
                    <nav aria-label="Pagination artikel" className="mt-12 flex items-center justify-center gap-2">
                        {Array.from({ length: pagination.last }, (_, index) => index + 1).map((number) => {
                            const isCurrent = number === pagination.current;
                            return (
                                <button
                                    key={number}
                                    type="button"
                                    onClick={() => updateFilter('page', String(number))}
                                    aria-current={isCurrent ? 'page' : undefined}
                                    className={`h-10 w-10 rounded-input text-sm font-bold transition-colors ${
                                        isCurrent
                                            ? 'bg-primary text-white'
                                            : 'border border-line bg-surface text-ink-2 hover:bg-surface-2'
                                    }`}
                                >
                                    {number}
                                </button>
                            );
                        })}
                    </nav>
                )}
            </div>
        </PublicBlogLayout>
    );
}
