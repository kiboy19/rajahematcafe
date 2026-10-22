import * as React from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import {
    ArrowRight,
    Clock,
    Coffee,
    MapPin,
    MessageCircle,
    Phone,
    QrCode,
    Globe,
    ThumbsUp,
    Check,
    Heart,
    Search,
    RotateCcw,
    Menu,
} from 'lucide-react';
import { money } from '@/react/spa/api';
import type { BlogCard } from './BlogListPage';
import { usePublicPageView } from '../usePublicPageView';
import { useScrollReveal } from '../hooks';
import { Button, Card, Badge, Input, Textarea, Label, FormField, Toast, Dialog, DialogTrigger, DialogContent, DialogTitle, DialogDescription } from '../ui-primitives';

/** Tujuan CTA utama website publik (A4): langsung ke pendaftaran pelanggan. */
export const REGISTER_CTA = '/daftar?return_to=%2Fmeja';

export interface PublicBranding {
    brand_name: string;
    brand_tagline?: string | null;
    logo_url?: string | null;
    description?: string | null;
    address?: string | null;
    phone?: string | null;
    whatsapp_link?: string | null;
    instagram_url?: string | null;
    facebook_url?: string | null;
    google_maps_embed_url?: string | null;
    opening_hours?: string | null;
    footer_text?: string | null;
}

interface MenuCard {
    id: number;
    name: string;
    price: number | string;
    image_url?: string | null;
    category_id?: number | null;
    category_name?: string | null;
    description?: string | null;
}

interface HomePayload {
    branding: PublicBranding;
    featured_menu_items: MenuCard[];
    categories: Array<{ id: number; name: string; menu_items_count?: number }>;
    posts: BlogCard[];
}

const FALLBACK_BRANDING: PublicBranding = {
    brand_name: 'Rajahemat Cafe',
    brand_tagline: 'Ngopi Hemat, Rasa Nikmat',
};

function usePublicData<T>(endpoint: string): { data: T | null; loading: boolean } {
    const [data, setData] = React.useState<T | null>(null);
    const [loading, setLoading] = React.useState(true);

    React.useEffect(() => {
        let active = true;

        fetch(endpoint, { headers: { Accept: 'application/json' } })
            .then((response) => response.json())
            .then((json) => {
                if (active) {
                    setData((json.data ?? null) as T | null);
                }
            })
            .finally(() => {
                if (active) {
                    setLoading(false);
                }
            });

        return () => {
            active = false;
        };
    }, [endpoint]);

    return { data, loading };
}

function formatDate(value?: string | null): string {
    if (!value) {
        return '';
    }

    return new Date(value).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
}

function PublicHeader({ branding }: { branding: PublicBranding }) {
    const navLinks = [
        { to: '/daftar-menu', label: 'Menu' },
        { to: '/tentang-kami', label: 'Tentang Kami' },
        { to: '/blog', label: 'Blog' },
        { to: '/kontak', label: 'Kontak' },
    ];

    return (
        <header className="sticky top-0 z-40 border-b border-line bg-bg/95 backdrop-blur">
            <div className="mx-auto flex h-16 max-w-7xl items-center justify-between gap-4 px-4">
                <Link to="/" className="flex min-w-0 items-center gap-3">
                    {branding.logo_url ? (
                        <img
                            src={branding.logo_url}
                            alt={branding.brand_name}
                            className="h-10 w-10 rounded-full object-cover"
                        />
                    ) : (
                        <span className="inline-flex h-10 w-10 items-center justify-center rounded-full bg-accent text-ink-on-dark">
                            <Coffee className="h-5 w-5" aria-hidden="true" />
                        </span>
                    )}
                    <span className="truncate font-display text-lg font-bold">{branding.brand_name}</span>
                </Link>

                <nav aria-label="Navigasi utama" className="hidden items-center gap-6 text-sm font-semibold md:flex">
                    {navLinks.map((link) => (
                        <Link key={link.to} to={link.to} className="rounded-input px-1 py-2 hover:text-primary">{link.label}</Link>
                    ))}
                </nav>

                <div className="flex items-center gap-2">
                    <Link
                        to="/masuk"
                        className="hidden rounded-input px-3 py-2 text-sm font-semibold text-ink-2 hover:bg-surface-2 hover:text-ink sm:block"
                    >
                        Masuk
                    </Link>
                    <Button asChild size="sm" variant="outline">
                        <Link to="/daftar-menu">Jelajahi Menu</Link>
                    </Button>

                    {/* Drawer navigasi mobile (Radix Dialog: focus trap + scroll lock + Escape). */}
                    <Dialog>
                        <DialogTrigger asChild>
                            <button
                                type="button"
                                aria-label="Buka menu navigasi"
                                className="inline-flex h-11 w-11 items-center justify-center rounded-input hover:bg-surface-2 md:hidden"
                            >
                                <Menu className="h-5 w-5" aria-hidden="true" />
                            </button>
                        </DialogTrigger>
                        <DialogContent className="max-w-xs rounded-card" aria-describedby={undefined}>
                            <div className="flex items-center gap-3">
                                {branding.logo_url ? (
                                    <img src={branding.logo_url} alt={branding.brand_name} className="h-10 w-10 rounded-full object-cover" />
                                ) : (
                                    <span className="inline-flex h-10 w-10 items-center justify-center rounded-full bg-accent text-ink-on-dark">
                                        <Coffee className="h-5 w-5" aria-hidden="true" />
                                    </span>
                                )}
                                <DialogTitle className="font-display text-lg font-bold">{branding.brand_name}</DialogTitle>
                            </div>
                            <DialogDescription className="mt-1 text-xs text-ink-2">
                                {branding.opening_hours ?? 'Setiap hari · 08.00 – 22.00'}
                            </DialogDescription>
                            <nav aria-label="Navigasi utama" className="mt-6 grid">
                                {navLinks.map((link) => (
                                    <Link key={link.to} to={link.to} className="rounded-input px-2 py-3 text-sm font-semibold hover:bg-surface-2">
                                        {link.label}
                                    </Link>
                                ))}
                            </nav>
                            <div className="mt-5 grid grid-cols-2 gap-2">
                                <Button asChild variant="outline">
                                    <Link to="/masuk">Masuk</Link>
                                </Button>
                                <Button asChild>
                                    <Link to="/daftar-menu">Jelajahi Menu</Link>
                                </Button>
                            </div>
                        </DialogContent>
                    </Dialog>
                </div>
            </div>
        </header>
    );
}

