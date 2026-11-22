import { Check } from 'lucide-react';
import type { Stap } from '@/types/samenwerking';

/** De 11 stappen. Op de telefoon horizontaal scrollbaar, met de huidige stap in beeld. */
export function Tijdlijn({ stappen }: { stappen: Stap[] }) {
    return (
        <ol className="flex gap-1 overflow-x-auto pb-1" aria-label="Voortgang">
            {stappen.map((s) => (
                <li
                    key={s.number}
                    aria-current={s.current ? 'step' : undefined}
                    className={`flex min-w-[88px] flex-1 flex-col gap-1 rounded-md px-2 py-1.5 text-[11px] leading-tight ${s.current ? 'bg-violet text-white' : s.done ? 'bg-violet/10 text-violet' : 'bg-muted text-muted-foreground'}`}
                >
                    <span className="flex items-center gap-1 font-bold">
                        {s.done && !s.current ? <Check className="size-3" /> : `${s.number}.`}
                    </span>
                    {s.label}
                </li>
            ))}
        </ol>
    );
}
