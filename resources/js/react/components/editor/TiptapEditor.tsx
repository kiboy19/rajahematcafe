import * as React from 'react';
import { useEditor, EditorContent } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import Link from '@tiptap/extension-link';
import Image from '@tiptap/extension-image';
import { Button } from '@/react/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter } from '@/react/components/ui/dialog';
import { Input } from '@/react/components/ui/input';
import { Switch } from '@/react/components/ui/switch';
import { cn } from '@/react/lib/utils';
import { Link2, Image as ImageIcon, Bold, Italic, Strikethrough, List, ListOrdered, Quote, Heading2, Heading3, Undo, Redo, Loader2 } from 'lucide-react';

export interface TiptapEditorProps {
    /** Konten HTML awal. */
    value: string;
    /** Callback saat konten berubah (HTML). */
    onChange: (html: string) => void;
    /** Placeholder saat editor kosong. */
    placeholder?: string;
    /** Disabled state. */
    disabled?: boolean;
    /** Endpoint unggah gambar inline. */
    uploadUrl?: string;
}

/**
 * Editor Tiptap dengan toolbar: bold/italic/strike, heading 2-3, list,
 * blockquote, tautan, gambar (unggah ke server), undo/redo.
 */
export function TiptapEditor({
    value,
    onChange,
    placeholder = 'Tulis artikel di sini…',
    disabled = false,
    uploadUrl = '/api/kelola-blog/media',
}: TiptapEditorProps) {
    const [linkDialogOpen, setLinkDialogOpen] = React.useState(false);
    const [linkUrl, setLinkUrl] = React.useState('');
    const [linkTargetBlank, setLinkTargetBlank] = React.useState(true);

    const [imageDialogOpen, setImageDialogOpen] = React.useState(false);
    const [imageUrl, setImageUrl] = React.useState('');
    const [imageUploading, setImageUploading] = React.useState(false);
    const [imageError, setImageError] = React.useState<string | null>(null);

    const editor = useEditor({
        immediatelyRender: false,
        extensions: [
            StarterKit.configure({
                heading: { levels: [2, 3] },
            }),
            Link.configure({
                openOnClick: false,
                HTMLAttributes: {
                    rel: 'noopener noreferrer',
                },
            }),
            Image.configure({
                inline: false,
                HTMLAttributes: {
                    class: 'rounded-lg',
                },
            }),
        ],
        content: value,
        editable: !disabled,
        onUpdate: ({ editor }) => {
            onChange(editor.getHTML());
        },
    });

    // Sinkronkan konten bila nilai dari luar berubah (mis. ganti artikel yang diedit).
    React.useEffect(() => {
        if (!editor) {
            return;
        }

        if (editor.getHTML() !== value) {
            editor.commands.setContent(value, { emitUpdate: false });
        }
    }, [editor, value]);

    function openLinkDialog() {
        if (!editor) {
            return;
        }

        setLinkUrl(editor.getAttributes('link').href ?? '');
        setLinkTargetBlank(editor.getAttributes('link').target !== '_self');
        setLinkDialogOpen(true);
    }

    function saveLink() {
        if (!editor) {
            return;
        }

        editor
            .chain()
            .focus()
            .extendMarkRange('link')
            .setLink({ href: linkUrl, target: linkTargetBlank ? '_blank' : null })
            .run();

        setLinkDialogOpen(false);
    }

    function removeLink() {
        editor?.chain().focus().unsetLink().run();
    }

    function openImageDialog() {
        setImageUrl('');
        setImageError(null);
        setImageDialogOpen(true);
    }

    async function handleImageUpload(event: React.ChangeEvent<HTMLInputElement>) {
        const file = event.target.files?.[0];

        if (!file) {
            return;
        }

        if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
            setImageError('Format tidak didukung (JPEG, PNG, WebP).');

            return;
        }

        if (file.size > 4 * 1024 * 1024) {
            setImageError('Ukuran maksimal 4 MB.');

            return;
        }

        setImageUploading(true);
        setImageError(null);

        const formData = new FormData();
        formData.append('image', file);

        try {
            const response = await fetch(uploadUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
                },
                body: formData,
            });
            const json = await response.json();

            if (response.ok && json.data?.url) {
                setImageUrl(json.data.url);
            } else {
                setImageError(json.message ?? 'Gagal mengunggah gambar.');
            }
        } catch {
            setImageError('Kesalahan jaringan saat unggah.');
        } finally {
            setImageUploading(false);
        }
    }

    function insertImage() {
        if (!editor || !imageUrl) {
            return;
        }

        editor.chain().focus().setImage({ src: imageUrl }).run();
        setImageDialogOpen(false);
    }

    if (!editor) {
        return <div className="h-64 animate-pulse rounded-input border border-line bg-surface" />;
    }

    const toolbarButton = 'h-9 w-9 p-0';
    const activeClass = 'bg-surface-2 text-primary';

    return (
        <div className="flex flex-col gap-2 overflow-hidden rounded-card border border-line bg-surface">
            <div className="flex flex-wrap items-center gap-1 border-b border-line bg-surface/50 p-2">
                <div className="flex items-center gap-0.5 border-r border-line pr-2">
                    <Button
                        type="button"
                        variant="ghost"
                        className={cn(toolbarButton, editor.isActive('bold') && activeClass)}
                        onClick={() => editor.chain().focus().toggleBold().run()}
                        disabled={disabled}
                        aria-pressed={editor.isActive('bold')}
                        aria-label="Tebal"
                    >
                        <Bold className="h-4 w-4" />
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        className={cn(toolbarButton, editor.isActive('italic') && activeClass)}
                        onClick={() => editor.chain().focus().toggleItalic().run()}
                        disabled={disabled}
                        aria-pressed={editor.isActive('italic')}
                        aria-label="Miring"
                    >
                        <Italic className="h-4 w-4" />
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        className={cn(toolbarButton, editor.isActive('strike') && activeClass)}
                        onClick={() => editor.chain().focus().toggleStrike().run()}
                        disabled={disabled}
                        aria-pressed={editor.isActive('strike')}
                        aria-label="Coret"
                    >
                        <Strikethrough className="h-4 w-4" />
                    </Button>
                </div>

                <div className="flex items-center gap-0.5 border-r border-line px-2">
                    <Button
                        type="button"
                        variant="ghost"
                        className={cn(toolbarButton, editor.isActive('heading', { level: 2 }) && activeClass)}
                        onClick={() => editor.chain().focus().toggleHeading({ level: 2 }).run()}
                        disabled={disabled}
                        aria-pressed={editor.isActive('heading', { level: 2 })}
                        aria-label="Judul 2"
                    >
                        <Heading2 className="h-4 w-4" />
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        className={cn(toolbarButton, editor.isActive('heading', { level: 3 }) && activeClass)}
                        onClick={() => editor.chain().focus().toggleHeading({ level: 3 }).run()}
                        disabled={disabled}
                        aria-pressed={editor.isActive('heading', { level: 3 })}
                        aria-label="Judul 3"
                    >
                        <Heading3 className="h-4 w-4" />
                    </Button>
                </div>

                <div className="flex items-center gap-0.5 border-r border-line px-2">
                    <Button
                        type="button"
                        variant="ghost"
                        className={cn(toolbarButton, editor.isActive('bulletList') && activeClass)}
                        onClick={() => editor.chain().focus().toggleBulletList().run()}
                        disabled={disabled}
                        aria-pressed={editor.isActive('bulletList')}
                        aria-label="Daftar butir"
                    >
                        <List className="h-4 w-4" />
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        className={cn(toolbarButton, editor.isActive('orderedList') && activeClass)}
                        onClick={() => editor.chain().focus().toggleOrderedList().run()}
                        disabled={disabled}
                        aria-pressed={editor.isActive('orderedList')}
                        aria-label="Daftar bernomor"
                    >
                        <ListOrdered className="h-4 w-4" />
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        className={cn(toolbarButton, editor.isActive('blockquote') && activeClass)}
                        onClick={() => editor.chain().focus().toggleBlockquote().run()}
                        disabled={disabled}
                        aria-pressed={editor.isActive('blockquote')}
                        aria-label="Kutipan"
                    >
                        <Quote className="h-4 w-4" />
                    </Button>
                </div>

                <div className="flex items-center gap-0.5 border-r border-line px-2">
                    <Button
                        type="button"
                        variant="ghost"
                        className={cn(toolbarButton, editor.isActive('link') && activeClass)}
                        onClick={openLinkDialog}
                        disabled={disabled}
                        aria-pressed={editor.isActive('link')}
                        aria-label="Tautan"
                    >
                        <Link2 className="h-4 w-4" />
                    </Button>
                    {editor.isActive('link') && (
                        <Button
                            type="button"
                            variant="ghost"
                            className={cn(toolbarButton, 'text-bad')}
                            onClick={removeLink}
                            disabled={disabled}
                            aria-label="Hapus tautan"
                        >
                            <Link2 className="h-4 w-4" />
                        </Button>
                    )}
                    <Button
                        type="button"
                        variant="ghost"
                        className={toolbarButton}
                        onClick={openImageDialog}
                        disabled={disabled}
                        aria-label="Sisipkan gambar"
                    >
                        <ImageIcon className="h-4 w-4" />
                    </Button>
                </div>

                <div className="flex-1" />

                <div className="flex items-center gap-0.5">
                    <Button
                        type="button"
                        variant="ghost"
                        className={toolbarButton}
                        onClick={() => editor.chain().focus().undo().run()}
                        disabled={disabled || !editor.can().undo()}
                        aria-label="Urungkan"
                    >
                        <Undo className="h-4 w-4" />
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        className={toolbarButton}
                        onClick={() => editor.chain().focus().redo().run()}
                        disabled={disabled || !editor.can().redo()}
                        aria-label="Ulangi"
                    >
                        <Redo className="h-4 w-4" />
                    </Button>
                </div>
            </div>

            <EditorContent
                editor={editor}
                className="prose-editor min-h-72 flex-1 p-4 [&_.ProseMirror]:min-h-64 [&_.ProseMirror]:outline-none [&_.ProseMirror_p.is-editor-empty:first-child::before]:pointer-events-none [&_.ProseMirror_p.is-editor-empty:first-child::before]:float-left [&_.ProseMirror_p.is-editor-empty:first-child::before]:h-0 [&_.ProseMirror_p.is-editor-empty:first-child::before]:text-ink-2 [&_.ProseMirror_p.is-editor-empty:first-child::before]:content-[attr(data-placeholder)]"
                data-placeholder={placeholder}
            />

            <Dialog open={linkDialogOpen} onOpenChange={setLinkDialogOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Atur Tautan</DialogTitle>
                        <DialogDescription>Masukkan URL tujuan tautan.</DialogDescription>
                    </DialogHeader>
                    <form
                        onSubmit={(event) => {
                            event.preventDefault();
                            saveLink();
                        }}
                        className="flex flex-col gap-3"
                    >
                        <Input
                            type="url"
                            value={linkUrl}
                            onChange={(event) => setLinkUrl(event.target.value)}
                            placeholder="https://contoh.com"
                            required
                            autoFocus
                        />
                        <div className="flex items-center justify-between rounded-input border border-line p-3">
                            <div>
                                <span className="block text-xs font-semibold">Buka di tab baru</span>
                                <span className="text-[11px] text-ink-2">Menambahkan rel="noopener noreferrer"</span>
                            </div>
                            <Switch checked={linkTargetBlank} onCheckedChange={setLinkTargetBlank} />
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setLinkDialogOpen(false)}>
                                Batal
                            </Button>
                            <Button type="submit" disabled={!linkUrl.trim()}>
                                Simpan Tautan
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog open={imageDialogOpen} onOpenChange={setImageDialogOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Sisipkan Gambar</DialogTitle>
                        <DialogDescription>Unggah dari perangkat atau tempel URL gambar.</DialogDescription>
                    </DialogHeader>
                    <form
                        onSubmit={(event) => {
                            event.preventDefault();
                            insertImage();
                        }}
                        className="flex flex-col gap-3"
                    >
                        <div className="flex flex-col gap-2">
                            <label className="text-xs font-semibold" htmlFor="post-image-file">
                                Unggah File (JPEG/PNG/WebP, maks 4 MB)
                            </label>
                            <Input
                                id="post-image-file"
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                onChange={handleImageUpload}
                                disabled={disabled || imageUploading}
                            />
                        </div>
                        <div className="flex flex-col gap-2">
                            <label className="text-xs font-semibold" htmlFor="post-image-url">
                                Atau URL Gambar
                            </label>
                            <Input
                                id="post-image-url"
                                type="url"
                                value={imageUrl}
                                onChange={(event) => setImageUrl(event.target.value)}
                                placeholder="https://contoh.com/gambar.jpg"
                            />
                        </div>
                        {imageError && (
                            <span role="alert" className="text-[11px] font-medium text-bad">
                                {imageError}
                            </span>
                        )}
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setImageDialogOpen(false)}>
                                Batal
                            </Button>
                            <Button type="submit" disabled={imageUploading || !imageUrl.trim()}>
                                {imageUploading && <Loader2 className="h-4 w-4 animate-spin" />}
                                Sisipkan Gambar
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </div>
    );
}