function PublicFooter({ branding }: { branding: PublicBranding }) {
    return (
        <footer className="mt-16 bg-accent text-ink-on-dark">
            <div className="mx-auto grid max-w-7xl gap-8 px-4 py-12 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <h2 className="font-display text-xl font-bold">{branding.brand_name}</h2>
                    <p className="mt-2 text-sm opacity-80">{branding.brand_tagline}</p>
                    <p className="mt-4 text-sm opacity-80">
                        {branding.description
                            ?? 'Tempat ngopi nyaman dengan rasa nikmat dan harga bersahabat.'}
                    </p>
                    <div className="mt-4 flex items-center gap-3">
                        {branding.instagram_url && (
                            <a
                                href={branding.instagram_url}
                                target="_blank"
                                rel="noopener noreferrer"
                                aria-label="Instagram"
                                className="opacity-80 hover:opacity-100"
                            >
                                <Globe className="h-5 w-5" />
                            </a>
                        )}
                        {branding.facebook_url && (
                            <a
                                href={branding.facebook_url}
                                target="_blank"
                                rel="noopener noreferrer"
                                aria-label="Facebook"
                                className="opacity-80 hover:opacity-100"
                            >
                                <ThumbsUp className="h-5 w-5" />
                            </a>
                        )}
                        {branding.whatsapp_link && (
                            <a
                                href={branding.whatsapp_link}
                                target="_blank"
                                rel="noopener noreferrer"
                                aria-label="WhatsApp"
                                className="opacity-80 hover:opacity-100"
                            >
                                <MessageCircle className="h-5 w-5" />
                            </a>
                        )}
                    </div>
                </div>

                <div>
                    <h3 className="font-semibold">Navigasi</h3>
                    <nav aria-label="Navigasi footer" className="mt-3 flex flex-col gap-2 text-sm opacity-80">
                        <Link to="/daftar-menu">Daftar Menu</Link>
                        <Link to="/blog">Blog</Link>
                        <Link to="/tentang-kami">Tentang Kami</Link>
                        <Link to="/kontak">Kontak</Link>
                    </nav>
                </div>

                <div>
                    <h3 className="font-semibold">Member</h3>
                    <nav aria-label="Navigasi member" className="mt-3 flex flex-col gap-2 text-sm opacity-80">
                        <Link to="/masuk">Masuk</Link>
                        <Link to="/daftar">Daftar akun</Link>
                        <Link to="/masuk">Saldo & pesan cepat</Link>
                    </nav>
                </div>

                <div>
                    <h3 className="font-semibold">Hubungi Kami</h3>
                    <p className="mt-3 text-sm opacity-80">
                        {branding.address ?? 'Kunjungi kami untuk secangkir kopi terbaik.'}
                    </p>
                    {branding.phone && <p className="mt-2 text-sm opacity-80">{branding.phone}</p>}
                    {branding.opening_hours && (
                        <p className="mt-2 text-sm opacity-80">Buka {branding.opening_hours}</p>
                    )}
                </div>
            </div>

            <div className="border-t border-white/10 py-4 text-center text-xs opacity-60">
                {branding.footer_text ?? `© ${new Date().getFullYear()} ${branding.brand_name}`}
            </div>
        </footer>
    );
}

