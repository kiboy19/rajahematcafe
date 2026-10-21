import * as React from 'react';
import { Link } from 'react-router-dom';
import {
    ArrowRight,
    Clock,
    Coffee,
    MapPin,
    MessageCircle,
    Phone,
    QrCode,
    Wallet,
    Globe,
} from 'lucide-react';
import { money } from '@/react/spa/api';
import type { BlogCard } from './BlogListPage';
import { usePublicPageView } from '../usePublicPageView';

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
    category_name?: string | null;
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
                            <Coffee className="h-5 w-5" />
                        </span>
                    )}
                    <span className="truncate font-display text-lg font-bold">{branding.brand_name}</span>
                </Link>

                <nav className="hidden items-center gap-6 text-sm font-semibold md:flex">
                    <Link to="/daftar-menu" className="hover:text-primary">Menu</Link>
                    <Link to="/tentang-kami" className="hover:text-primary">Tentang Kami</Link>
                    <Link to="/blog" className="hover:text-primary">Blog</Link>
                    <Link to="/kontak" className="hover:text-primary">Kontak</Link>
                </nav>

                <div className="flex items-center gap-2">
                    <Link
                        to="/masuk"
                        className="hidden rounded-input px-3 py-2 text-sm font-semibold text-ink-2 hover:bg-surface-2 hover:text-ink sm:block"
                    >
                        Masuk
                    </Link>
                    <Link
                        to={REGISTER_CTA}
                        className="rounded-input bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-hover"
                    >
                        Pesan Sekarang
                    </Link>
                </div>
            </div>
        </header>
    );
}

function PublicFooter({ branding }: { branding: PublicBranding }) {
    return (
        <footer className="mt-16 bg-accent text-ink-on-dark">
            <div className="mx-auto grid max-w-7xl gap-8 px-4 py-12 sm:grid-cols-2 lg:grid-cols-3">
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
                    <div className="mt-3 flex flex-col gap-2 text-sm opacity-80">
                        <Link to="/daftar-menu">Daftar Menu</Link>
                        <Link to="/blog">Blog</Link>
                        <Link to="/tentang-kami">Tentang Kami</Link>
                        <Link to="/kontak">Kontak</Link>
                    </div>
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
            <PublicHeader branding={brand} />
            <main className="pb-20 md:pb-0">{children}</main>
            <PublicFooter branding={brand} />

            {/* CTA lengket khusus layar kecil. */}
            <div className="fixed inset-x-0 bottom-0 z-40 border-t border-line bg-surface p-3 md:hidden">
                <Link
                    to={REGISTER_CTA}
                    className="flex h-11 w-full items-center justify-center gap-2 rounded-input bg-primary font-semibold text-white"
                >
                    Pesan Sekarang
                    <ArrowRight className="h-4 w-4" />
                </Link>
            </div>
        </div>
    );
}

function HeroSection({ branding }: { branding: PublicBranding }) {
    return (
        <section className="overflow-hidden bg-surface-2">
            <div className="mx-auto grid max-w-7xl items-center gap-10 px-4 py-16 md:grid-cols-2 md:py-24">
                <div>
                    <p className="font-semibold uppercase tracking-[.2em] text-primary">
                        Selamat datang di {branding.brand_name}
                    </p>
                    <h1 className="mt-4 font-display text-4xl font-bold leading-tight sm:text-6xl">
                        Kopi nikmat,
                        <br />
                        <span className="text-primary">harga bersahabat.</span>
                    </h1>
                    <p className="mt-5 max-w-lg text-lg text-ink-2">
                        {branding.brand_tagline ?? 'Ngopi hemat, rasa nikmat.'}
                    </p>
                    <div className="mt-8 flex flex-wrap gap-3">
                        <Link
                            to={REGISTER_CTA}
                            className="inline-flex items-center gap-2 rounded-input bg-primary px-5 py-3 font-semibold text-white hover:bg-primary-hover"
                        >
                            Pesan Sekarang
                            <ArrowRight className="h-4 w-4" />
                        </Link>
                        <Link
                            to="/daftar-menu"
                            className="rounded-input border border-line-strong bg-surface px-5 py-3 font-semibold hover:bg-surface-2"
                        >
                            Lihat Menu
                        </Link>
                    </div>
                    <p className="mt-4 text-xs text-ink-2">
                        Daftar sekali, langsung pesan dari meja lewat QR.
                    </p>
                </div>

                <div className="flex justify-center">
                    <div className="flex h-64 w-64 items-center justify-center rounded-full bg-primary/10 text-8xl shadow-2 sm:h-96 sm:w-96">
                        ☕
                    </div>
                </div>
            </div>
        </section>
    );
}

