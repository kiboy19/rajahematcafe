import * as React from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { Clock, Eye, Search, Loader2 } from 'lucide-react';
import { Input } from '@/react/components/ui/input';
import { Badge } from '@/react/components/ui/badge';
import { PageHeader } from '../ui';
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
    const [search, setSearch] = React.useState('');
    const [searchInput, setSearchInput] = React.useState('');
    const [params, setParams] = useSearchParams();
    const page = Number(params.get('page') ?? 1);
    const category = params.get('kategori') ?? '';
    const [pagination, setPagination] = React.useState<{ current: number; last: number; total: number }>({ current: 1, last: 1, total: 0 });

    React.useEffect(() => {
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
            .then((res) => res.json())
            .then((json) => {
                if (!active) return;
                setPosts(json.data ?? []);
                setPagination({ current: json.current_page ?? 1, last: json.last_page ?? 1, total: json.total ?? 0 });
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

    function updateFilter(key: string, value: string) {
        const next = new URLSearchParams(params);
        if (value) next.set(key, value);
        else next.delete(key);
        next.delete('page');
        setParams(next);
    }

    return (
        <PublicBlogLayout>
            <div className="mx-auto max-w-6xl px-4 py-10">
            <PageHeader
                title="Blog Rajahemat"
                subtitle="Cerita, berita, dan tips seputar kopi serta kegiatan kafe kami."
            />

            <div className="mt-6 flex flex-wrap items-center gap-3">
                <form
                    className="relative flex-1 min-w-56 max-w-sm"
                    onSubmit={(event) => {
                        event.preventDefault();
                        setSearch(searchInput);
                        updateFilter('page', '');
                    }}
                >
                    <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-ink-2" />
                    <Input
                        value={searchInput}
                        onChange={(event) => setSearchInput(event.target.value)}
                        placeholder="Cari artikel…"
                        className="pl-9"
                    />
                </form>
                <div className="flex flex-wrap gap-2">
                    {CATEGORY_FILTERS.map((item) => (
                        <button
                            key={item.value}
                            type="button"
                            onClick={() => updateFilter('kategori', item.value)}
                            className={`rounded-full px-3 py-1.5 text-xs font-semibold transition-colors ${
                                category === item.value
                                    ? 'bg-primary text-white'
                                    : 'border border-line bg-surface text-ink-2 hover:bg-surface-2'
                            }`}
                        >
                            {item.label}
                        </button>
                    ))}
                </div>
            </div>

            {loading ? (
                <div className="flex items-center justify-center py-24 text-sm text-ink-2">
                    <Loader2 className="mr-2 h-5 w-5 animate-spin" />
                    Memuat artikel…
                </div>
            ) : error ? (
                <div className="py-24 text-center text-sm text-bad">{error}</div>
            ) : posts.length === 0 ? (
                <div className="py-24 text-center text-sm text-ink-2">
                    Belum ada artikel pada kategori ini.
                </div>
            ) : (
                <div className="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    {posts.map((post) => (
                        <article key={post.id} className="group flex flex-col overflow-hidden rounded-card border border-line bg-surface shadow-1 transition-shadow hover:shadow-2">
                            <Link to={`/blog/${post.slug}`} className="block aspect-video overflow-hidden bg-line/40">
                                {post.cover_image_url ? (
                                    <img
                                        src={post.cover_image_url}
                                        alt={post.title}
                                        loading="lazy"
                                        className="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
                                    />
                                ) : (
                                    <div className="flex h-full w-full items-center justify-center text-3xl">☕</div>
                                )}
                            </Link>
                            <div className="flex flex-1 flex-col gap-2 p-4">
                                {post.category_label && <Badge variant="default" className="w-fit">{post.category_label}</Badge>}
                                <Link to={`/blog/${post.slug}`} className="font-display font-bold text-ink line-clamp-2 hover:text-primary">
                                    {post.title}
                                </Link>
                                {post.excerpt && <p className="text-xs text-ink-2 line-clamp-3">{post.excerpt}</p>}
                                <div className="mt-auto flex items-center gap-4 pt-2 text-[11px] text-ink-2">
                                    <span className="inline-flex items-center gap-1">
                                        <Clock className="h-3 w-3" />
                                        {post.read_time_minutes} mnt baca
                                    </span>
                                    <span className="inline-flex items-center gap-1">
                                        <Eye className="h-3 w-3" />
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
                <div className="mt-10 flex items-center justify-center gap-2">
                    {Array.from({ length: pagination.last }, (_, index) => index + 1).map((number) => (
                        <button
                            key={number}
                            type="button"
                            onClick={() => updateFilter('page', String(number))}
                            className={`h-9 w-9 rounded-input text-sm font-semibold ${
                                number === pagination.current
                                    ? 'bg-primary text-white'
                                    : 'border border-line bg-surface text-ink-2 hover:bg-surface-2'
                            }`}
                        >
                            {number}
                        </button>
                    ))}
                </div>
            )}
        </div>
        </PublicBlogLayout>
    );
}