export function PublicShell({
    children,
    branding,
}: {
    children: React.ReactNode;
    branding?: PublicBranding;
}) {
    const brand = branding ?? FALLBACK_BRANDING;

    usePublicPageView();

    return (
        <div className="min-h-screen bg-bg text-ink">
            <a href="#main-content" className="sr-only focus:not-sr-only focus:fixed focus:top-4 focus:left-4 focus:z-50 focus:rounded-input focus:bg-primary focus:px-4 focus:py-2 focus:text-white">
                Loncati ke konten utama
            </a>
            <PublicHeader branding={brand} />
            <main id="main-content" className="pb-20 md:pb-0">{children}</main>
            <PublicFooter branding={brand} />

            {/* CTA lengket khusus layar kecil. */}
            <div className="fixed inset-x-0 bottom-0 z-40 border-t border-line bg-surface p-3 md:hidden">
                <Button asChild className="w-full" variant="outline">
                    <Link to="/daftar-menu">
                        Jelajahi Menu
                        <ArrowRight className="h-4 w-4" aria-hidden="true" />
                    </Link>
                </Button>
            </div>
        </div>
    );
}

function HeroSection({ branding }: { branding: PublicBranding }) {
    return (
        <section className="overflow-hidden bg-surface-2">
            <div className="mx-auto grid max-w-7xl items-center gap-10 px-4 py-14 md:grid-cols-[1.02fr_.98fr] md:gap-14 md:py-20">
                <div className="motion-fade-up">
                    <Badge variant="outline" className="border-primary/20 bg-surface-strong text-primary">
                        Kedai kopi & eatery lokal
                    </Badge>
                    <h1 className="mt-5 max-w-xl font-display text-4xl font-bold leading-[1.05] sm:text-6xl">
                        Ngopi hemat,<br />rasa nikmat.
                    </h1>
                    <p className="mt-5 max-w-lg text-lg leading-relaxed text-ink-2">
                        {branding.description ?? `${branding.brand_name} adalah kedai kecil tempat biji kopi lokal diseduh dengan serius, makanan dibuat di dapur sendiri, dan semuanya disajikan dengan harga yang tetap masuk akal.`}
                    </p>
                    <div className="mt-6 flex flex-wrap gap-x-6 gap-y-2 rounded-card border border-line bg-surface p-4 text-sm font-semibold shadow-1">
                        <span className="flex items-center gap-2"><Clock className="h-4 w-4 text-primary" aria-hidden="true" />{branding.opening_hours ?? 'Buka setiap hari'}</span>
                        <span className="flex items-center gap-2"><MapPin className="h-4 w-4 text-primary" aria-hidden="true" />{branding.address ?? 'Kunjungi kedai kami'}</span>
                    </div>
                    <div className="mt-6 flex flex-wrap items-center gap-4">
                        <Button asChild variant="outline"><Link to="/daftar-menu">Jelajahi Menu <ArrowRight className="h-4 w-4" aria-hidden="true" /></Link></Button>
                        <a href="#kenali-kami" className="font-semibold text-primary hover:text-primary-hover">Kenali kami ↓</a>
                    </div>
                </div>
                <div className="relative mx-auto h-72 w-full max-w-lg md:h-[380px] motion-fade-up" aria-label="Suasana Rajahemat Cafe">
                    <div className="absolute left-0 top-0 z-10 flex aspect-[4/3] w-[76%] items-end overflow-hidden rounded-card bg-thumb shadow-2">
                        <img
                            src="/images/hero-cafe.svg"
                            alt="Sudut brew bar Rajahemat saat pagi hari"
                            loading="lazy"
                            className="absolute inset-0 h-full w-full object-cover"
                        />
                        <span className="relative m-3 rounded-input bg-surface/95 px-3 py-1.5 text-xs font-semibold text-ink-2">Sudut brew bar, pagi hari</span>
                    </div>
                    <div className="absolute bottom-0 right-0 z-20 flex aspect-[4/3] w-[44%] items-end overflow-hidden rounded-card border-4 border-surface bg-surface-strong shadow-2">
                        <img
                            src="/images/menu-placeholder.svg"
                            alt="Hidangan roti panggang"
                            loading="lazy"
                            className="absolute inset-0 h-full w-full object-cover"
                        />
                        <span className="relative m-2 rounded-input bg-surface/95 px-2 py-1 text-[11px] font-semibold text-ink-2">Roti panggang</span>
                    </div>
                </div>
            </div>
        </section>
    );
}

