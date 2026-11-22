import { Head, Link, router, useForm } from '@inertiajs/react';
import { CalendarClock, LayoutGrid, List, Phone, Plus, UserPlus } from 'lucide-react';
import { useMemo, useState } from 'react';
import { tekstvak } from '@/components/communicatie/opmaak';
import { Button } from '@/components/ui/button';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { datum, datumTijd } from '@/lib/datum';
import { cn } from '@/lib/utils';
import * as team from '@/routes/team';
import communicatie from '@/routes/team/communicatie';
import { PaginaKop } from '@/components/pagina';
import { Fouten, Label, Paneel, vandaag } from '../_onderdelen/basis';

type Fase = { key: string; name: string };
type Lead = {
    id: number;
    name: string;
    company: string | null;
    email: string;
    phone: string | null;
    city: string | null;
    message: string | null;
    created_at: string;
    stage: string;
    is_customer: boolean;
    owner: { id: number; name: string } | null;
    next_action: string | null;
    next_action_on: string | null;
    overdue: boolean;
    source: string;
    invited_by: string | null;
    history: { id: number; at: string; user: string | null; event: string; text: string }[];
};
type Props = { stages: Fase[]; team: { id: number; name: string }[]; leads: Lead[] };

const faseKleur: Record<string, string> = {
    nieuw: 'border-t-violet',
    gebeld: 'border-t-sky-500',
    afspraak: 'border-t-amber-500',
    offerte: 'border-t-orange-500',
    klant: 'border-t-emerald-500',
    verloren: 'border-t-zinc-400',
};

/** Link naar het bestaande formulier Bedrijf toevoegen, met de kennismaking erbij. */
const klantMakenUrl = (l: Lead) => `${team.bedrijven.url({ query: { lead: l.id } })}#bedrijf-toevoegen`;

