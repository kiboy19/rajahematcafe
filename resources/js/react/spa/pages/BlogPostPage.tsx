import * as React from 'react';
import { Link, useParams } from 'react-router-dom';
import { Clock, Eye, ArrowLeft, Share2, MessageCircle } from 'lucide-react';
import { useScrollReveal } from '../hooks';
import { Badge, Button, Toast } from '../ui-primitives';
import { PublicBlogLayout } from './PublicPages';
import type { BlogCard } from './BlogListPage';

interface BlogDetail extends BlogCard {
    content: string;
    author?: { id: number; name: string } | null;
    meta_title?: string | null;
    meta_description?: string | null;
}

function formatDate(value?: string | null): string {
    if (!value) return '';
    return new Date(value).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
}

/** Halaman detail artikel blog publik. */
export function BlogPostPage() {
    const { slug } = useParams<{ slug: string }>();
    const [post, setPost] = React.useState<BlogDetail | null>(null);
    const [related, setRelated] = React.useState<BlogCard[]>([]);
    const [loading, setLoading] = React.useState(true);
    const [notFound, setNotFound] = React.useState(false);
    const [failed, setFailed] = React.useState(false);
    const [message, setMessage] = React.useState<{ text: string; tone: 'success' | 'error' } | null>(null);
    const coverRef = useScrollReveal<HTMLDivElement>();

    const load = React.useCallback(() => {
        setLoading(true);
        setNotFound(false);
        setFailed(false);
        setPost(null);

        fetch(`/api/publik/blog/${slug}`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        })
            .then(async (res) => {
                if (res.status === 404) {
                    setNotFound(true);
                    return null;
                }

                if (!res.ok) {
                    setFailed(true);
                    return null;
                }

                return res.json();
            })
            .then((json) => {
                if (!json) return;
                setPost(json.data ?? null);
                setRelated((json.related ?? []).slice(0, 3));
            })
            .catch(() => setFailed(true))
            .finally(() => setLoading(false));
    }, [slug]);

    React.useEffect(() => {
        load();
    }, [load]);

    React.useEffect(() => {
        if (!post) return;
        document.title = `${post.meta_title ?? post.title} — Rajahemat Cafe`;
    }, [post]);

    if (loading) {
        return (
            <PublicBlogLayout>
                <div className="mx-auto max-w-3xl px-4 py-10" aria-busy="true">
                    <div className="h-4 w-24 animate-pulse rounded bg-surface-2" />
                    <div className="mt-6 h-9 w-3/4 animate-pulse rounded bg-surface-2" />
                    <div className="mt-3 h-4 w-1/2 animate-pulse rounded bg-surface-2" />
                    <div className="mt-6 aspect-video w-full animate-pulse rounded-card bg-surface-2" />
                    <div className="mt-8 space-y-3">
                        {[1, 2, 3, 4, 5].map((i) => (
                            <div key={i} className="h-4 animate-pulse rounded bg-surface-2" />
                        ))}
                    </div>
                </div>
            </PublicBlogLayout>
        );
    }

    if (failed) {
        return (
            <PublicBlogLayout>
                <div className="mx-auto max-w-2xl px-4 py-24 text-center">
                    <h1 className="font-display text-2xl font-bold">Gagal memuat artikel</h1>
                    <p className="mt-2 text-sm text-ink-2">Periksa koneksi Anda, lalu coba lagi.</p>
                    <Button className="mt-6" onClick={load}>Coba lagi</Button>
                </div>
            </PublicBlogLayout>
        );
    }

    if (notFound || !post) {
        return (
            <PublicBlogLayout>
                <div className="mx-auto max-w-2xl px-4 py-24 text-center">
                    <h1 className="mt-4 font-display text-2xl font-bold">Artikel tidak ditemukan</h1>
                    <p className="mt-2 text-sm text-ink-2">Mungkin artikel telah dihapus atau belum diterbitkan.</p>
                    <Button asChild variant="outline" className="mt-6">
                        <Link to="/blog">
                            <ArrowLeft className="h-4 w-4" aria-hidden="true" />
                            Kembali ke Blog
                        </Link>
                    </Button>
                </div>
            </PublicBlogLayout>
        );
    }

    const shareUrl = typeof window !== 'undefined' ? window.location.href : '';

    async function copyLink(): Promise<void> {
        try {
            await navigator.clipboard.writeText(shareUrl);
            setMessage({ text: 'Tautan artikel disalin.', tone: 'success' });
        } catch {
            setMessage({ text: 'Tidak bisa menyalin. Salin manual dari address bar.', tone: 'error' });
        }
    }

    return (
        <PublicBlogLayout>
            <article className="mx-auto max-w-3xl px-4 py-10">
                <Link to="/blog" className="inline-flex items-center gap-1 text-xs font-semibold text-ink-2 hover:text-primary">
                    <ArrowLeft className="h-4 w-4" aria-hidden="true" />
                    Semua Artikel
                </Link>

                <header className="mt-4 flex flex-col gap-3 motion-fade-up">
                    {post.category_label && <Badge variant="outline" className="w-fit border-primary/20 text-primary">{post.category_label}</Badge>}
                    <h1 className="font-display text-3xl font-bold leading-tight sm:text-4xl">{post.title}</h1>
                    <div className="flex flex-wrap items-center gap-4 text-xs text-ink-2">
                        {post.author?.name && <span>Oleh {post.author.name}</span>}
                        <span>{formatDate(post.published_at)}</span>
                        <span className="inline-flex items-center gap-1">
                            <Clock className="h-3 w-3" aria-hidden="true" />
                            {post.read_time_minutes} menit baca
                        </span>
                        <span className="inline-flex items-center gap-1">
                            <Eye className="h-3 w-3" aria-hidden="true" />
                            {post.views_count} dilihat
                        </span>
                    </div>
                </header>

                <div ref={coverRef}>
                    {post.cover_image_url ? (
                        <div className="mt-6 aspect-video w-full overflow-hidden rounded-card border border-line">
                            <img src={post.cover_image_url} alt={post.title} className="h-full w-full object-cover motion-ken-burns motion-reduced" />
                        </div>
                    ) : null}
                </div>

                <div
                    className="prose prose-neutral mt-8 max-w-none text-ink [&_a]:text-primary [&_blockquote]:border-l-4 [&_blockquote]:border-primary/40 [&_blockquote]:pl-4 [&_blockquote]:italic [&_h2]:mt-8 [&_h2]:font-display [&_h2]:text-2xl [&_h2]:font-bold [&_h3]:mt-6 [&_h3]:font-display [&_h3]:text-xl [&_h3]:font-bold [&_img]:rounded-lg [&_ol]:list-decimal [&_ol]:pl-6 [&_p]:my-4 [&_p]:leading-relaxed [&_ul]:list-disc [&_ul]:pl-6"
                    dangerouslySetInnerHTML={{ __html: post.content }}
                />

                <div className="mt-10 flex flex-wrap items-center gap-3 border-t border-line pt-6">
                    <span className="inline-flex items-center gap-2 text-xs font-semibold text-ink-2">
                        <Share2 className="h-4 w-4" aria-hidden="true" />
                        Bagikan:
                    </span>
                    <a
                        href={`https://wa.me/?text=${encodeURIComponent(`${post.title} ${shareUrl}`)}`}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="inline-flex items-center gap-1 rounded-full border border-line px-3 py-2 text-xs font-semibold text-ok hover:bg-ok-bg"
                    >
                        <MessageCircle className="h-3.5 w-3.5" aria-hidden="true" />
                        WhatsApp
                    </a>
                    <a
                        href={`https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(shareUrl)}`}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="inline-flex items-center gap-1 rounded-full border border-line px-3 py-2 text-xs font-semibold text-ink-2 hover:bg-surface-2"
                    >
                        Facebook
                    </a>
                    <button
                        type="button"
                        onClick={copyLink}
                        className="inline-flex items-center gap-1 rounded-full border border-line px-3 py-2 text-xs font-semibold text-ink-2 hover:bg-surface-2"
                    >
                        Salin Tautan
                    </button>
                </div>

                {related.length > 0 && (
                    <section className="mt-12">
                        <h2 className="font-display text-xl font-bold">Artikel Lainnya</h2>
                        <div className="mt-4 grid gap-5 sm:grid-cols-3">
                            {related.map((item) => (
                                <Link
                                    key={item.id}
                                    to={`/blog/${item.slug}`}
                                    className="group flex flex-col overflow-hidden rounded-card border border-line bg-surface transition-all hover:-translate-y-0.5 hover:shadow-2"
                                >
                                    <div className="aspect-video overflow-hidden bg-thumb">
                                        {item.cover_image_url ? (
                                            <img src={item.cover_image_url} alt={item.title} loading="lazy" className="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105" />
                                        ) : null}
                                    </div>
                                    <div className="p-3">
                                        <h3 className="line-clamp-2 font-display text-sm font-bold">{item.title}</h3>
                                        <span className="mt-1 block text-[11px] text-ink-2">{formatDate(item.published_at)}</span>
                                    </div>
                                </Link>
                            ))}
                        </div>
                    </section>
                )}
            </article>
            <Toast message={message} />
        </PublicBlogLayout>
    );
}