const FEATURES = [
    {
        icon: QrCode,
        title: 'Pesan lewat QR meja',
        body: 'Pindai QR di meja, pilih menu, dan pesanan langsung masuk dapur.',
    },
    {
        icon: Wallet,
        title: 'Saldo cashless',
        body: 'Isi saldo sekali di kasir, bayar pesanan tanpa uang tunai.',
    },
    {
        icon: Coffee,
        title: 'Kopi segar tiap hari',
        body: 'Biji pilihan yang diseduh saat dipesan, bukan disimpan lama.',
    },
];

function FeaturesSection() {
    return (
        <section className="mx-auto max-w-7xl px-4 py-16">
            <div className="grid gap-5 sm:grid-cols-3">
                {FEATURES.map((feature) => (
                    <div key={feature.title} className="rounded-card border border-line bg-surface p-6 shadow-1">
                        <feature.icon className="h-6 w-6 text-primary" />
                        <h2 className="mt-4 font-display text-lg font-bold">{feature.title}</h2>
                        <p className="mt-2 text-sm text-ink-2">{feature.body}</p>
                    </div>
                ))}
            </div>
        </section>
    );
}

function MenuCardTile({ item }: { item: MenuCard }) {
    return (
        <div className="overflow-hidden rounded-card border border-line bg-surface shadow-1">
            <div className="flex aspect-square items-center justify-center bg-thumb text-5xl">
                {item.image_url ? (
                    <img
                        src={item.image_url}
                        alt={item.name}
                        loading="lazy"
                        className="h-full w-full object-cover"
                    />
                ) : (
                    <Coffee className="h-10 w-10 text-primary/40" />
                )}
            </div>
            <div className="p-4">
                <p className="text-xs text-ink-2">{item.category_name}</p>
                <h3 className="mt-1 font-display font-bold">{item.name}</h3>
                <p className="mt-2 font-semibold text-primary tabular">{money(item.price)}</p>
            </div>
        </div>
    );
}

function FeaturedMenuSection({ items, loading }: { items: MenuCard[]; loading: boolean }) {
    return (
        <section className="mx-auto max-w-7xl px-4 py-16">
            <div className="flex items-end justify-between gap-4">
                <div>
                    <p className="text-sm font-semibold uppercase tracking-wider text-primary">
                        Favorit pelanggan
                    </p>
                    <h2 className="mt-2 font-display text-3xl font-bold">Menu Unggulan</h2>
                </div>
                <Link
                    to="/daftar-menu"
                    className="hidden items-center gap-1 text-sm font-semibold text-primary sm:flex"
                >
                    Lihat semua
                    <ArrowRight className="h-4 w-4" />
                </Link>
            </div>

            {loading ? (
                <p className="py-12 text-center text-sm text-ink-2">Memuat menu…</p>
            ) : items.length === 0 ? (
                <p className="py-12 text-center text-sm text-ink-2">Menu akan segera hadir.</p>
            ) : (
                <div className="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                    {items.map((item) => (
                        <MenuCardTile key={item.id} item={item} />
                    ))}
                </div>
            )}
        </section>
    );
}

function StorySection({ branding }: { branding: PublicBranding }) {
    return (
        <section className="bg-surface-2">
            <div className="mx-auto grid max-w-7xl gap-8 px-4 py-16 md:grid-cols-2">
                <div>
                    <p className="font-semibold uppercase tracking-wider text-primary">Cerita kami</p>
                    <h2 className="mt-2 font-display text-3xl font-bold">
                        Ruang nyaman untuk setiap cerita.
                    </h2>
                    <p className="mt-4 leading-relaxed text-ink-2">
                        {branding.description
                            ?? 'Kami percaya kopi yang baik tidak harus mahal. Rajahemat Cafe hadir menemani hari Anda dengan minuman berkualitas, makanan lezat, dan suasana yang hangat.'}
                    </p>
                    <Link
                        to="/tentang-kami"
                        className="mt-6 inline-flex items-center gap-2 font-semibold text-primary"
                    >
                        Kenal lebih dekat
                        <ArrowRight className="h-4 w-4" />
                    </Link>
                </div>

                <div className="rounded-card bg-accent p-8 text-ink-on-dark">
                    <h3 className="font-display text-2xl font-bold">Ayo ngopi hari ini</h3>
                    <p className="mt-3 opacity-80">
                        Daftar sebagai member, pesan dari meja, dan nikmati pengalaman terbaik di{' '}
                        {branding.brand_name}.
                    </p>
                    <Link
                        to={REGISTER_CTA}
                        className="mt-6 inline-flex rounded-input bg-primary px-5 py-3 font-semibold text-white hover:bg-primary-hover"
                    >
                        Daftar & Pesan
                    </Link>
                </div>
            </div>
        </section>
    );
}

