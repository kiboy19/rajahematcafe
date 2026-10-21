import * as React from 'react';
import { cva, type VariantProps } from 'class-variance-authority';
import { cn } from '@/react/lib/utils';

const badgeVariants = cva(
    'inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-bold transition-colors',
    {
        variants: {
            variant: {
                default: 'bg-primary/10 text-primary border border-primary/20',
                success: 'bg-ok-bg text-ok border border-ok/20',
                warning: 'bg-warn-bg text-warn border border-warn/20',
                danger: 'bg-bad-bg text-bad border border-bad/20',
                outline: 'text-ink border border-line',
            },
        },
        defaultVariants: {
            variant: 'default',
        },
    }
);

export interface BadgeProps
    extends React.HTMLAttributes<HTMLDivElement>,
        VariantProps<typeof badgeVariants> {}

function Badge({ className, variant, ...props }: BadgeProps) {
    return <div className={cn(badgeVariants({ variant }), className)} {...props} />;
}

export { Badge, badgeVariants };