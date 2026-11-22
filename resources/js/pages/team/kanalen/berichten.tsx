import { Head, Link, useForm } from '@inertiajs/react';
import { ChevronLeft, Clock, Send } from 'lucide-react';
import { useEffect, useRef } from 'react';
import { KanalenTabs } from '@/components/kanalen/onderdelen';
import { Kaart, Leeg, MeerLijst, Pagina, PaginaKop, Rij, TweeKolommen } from '@/components/pagina';
import { Button } from '@/components/ui/button';
import { datumTijd } from '@/lib/datum';
import { cn } from '@/lib/utils';
import kanalen from '@/routes/team/kanalen';

type GesprekItem = { id: number; naam: string | null; laatste: string | null; op: string | null; ongelezen: number; antwoorden_tot: string | null };
type Bericht = { id: number; richting: 'in' | 'uit'; tekst: string | null; bijlagen: { soort: string | null; url: string | null }[] | null; op: string; door: string | null };
type Gesprek = { id: number; naam: string | null; antwoorden_tot: string | null; berichten: Bericht[] };

type Props = { gekoppeld: boolean; mag_berichten: boolean; venster_uren: number; gesprekken: GesprekItem[]; gesprek: Gesprek | null };

function urenTot(iso: string): number {
    return Math.max(0, Math.round((new Date(iso).getTime() - Date.now()) / 3600000));
}

function Venster({ gesprek, uren }: { gesprek: Gesprek; uren: number }) {
    return gesprek.antwoorden_tot ? (
        <p className="flex items-center gap-2 rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-900 dark:bg-emerald-900/30 dark:text-emerald-100">
            <Clock className="size-4 shrink-0" aria-hidden /> Antwoorden kan nog {urenTot(gesprek.antwoorden_tot)} uur (tot {datumTijd(gesprek.antwoorden_tot)}).
        </p>
    ) : (
        <p className="flex items-center gap-2 rounded-lg bg-amber-100 px-3 py-2 text-sm text-amber-900 dark:bg-amber-900/40 dark:text-amber-100">
            <Clock className="size-4 shrink-0" aria-hidden /> Het {uren}-uursvenster van Meta is voorbij. Antwoorden kan weer zodra @{gesprek.naam ?? 'de ander'} een nieuw bericht stuurt.
        </p>
    );
}

function GesprekPaneel({ gesprek, uren }: { gesprek: Gesprek; uren: number }) {
    const form = useForm({ tekst: '' });
    const lijst = useRef<HTMLDivElement>(null);

    useEffect(() => {
        lijst.current?.scrollTo({ top: lijst.current.scrollHeight });
    }, [gesprek.berichten.length]);

    return (
        <Kaart className="space-y-3">
            <div className="flex items-center gap-2">
                <Link href={kanalen.berichten.url()} className="inline-flex size-10 items-center justify-center rounded-md hover:bg-muted lg:hidden" aria-label="Terug naar gesprekken">
                    <ChevronLeft className="size-5" aria-hidden />
                </Link>
                <p className="font-heading text-lg font-bold">@{gesprek.naam ?? 'onbekend'}</p>
            </div>
            <Venster gesprek={gesprek} uren={uren} />
            <div ref={lijst} className="max-h-[55vh] space-y-2 overflow-y-auto rounded-lg bg-muted/30 p-2">
                {gesprek.berichten.map((b) => (
                    <div key={b.id} className={cn('flex', b.richting === 'uit' ? 'justify-end' : 'justify-start')}>
                        <div className={cn('max-w-[85%] rounded-2xl px-3 py-2 text-sm', b.richting === 'uit' ? 'bg-violet text-white' : 'border bg-card')}>
                            {b.tekst && <p className="whitespace-pre-line">{b.tekst}</p>}
                            {b.bijlagen?.map((a, i) =>
                                a.url ? (
                                    <a key={i} href={a.url} target="_blank" rel="noreferrer" className="underline">
                                        Bijlage ({a.soort ?? 'bestand'})
                                    </a>
                                ) : null,
                            )}
                            <p className={cn('mt-0.5 text-xs', b.richting === 'uit' ? 'text-white/75' : 'text-muted-foreground')}>
                                {datumTijd(b.op)}
                                {b.door && `, ${b.door}`}
                            </p>
                        </div>
                    </div>
                ))}
            </div>
            <form
                className="space-y-1"
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post(kanalen.berichten.sturen.url(gesprek.id), { preserveScroll: true, onSuccess: () => form.reset() });
                }}
            >
                <div className="flex gap-2">
                    <textarea
                        className="min-h-10 min-w-0 flex-1 rounded-lg border bg-background px-3 py-2 text-sm disabled:opacity-50"
                        rows={2}
                        maxLength={1000}
                        placeholder={gesprek.antwoorden_tot ? 'Typ je antwoord' : 'Antwoorden kan nu niet'}
                        disabled={!gesprek.antwoorden_tot}
                        value={form.data.tekst}
                        onChange={(e) => form.setData('tekst', e.target.value)}
                        aria-label="Antwoord"
                    />
                    <Button type="submit" className="h-auto min-h-10 bg-violet text-white hover:bg-violet/90" disabled={!gesprek.antwoorden_tot || form.processing || !form.data.tekst.trim()}>
                        <Send className="size-4" aria-hidden /> <span className="max-sm:sr-only">Stuur</span>
                    </Button>
                </div>
                {form.errors.tekst && <p className="text-sm text-rood">{form.errors.tekst}</p>}
            </form>
        </Kaart>
    );
}