/** Sales-pipeline (sales-pipeline.html): kennismakingen per fase. Sleep een kaart naar een andere fase, of open hem. */
export default function Sales({ stages, team: teamleden, leads }: Props) {
    const [weergave, setWeergave] = useState<'bord' | 'lijst'>('bord');
    const [gekozenId, setGekozenId] = useState<number | null>(null);
    const [toevoegen, setToevoegen] = useState(false);
    const [sleep, setSleep] = useState<number | null>(null);
    const [boven, setBoven] = useState<string | null>(null);
    const gekozen = leads.find((l) => l.id === gekozenId) ?? null;
    const vandaagDoen = leads.filter((l) => l.next_action_on && l.next_action_on <= vandaag() && !['klant', 'verloren'].includes(l.stage)).length;

    const verplaats = (lead: Lead, fase: string) => {
        if (fase === lead.stage) {
            return;
        }
        if (fase === 'klant') {
            router.visit(klantMakenUrl(lead));

            return;
        }
        router.post(communicatie.sales.fase.url(lead.id), { stage: fase }, { preserveScroll: true });
    };

    const lijst = useMemo(
        () => [...leads].sort((a, b) => (a.next_action_on ?? '9999').localeCompare(b.next_action_on ?? '9999') || b.created_at.localeCompare(a.created_at)),
        [leads],
    );

    return (
        <>
            <Head title="Sales-pipeline" />
            <div className="mx-auto w-full max-w-[1600px] space-y-5 p-4 md:p-6">
                <PaginaKop
                    titel="Sales-pipeline"
                    omschrijving="Nieuwe ondernemers binnenhalen. Sleep een kaart naar de volgende fase, of open hem."
                    acties={
                        <>
                            <div className="inline-flex rounded-md border p-0.5" role="group" aria-label="Weergave">
                                <button type="button" aria-pressed={weergave === 'bord'} onClick={() => setWeergave('bord')} className="inline-flex items-center gap-1 rounded px-2.5 py-1.5 text-sm font-semibold aria-pressed:bg-aubergine aria-pressed:text-white">
                                    <LayoutGrid className="size-4" aria-hidden /> Bord
                                </button>
                                <button type="button" aria-pressed={weergave === 'lijst'} onClick={() => setWeergave('lijst')} className="inline-flex items-center gap-1 rounded px-2.5 py-1.5 text-sm font-semibold aria-pressed:bg-aubergine aria-pressed:text-white">
                                    <List className="size-4" aria-hidden /> Lijst
                                </button>
                            </div>
                            <Button onClick={() => setToevoegen(!toevoegen)} className="bg-violet hover:bg-violet/90" aria-expanded={toevoegen}>
                                <Plus className="size-4" aria-hidden /> Lead toevoegen
                            </Button>
                        </>
                    }
                />
                <Fouten />

                <div className="flex flex-wrap gap-2 text-sm">
                    <Label kleur={vandaagDoen > 0 ? 'rood' : 'grijs'}>
                        <CalendarClock className="mr-1 size-3.5" aria-hidden /> Vandaag doen: {vandaagDoen}
                    </Label>
                    <Label kleur="grijs">Termijnen (voorstel): contact binnen 1 werkdag, voorstel 14 dagen geldig.</Label>
                </div>

                {toevoegen && <NieuweLead sluit={() => setToevoegen(false)} />}

                {weergave === 'bord' ? (
                    <div className="grid gap-3 md:grid-cols-3 xl:grid-cols-6" data-test="pipeline">
                        {stages.map((f) => {
                            const kaarten = leads.filter((l) => l.stage === f.key);

                            return (
                                <section
                                    key={f.key}
                                    aria-label={f.name}
                                    onDragOver={(e) => {
                                        e.preventDefault();
                                        setBoven(f.key);
                                    }}
                                    onDragLeave={() => setBoven(null)}
                                    onDrop={(e) => {
                                        e.preventDefault();
                                        setBoven(null);
                                        const lead = leads.find((l) => l.id === sleep);
                                        if (lead) {
                                            verplaats(lead, f.key);
                                        }
                                    }}
                                    className={cn('min-w-0 rounded-2xl border border-t-4 bg-muted/40 p-2', faseKleur[f.key], boven === f.key && 'ring-2 ring-violet/40')}
                                    data-test={`fase-${f.key}`}
                                >
                                    <h2 className="flex items-center justify-between px-1.5 py-1 text-sm font-bold">
                                        {f.name} <span className="font-normal text-muted-foreground">{kaarten.length}</span>
                                    </h2>
                                    <ul className="mt-1 space-y-2">
                                        {kaarten.map((l) => (
                                            <li key={l.id}>
                                                <Kaart lead={l} open={() => setGekozenId(l.id)} onDragStart={() => setSleep(l.id)} />
                                            </li>
                                        ))}
                                        {kaarten.length === 0 && <li className="px-1.5 py-3 text-xs text-muted-foreground">Geen leads</li>}
                                    </ul>
                                </section>
                            );
                        })}
                    </div>
                ) : (
                    <ul className="divide-y rounded-2xl border bg-card" data-test="pipeline-lijst">
                        {lijst.map((l) => (
                            <li key={l.id}>
                                <button type="button" onClick={() => setGekozenId(l.id)} className="grid w-full gap-1 p-3 text-left hover:bg-muted/50 md:grid-cols-[2fr_1fr_2fr_1fr] md:items-center md:gap-3">
                                    <span className="min-w-0">
                                        <span className="block truncate font-semibold">{l.company ?? l.name}</span>
                                        <span className="block truncate text-xs text-muted-foreground">{[l.name, l.city].filter(Boolean).join(', ')}</span>
                                    </span>
                                    <span>
                                        <Label kleur={l.stage === 'klant' ? 'groen' : l.stage === 'verloren' ? 'grijs' : 'violet'}>{stages.find((f) => f.key === l.stage)?.name}</Label>
                                    </span>
                                    <span className={cn('text-sm', l.overdue && 'font-semibold text-rood')}>
                                        {l.next_action ? `${l.next_action}${l.next_action_on ? `, ${datum(l.next_action_on)}` : ''}` : <span className="text-muted-foreground">Geen volgende actie</span>}
                                    </span>
                                    <span className="text-sm text-muted-foreground">{l.owner?.name ?? 'Geen eigenaar'}</span>
                                </button>
                            </li>
                        ))}
                    </ul>
                )}
            </div>

            <Sheet open={gekozen !== null} onOpenChange={(open) => !open && setGekozenId(null)}>
                <SheetContent className="w-full overflow-y-auto sm:max-w-md">
                    {gekozen && <Detail key={gekozen.id} lead={gekozen} stages={stages} teamleden={teamleden} verplaats={verplaats} />}
                </SheetContent>
            </Sheet>
        </>
    );
}