/** Kenali Kami — brand story with quote, traits, and atmosphere photo. */
function AboutSection({ branding }: { branding: PublicBranding }) {
    const photoRef = useScrollReveal<HTMLDivElement>();

    return (
        <section id="kenali-kami" className="bg-surface-2">
            <div className="mx-auto grid max-w-7xl gap-12 px-4 py-16 md:grid-cols-[1.05fr_.95fr] md:gap-14 md:items-center">
                <div className="motion-fade-up">
                    <p className="font-semibold uppercase tracking-wider text-primary">Perkenalan</p>
                    <h2 className="mt-2 font-display text-3xl font-bold sm:text-4xl">Kopi lokal, diseduh dengan cerita.</h2>
                    <p className="mt-4 leading-relaxed text-ink-2">
                        Kami bekerja sama dengan petani kopi di lereng Gedung Songo dan Karangsari. Setiap musim panen, kami memilih lot terbaik, lalu menyeduhnya segar setiap hari.
                    </p>
                    <blockquote className="mt-6 border-l-3 border-primary pl-4 font-display text-lg font-medium italic leading-relaxed text-ink">
                        "Kopi yang baik tidak harus mahal — ia harus jujur, dari biji ke cangkir."
                    </blockquote>
                    <div className="mt-6 flex flex-wrap gap-6">
                        <span className="flex items-center gap-2 text-sm text-ink-2"><Check className="h-4 w-4 text-primary shrink-0" aria-hidden="true" />Biji kopi lokal</span>
                        <span className="flex items-center gap-2 text-sm text-ink-2"><Check className="h-4 w-4 text-primary shrink-0" aria-hidden="true" />Diseduh saat dipesan</span>
                        <span className="flex items-center gap-2 text-sm text-ink-2"><Check className="h-4 w-4 text-primary shrink-0" aria-hidden="true" />Harga bersahabat</span>
                    </div>
                    <Link to="/tentang-kami" className="mt-6 inline-flex items-center gap-2 font-semibold text-primary hover:text-primary-hover">Baca cerita lengkap →</Link>
                </div>
                <div ref={photoRef} className="relative mx-auto h-[340px] w-full max-w-md motion-fade-up" aria-label="Interior kedai">
                    <div className="absolute inset-0 overflow-hidden rounded-card bg-thumb motion-ken-burns motion-reduced">
                        <img
                            src="/images/about-interior.svg"
                            alt="Interior kedai Rajahemat"
                            loading="lazy"
                            className="h-full w-full object-cover"
                        />
                    </div>
                    <div className="absolute bottom-0 right-0 z-10 mx-4 mb-4 flex w-[300px] max-w-full overflow-hidden rounded-card border border-line bg-surface shadow-2">
                        <div className="p-4">
                            <h3 className="font-display text-base font-bold">Ayo ngopi hari ini</h3>
                            <p className="mt-1 text-sm text-ink-2">Daftar member, pesan dari meja, nikmati pengalaman terbaik di {branding.brand_name}.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    );
}

/** Menu section with category tabs and product cards. */
function MenuCardTile({ item }: { item: MenuCard }) {
    return (
        <Card className="overflow-hidden transition-transform duration-200 hover:-translate-y-0.5 hover:shadow-2">
            <div className="flex aspect-[4/3] items-center justify-center bg-thumb text-5xl">
                {item.image_url ? (
                    <img src={item.image_url} alt={item.name} loading="lazy" className="h-full w-full object-cover" />
                ) : (
                    <img
                        src="/images/menu-placeholder.svg"
                        alt={item.name}
                        loading="lazy"
                        className="h-full w-full object-cover opacity-85"
                    />
                )}
            </div>
            <div className="p-4">
                <h3 className="font-display text-base font-bold leading-tight">{item.name}</h3>
                <p className="mt-1 line-clamp-2 text-xs leading-relaxed text-ink-2">{item.description ?? 'Diseduh dan disajikan segar saat dipesan.'}</p>
                <p className="mt-3 font-bold text-primary tabular">{money(item.price)}</p>
            </div>
        </Card>
    );
}

