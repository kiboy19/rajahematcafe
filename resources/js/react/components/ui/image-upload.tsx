import * as React from 'react';
import { cn } from '@/react/lib/utils';
import { X, Image, Upload } from 'lucide-react';

export interface ImageUploadProps {
    /** Label visible to user. */
    label: string;
    /** Current image URL if already saved. */
    value?: string | null;
    /** Called when user selects a new file or removes the image. */
    onChange: (file: File | null, remove: boolean) => void;
    /** Optional error string. */
    error?: string;
    /** Optional hint. */
    hint?: string;
    /** Accept attribute for input. */
    accept?: string;
    /** Max size in MB for user feedback. */
    maxSizeMB?: number;
    disabled?: boolean;
    id?: string;
}

/**
 * Komponen unggah gambar sederhana dengan pratinjau, hapus, dan validasi sisi klien.
 * Tidak melakukan request server; hanya memikirkan File ke onChange.
 */
export function ImageUpload({
    label,
    value,
    onChange,
    error,
    hint,
    accept = 'image/jpeg,image/png,image/webp',
    maxSizeMB = 2,
    disabled = false,
    id,
}: ImageUploadProps) {
    const [preview, setPreview] = React.useState<string | null>(value ?? null);
    const [fileInputKey, setFileInputKey] = React.useState(0);

    const fileRef = React.useRef<HTMLInputElement>(null);

    React.useEffect(() => {
        setPreview(value ?? null);
    }, [value]);

    function handleFileChange(event: React.ChangeEvent<HTMLInputElement>) {
        const file = event.target.files?.[0] ?? null;

        if (!file) {
            onChange(null, false);
            setPreview(null);
            return;
        }

        // Validasi tipe
        const validTypes = ['image/jpeg', 'image/png', 'image/webp'];
        if (!validTypes.includes(file.type)) {
            onChange(null, false);
            setPreview(null);
            alert('Format gambar tidak didukung. Gunakan JPEG, PNG, atau WebP.');
            return;
        }

        // Validasi ukuran (bytes)
        if (file.size > maxSizeMB * 1024 * 1024) {
            onChange(null, false);
            setPreview(null);
            alert(`Ukuran gambar melebihi ${maxSizeMB} MB.`);
            return;
        }

        onChange(file, false);
        setPreview(URL.createObjectURL(file));
    }

    function handleRemove() {
        if (preview && preview.startsWith('blob:')) {
            URL.revokeObjectURL(preview);
        }
        onChange(null, true);
        setPreview(null);
        setFileInputKey((k) => k + 1);
    }

    function triggerFileInput() {
        fileRef.current?.click();
    }

    return (
        <div className={cn('flex flex-col gap-1', id && {})}>
            <label htmlFor={id} className="text-xs font-semibold text-ink">
                {label}
            </label>
            <div className="relative">
                <div className="rounded-input border border-line-strong bg-surface overflow-hidden aspect-square max-w-32">
                    {preview ? (
                        <>
                            <img
                                src={preview}
                                alt="Preview"
                                className="h-full w-full object-cover"
                            />
                            <button
                                type="button"
                                onClick={handleRemove}
                                disabled={disabled}
                                className="absolute top-1 right-1 rounded-full bg-black/50 p-1 text-white hover:bg-black/70 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                                aria-label="Hapus gambar"
                            >
                                <X className="h-4 w-4" />
                            </button>
                        </>
                    ) : (
                        <button
                            type="button"
                            onClick={triggerFileInput}
                            disabled={disabled}
                            className="flex h-full w-full flex-col items-center justify-center gap-2 text-ink-2 hover:text-ink transition-colors"
                        >
                            <Upload className="h-8 w-8 text-ink-2/50" />
                            <span className="text-xs">Klik atau tarik untuk unggah</span>
                        </button>
                    )}
                </div>
                <input
                    ref={fileRef}
                    key={fileInputKey}
                    id={id}
                    type="file"
                    accept={accept}
                    onChange={handleFileChange}
                    className="hidden"
                    disabled={disabled}
                    aria-label={label}
                />
            </div>
            {hint && !error && <span className="text-[11px] text-ink-2">{hint}</span>}
            {error && <span role="alert" className="text-[11px] font-medium text-bad">{error}</span>}
        </div>
    );
}