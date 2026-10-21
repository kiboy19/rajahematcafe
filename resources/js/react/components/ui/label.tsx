import * as React from 'react';
import * as LabelPrimitive from '@radix-ui/react-label';
import { cn } from '@/react/lib/utils';

const Label = React.forwardRef<
    React.ComponentRef<typeof LabelPrimitive.Root>,
    React.ComponentPropsWithoutRef<typeof LabelPrimitive.Root>
>(({ className, ...props }, ref) => (
    <LabelPrimitive.Root
        ref={ref}
        className={cn('text-xs font-semibold text-ink', className)}
        {...props}
    />
));
Label.displayName = LabelPrimitive.Root.displayName;

/** Field wrapper: label + kontrol + pesan error inline (shadcn FormField pattern). */
function FormField({
    label,
    htmlFor,
    error,
    hint,
    children,
    className,
}: {
    label: string;
    htmlFor?: string;
    error?: string;
    hint?: string;
    children: React.ReactNode;
    className?: string;
}) {
    return (
        <div className={cn('flex flex-col gap-1', className)}>
            <Label htmlFor={htmlFor}>{label}</Label>
            {children}
            {hint && !error ? <span className="text-[11px] text-ink-2">{hint}</span> : null}
            {error ? (
                <span role="alert" className="text-[11px] font-medium text-bad">
                    {error}
                </span>
            ) : null}
        </div>
    );
}

export { Label, FormField };