/** Eigen kanalen, berichten: de Instagram-inbox van MEBRANDING. */
export default function Berichten({ gekoppeld, mag_berichten, venster_uren, gesprekken, gesprek }: Props) {
    const ongelezen = gesprekken.reduce((s, g) => s + g.ongelezen, 0);

    const lijst = gesprekken.length ? (
        <MeerLijst eerst={10} label="Meer gesprekken">
            {gesprekken.map((g) => (
                <li key={g.id}>
                    <Link href={kanalen.berichten.url({ query: { gesprek: g.id } })} preserveScroll className="block">
                        <Rij
                            className={cn('hover:border-violet', gesprek?.id === g.id && 'border-violet ring-2 ring-violet/30')}
                            titel={`@${g.naam ?? 'onbekend'}`}
                            onder={g.laatste ?? 'Bijlage'}
                            rechts={
                                <span className="flex flex-col items-end gap-1">
                                    <span className="text-xs text-muted-foreground">{datumTijd(g.op)}</span>
                                    {g.ongelezen > 0 ? (
                                        <span className="rounded-full bg-violet px-1.5 text-xs font-semibold text-white tabular-nums">{g.ongelezen}</span>
                                    ) : g.antwoorden_tot ? (
                                        <span className="text-xs text-emerald-700 dark:text-emerald-300">nog {urenTot(g.antwoorden_tot)} u</span>
                                    ) : null}
                                </span>
                            }
                        />
                    </Link>
                </li>
            ))}
        </MeerLijst>
    ) : (
        <Leeg titel="Nog geen berichten">
            {!gekoppeld
                ? 'Koppel Instagram onder Overzicht. Daarna komen nieuwe berichten hier direct binnen.'
                : !mag_berichten
                  ? 'Bij het koppelen is geen toestemming gegeven voor berichten. Koppel opnieuw en vink berichten aan.'
                  : 'Zodra iemand het Instagram-account van MEBRANDING een bericht stuurt, staat het hier.'}
        </Leeg>
    );

    return (
        <>
            <Head title="Berichten" />
            <Pagina>
                <PaginaKop
                    boven="Eigen kanalen"
                    titel="Berichten"
                    omschrijving={`Instagram-berichten aan MEBRANDING. Meta staat een antwoord alleen toe binnen ${venster_uren} uur na het laatste bericht van de ander. TikTok heeft geen API voor berichten.`}
                />
                <KanalenTabs actief="berichten" ongelezen={ongelezen} />
                <TweeKolommen
                    hoofd={gesprek ? <GesprekPaneel gesprek={gesprek} uren={venster_uren} /> : <div className="hidden lg:block"><Leeg titel="Kies een gesprek">Kies hiernaast een gesprek om het te lezen en te beantwoorden.</Leeg></div>}
                    zij={<div className={cn(gesprek && 'max-lg:hidden')}>{lijst}</div>}
                />
            </Pagina>
        </>
    );
}