function Kaart({ lead, open, onDragStart }: { lead: Lead; open: () => void; onDragStart: () => void }) {
    return (
        <button
            type="button"
            draggable={!lead.is_customer}
            onDragStart={onDragStart}
            onClick={open}
            className="block w-full cursor-grab rounded-xl border bg-card p-3 text-left shadow-xs transition hover:border-violet active:cursor-grabbing"
            data-test="lead-kaart"
        >
            <span className="block font-semibold break-words">{lead.company ?? lead.name}</span>
            <span className="block truncate text-xs text-muted-foreground">{[lead.name, lead.city].filter(Boolean).join(', ')}</span>
            <span className="mt-1.5 block text-xs break-words">Bron: {lead.invited_by ? `uitgenodigd door ${lead.invited_by}` : lead.source}</span>
            {lead.next_action && (
                <span className={cn('mt-1.5 block text-xs font-semibold', lead.overdue ? 'text-rood' : 'text-violet')}>
                    {lead.next_action}
                    {lead.next_action_on && `, ${datum(lead.next_action_on)}`}
                </span>
            )}
        </button>
    );
}

function Detail({ lead, stages, teamleden, verplaats }: { lead: Lead; stages: Fase[]; teamleden: { id: number; name: string }[]; verplaats: (l: Lead, f: string) => void }) {
    const form = useForm({ owner_id: lead.owner ? String(lead.owner.id) : '', next_action: lead.next_action ?? '', next_action_on: lead.next_action_on ?? '' });
    const notitie = useForm({ body: '' });

    return (
        <div className="space-y-5 pb-6">
            <SheetHeader className="pr-10">
                <SheetTitle className="font-heading text-xl font-extrabold">{lead.company ?? lead.name}</SheetTitle>
                <SheetDescription>
                    {[lead.city, `${lead.name}`].filter(Boolean).join(', ')}. Bron: {lead.invited_by ? `uitgenodigd door ${lead.invited_by}` : lead.source}. Binnen {datumTijd(lead.created_at)}.
                </SheetDescription>
            </SheetHeader>
            <div className="space-y-5 px-4">
                <div className="flex flex-wrap gap-2">
                    {lead.phone && (
                        <a href={`tel:${lead.phone.replace(/\s/g, '')}`} className="inline-flex items-center gap-1.5 rounded-md border px-3 py-2 text-sm font-semibold hover:border-violet">
                            <Phone className="size-4" aria-hidden /> Bellen
                        </a>
                    )}
                    <a href={`mailto:${lead.email}`} className="inline-flex items-center gap-1.5 rounded-md border px-3 py-2 text-sm font-semibold hover:border-violet">
                        {lead.email}
                    </a>
                    {!lead.is_customer && (
                        <Link href={klantMakenUrl(lead)} className="inline-flex items-center gap-1.5 rounded-md bg-violet px-3 py-2 text-sm font-semibold text-white" data-test="klant-maken">
                            <UserPlus className="size-4" aria-hidden /> Klant maken
                        </Link>
                    )}
                </div>
                {lead.message && <p className="rounded-xl bg-muted p-3 text-sm whitespace-pre-line">{lead.message}</p>}

                <fieldset>
                    <legend className="mb-1.5 text-sm font-semibold">Fase</legend>
                    <div className="flex flex-wrap gap-1.5">
                        {stages.map((f) => (
                            <button
                                key={f.key}
                                type="button"
                                aria-pressed={lead.stage === f.key}
                                disabled={lead.is_customer}
                                onClick={() => verplaats(lead, f.key)}
                                className="rounded-full border px-3 py-1.5 text-sm font-semibold aria-pressed:border-aubergine aria-pressed:bg-aubergine aria-pressed:text-white disabled:opacity-60"
                            >
                                {f.name}
                            </button>
                        ))}
                    </div>
                    {lead.is_customer && <p className="mt-1 text-xs text-muted-foreground">Deze kennismaking is klant geworden via Bedrijf toevoegen.</p>}
                </fieldset>

                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.post(communicatie.sales.bijwerken.url(lead.id), { preserveScroll: true });
                    }}
                    className="space-y-3 rounded-xl border p-3"
                >
                    <label className="block space-y-1 text-sm font-semibold">
                        Eigenaar
                        <select value={form.data.owner_id} onChange={(e) => form.setData('owner_id', e.target.value)} className={tekstvak}>
                            <option value="">Geen</option>
                            {teamleden.map((u) => (
                                <option key={u.id} value={u.id}>
                                    {u.name}
                                </option>
                            ))}
                        </select>
                    </label>
                    <div className="grid grid-cols-[1fr_150px] gap-2">
                        <label className="block space-y-1 text-sm font-semibold">
                            Volgende actie
                            <input value={form.data.next_action} onChange={(e) => form.setData('next_action', e.target.value)} placeholder="Bijvoorbeeld bellen" className={tekstvak} />
                        </label>
                        <label className="block space-y-1 text-sm font-semibold">
                            Datum
                            <input type="date" value={form.data.next_action_on} onChange={(e) => form.setData('next_action_on', e.target.value)} className={tekstvak} />
                        </label>
                    </div>
                    <Button type="submit" variant="outline" disabled={form.processing || !form.isDirty}>
                        Opslaan
                    </Button>
                </form>

                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        notitie.post(communicatie.sales.notitie.url(lead.id), { preserveScroll: true, onSuccess: () => notitie.reset() });
                    }}
                    className="space-y-2"
                >
                    <label htmlFor="notitie" className="text-sm font-semibold">
                        Notitie
                    </label>
                    <textarea id="notitie" rows={3} required value={notitie.data.body} onChange={(e) => notitie.setData('body', e.target.value)} className={tekstvak} placeholder="Wat is er besproken?" />
                    {notitie.errors.body && <p className="text-sm text-rood">{notitie.errors.body}</p>}
                    <Button type="submit" disabled={notitie.processing} className="bg-violet hover:bg-violet/90">
                        Notitie opslaan
                    </Button>
                </form>

                <section>
                    <h3 className="mb-2 text-sm font-bold">Historie</h3>
                    {lead.history.length === 0 ? (
                        <p className="text-sm text-muted-foreground">Nog niets vastgelegd.</p>
                    ) : (
                        <ol className="space-y-2 border-l-2 border-violet/30 pl-3" data-test="historie">
                            {lead.history.map((h) => (
                                <li key={h.id} className="text-sm">
                                    <span className="block text-xs text-muted-foreground">
                                        {datumTijd(h.at)}
                                        {h.user ? `, ${h.user}` : ''}
                                    </span>
                                    <span className="whitespace-pre-line">{h.text}</span>
                                </li>
                            ))}
                        </ol>
                    )}
                </section>
            </div>
        </div>
    );
}

