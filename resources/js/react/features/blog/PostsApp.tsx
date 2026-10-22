import * as React from 'react';
import { apiRequest, firstError, type ValidationErrors } from '@/react/lib/api';
import { TiptapEditor } from '@/react/components/editor/TiptapEditor';
import { Plus, Pencil, Trash2, Search, Loader2, Globe, Eye, Calendar, Sparkles } from 'lucide-react';
import { Button, Input, Textarea, FormField, Badge, ImageUpload, Table, TableWrap, TableHeader, TableBody, TableRow, TableHead, TableCell, Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter, AlertDialog, AlertDialogContent, AlertDialogHeader, AlertDialogTitle, AlertDialogDescription, AlertDialogFooter, AlertDialogAction, AlertDialogCancel } from '../../spa/ui-primitives';

export interface PostItem {
    id: number;
    title: string;
    slug: string;
    category: string;
    excerpt?: string | null;
    content: string;
    status: 'draft' | 'published';
    published_at?: string | null;
    views_count: number;
    cover_image_url?: string | null;
    meta_title?: string | null;
    meta_description?: string | null;
    author?: { id: number; name: string } | null;
}

interface PaginatedPosts {
    data: PostItem[];
    current_page: number;
    last_page: number;
    total: number;
}

const CATEGORIES = [
    { value: 'umum', label: 'Umum' },
    { value: 'berita', label: 'Berita' },
    { value: 'tips-kopi', label: 'Tips Kopi' },
    { value: 'event', label: 'Event' },
    { value: 'promo', label: 'Promo' },
];

