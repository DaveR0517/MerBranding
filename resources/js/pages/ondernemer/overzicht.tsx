import { Head, Link } from '@inertiajs/react';
import { CalendarClock, ChevronRight, Eye, Plus } from 'lucide-react';
import { StatusBadge } from '@/components/samenwerking/status-badge';
import { Button } from '@/components/ui/button';
import { datum, datumTijd } from '@/lib/datum';
import { aanvragen, overzicht, samenwerking } from '@/routes/ondernemer';
import type { SamenwerkingKort } from '@/types/samenwerking';
import { Blok, BlokKop, Kop } from './_onderdelen/opmaak';
import { Tegoed } from './_onderdelen/tegoed';

type Props = {
    company: { name: string; available_points: number; reserved_points: number };
    collaborations: SamenwerkingKort[];
};

const afgesloten = ['afgerond', 'geannuleerd', 'niet_verschenen'];

function uur(iso: string | null): string {
    return iso ? new Date(iso).toLocaleTimeString('nl-NL', { timeZone: 'Europe/Amsterdam', hour: '2-digit', minute: '2-digit' }) : '';
}

/** Wat er onder "Bezoek" staat: het moment, of waar de samenwerking op wacht. */
function bezoekTekst(c: SamenwerkingKort): string {
    if (c.visit_at) {
        return datumTijd(c.visit_at);
    }
    if (c.status.value === 'creator_gevonden') {
        return 'Creator kiest een tijdvak';
    }

    return afgesloten.includes(c.status.value) ? 'Geen bezoek' : 'Nog niet gepland';
}

export default function Overzicht({ company, collaborations }: Props) {
    const lopend = collaborations.filter((c) => !afgesloten.includes(c.status.value));
    const klaar = collaborations.filter((c) => afgesloten.includes(c.status.value));
    const terControle = collaborations.filter((c) => c.status.value === 'ter_controle');
    const bezoeken = collaborations
        .filter((c) => c.status.value === 'bezoek_gepland' && c.visit_at)
        .sort((a, b) => (a.visit_at ?? '').localeCompare(b.visit_at ?? ''));

    return (
        <>
            <Head title="Overzicht" />
            <div className="mx-auto w-full max-w-6xl space-y-6 p-4 md:p-6">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <Kop onder={company.name}>Overzicht</Kop>
                    <Button asChild size="lg" className="w-full sm:w-auto">
                        <Link href={aanvragen()} data-test="aanvragen">
                            <Plus /> Samenwerking aanvragen
                        </Link>
                    </Button>
                </div>

                {(terControle.length > 0 || bezoeken.length > 0) && (
                    <section aria-labelledby="actie" className="space-y-2">
                        <h2 id="actie" className="text-sm font-semibold text-muted-foreground">
                            Actie nodig
                        </h2>
                        <div className="grid gap-2 md:grid-cols-2">
                            {terControle.map((c) => (
                                <Link
                                    key={c.id}
                                    href={samenwerking(c.id)}
                                    className="flex items-center gap-3 rounded-xl bg-violet p-4 text-white transition hover:bg-violet/90"
                                >
                                    <Eye className="size-5 shrink-0" aria-hidden />
                                    <span className="min-w-0 flex-1">
                                        <span className="block font-semibold">Video ter controle</span>
                                        <span className="block truncate text-sm text-white/80">
                                            {c.creator ?? 'Creator'} voor {c.location}
                                        </span>
                                    </span>
                                    <span className="font-semibold">Meekijken</span>
                                </Link>
                            ))}
                            {bezoeken.slice(0, 2).map((c) => (
                                <Link
                                    key={c.id}
                                    href={samenwerking(c.id)}
                                    className="flex items-center gap-3 rounded-xl border border-l-4 border-l-rood bg-card p-4 transition hover:border-violet"
                                >
                                    <CalendarClock className="size-5 shrink-0 text-rood" aria-hidden />
                                    <span className="min-w-0 flex-1">
                                        <span className="block font-semibold">Bezoek {datum(c.visit_at)}</span>
                                        <span className="block text-sm text-muted-foreground">
                                            {c.creator} bij {c.location}, {uur(c.visit_at)}
                                        </span>
                                    </span>
                                    <span className="font-semibold text-violet">Details</span>
                                </Link>
                            ))}
                        </div>
                    </section>
                )}

                <Tegoed beschikbaar={company.available_points} gereserveerd={company.reserved_points} />

                {collaborations.length === 0 ? (
                    <Blok className="py-10 text-center">
                        <h2 className="font-heading text-xl font-bold">Nog geen samenwerkingen</h2>
                        <p className="mx-auto mt-2 max-w-md text-muted-foreground">
                            Je tegoed staat klaar. Vraag je eerste samenwerking aan: jij vertelt wat je wilt laten zien, wij zoeken
                            de creator die bij je zaak past. De creator komt langs, maakt een video en wij controleren hem voordat
                            hij online gaat.
                        </p>
                        <Button asChild size="lg" className="mt-5">
                            <Link href={aanvragen()}>
                                <Plus /> Eerste samenwerking aanvragen
                            </Link>
                        </Button>
                    </Blok>
                ) : (
                    <>
                        <Lijst titel="Lopende samenwerkingen" items={lopend} leeg="Er loopt nu niets. Vraag een nieuwe samenwerking aan." />
                        {klaar.length > 0 && <Lijst titel="Afgerond en gestopt" items={klaar} />}
                    </>
                )}
            </div>
        </>
    );
}

