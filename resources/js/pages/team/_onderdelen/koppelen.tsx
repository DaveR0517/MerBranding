import { router } from '@inertiajs/react';
import { Ban, MapPin } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { datumTijd } from '@/lib/datum';
import * as team from '@/routes/team';
import type { SamenwerkingVol } from '@/types/samenwerking';
import { Label, Leeg, Paneel, initialen } from './basis';

export type Match = {
    id: number;
    name: string;
    level: string;
    level_value: string;
    city: string | null;
    instagram: string | null;
    tiktok: string | null;
    niches: string[];
    points: number;
    reward: string;
    reasons: string[];
    blocked: string | null;
};

const antwoorden: Record<string, { tekst: string; kleur: 'groen' | 'rood' | 'grijs' }> = {
    geaccepteerd: { tekst: 'Geaccepteerd', kleur: 'groen' },
    geweigerd: { tekst: 'Geweigerd', kleur: 'rood' },
    verlopen: { tekst: 'Verlopen', kleur: 'grijs' },
    ingetrokken: { tekst: 'Ingetrokken', kleur: 'grijs' },
};

export function Uitnodigingen({ uitnodigingen }: { uitnodigingen: NonNullable<SamenwerkingVol['invitations']> }) {
    if (uitnodigingen.length === 0) {
        return null;
    }

    return (
        <Paneel titel="Uitnodigingen" sub="Een creator heeft een beperkte tijd om te reageren.">
            <ul className="divide-y" data-test="uitnodigingen">
                {uitnodigingen.map((u) => {
                    const a = u.answer ? antwoorden[u.answer] ?? { tekst: u.answer, kleur: 'grijs' as const } : null;

                    return (
                        <li key={u.id} className="flex flex-wrap items-center justify-between gap-2 py-2.5 text-sm">
                            <span className="font-semibold">{u.creator}</span>
                            <span className="flex flex-wrap items-center gap-2">
                                {a ? <Label kleur={a.kleur}>{a.tekst}</Label> : <Label kleur="amber">Wacht op antwoord</Label>}
                                {u.open && <span className="text-muted-foreground">Verloopt {datumTijd(u.expires_at)}</span>}
                            </span>
                        </li>
                    );
                })}
            </ul>
        </Paneel>
    );
}

export function Matchlijst({ c, matches }: { c: SamenwerkingVol; matches: Match[] }) {
    const [bezig, setBezig] = useState<number | null>(null);
    const open = c.invitations?.find((i) => i.open);

    const nodigUit = (m: Match) =>
        router.post(
            team.uitnodigen.url(c.id),
            { creator_id: m.id },
            { preserveScroll: true, onStart: () => setBezig(m.id), onFinish: () => setBezig(null) },
        );

    const passend = matches.filter((m) => !m.blocked).length;

    return (
        <Paneel
            titel="Creator koppelen"
            sub={
                <>
                    {passend} passende {passend === 1 ? 'creator' : 'creators'}. Gevraagd niveau: {c.creator_level ?? 'geen voorkeur'}.
                    {c.alcohol && ' Er komt alcohol in beeld, dus alleen creators van 25 jaar of ouder.'}
                </>
            }
        >
            {open && (
                <p className="mb-3 rounded-xl bg-amber-50 p-3 text-sm text-amber-900">
                    {open.creator} is uitgenodigd en kan reageren tot {datumTijd(open.expires_at)}. Een nieuwe uitnodiging kan pas
                    na een antwoord.
                </p>
            )}
            {matches.length === 0 ? (
                <Leeg>Geen actieve creators gevonden.</Leeg>
            ) : (
                <ul className="space-y-2" data-test="matchlijst">
                    {matches.map((m) => (
                        <li
                            key={m.id}
                            data-test="match"
                            className={`flex flex-wrap items-start gap-3 rounded-xl border p-3 sm:flex-nowrap sm:items-center ${m.blocked ? 'bg-muted/50 text-muted-foreground' : 'bg-background'}`}
                        >
                            <span
                                aria-hidden
                                className={`grid size-11 shrink-0 place-items-center rounded-full font-heading text-sm font-bold ${m.blocked ? 'bg-muted text-muted-foreground' : 'bg-brand-gradient text-white'}`}
                            >
                                {initialen(m.name)}
                            </span>
                            <div className="min-w-0 flex-1">
                                <p className="flex flex-wrap items-center gap-2">
                                    <span className={`font-semibold ${m.blocked ? '' : 'text-foreground'}`}>{m.name}</span>
                                    <Label kleur={m.blocked ? 'grijs' : 'violet'}>{m.level}</Label>
                                </p>
                                <p className="mt-0.5 flex flex-wrap items-center gap-x-3 gap-y-0.5 text-sm text-muted-foreground">
                                    {m.city && (
                                        <span className="inline-flex items-center gap-1">
                                            <MapPin className="size-3.5" aria-hidden />
                                            {m.city}
                                        </span>
                                    )}
                                    {m.instagram && <span>Instagram @{m.instagram.replace(/^@/, '')}</span>}
                                    {m.tiktok && <span>TikTok @{m.tiktok.replace(/^@/, '')}</span>}
                                </p>
                                <p className="mt-1 text-sm">
                                    Krijgt: <span className="font-semibold">{m.reward}</span>
                                </p>
                                {m.blocked ? (
                                    <p className="mt-1.5 inline-flex items-center gap-1.5 text-sm font-semibold">
                                        <Ban className="size-4" aria-hidden />
                                        {m.blocked}
                                    </p>
                                ) : (
                                    m.reasons.length > 0 && (
                                        <p className="mt-1.5 flex flex-wrap gap-1.5">
                                            {m.reasons.map((r) => (
                                                <Label key={r} kleur="groen">
                                                    {r}
                                                </Label>
                                            ))}
                                        </p>
                                    )
                                )}
                            </div>
                            {!m.blocked && (
                                <Button
                                    className="w-full bg-violet text-white hover:bg-violet/90 sm:w-auto"
                                    disabled={!!open || bezig !== null}
                                    onClick={() => nodigUit(m)}
                                >
                                    {bezig === m.id ? 'Bezig...' : 'Uitnodigen'}
                                </Button>
                            )}
                        </li>
                    ))}
                </ul>
            )}
        </Paneel>
    );
}
