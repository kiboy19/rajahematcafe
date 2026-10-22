import * as React from 'react';
import { cn } from '@/react/lib/utils';

function TableWrap({ className, ...props }: React.HTMLAttributes<HTMLDivElement>) {
    return <div className={cn('w-full overflow-x-auto', className)} {...props} />;
}

const Table = React.forwardRef<HTMLTableElement, React.TableHTMLAttributes<HTMLTableElement>>(
    ({ className, ...props }, ref) => {
        const innerRef = React.useRef<HTMLTableElement>(null);

        // Salin teks <th> ke data-label setiap <td> agar layout kartu mobile
        // (.table-cards di app.css) punya label tanpa sunting manual per fitur.
        React.useLayoutEffect(() => {
            const table = innerRef.current;
            if (!table) return;
            const heads = Array.from(table.querySelectorAll('thead th')).map((th) => th.textContent?.trim() ?? '');
            table.querySelectorAll('tbody tr').forEach((row) => {
                Array.from(row.children).forEach((cell, index) => {
                    if (cell instanceof HTMLElement && heads[index]) {
                        cell.dataset.label = heads[index];
                    }
                });
            });
        });

        React.useImperativeHandle(ref, () => innerRef.current as HTMLTableElement, []);

        return <table ref={innerRef} className={cn('table-cards w-full caption-bottom text-sm', className)} {...props} />;
    }
);
Table.displayName = 'Table';

const TableHeader = React.forwardRef<HTMLTableSectionElement, React.HTMLAttributes<HTMLTableSectionElement>>(
    ({ className, ...props }, ref) => (
        <thead ref={ref} className={cn('bg-surface-2 text-ink-2 text-[11px] uppercase tracking-wider', className)} {...props} />
    )
);
TableHeader.displayName = 'TableHeader';

const TableBody = React.forwardRef<HTMLTableSectionElement, React.HTMLAttributes<HTMLTableSectionElement>>(
    ({ className, ...props }, ref) => (
        <tbody ref={ref} className={cn('divide-y divide-line', className)} {...props} />
    )
);
TableBody.displayName = 'TableBody';

const TableRow = React.forwardRef<HTMLTableRowElement, React.HTMLAttributes<HTMLTableRowElement>>(
    ({ className, ...props }, ref) => (
        <tr ref={ref} className={cn('transition-colors hover:bg-surface-2/50', className)} {...props} />
    )
);
TableRow.displayName = 'TableRow';

const TableHead = React.forwardRef<HTMLTableCellElement, React.ThHTMLAttributes<HTMLTableCellElement>>(
    ({ className, ...props }, ref) => (
        <th ref={ref} className={cn('px-4 py-3 text-left font-bold', className)} {...props} />
    )
);
TableHead.displayName = 'TableHead';

const TableCell = React.forwardRef<HTMLTableCellElement, React.TdHTMLAttributes<HTMLTableCellElement>>(
    ({ className, ...props }, ref) => (
        <td ref={ref} className={cn('px-4 py-3 align-middle', className)} {...props} />
    )
);
TableCell.displayName = 'TableCell';

export { Table, TableWrap, TableHeader, TableBody, TableRow, TableHead, TableCell };