export function PostsApp({ initialData }: { initialData?: PaginatedPosts }) {
    const [posts, setPosts] = React.useState<PostItem[]>(initialData?.data ?? []);
    const [search, setSearch] = React.useState('');
    const [statusFilter, setStatusFilter] = React.useState<string>('all');
    const [loading, setLoading] = React.useState(false);

    const [editorOpen, setEditorOpen] = React.useState(false);
    const [editingPost, setEditingPost] = React.useState<PostItem | null>(null);
    const [deleteTarget, setDeleteTarget] = React.useState<PostItem | null>(null);

    // Form states
    const [formTitle, setFormTitle] = React.useState('');
    const [formCategory, setFormCategory] = React.useState('umum');
    const [formExcerpt, setFormExcerpt] = React.useState('');
    const [formContent, setFormContent] = React.useState('');
    const [formStatus, setFormStatus] = React.useState<'draft' | 'published'>('draft');
    const [formMetaTitle, setFormMetaTitle] = React.useState('');
    const [formMetaDesc, setFormMetaDesc] = React.useState('');
    const [formCoverFile, setFormCoverFile] = React.useState<File | null>(null);
    const [formCoverRemove, setFormCoverRemove] = React.useState(false);
    const [formCoverUrl, setFormCoverUrl] = React.useState<string | null>(null);

    const [errors, setErrors] = React.useState<ValidationErrors>({});
    const [submitting, setSubmitting] = React.useState(false);
    const [flash, setFlash] = React.useState<{ text: string; type: 'success' | 'error' } | null>(null);

    function notify(text: string, type: 'success' | 'error' = 'success') {
        setFlash({ text, type });
        setTimeout(() => setFlash(null), 4000);
    }

    async function fetchPosts() {
        setLoading(true);
        const params = new URLSearchParams();
        if (search) params.set('search', search);
        if (statusFilter !== 'all') params.set('status', statusFilter);

        const res = await apiRequest<PaginatedPosts>('GET', `/api/kelola-blog?${params.toString()}`);
        setLoading(false);

        if (res.ok && res.data) {
            setPosts(res.data.data ?? []);
        }
    }

    React.useEffect(() => {
        fetchPosts();
    }, [search, statusFilter]);

    function openCreate() {
        setEditingPost(null);
        setFormTitle('');
        setFormCategory('umum');
        setFormExcerpt('');
        setFormContent('<p>Tulis artikel menarik di sini…</p>');
        setFormStatus('draft');
        setFormMetaTitle('');
        setFormMetaDesc('');
        setFormCoverFile(null);
        setFormCoverRemove(false);
        setFormCoverUrl(null);
        setErrors({});
        setEditorOpen(true);
    }

    function openEdit(post: PostItem) {
        setEditingPost(post);
        setFormTitle(post.title);
        setFormCategory(post.category);
        setFormExcerpt(post.excerpt ?? '');
        setFormContent(post.content);
        setFormStatus(post.status);
        setFormMetaTitle(post.meta_title ?? '');
        setFormMetaDesc(post.meta_description ?? '');
        setFormCoverFile(null);
        setFormCoverRemove(false);
        setFormCoverUrl(post.cover_image_url ?? null);
        setErrors({});
        setEditorOpen(true);
    }

    function buildFormData(): FormData {
        const data = new FormData();
        data.append('title', formTitle);
        data.append('category', formCategory);
        data.append('content', formContent);
        data.append('status', formStatus);
        if (formExcerpt) data.append('excerpt', formExcerpt);
        if (formMetaTitle) data.append('meta_title', formMetaTitle);
        if (formMetaDesc) data.append('meta_description', formMetaDesc);

        if (editingPost) {
            data.append('_method', 'PATCH');
        }

        if (formCoverFile) {
            data.append('cover_image', formCoverFile);
        } else if (formCoverRemove) {
            data.append('remove_cover_image', '1');
        }

        return data;
    }

    async function handleSave(event: React.FormEvent) {
        event.preventDefault();
        setSubmitting(true);
        setErrors({});

        const isCreate = editingPost === null;
        const url = isCreate ? '/api/kelola-blog' : `/api/kelola-blog/${editingPost.id}`;

        const res = await apiRequest<PostItem>('POST', url, buildFormData());
        setSubmitting(false);

        if (res.ok) {
            setEditorOpen(false);
            notify(isCreate ? 'Artikel berhasil dibuat.' : 'Artikel berhasil diperbarui.');
            fetchPosts();
        } else {
            setErrors(res.errors ?? {});
            notify(res.message, 'error');
        }
    }

    async function togglePublish(post: PostItem) {
        const action = post.status === 'published' ? 'draf' : 'terbitkan';
        const res = await apiRequest('POST', `/api/kelola-blog/${post.id}/${action}`);

        if (res.ok) {
            notify(post.status === 'published' ? 'Artikel diubah jadi draf.' : 'Artikel telah terbit.');
            fetchPosts();
        } else {
            notify(res.message, 'error');
        }
    }

    async function handleDelete() {
        if (!deleteTarget) return;

        const res = await apiRequest('DELETE', `/api/kelola-blog/${deleteTarget.id}`);
        if (res.ok) {
            notify('Artikel berhasil dihapus.');
            setPosts((prev) => prev.filter((p) => p.id !== deleteTarget.id));
        } else {
            notify(res.message, 'error');
        }
        setDeleteTarget(null);
    }

    return (
        <div className="flex flex-col gap-5">
            {flash && (
                <div
                    role="alert"
                    className={`rounded-input border p-3 text-sm font-medium ${
                        flash.type === 'success'
                            ? 'border-ok/30 bg-ok-bg text-ok'
                            : 'border-bad/30 bg-bad-bg text-bad'
                    }`}
                >
                    {flash.text}
                </div>
            )}

            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="flex flex-wrap items-center gap-3 flex-1 min-w-60 max-w-lg">
                    <div className="relative flex-1">
                        <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-ink-2" />
                        <Input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Cari judul atau ringkasan…"
                            className="pl-9"
                        />
                    </div>
                    <select
                        value={statusFilter}
                        onChange={(e) => setStatusFilter(e.target.value)}
                        className="h-11 rounded-input border border-line-strong bg-surface px-3 text-sm"
                    >
                        <option value="all">Semua Status</option>
                        <option value="published">Terbit</option>
                        <option value="draft">Draf</option>
                    </select>
                </div>
                <Button onClick={openCreate}>
                    <Plus className="h-4 w-4" />
                    Tulis Artikel Baru
                </Button>
            </div>

            <section className="overflow-hidden rounded-card border border-line bg-surface shadow-1">
                <TableWrap>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead className="w-16">Cover</TableHead>
                                <TableHead>Judul Artikel</TableHead>
                                <TableHead className="w-28">Kategori</TableHead>
                                <TableHead className="w-24">Status</TableHead>
                                <TableHead className="w-20 text-center">Dilihat</TableHead>
                                <TableHead className="w-32">Tanggal</TableHead>
                                <TableHead className="w-32 text-right">Aksi</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {loading ? (
                                <TableRow>
                                    <TableCell colSpan={7} className="py-8 text-center text-sm text-ink-2">
                                        <Loader2 className="h-5 w-5 animate-spin inline-block mr-2" />
                                        Memuat artikel…
                                    </TableCell>
                                </TableRow>
                            ) : posts.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={7} className="py-8 text-center text-sm text-ink-2">
                                        Belum ada artikel ditemukan. Klik "Tulis Artikel Baru" untuk mulai.
                                    </TableCell>
                                </TableRow>
                            ) : (
                                posts.map((post) => (
                                    <TableRow key={post.id}>
                                        <TableCell>
                                            {post.cover_image_url ? (
                                                <img
                                                    src={post.cover_image_url}
                                                    alt={post.title}
                                                    className="h-10 w-14 rounded object-cover"
                                                />
                                            ) : (
                                                <div className="flex h-10 w-14 items-center justify-center rounded bg-line/40 text-xs text-ink-2">
                                                    —
                                                </div>
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            <div className="font-semibold text-ink line-clamp-1">{post.title}</div>
                                            <div className="text-xs text-ink-2 line-clamp-1">{post.excerpt || post.slug}</div>
                                        </TableCell>
                                        <TableCell>
                                            <span className="text-xs capitalize font-medium text-ink-2">
                                                {CATEGORIES.find((c) => c.value === post.category)?.label ?? post.category}
                                            </span>
                                        </TableCell>
                                        <TableCell>
                                            <button
                                                type="button"
                                                onClick={() => togglePublish(post)}
                                                className="cursor-pointer rounded-full focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                                                title="Klik untuk ubah status terbit/draf"
                                            >
                                                <Badge variant={post.status === 'published' ? 'success' : 'default'}>
                                                    {post.status === 'published' ? 'Terbit' : 'Draf'}
                                                </Badge>
                                            </button>
                                        </TableCell>
                                        <TableCell className="text-center tabular text-xs text-ink-2">
                                            {post.views_count}
                                        </TableCell>
                                        <TableCell className="text-xs text-ink-2">
                                            {post.published_at
                                                ? new Date(post.published_at).toLocaleDateString('id-ID', {
                                                      day: 'numeric',
                                                      month: 'short',
                                                      year: 'numeric',
                                                  })
                                                : '—'}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <div className="inline-flex items-center gap-1">
                                                {post.status === 'published' && (
                                                    <a
                                                        href={`/blog/${post.slug}`}
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        className="inline-flex h-9 w-9 items-center justify-center rounded-input text-ink-2 hover:bg-surface-2 hover:text-ink"
                                                        title="Lihat halaman publik"
                                                    >
                                                        <Globe className="h-4 w-4" />
                                                    </a>
                                                )}
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() => openEdit(post)}
                                                    aria-label={`Ubah ${post.title}`}
                                                >
                                                    <Pencil className="h-4 w-4" />
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() => setDeleteTarget(post)}
                                                    className="text-bad hover:bg-bad-bg hover:text-bad"
                                                    aria-label={`Hapus ${post.title}`}
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                </Button>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))
                            )}
                        </TableBody>
                    </Table>
                </TableWrap>
            </section>

            {/* Editor Modal */}
            <Dialog open={editorOpen} onOpenChange={setEditorOpen}>
                <DialogContent className="max-w-4xl max-h-[92vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>{editingPost ? 'Edit Artikel' : 'Tulis Artikel Baru'}</DialogTitle>
                        <DialogDescription>
                            Isi artikel ditulis menggunakan Tiptap editor dan tersanitasi otomatis sebelum disimpan.
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={handleSave} className="flex flex-col gap-5">
                        <FormField label="Judul Artikel" error={firstError(errors, 'title')}>
                            <Input
                                value={formTitle}
                                onChange={(e) => setFormTitle(e.target.value)}
                                placeholder="Contoh: 5 Rahasia Menyeduh Kopi Nikmat di Rumah"
                                maxLength={200}
                                required
                                autoFocus
                            />
                        </FormField>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <FormField label="Kategori" error={firstError(errors, 'category')}>
                                <select
                                    value={formCategory}
                                    onChange={(e) => setFormCategory(e.target.value)}
                                    className="h-11 rounded-input border border-line-strong bg-surface px-3 text-sm"
                                >
                                    {CATEGORIES.map((c) => (
                                        <option key={c.value} value={c.value}>
                                            {c.label}
                                        </option>
                                    ))}
                                </select>
                            </FormField>

                            <FormField label="Status Publikasi" error={firstError(errors, 'status')}>
                                <select
                                    value={formStatus}
                                    onChange={(e) => setFormStatus(e.target.value as 'draft' | 'published')}
                                    className="h-11 rounded-input border border-line-strong bg-surface px-3 text-sm"
                                >
                                    <option value="draft">Draf (Hanya terlihat oleh staf)</option>
                                    <option value="published">Terbit (Terlihat di website publik)</option>
                                </select>
                            </FormField>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <ImageUpload
                                label="Gambar Sampul (Cover)"
                                value={formCoverUrl}
                                onChange={(file, remove) => {
                                    setFormCoverFile(file);
                                    setFormCoverRemove(remove);
                                    if (file) setFormCoverUrl(URL.createObjectURL(file));
                                }}
                                error={firstError(errors, 'cover_image')}
                                hint="Opsional · Rasio 16:9 disarankan · maks 2 MB"
                            />

                            <FormField label="Ringkasan (Excerpt)" error={firstError(errors, 'excerpt')} hint="Tampil di kartu blog & mesin pencari">
                                <Textarea
                                    value={formExcerpt}
                                    onChange={(e) => setFormExcerpt(e.target.value)}
                                    placeholder="Ringkasan singkat maksimal 300 karakter..."
                                    maxLength={300}
                                    className="min-h-24"
                                />
                            </FormField>
                        </div>

                        {/* Tiptap Editor */}
                        <div className="flex flex-col gap-1">
                            <label className="text-xs font-semibold text-ink">Konten Artikel</label>
                            <TiptapEditor
                                value={formContent}
                                onChange={setFormContent}
                                placeholder="Mulai tulis cerita, berita, atau panduan kopi di sini…"
                            />
                            {firstError(errors, 'content') && (
                                <span role="alert" className="text-[11px] font-medium text-bad">
                                    {firstError(errors, 'content')}
                                </span>
                            )}
                        </div>

                        {/* Metadata SEO Opsional */}
                        <details className="rounded-input border border-line p-3">
                            <summary className="cursor-pointer text-xs font-semibold text-ink flex items-center gap-2">
                                <Sparkles className="h-4 w-4 text-primary" />
                                Pengaturan Meta SEO (Opsional)
                            </summary>
                            <div className="mt-3 flex flex-col gap-3">
                                <FormField label="Meta Title" error={firstError(errors, 'meta_title')} hint="Default: mengikuti judul artikel">
                                    <Input
                                        value={formMetaTitle}
                                        onChange={(e) => setFormMetaTitle(e.target.value)}
                                        placeholder="Judul ramah SEO (maks 200 karakter)"
                                        maxLength={200}
                                    />
                                </FormField>
                                <FormField label="Meta Description" error={firstError(errors, 'meta_description')} hint="Default: mengikuti ringkasan">
                                    <Textarea
                                        value={formMetaDesc}
                                        onChange={(e) => setFormMetaDesc(e.target.value)}
                                        placeholder="Deskripsi untuk snippet Google (maks 320 karakter)"
                                        maxLength={320}
                                    />
                                </FormField>
                            </div>
                        </details>

                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setEditorOpen(false)}>
                                Batal
                            </Button>
                            <Button type="submit" disabled={submitting}>
                                {submitting && <Loader2 className="h-4 w-4 animate-spin" />}
                                {editingPost ? 'Simpan Perubahan' : 'Buat Artikel'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Konfirmasi Hapus */}
            <AlertDialog open={deleteTarget !== null} onOpenChange={(open) => !open && setDeleteTarget(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Hapus Artikel "{deleteTarget?.title}"?</AlertDialogTitle>
                        <AlertDialogDescription>
                            Tindakan ini tidak dapat dibatalkan. Artikel dan gambar sampulnya akan dihapus permanen.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Batal</AlertDialogCancel>
                        <AlertDialogAction variant="destructive" onClick={handleDelete}>
                            Ya, Hapus
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </div>
    );
}