function Lijst({ titel, items, leeg }: { titel: string; items: SamenwerkingKort[]; leeg?: string }) {
    return (
        <Blok className="p-0 md:p-0">
            <div className="px-4 pt-4 md:px-6 md:pt-5">
                <BlokKop rechts={<span className="text-sm text-muted-foreground">{items.length}</span>}>{titel}</BlokKop>
            </div>
            {items.length === 0 ? (
                <p className="px-4 pb-5 text-sm text-muted-foreground md:px-6">{leeg}</p>
            ) : (
                <>
                    <div className="hidden grid-cols-[2fr_1.2fr_1.2fr_1.3fr_auto] gap-4 border-b px-6 pb-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase md:grid">
                        <span>Samenwerking</span>
                        <span>Creator</span>
                        <span>Status</span>
                        <span>Bezoek</span>
                        <span className="sr-only">Openen</span>
                    </div>
                    <ul className="divide-y">
                        {items.map((c) => (
                            <li key={c.id}>
                                <Link
                                    href={samenwerking(c.id)}
                                    className="grid grid-cols-[1fr_auto] items-center gap-x-3 gap-y-1 px-4 py-3 transition hover:bg-violet/5 md:grid-cols-[2fr_1.2fr_1.2fr_1.3fr_auto] md:gap-4 md:px-6"
                                    data-test="samenwerking"
                                >
                                    <span className="min-w-0">
                                        <span className="block truncate font-semibold">{c.title}</span>
                                        <span className="block truncate text-sm text-muted-foreground">
                                            {c.location} · {c.number}
                                        </span>
                                    </span>
                                    <ChevronRight className="size-4 text-muted-foreground md:order-last" aria-hidden />
                                    <span className="col-span-2 text-sm md:col-span-1">
                                        <span className="text-muted-foreground md:hidden">Creator: </span>
                                        {c.creator ?? <span className="text-muted-foreground">Nog geen creator</span>}
                                    </span>
                                    <span className="col-span-2 md:col-span-1">
                                        <StatusBadge status={c.status} />
                                    </span>
                                    <span className="col-span-2 text-sm text-muted-foreground md:col-span-1">
                                        <span className="md:hidden">Bezoek: </span>
                                        {bezoekTekst(c)}
                                    </span>
                                </Link>
                            </li>
                        ))}
                    </ul>
                </>
            )}
        </Blok>
    );
}

Overzicht.layout = { breadcrumbs: [{ title: 'Overzicht', href: overzicht() }] };
