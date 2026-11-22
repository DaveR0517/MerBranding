import type { ComponentProps, ReactNode } from 'react';
import { cn } from '@/lib/utils';

/** Meerregelig tekstveld in de stijl van de Input van shadcn. */
export function Tekstvak({ className, ...props }: ComponentProps<'textarea'>) {
    return (
        <textarea
            className={cn(
                'flex min-h-28 w-full rounded-md border border-input bg-transparent px-3 py-2 text-base shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive md:text-sm',
                className,
            )}
            {...props}
        />
    );
}

/** Een rij gegevens: label boven, waarde eronder. */
export function Gegeven({
    label,
    children,
}: {
    label: string;
    children: ReactNode;
}) {
    return (
        <div>
            <dt className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                {label}
            </dt>
            <dd className="mt-0.5 break-words">{children}</dd>
        </div>
    );
}
