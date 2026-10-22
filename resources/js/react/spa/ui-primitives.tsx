import * as React from 'react';
import { Loader2 } from 'lucide-react';
import { cn } from '@/react/lib/utils';
import {
    Button as ButtonBase,
    buttonVariants,
    type ButtonProps as ButtonBaseProps,
} from '@/react/components/ui/button';
import { Input as InputBase, Textarea, Select, inputBase } from '@/react/components/ui/input';
import { Label as LabelBase } from '@/react/components/ui/label';
import { Badge as BadgeBase } from '@/react/components/ui/badge';
import * as DialogBase from '@/react/components/ui/dialog';
import * as AlertDialogBase from '@/react/components/ui/alert-dialog';
import { Switch as SwitchBase } from '@/react/components/ui/switch';
import { ImageUpload as ImageUploadBase } from '@/react/components/ui/image-upload';
import * as TableBase from '@/react/components/ui/table';
import { ErrorBox as ErrorBoxBase, Field as FieldBase } from './ui';

/**
 * Lapisan primitif terpadu untuk SPA (publik, auth, shell).
 * Satu kontrak di atas primitif `components/ui/*` (Radix + cva) tanpa menduplikasi gaya.
 * Referensi: docs/design-system.md §7 dan docs/redesign-implementation-plan.md Batch 1.
 */

/* ---------------------------------- Button --------------------------------- */

export interface ButtonProps extends ButtonBaseProps {
    /** Menampilkan spinner, mengunci klik, dan menandai aria-busy saat request berlangsung. */
    pending?: boolean;
}

export const Button = React.forwardRef<HTMLButtonElement, ButtonProps>(
    ({ pending = false, disabled, children, className, asChild, ...props }, ref) => {
        if (asChild) {
            return (
                <ButtonBase ref={ref} asChild className={className} {...props}>
                    {children}
                </ButtonBase>
            );
        }

        return (
            <ButtonBase
                ref={ref}
                aria-busy={pending || undefined}
                disabled={disabled || pending}
                className={className}
                {...props}
            >
                {pending ? (
                    <>
                        <Loader2 className="h-4 w-4 animate-spin" aria-hidden="true" />
                        {children}
                    </>
                ) : (
                    children
                )}
            </ButtonBase>
        );
    }
);
Button.displayName = 'Button';

/* ------------------------------ Form controls ------------------------------ */

export const Input = React.forwardRef<HTMLInputElement, React.InputHTMLAttributes<HTMLInputElement>>(
    ({ className, ...props }, ref) => (
        <InputBase ref={ref} className={cn('h-10', className)} {...props} />
    )
);
Input.displayName = 'Input';

export { Textarea, Select, buttonVariants };

/** Kelas input baku — dipakai langsung bila kontrol dibuat tanpa komponen Input. */
export const inputClass = cn(inputBase, 'h-10');
export const selectClass = inputClass;

export const Label = LabelBase;

/**
 * Field wrapper: label + control + error/hint.
 * errorId allows <input aria-describedby={errorId}> for screen readers.
 */
export function FormField({
    label,
    htmlFor,
    error,
    errorId,
    hint,
    hintId,
    children,
    className,
}: {
    label: string;
    htmlFor?: string;
    error?: string;
    errorId?: string;
    hint?: string;
    hintId?: string;
    children: React.ReactNode;
    className?: string;
}) {
    return (
        <div className={cn('flex flex-col gap-1', className)}>
            <LabelBase htmlFor={htmlFor}>{label}</LabelBase>
            {children}
            {hint && !error ? <span id={hintId} className="text-[11px] text-ink-2">{hint}</span> : null}
            {error ? (
                <span id={errorId} role="alert" className="text-[11px] font-medium text-bad">
                    {error}
                </span>
            ) : null}
        </div>
    );
}