function BlogPreviewSection({ posts }: { posts: BlogCard[] }) {
    if (posts.length === 0) {
        return null;
    }

    return (
        <section className="mx-auto max-w-7xl px-4 py-16">
            <div className="flex items-end justify-between gap-4">
                <div>
                    <p className="text-sm font-semibold uppercase tracking-wider text-primary">
                        Dari cerita kami
                    </p>
                    <h2 className="mt-2 font-display text-3xl font-bold">Artikel Terbaru</h2>
                </div>
                <Link to="/blog" className="text-sm font-semibold text-primary">
                    Lihat semua
                </Link>
            </div>

            <div className="mt-8 grid gap-5 md:grid-cols-3">
                {posts.map((post) => (
                    <Link
                        key={post.id}
                        to={`/blog/${post.slug}`}
                        className="overflow-hidden rounded-card border border-line bg-surface shadow-1 transition-shadow hover:shadow-2"
                    >
                        <div className="flex aspect-video items-center justify-center bg-thumb text-3xl">
                            {post.cover_image_url ? (
                                <img
                                    src={post.cover_image_url}
                                    alt={post.title}
                                    loading="lazy"
                                    className="h-full w-full object-cover"
                                />
                            ) : (
                                <Coffee className="h-8 w-8 text-primary/40" />
                            )}
                        </div>
                        <div className="p-5">
                            <p className="text-xs font-semibold text-primary">{post.category_label}</p>
                            <h3 className="mt-2 font-display text-lg font-bold line-clamp-2">{post.title}</h3>
                            <p className="mt-2 line-clamp-2 text-sm text-ink-2">{post.excerpt}</p>
                            <p className="mt-3 text-[11px] text-ink-2">{formatDate(post.published_at)}</p>
                        </div>
                    </Link>
                ))}
            </div>
        </section>
    );
}

function LocationSection({ branding }: { branding: PublicBranding }) {
    return (
        <section className="mx-auto max-w-7xl px-4 py-16">
            <div className="grid gap-8 md:grid-cols-2">
                <div>
                    <p className="font-semibold uppercase tracking-wider text-primary">Lokasi</p>
                    <h2 className="mt-2 font-display text-3xl font-bold">Mampir ke kafe kami</h2>

                    <div className="mt-6 flex flex-col gap-4 text-sm">
                        <p className="flex items-start gap-3 text-ink-2">
                            <MapPin className="mt-0.5 h-4 w-4 shrink-0 text-primary" />
                            {branding.address ?? 'Alamat kafe akan segera diperbarui.'}
                        </p>
                        <p className="flex items-start gap-3 text-ink-2">
                            <Clock className="mt-0.5 h-4 w-4 shrink-0 text-primary" />
                            {branding.opening_hours ?? 'Jam buka menyusul.'}
                        </p>
                        {branding.phone && (
                            <p className="flex items-start gap-3 text-ink-2">
                                <Phone className="mt-0.5 h-4 w-4 shrink-0 text-primary" />
                                {branding.phone}
                            </p>
                        )}
                    </div>

                    {branding.whatsapp_link && (
                        <a
                            href={branding.whatsapp_link}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="mt-6 inline-flex items-center gap-2 rounded-input bg-ok px-5 py-3 text-sm font-semibold text-white hover:opacity-90"
                        >
                            <MessageCircle className="h-4 w-4" />
                            Chat WhatsApp
                        </a>
                    )}
                </div>

                <div className="overflow-hidden rounded-card border border-line bg-surface-2">
                    {branding.google_maps_embed_url ? (
                        <iframe
                            src={branding.google_maps_embed_url}
                            title={`Lokasi ${branding.brand_name}`}
                            loading="lazy"
                            referrerPolicy="no-referrer-when-downgrade"
                            className="h-72 w-full border-0"
                        />
                    ) : (
                        <div className="flex h-72 flex-col items-center justify-center gap-2 text-ink-2">
                            <MapPin className="h-8 w-8 text-primary/40" />
                            <span className="text-sm">Peta lokasi belum diatur.</span>
                        </div>
                    )}
                </div>
            </div>
        </section>
    );
}

