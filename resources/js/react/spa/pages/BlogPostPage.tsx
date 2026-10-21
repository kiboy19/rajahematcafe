import * as React from 'react';
import { Link, useParams } from 'react-router-dom';
import { Clock, Eye, ArrowLeft, Share2, Loader2, MessageCircle } from 'lucide-react';
import { Badge } from '@/react/components/ui/badge';
import { Button } from '@/react/components/ui/button';
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

    React.useEffect(() => {
        let active = true;
        setLoading(true);
        setNotFound(false);
        setPost(null);

        fetch(`/api/publik/blog/${slug}`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        })
            .then(async (res) => {
                if (res.status === 404) {
                    if (active) {
                        setNotFound(true);
                        setLoading(false);
                    }

                    return null;
                }

                return res.json();
            })
            .then((json) => {
                if (!active || !json) return;
                setPost(json.data ?? null);
                setRelated(json.related ?? []);
                setLoading(false);
            })
            .catch(() => {
                if (!active) return;
                setNotFound(true);
                setLoading(false);
            });

        return () => {
            active = false;
        };
    }, [slug]);

    if (loading) {

        return (
            <PublicBlogLayout>
                <div className="flex items-center justify-center py-32 text-sm text-ink-2">
                    <Loader2 className="mr-2 h-5 w-5 animate-spin" />
                    Memuat artikel…
                </div>
            </PublicBlogLayout>
        );
    }

    if (notFound || !post) {
        return (
            <PublicBlogLayout>
                <div className="mx-auto max-w-2xl px-4 py-24 text-center">
                    <p className="text-5xl">☕</p>
                    <h1 className="mt-4 font-display text-2xl font-bold">Artikel tidak ditemukan</h1>
                    <p className="mt-2 text-sm text-ink-2">Mungkin artikel telah dihapus atau belum diterbitkan.</p>
                    <Button asChild variant="outline" className="mt-6">
                        <Link to="/blog">
                            <ArrowLeft className="h-4 w-4" />
                            Kembali ke Blog
                        </Link>
                    </Button>
                </div>
            </PublicBlogLayout>
        );
    }

    const shareUrl = typeof window !== 'undefined' ? window.location.href : '';

    return (
        <PublicBlogLayout>
            <article className="mx-auto max-w-3xl px-4 py-10">
            <Link to="/blog" className="inline-flex items-center gap-1 text-xs font-semibold text-ink-2 hover:text-primary">
                <ArrowLeft className="h-4 w-4" />
                Semua Artikel
            </Link>

            <header className="mt-4 flex flex-col gap-3">
                {post.category_label && <Badge variant="default" className="w-fit">{post.category_label}</Badge>}
                <h1 className="font-display text-3xl font-bold leading-tight sm:text-4xl">{post.title}</h1>
                <div className="flex flex-wrap items-center gap-4 text-xs text-ink-2">
                    {post.author?.name && <span>Oleh {post.author.name}</span>}
                    <span>{formatDate(post.published_at)}</span>
                    <span className="inline-flex items-center gap-1">
                        <Clock className="h-3 w-3" />
                        {post.read_time_minutes} menit baca
                    </span>
                    <span className="inline-flex items-center gap-1">
                        <Eye className="h-3 w-3" />
                        {post.views_count} dilihat
                    </span>
                </div>
            </header>

            {post.cover_image_url && (
                <img
                    src={post.cover_image_url}
                    alt={post.title}
                    className="mt-6 aspect-video w-full rounded-card border border-line object-cover"
                />
            )}

            <div
                className="prose prose-neutral mt-8 max-w-none text-ink [&_a]:text-primary [&_blockquote]:border-l-4 [&_blockquote]:border-primary/40 [&_blockquote]:pl-4 [&_blockquote]:italic [&_h2]:mt-8 [&_h2]:font-display [&_h2]:text-2xl [&_h2]:font-bold [&_h3]:mt-6 [&_h3]:font-display [&_h3]:text-xl [&_h3]:font-bold [&_img]:rounded-lg [&_ol]:list-decimal [&_ol]:pl-6 [&_p]:my-4 [&_p]:leading-relaxed [&_ul]:list-disc [&_ul]:pl-6"
                dangerouslySetInnerHTML={{ __html: post.content }}
            />

            <div className="mt-10 flex flex-wrap items-center gap-3 border-t border-line pt-6">
                <span className="inline-flex items-center gap-2 text-xs font-semibold text-ink-2">
                    <Share2 className="h-4 w-4" />
                    Bagikan:
                </span>
                <a
                    href={`https://wa.me/?text=${encodeURIComponent(`${post.title} ${shareUrl}`)}`}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="inline-flex items-center gap-1 rounded-full border border-line px-3 py-1.5 text-xs font-semibold text-ok hover:bg-ok-bg"
                >
                    <MessageCircle className="h-3.5 w-3.5" />
                    WhatsApp
                </a>
                <a
                    href={`https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(shareUrl)}`}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="inline-flex items-center gap-1 rounded-full border border-line px-3 py-1.5 text-xs font-semibold text-ink-2 hover:bg-surface-2"
                >
                    Facebook
                </a>
                <button
                    type="button"
                    onClick={() => navigator.clipboard?.writeText(shareUrl)}
                    className="inline-flex items-center gap-1 rounded-full border border-line px-3 py-1.5 text-xs font-semibold text-ink-2 hover:bg-surface-2"
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
                                className="group flex flex-col overflow-hidden rounded-card border border-line bg-surface shadow-1 hover:shadow-2"
                            >
                                <div className="aspect-video overflow-hidden bg-line/40">
                                    {item.cover_image_url ? (
                                        <img src={item.cover_image_url} alt={item.title} loading="lazy" className="h-full w-full object-cover group-hover:scale-105 transition-transform" />
                                    ) : (
                                        <div className="flex h-full w-full items-center justify-center text-2xl">☕</div>
                                    )}
                                </div>
                                <div className="p-3">
                                    <h3 className="text-sm font-semibold text-ink line-clamp-2">{item.title}</h3>
                                    <span className="mt-1 block text-[11px] text-ink-2">{formatDate(item.published_at)}</span>
                                </div>
                            </Link>
                        ))}
                    </div>
                </section>
            )}
        </article>
        </PublicBlogLayout>
    );
}