/** Wrapper label lama; dipertahankan selama migrasi halaman lain berlangsung. */
export const Field = FieldBase;
export const ErrorBox = ErrorBoxBase;

/* --------------------------------- Panel / Card ---------------------------- */

export { Panel as Card } from './ui';

/* --------------------------------- Badge ----------------------------------- */

export const Badge = BadgeBase;

/* --------------------------------- Dialog ---------------------------------- */

export const Dialog = DialogBase.Dialog;
export const DialogTrigger = DialogBase.DialogTrigger;
export const DialogClose = DialogBase.DialogClose;
export const DialogContent = DialogBase.DialogContent;
export const DialogHeader = DialogBase.DialogHeader;
export const DialogFooter = DialogBase.DialogFooter;
export const DialogTitle = DialogBase.DialogTitle;
export const DialogDescription = DialogBase.DialogDescription;

export const AlertDialog = AlertDialogBase.AlertDialog;
export const AlertDialogTrigger = AlertDialogBase.AlertDialogTrigger;
export const AlertDialogContent = AlertDialogBase.AlertDialogContent;
export const AlertDialogHeader = AlertDialogBase.AlertDialogHeader;
export const AlertDialogFooter = AlertDialogBase.AlertDialogFooter;
export const AlertDialogTitle = AlertDialogBase.AlertDialogTitle;
export const AlertDialogDescription = AlertDialogBase.AlertDialogDescription;
export const AlertDialogAction = AlertDialogBase.AlertDialogAction;
export const AlertDialogCancel = AlertDialogBase.AlertDialogCancel;

export const Switch = SwitchBase;
export const ImageUpload = ImageUploadBase;
export const Table = TableBase.Table;
export const TableWrap = TableBase.TableWrap;
export const TableHeader = TableBase.TableHeader;
export const TableBody = TableBase.TableBody;
export const TableRow = TableBase.TableRow;
export const TableHead = TableBase.TableHead;
export const TableCell = TableBase.TableCell;

/* --------------------------------- Feedback -------------------------------- */

/* --------------------------------- Breadcrumb ------------------------------ */

export function Breadcrumb({
    items,
}: {
    items: Array<{ label: string; href?: string }>;
}) {
    if (items.length === 0) return null;

    return (
        <nav aria-label="Breadcrumb" className="mb-4">
            <ol className="flex flex-wrap items-center gap-1.5 text-xs text-ink-2">
                {items.map((item, index) => {
                    const isLast = index === items.length - 1;
                    return (
                        <li key={index} className="inline-flex items-center gap-1.5">
                            {index > 0 && <span className="opacity-40" aria-hidden="true">/</span>}
                            {isLast || !item.href ? (
                                <span aria-current={isLast ? 'page' : undefined} className={isLast ? 'font-semibold text-ink' : ''}>
                                    {item.label}
                                </span>
                            ) : (
                                <a href={item.href} className="hover:text-primary transition-colors">
                                    {item.label}
                                </a>
                            )}
                        </li>
                    );
                })}
            </ol>
        </nav>
    );
}

export function Spinner({ className }: { className?: string }) {
    return <Loader2 className={cn('h-4 w-4 animate-spin', className)} aria-hidden="true" />;
}

export function Toast({ message }: { message: { text: string; tone: 'success' | 'error' } | null }) {
    if (!message) {
        return null;
    }

    // Error memakai role="alert" agar screen reader langsung menyela; info/sukses cukup aria-live polite.
    const isError = message.tone === 'error';

    return (
        <div
            role={isError ? 'alert' : 'status'}
            aria-live={isError ? 'assertive' : 'polite'}
            className={cn(
                'fixed bottom-5 right-5 z-50 max-w-sm rounded-input border border-l-4 bg-surface p-3 text-sm shadow-2',
                message.tone === 'success' ? 'border-ok/20 border-l-ok' : 'border-bad/20 border-l-bad'
            )}
        >
            {message.text}
        </div>
    );
}