/** Landing page publik: hero, keunggulan, menu unggulan, cerita, blog, dan lokasi. */
export function LandingPage() {
    const { data, loading } = usePublicData<HomePayload>('/api/publik/beranda');
    const branding = data?.branding ?? FALLBACK_BRANDING;

    return (
        <PublicShell branding={branding}>
            <HeroSection branding={branding} />
            <FeaturesSection />
            <FeaturedMenuSection items={data?.featured_menu_items ?? []} loading={loading} />
            <StorySection branding={branding} />
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
    const { data, loading } = usePublicData<{
        branding: PublicBranding;
        groups: Array<{ category: string; items: MenuCard[] }>;
        total: number;
    }>('/api/publik/menu');

    return (
        <PublicShell branding={data?.branding}>
            <div className="mx-auto max-w-7xl px-4 py-12">
                <p className="font-semibold uppercase tracking-wider text-primary">Pilihan kami</p>
                <h1 className="mt-2 font-display text-4xl font-bold">Daftar Menu</h1>
                <p className="mt-2 text-sm text-ink-2">
                    Semua menu yang tampil di sini tersedia hari ini.
                </p>

                {loading ? (
                    <p className="py-16 text-center text-sm text-ink-2">Memuat menu…</p>
                ) : (data?.groups ?? []).length === 0 ? (
                    <p className="py-16 text-center text-sm text-ink-2">
                        Belum ada menu yang tersedia saat ini.
                    </p>
                ) : (
                    (data?.groups ?? []).map((group) => (
                        <section key={group.category} className="mt-10">
                            <h2 className="font-display text-2xl font-bold">{group.category}</h2>
                            <div className="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                {group.items.map((item) => (
                                    <MenuCardTile key={item.id} item={item} />
                                ))}
                            </div>
                        </section>
                    ))
                )}
            </div>
        </PublicShell>
    );
}

/** Halaman tentang kafe. */
export function AboutPage() {
    return (
        <PublicShell>
            <div className="mx-auto max-w-4xl px-4 py-16">
                <p className="font-semibold uppercase tracking-wider text-primary">Tentang kami</p>
                <h1 className="mt-3 font-display text-4xl font-bold">Kopi yang dekat dengan hati.</h1>
                <p className="mt-6 text-lg leading-relaxed text-ink-2">
                    Rajahemat Cafe adalah ruang berkumpul yang menghadirkan kopi nikmat, makanan
                    lezat, dan harga yang tetap bersahabat. Kami ingin setiap kunjungan terasa
                    seperti pulang.
                </p>

                <div className="mt-12 grid gap-5 sm:grid-cols-3">
                    {[
                        { icon: Coffee, title: 'Berkualitas', body: 'Bahan pilihan, diracik dengan perhatian.' },
                        { icon: Clock, title: 'Nyaman', body: 'Tempat bertemu, bekerja, dan bercerita.' },
                        { icon: MessageCircle, title: 'Bersahabat', body: 'Harga hemat tanpa mengurangi rasa.' },
                    ].map((item) => (
                        <div key={item.title} className="rounded-card bg-surface-2 p-6">
                            <item.icon className="text-primary" />
                            <h2 className="mt-4 font-bold">{item.title}</h2>
                            <p className="mt-2 text-sm text-ink-2">{item.body}</p>
                        </div>
                    ))}
                </div>

                <div className="mt-10 rounded-card border border-line bg-surface p-6">
                    <h2 className="font-display text-xl font-bold">Ingin memesan sekarang?</h2>
                    <p className="mt-2 text-sm text-ink-2">
                        Buat akun pelanggan, lalu pesan langsung dari meja lewat QR.
                    </p>
                    <Link
                        to={REGISTER_CTA}
                        className="mt-4 inline-flex items-center gap-2 rounded-input bg-primary px-5 py-3 text-sm font-semibold text-white hover:bg-primary-hover"
                    >
                        Daftar & Pesan
                        <ArrowRight className="h-4 w-4" />
                    </Link>
                </div>
            </div>
        </PublicShell>
    );
}

/** Halaman kontak dan lokasi. */
export function ContactPage() {
    const { data } = usePublicData<HomePayload>('/api/publik/beranda');
    const branding = data?.branding ?? FALLBACK_BRANDING;

    return (
        <PublicShell branding={branding}>
            <div className="mx-auto max-w-4xl px-4 py-16">
                <p className="font-semibold uppercase tracking-wider text-primary">Kontak</p>
                <h1 className="mt-3 font-display text-4xl font-bold">Mari berkunjung.</h1>

                <div className="mt-10 grid gap-5 sm:grid-cols-2">
                    <div className="rounded-card border border-line bg-surface p-6 shadow-1">
                        <MapPin className="text-primary" />
                        <h2 className="mt-4 font-bold">Alamat</h2>
                        <p className="mt-2 text-sm text-ink-2">
                            {branding.address ?? 'Alamat kafe akan segera diperbarui.'}
                        </p>
                    </div>

                    <div className="rounded-card border border-line bg-surface p-6 shadow-1">
                        <Phone className="text-primary" />
                        <h2 className="mt-4 font-bold">Hubungi kami</h2>
                        <p className="mt-2 text-sm text-ink-2">
                            {branding.phone ?? 'Hubungi kami melalui WhatsApp.'}
                        </p>
                        {branding.whatsapp_link && (
                            <a
                                href={branding.whatsapp_link}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="mt-4 inline-flex items-center gap-2 font-semibold text-primary"
                            >
                                <MessageCircle className="h-4 w-4" />
                                WhatsApp
                            </a>
                        )}
                    </div>
                </div>

                <LocationSection branding={branding} />
            </div>
        </PublicShell>
    );
}