function NieuweLead({ sluit }: { sluit: () => void }) {
    const form = useForm({ name: '', company: '', email: '', phone: '', city: '', message: '' });
    const veld = (naam: keyof typeof form.data, label: string, type = 'text', verplicht = false) => (
        <label className="block space-y-1 text-sm font-semibold">
            {label}
            {!verplicht && <span className="font-normal text-muted-foreground"> (optioneel)</span>}
            <input type={type} required={verplicht} value={form.data[naam]} onChange={(e) => form.setData(naam, e.target.value)} className={tekstvak} />
            {form.errors[naam] && <span className="block text-sm font-normal text-rood">{form.errors[naam]}</span>}
        </label>
    );

    return (
        <Paneel titel="Lead toevoegen" sub="Bijvoorbeeld na een telefoontje of een ontmoeting. De lead komt in de fase Nieuw, met jou als eigenaar.">
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post(communicatie.sales.toevoegen.url(), { preserveScroll: true, onSuccess: sluit });
                }}
                className="grid gap-3 md:grid-cols-3"
                data-test="lead-formulier"
            >
                {veld('company', 'Bedrijfsnaam', 'text', true)}
                {veld('name', 'Contactpersoon', 'text', true)}
                {veld('email', 'E-mail', 'email', true)}
                {veld('phone', 'Telefoon', 'tel')}
                {veld('city', 'Plaats')}
                {veld('message', 'Notitie bij binnenkomst')}
                <div className="flex gap-2 md:col-span-3">
                    <Button type="submit" disabled={form.processing} className="bg-violet hover:bg-violet/90">
                        Toevoegen
                    </Button>
                    <Button type="button" variant="outline" onClick={sluit}>
                        Annuleren
                    </Button>
                </div>
            </form>
        </Paneel>
    );
}
