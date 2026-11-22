import type { StatusInfo } from '@/types/samenwerking';

const kleur: Record<string, string> = {
    afgerond: 'bg-emerald-100 text-emerald-800',
    live: 'bg-emerald-100 text-emerald-800',
    goedgekeurd: 'bg-emerald-50 text-emerald-700',
    ter_controle: 'bg-amber-100 text-amber-800',
    feedback: 'bg-orange-100 text-orange-800',
    geannuleerd: 'bg-zinc-200 text-zinc-700',
    niet_verschenen: 'bg-red-100 text-red-800',
};

export function StatusBadge({ status }: { status: StatusInfo }) {
    return (
        <span
            className={`inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold whitespace-nowrap ${kleur[status.value] ?? 'bg-violet/10 text-violet'}`}
        >
            {status.number && <span aria-hidden>{status.number}.</span>}
            {status.label}
        </span>
    );
}