function MenuSection({
    categories,
    items,
    loading,
}: {
    categories: Array<{ id: number; name: string }>;
    items: MenuCard[];
    loading: boolean;
}) {
    const defaultCat = categories[0]?.name ?? 'Kopi';
    const [selectedCat, setSelectedCat] = React.useState<string>(defaultCat);

    React.useEffect(() => {
        if (categories.length > 0 && !categories.some((c) => c.name === selectedCat)) {
            setSelectedCat(categories[0].name);
        }
    }, [categories, selectedCat]);

    const activeCategoryId = categories.find((c) => c.name === selectedCat)?.id;
    const currentItems = activeCategoryId
        ? items.filter((item) => item.category_id === activeCategoryId)
        : items;

    return (
        <section className="mx-auto max-w-7xl px-4 py-16 motion-fade-up">
            <div className="flex items-end justify-between gap-4">
                <div>
                    <p className="font-semibold uppercase tracking-wider text-primary">Menu</p>
                    <h2 className="mt-2 font-display text-3xl font-bold sm:text-4xl">Ada apa di menu kami?</h2>
                    <p className="mt-2 max-w-2xl text-sm leading-relaxed text-ink-2">
                        Dari espresso pertama jam tujuh pagi sampai camilan sore — semuanya dibuat saat dipesan, tidak disimpan lama.
                    </p>
                </div>
            </div>

            {categories.length > 0 && (
                <div className="mt-8 flex gap-2 overflow-x-auto pb-2">
                    {categories.map((c) => {
                        const isActive = c.name === selectedCat;
                        return (
                            <button
                                key={c.id}
                                type="button"
                                onClick={() => setSelectedCat(c.name)}
                                className={`whitespace-nowrap rounded-full px-5 py-2 text-sm font-bold transition-colors ${
                                    isActive
                                        ? 'bg-surface-strong text-primary'
                                        : 'border border-line-strong text-ink-2 hover:bg-surface'
                                }`}
                            >
                                {c.name}
                            </button>
                        );
                    })}
                </div>
            )}

            {loading ? (
                <div className="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                    {[1, 2, 3, 4].map((i) => (
                        <div key={i} className="h-64 rounded-card bg-surface-2 animate-pulse" />
                    ))}
                </div>
            ) : currentItems.length === 0 ? (
                <div className="mt-8 rounded-card border border-line bg-surface p-12 text-center">
                    <p className="text-sm font-semibold text-ink-2">Belum ada menu di kategori ini.</p>
                    <Link to="/daftar-menu" className="mt-3 inline-block text-sm font-bold text-primary hover:underline">
                        Lihat semua menu →
                    </Link>
                </div>
            ) : (
                <div className="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                    {currentItems.map((item) => (
                        <MenuCardTile key={item.id} item={item} />
                    ))}
                </div>
            )}

            <div className="mt-10 flex flex-wrap items-center justify-between gap-4">
                <div className="flex items-center gap-3 rounded-card bg-surface-2 p-3 text-sm text-ink-2">
                    <QrCode className="h-5 w-5 shrink-0 text-primary" aria-hidden="true" />
                    <span><b>Sedang duduk di cafe?</b> Pindai QR di meja Anda untuk memesan langsung ke barista.</span>
                </div>
                <Link to="/daftar-menu" className="font-semibold text-primary hover:text-primary-hover">
                    Lihat seluruh menu →
                </Link>
            </div>
        </section>
    );
}

function BlogPreviewSection({ posts }: { posts: BlogCard[] }) {
    if (posts.length === 0) {
        return null;
    }

    return (
        <section className="mx-auto max-w-7xl px-4 py-16 motion-fade-up">
            <div className="flex items-end justify-between gap-4">
                <div>
                    <p className="text-sm font-semibold uppercase tracking-wider text-primary">Blog</p>
                    <h2 className="mt-2 font-display text-3xl font-bold sm:text-4xl">Cerita dari balik bar.</h2>
                </div>
                <Link to="/blog" className="font-semibold text-primary hover:text-primary-hover">
                    Semua artikel →
                </Link>
            </div>

            <div className="mt-8 grid gap-6 md:grid-cols-3">
                {posts.map((post) => (
                    <Link
                        key={post.id}
                        to={`/blog/${post.slug}`}
                        className="group overflow-hidden rounded-card border border-line bg-surface transition-all duration-200 hover:-translate-y-1 hover:shadow-2"
                    >
                        <div className="flex aspect-video items-center justify-center bg-thumb overflow-hidden">
                            {post.cover_image_url ? (
                                <img
                                    src={post.cover_image_url}
                                    alt={post.title}
                                    loading="lazy"
                                    className="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105"
                                />
                            ) : (
                                <Coffee className="h-12 w-12 text-primary/30" aria-hidden="true" />
                            )}
                        </div>
                        <div className="p-5">
                            {post.category_label && (
                                <Badge variant="outline" className="mb-3 border-primary/20 text-primary">
                                    {post.category_label}
                                </Badge>
                            )}
                            <h3 className="font-display text-lg font-bold leading-tight group-hover:text-primary transition-colors">
                                {post.title}
                            </h3>
                            <p className="mt-2 line-clamp-2 text-sm leading-relaxed text-ink-2">{post.excerpt}</p>
                            <p className="mt-4 text-[11px] font-medium text-ink-2 opacity-60">
                                {formatDate(post.published_at)}
                            </p>
                        </div>
                    </Link>
                ))}
            </div>
            <div className="mt-6 sm:hidden">
                <Link to="/blog" className="font-semibold text-primary hover:text-primary-hover">
                    Semua artikel →
                </Link>
            </div>
        </section>
    );
}

