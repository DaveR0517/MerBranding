import { router, usePage } from '@inertiajs/react';
import { LogIn } from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { Kaart, Sectie } from '@/components/pagina';
import { cn } from '@/lib/utils';
import { inloggenAls } from '@/routes';
import type { Auth } from '@/types/auth';

/** Hulpjes voor de teamschermen: paneel (op de bouwdoos), velden, labels en datums. Kop, pagina en lege staat komen uit `@/components/pagina`. */

/**
 * Paneel: een Kaart uit de bouwdoos met een Sectie-kop erin (titel, omschrijving, acties).
 * Geen eigen opmaak; `id` dient als ankerpunt voor knoppen die naar het paneel springen.
 * De kop mag op de telefoon onder elkaar vallen (flex-wrap), zodat brede acties de omschrijving niet samenknijpen.
 */
export function Paneel({
    titel,
    sub,
    children,
    className,
    acties,
    id,
}: {
    titel?: ReactNode;
    sub?: ReactNode;
    children: ReactNode;
    className?: string;
    acties?: ReactNode;
    id?: string;
}) {
    const kaart = (
        <Kaart className={className}>
            <Sectie titel={titel} omschrijving={sub} actie={acties} className="[&>div:first-child]:flex-wrap">
                {children}
            </Sectie>
        </Kaart>
    );

    return id ? (
        <div id={id} className="scroll-mt-4">
            {kaart}
        </div>
    ) : (
        kaart
    );
}

/**
 * Gekozen tabblad dat blijft staan na opslaan. Een formulier laadt de pagina opnieuw (de staat begint dan
 * opnieuw); daarom onthouden we het tabblad per scherm in de sessie van de browser.
 */
export function useTab<T extends string>(sleutel: string, standaard: T, geldig: readonly T[]): [T, (t: T) => void] {
    const [tab, zet] = useState<T>(() => {
        try {
            const bewaard = window.sessionStorage.getItem(`tab:${sleutel}`) as T | null;

            return bewaard && geldig.includes(bewaard) ? bewaard : standaard;
        } catch {
            return standaard;
        }
    });

    const kies = (t: T) => {
        zet(t);

        try {
            window.sessionStorage.setItem(`tab:${sleutel}`, t);
        } catch {
            // Geen opslag beschikbaar: het tabblad geldt dan alleen tot de volgende keer laden.
        }
    };

    return [tab, kies];
}

export function Label({
    children,
    kleur = 'violet',
}: {
    children: ReactNode;
    kleur?: 'violet' | 'rood' | 'groen' | 'grijs' | 'amber';
}) {
    const kleuren = {
        violet: 'bg-violet/10 text-violet',
        rood: 'bg-rood/10 text-rood',
        groen: 'bg-emerald-100 text-emerald-800',
        grijs: 'bg-muted text-muted-foreground',
        amber: 'bg-amber-100 text-amber-800',
    };

    return (
        <span
            className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold whitespace-nowrap ${kleuren[kleur]}`}
        >
            {children}
        </span>
    );
}

/** Formulierveld met label en foutmelding. */
export function Veld({
    label,
    fout,
    hint,
    children,
    className,
    htmlFor,
}: {
    label: string;
    fout?: string;
    hint?: ReactNode;
    children: ReactNode;
    className?: string;
    htmlFor: string;
}) {
    return (
        <div className={cn('space-y-1.5', className)}>
            <label htmlFor={htmlFor} className="text-sm font-semibold">
                {label}
            </label>
            {children}
            {hint && <p className="text-xs text-muted-foreground">{hint}</p>}
            {fout && <p className="text-sm text-rood">{fout}</p>}
        </div>
    );
}

export const invoer =
    'h-10 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs outline-none focus-visible:border-violet focus-visible:ring-2 focus-visible:ring-violet/30';

/** Foutmeldingen van de server die bij geen veld horen (bijvoorbeeld uit de keten). */
export function Fouten({ negeer = [] }: { negeer?: string[] }) {
    const { errors } = usePage().props as unknown as { errors: Record<string, string> };
    const lijst = Object.entries(errors ?? {}).filter(([k]) => !negeer.includes(k));

    if (lijst.length === 0) {
        return null;
    }

    return (
        <div role="alert" className="rounded-xl border border-rood/30 bg-rood/5 p-3 text-sm text-rood">
            {lijst.map(([k, v]) => (
                <p key={k}>{v}</p>
            ))}
        </div>
    );
}

/** Leesbare naam van een logboekgebeurtenis. */
export function gebeurtenis(event: string): string {
    const namen: Record<string, string> = {
        status: 'Status gewijzigd',
        aangevraagd: 'Aangevraagd',
        uitgenodigd: 'Creator uitgenodigd',
        uitnodiging_geweigerd: 'Uitnodiging geweigerd',
        uitnodiging_verlopen: 'Uitnodiging verlopen',
        opmerking_ondernemer: 'Opmerking van de ondernemer',
        vergoeding_opeisbaar: 'Vergoeding opeisbaar',
        beoordeeld: 'Beoordeeld door de ondernemer',
    };

    return namen[event] ?? event.replaceAll('_', ' ');
}

/** Datum van morgen als JJJJ-MM-DD in Nederlandse tijd. */
export function morgen(): string {
    const d = new Date(Date.now() + 24 * 3600 * 1000);

    return d.toLocaleDateString('sv-SE', { timeZone: 'Europe/Amsterdam' });
}

export function vandaag(): string {
    return new Date().toLocaleDateString('sv-SE', { timeZone: 'Europe/Amsterdam' });
}

export function initialen(naam: string): string {
    return naam
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((w) => w[0]?.toUpperCase())
        .join('');
}

/** Alleen voor de Super Admin: kijk mee als deze gebruiker. */
export function InloggenAls({ userId, naam }: { userId: number; naam: string }) {
    const { auth } = usePage().props as unknown as { auth: Auth };

    if (!auth.is_super_admin) {
        return null;
    }

    return (
        <button
            type="button"
            onClick={() => router.post(inloggenAls.url(userId))}
            className="inline-flex items-center gap-1 rounded-md border px-2 py-1 text-xs font-semibold text-violet hover:border-violet"
            aria-label={`Inloggen als ${naam}`}
            data-test="inloggen-als"
        >
            <LogIn className="size-3.5" aria-hidden />
            Inloggen als
        </button>
    );
}