function LocationSection({ branding }: { branding: PublicBranding }) {
    const mapsQuery = encodeURIComponent(branding.address || branding.brand_name || 'Rajahemat Cafe');
    const directionsUrl = `https://www.google.com/maps/search/?api=1&query=${mapsQuery}`;

    return (
        <section className="mx-auto max-w-7xl px-4 py-16 motion-fade-up">
            <div className="grid gap-12 md:grid-cols-2 md:items-center">
                <div>
                    <p className="font-semibold uppercase tracking-wider text-primary">Kunjungi kami</p>
                    <h2 className="mt-2 font-display text-3xl font-bold sm:text-4xl">Mari ngopi bersama.</h2>
                    <p className="mt-4 max-w-md text-lg leading-relaxed text-ink-2">
                        Tempat parkir mudah, ruang luas, dan listrik di setiap meja untuk yang sedang kerja. Kami buka tanpa jeda sepanjang hari.
                    </p>

                    <div className="mt-8 flex flex-wrap items-center gap-4">
                        <Button asChild className="bg-accent text-ink-on-dark hover:bg-accent/90">
                            <a href={directionsUrl} target="_blank" rel="noopener noreferrer">
                                Petunjuk Arah <ArrowRight className="h-4 w-4" aria-hidden="true" />
                            </a>
                        </Button>
                        <Link to="/kontak" className="inline-flex items-center gap-1 font-semibold text-primary hover:text-primary-hover">
                            Hubungi kami →
                        </Link>
                    </div>
                </div>

                <div className="grid gap-6">
                    <div className="rounded-card border border-line bg-surface p-6 shadow-1">
                        <dl className="grid gap-5">
                            <div>
                                <dt className="text-[11px] font-bold uppercase tracking-widest text-ink-2">Alamat</dt>
                                <dd className="mt-1 font-semibold text-ink">
                                    {branding.address ?? 'Jl. Raya Kopi No. 12, Rajahemat'}
                                    <small className="block text-xs font-normal text-ink-2">Parkir depan dan belakang kedai</small>
                                </dd>
                            </div>
                            <div>
                                <dt className="text-[11px] font-bold uppercase tracking-widest text-ink-2">Jam Buka</dt>
                                <dd className="mt-1 font-semibold text-ink">
                                    {branding.opening_hours ?? 'Setiap hari · 08.00 – 22.00'}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-[11px] font-bold uppercase tracking-widest text-ink-2">Kontak</dt>
                                <dd className="mt-1 font-semibold text-ink">
                                    {branding.phone ?? 'hello@rajahemat.test'}
                                    {branding.instagram_url && (
                                        <small className="block text-xs font-normal text-ink-2">Instagram aktif</small>
                                    )}
                                </dd>
                            </div>
                        </dl>
                    </div>

                    <div className="h-64 overflow-hidden rounded-card border border-line bg-surface-2 shadow-1">
                        {branding.google_maps_embed_url ? (
                            <iframe
                                src={branding.google_maps_embed_url}
                                title={`Lokasi ${branding.brand_name}`}
                                loading="lazy"
                                referrerPolicy="no-referrer-when-downgrade"
                                className="h-full w-full border-0"
                            />
                        ) : (
                            <div className="flex h-full flex-col items-center justify-center gap-2 text-ink-2">
                                <MapPin className="h-8 w-8 text-primary/40" aria-hidden="true" />
                                <span className="text-sm">Peta lokasi kedai</span>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </section>
    );
}

/** Landing page publik: hero, kenali kami, menu kami, blog terbaru, dan kunjungi kami. */
export function LandingPage() {
    const { data, loading } = usePublicData<HomePayload>('/api/publik/beranda');
    const branding = data?.branding ?? FALLBACK_BRANDING;

    return (
        <PublicShell branding={branding}>
            <HeroSection branding={branding} />
            <AboutSection branding={branding} />
            <MenuSection
                categories={data?.categories ?? []}
                items={data?.featured_menu_items ?? []}
                loading={loading}
            />
            <BlogPreviewSection posts={data?.posts ?? []} />
            <LocationSection branding={branding} />
        </PublicShell>
    );
}

/** Layout pembungkus halaman blog publik (header + footer + pencatatan kunjungan). */
export function PublicBlogLayout({ children }: { children: React.ReactNode }) {
    const { data } = usePublicData<{ branding: PublicBranding }>('/api/publik/beranda');

    return <PublicShell branding={data?.branding}>{children}</PublicShell>;
}

/** Katalog menu publik lengkap, dikelompokkan per kategori. */
export function PublicMenuPage() {
    const [searchParams, setSearchParams] = useSearchParams();
    const category = searchParams.get('kategori') ?? '';
    const search = searchParams.get('cari') ?? '';
    const [searchInput, setSearchInput] = React.useState(search);
    const [data, setData] = React.useState<{ branding: PublicBranding; groups: Array<{ category: string; items: MenuCard[] }>; total: number } | null>(null);
    const [loading, setLoading] = React.useState(true);
    const [error, setError] = React.useState(false);

    const loadMenu = React.useCallback(() => {
        setLoading(true);
        setError(false);
        const query = new URLSearchParams();
        if (category) query.set('category', category);
        if (search) query.set('search', search);
        fetch(`/api/publik/menu?${query.toString()}`, { headers: { Accept: 'application/json' } })
            .then((response) => response.json())
            .then((json) => setData(json.data ?? null))
            .catch(() => setError(true))
            .finally(() => setLoading(false));
    }, [category, search]);

    React.useEffect(() => {
        const timer = window.setTimeout(() => {
            const next = new URLSearchParams(searchParams);
            if (searchInput) next.set('cari', searchInput); else next.delete('cari');
            if (searchInput !== search) setSearchParams(next, { replace: true });
        }, 200);
        return () => window.clearTimeout(timer);
    }, [searchInput, search, searchParams, setSearchParams]);

    React.useEffect(() => { loadMenu(); }, [loadMenu]);

    const groups = data?.groups ?? [];
    const categories = groups.map((group) => group.category);
    const visibleGroups = category ? groups.filter((group) => group.category === category) : groups;

    return (
        <PublicShell branding={data?.branding}>
            <div className="mx-auto max-w-7xl px-4 py-12">
                <div className="motion-fade-up">
                    <p className="font-semibold uppercase tracking-wider text-primary">Pilihan kami</p>
                    <h1 className="mt-2 font-display text-4xl font-bold">Daftar Menu</h1>
                    <p className="mt-2 text-sm text-ink-2">Semua menu yang tampil di sini tersedia hari ini.</p>
                </div>
                <div className="sticky top-16 z-20 mt-8 flex flex-col gap-3 border-y border-line bg-bg/95 py-3 backdrop-blur sm:flex-row">
                    <div className="relative flex-1">
                        <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-ink-2" aria-hidden="true" />
                        <Input value={searchInput} onChange={(event) => setSearchInput(event.target.value)} placeholder="Cari menu…" className="pl-9" aria-label="Cari menu" />
                    </div>
                    <div className="flex gap-2 overflow-x-auto pb-1">
                        <button type="button" onClick={() => { const next = new URLSearchParams(searchParams); next.delete('kategori'); setSearchParams(next); }} className={`whitespace-nowrap rounded-full px-4 py-2 text-sm font-semibold ${!category ? 'bg-primary text-white' : 'border border-line bg-surface text-ink-2'}`}>Semua</button>
                        {categories.map((name) => <button key={name} type="button" onClick={() => { const next = new URLSearchParams(searchParams); next.set('kategori', name); setSearchParams(next); }} className={`whitespace-nowrap rounded-full px-4 py-2 text-sm font-semibold ${category === name ? 'bg-primary text-white' : 'border border-line bg-surface text-ink-2'}`}>{name}</button>)}
                    </div>
                </div>
                {error ? <div role="alert" className="mt-8 flex items-center justify-between rounded-card border border-bad/20 bg-bad-bg p-4 text-sm text-bad">Gagal memuat menu.<Button variant="outline" size="sm" onClick={loadMenu}><RotateCcw className="h-4 w-4" />Coba lagi</Button></div> : null}
                {loading ? <div className="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">{[1,2,3,4,5,6,7,8].map((i) => <div key={i} className="h-64 animate-pulse rounded-card bg-surface-2" />)}</div> : visibleGroups.length === 0 ? <div className="py-24 text-center"><h2 className="font-display text-2xl font-bold">Tidak ditemukan</h2><p className="mt-2 text-sm text-ink-2">Coba kata kunci atau kategori lain.</p><Button variant="outline" className="mt-5" onClick={() => { setSearchInput(''); setSearchParams({}); }}>Reset filter</Button></div> : visibleGroups.map((group) => <section key={group.category} className="mt-10"><h2 className="font-display text-2xl font-bold">{group.category}</h2><div className="mt-4 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">{group.items.map((item) => <MenuCardTile key={item.id} item={item} />)}</div></section>)}
            </div>
        </PublicShell>
    );
}

/** Halaman tentang kafe: narasi brand, nilai, dan suasana. */
export function AboutPage() {
    const { data } = usePublicData<HomePayload>('/api/publik/beranda');
    const branding = data?.branding ?? FALLBACK_BRANDING;

    return (
        <PublicShell branding={branding}>
            <div className="mx-auto max-w-3xl px-4 py-16">
                <div className="motion-fade-up">
                    <p className="font-semibold uppercase tracking-wider text-primary">Tentang kami</p>
                    <h1 className="mt-3 font-display text-4xl font-bold">Kopi yang dekat dengan hati.</h1>
                    <p className="mt-6 text-lg leading-relaxed text-ink-2">
                        {branding.description
                            ?? `${branding.brand_name} adalah ruang berkumpul yang menghadirkan kopi nikmat, makanan lezat, dan harga yang tetap bersahabat. Kami ingin setiap kunjungan terasa seperti pulang.`}
                    </p>
                </div>

                <div className="mt-10 grid gap-6 sm:grid-cols-2 motion-fade-up">
                    <img
                        src="/images/hero-cafe.svg"
                        alt="Suasana kedai"
                        loading="lazy"
                        width={600}
                        height={450}
                        className="aspect-[4/3] w-full rounded-card border border-line object-cover"
                    />
                    <img
                        src="/images/about-interior.svg"
                        alt="Interior kedai"
                        loading="lazy"
                        width={600}
                        height={450}
                        className="aspect-[4/3] w-full rounded-card border border-line object-cover"
                    />
                </div>

                <div className="mt-12 grid gap-5 sm:grid-cols-3 motion-fade-up">
                    {[
                        { icon: Check, title: 'Biji lokal', body: 'Biji pilihan yang diseduh saat dipesan.' },
                        { icon: Clock, title: 'Nyaman', body: 'Tempat bertemu, bekerja, dan bercerita.' },
                        { icon: Heart, title: 'Bersahabat', body: 'Harga hemat tanpa mengurangi rasa.' },
                    ].map((item) => (
                        <div key={item.title} className="rounded-card bg-surface-2 p-6">
                            <item.icon className="h-6 w-6 text-primary" aria-hidden="true" />
                            <h2 className="mt-4 font-bold">{item.title}</h2>
                            <p className="mt-2 text-sm text-ink-2">{item.body}</p>
                        </div>
                    ))}
                </div>

                <blockquote className="mt-12 border-l-4 border-primary pl-5 font-display text-xl italic motion-fade-up">
                    “Kopi yang baik tidak harus mahal — ia harus jujur, dari biji ke cangkir.”
                </blockquote>
            </div>
        </PublicShell>
    );
}

/** Halaman kontak dan lokasi. */
export function ContactPage() {
    const { data } = usePublicData<HomePayload>('/api/publik/beranda');
    const branding = data?.branding ?? FALLBACK_BRANDING;
    const [contactStatus, setContactStatus] = React.useState<'idle' | 'success' | 'error'>('idle');
    const [submitting, setSubmitting] = React.useState(false);

    async function handleSubmit(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();
        const form = event.currentTarget;
        const payload = new FormData(form);
        payload.append('website', String(payload.get('website') ?? ''));

        setSubmitting(true);
        setContactStatus('idle');
        const csrf = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

        await fetch('/api/publik/kontak', { method: 'POST', credentials: 'same-origin', body: payload, headers: { 'X-CSRF-TOKEN': csrf, accept: 'application/json' } })
            .then((r) => setContactStatus(r.ok ? 'success' : 'error'))
            .catch(() => setContactStatus('error'));
        setSubmitting(false);
    }

    return (
        <PublicShell branding={branding}>
            <div className="mx-auto max-w-4xl px-4 py-16">
                <p className="font-semibold uppercase tracking-wider text-primary">Kontak</p>
                <h1 className="mt-3 font-display text-4xl font-bold">Mari berkunjung.</h1>
                <p className="mt-4 max-w-xl text-ink-2">
                    Isi formulir di bawah atau hubungi kami langsung melalui <a href={branding.whatsapp_link ?? '#'} target="_blank" rel="noopener noreferrer" className="text-primary font-semibold underline-offset-2 hover:underline">WhatsApp</a>. Balasan cepat selama jam operasional.
                </p>

                <form className="mt-8 grid gap-4" onSubmit={handleSubmit}>
                    <FormField label="Nama" htmlFor="contact-nama">
                        <Input id="contact-nama" name="name" placeholder="Nama Anda" required />
                    </FormField>
                    <FormField label="Email" htmlFor="contact-email">
                        <Input id="contact-email" name="email" type="email" placeholder="email@contoh.com" required />
                    </FormField>
                    <FormField label="Pesan" htmlFor="contact-pesan">
                        <Textarea id="contact-pesan" name="message" placeholder="Tulis pesan Anda…" required rows={4} />
                    </FormField>
                    <div className="absolute left-[-9999px] opacity-0" aria-hidden="true">
                        <Label htmlFor="contact-website">Website</Label>
                        <Input id="contact-website" name="website" tabIndex={-1} autoComplete="off" />
                    </div>
                    <Button type="submit" pending={submitting} className="w-fit">Kirim Pesan</Button>
                    {contactStatus === 'success' ? <p role="status" className="rounded-input bg-ok-bg p-3 text-sm text-ok">Pesan terkirim! Kami akan segera membalas.</p> : null}
                    {contactStatus === 'error' ? <p role="alert" className="rounded-input bg-bad-bg p-3 text-sm text-bad">Gagal mengirim. Coba lagi nanti.</p> : null}
                </form>
                <div className="mt-10 grid gap-5 sm:grid-cols-2">
                    <div className="rounded-card border border-line bg-surface p-6 shadow-1">
                        <MapPin className="text-primary" />
                        <h2 className="mt-4 font-bold">Alamat</h2>
                        <p className="mt-2 text-sm text-ink-2">{branding.address ?? 'Alamat kafe akan segera diperbarui.'}</p>
                    </div>
                    <div className="rounded-card border border-line bg-surface p-6 shadow-1">
                        <Phone className="text-primary" />
                        <h2 className="mt-4 font-bold">Hubungi kami</h2>
                        <p className="mt-2 text-sm text-ink-2">{branding.phone ?? 'Hubungi kami melalui WhatsApp.'}</p>
                    </div>
                </div>
                <LocationSection branding={branding} />
            </div>
        </PublicShell>
    );
}
