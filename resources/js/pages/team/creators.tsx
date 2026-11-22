import { Head, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import * as team from '@/routes/team';
import { opslaan as opslaanRoute } from '@/routes/team/creators';
import { Kop, Label, Leeg, Pagina, Paneel, Veld, initialen, invoer } from './_onderdelen/basis';

type CreatorRij = {
    id: number;
    name: string;
    email: string;
    level: string;
    city: string | null;
    instagram: string | null;
    tiktok: string | null;
    status: string;
    age: number | null;
    jobs: number;
};
type Niveau = { value: string; name: string };
type Aanmelding = {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    city: string | null;
    data: Record<string, unknown> | null;
};

const statusKleur: Record<string, 'groen' | 'amber' | 'grijs' | 'rood'> = {
    actief: 'groen',
    screening: 'amber',
    gepauzeerd: 'grijs',
    geblokkeerd: 'rood',
};

const handle = (h: string | null) => (h ? `@${h.replace(/^@/, '')}` : null);

/** Gebruikersnaam uit het websiteformulier (veld "instagram-naam"), zonder @. */
function uitData(data: Record<string, unknown> | null, sleutel: string): string {
    const waarde = data?.[sleutel];

    return typeof waarde === 'string' ? waarde.trim() : '';
}

export default function Creators({ creators, levels, leads }: { creators: CreatorRij[]; levels: Niveau[]; leads: Aanmelding[] }) {
    const form = useForm({
        name: '',
        email: '',
        level: levels[0]?.value ?? '',
        birthdate: '',
        city: '',
        instagram: '',
        tiktok: '',
        lead_id: '',
    });

    const overnemen = (l: Aanmelding) => {
        form.setData({
            ...form.data,
            name: l.name,
            email: l.email,
            city: l.city ?? '',
            birthdate: uitData(l.data, 'geboortedatum'),
            instagram: uitData(l.data, 'instagram-naam').replace(/^@/, ''),
            tiktok: uitData(l.data, 'tiktok-naam').replace(/^@/, ''),
            lead_id: String(l.id),
        });
        document.getElementById('creator-toevoegen')?.scrollIntoView({ behavior: 'smooth' });
    };

    const opslaan = (e: React.FormEvent) => {
        e.preventDefault();
        form.post(opslaanRoute.url(), { preserveScroll: true, onSuccess: () => form.reset() });
    };

    const veld = (naam: Exclude<keyof typeof form.data, 'level' | 'lead_id'>, label: string, opties: { type?: string; verplicht?: boolean; hint?: string } = {}) => (
        <Veld label={`${label}${opties.verplicht ? '' : ' (optioneel)'}`} htmlFor={`c-${naam}`} fout={form.errors[naam]} hint={opties.hint}>
            <input
                id={`c-${naam}`}
                name={naam}
                type={opties.type ?? 'text'}
                required={opties.verplicht}
                value={form.data[naam]}
                onChange={(e) => form.setData(naam, e.target.value)}
                className={invoer}
            />
        </Veld>
    );

    const gekozenLead = leads.find((l) => String(l.id) === form.data.lead_id);

    return (
        <>
            <Head title="Creators" />
            <Pagina breed>
                <Kop titel="Creators" sub={`${creators.length} ${creators.length === 1 ? 'creator' : 'creators'}`} />

                <div className="grid gap-5 lg:grid-cols-[minmax(0,1fr)_minmax(0,400px)]">
                    <div>
                        {creators.length === 0 ? (
                            <Leeg>Nog geen creators.</Leeg>
                        ) : (
                            <>
                                <ul className="space-y-2 md:hidden" data-test="creators">
                                    {creators.map((c) => (
                                        <li key={c.id} className="flex items-start gap-3 rounded-2xl border bg-card p-4">
                                            <span className="grid size-10 shrink-0 place-items-center rounded-full bg-brand-gradient font-heading text-sm font-bold text-white" aria-hidden>
                                                {initialen(c.name)}
                                            </span>
                                            <div className="min-w-0 flex-1 text-sm">
                                                <p className="flex flex-wrap items-center gap-1.5">
                                                    <span className="font-semibold">{c.name}</span>
                                                    <Label>{c.level}</Label>
                                                    <Label kleur={statusKleur[c.status] ?? 'grijs'}>{c.status}</Label>
                                                </p>
                                                <p className="mt-0.5 text-muted-foreground">
                                                    {[c.city, c.age !== null ? `${c.age} jaar` : null].filter(Boolean).join(' · ')}
                                                </p>
                                                <p className="text-muted-foreground">
                                                    {[c.instagram && `Instagram ${handle(c.instagram)}`, c.tiktok && `TikTok ${handle(c.tiktok)}`].filter(Boolean).join(' · ') ||
                                                        'Geen kanalen'}
                                                </p>
                                                <p className="mt-0.5">{c.jobs} {c.jobs === 1 ? 'klus' : 'klussen'}</p>
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                                <div className="hidden overflow-hidden rounded-2xl border bg-card md:block">
                                    <table className="w-full text-sm">
                                        <thead className="bg-muted/60 text-left text-xs tracking-wide text-muted-foreground uppercase">
                                            <tr>
                                                <th className="px-4 py-3 font-semibold">Naam</th>
                                                <th className="px-4 py-3 font-semibold">Niveau</th>
                                                <th className="px-4 py-3 font-semibold">Plaats</th>
                                                <th className="px-4 py-3 font-semibold">Leeftijd</th>
                                                <th className="px-4 py-3 font-semibold">Kanalen</th>
                                                <th className="px-4 py-3 text-right font-semibold">Klussen</th>
                                                <th className="px-4 py-3 font-semibold">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y">
                                            {creators.map((c) => (
                                                <tr key={c.id}>
                                                    <td className="px-4 py-3">
                                                        <span className="font-semibold">{c.name}</span>
                                                        <span className="block text-xs text-muted-foreground">{c.email}</span>
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        <Label>{c.level}</Label>
                                                    </td>
                                                    <td className="px-4 py-3">{c.city ?? '-'}</td>
                                                    <td className="px-4 py-3">{c.age ?? '-'}</td>
                                                    <td className="px-4 py-3 text-xs">
                                                        {c.instagram && <span className="block">Instagram {handle(c.instagram)}</span>}
                                                        {c.tiktok && <span className="block">TikTok {handle(c.tiktok)}</span>}
                                                        {!c.instagram && !c.tiktok && <span className="text-muted-foreground">Geen</span>}
                                                    </td>
                                                    <td className="px-4 py-3 text-right font-mono">{c.jobs}</td>
                                                    <td className="px-4 py-3">
                                                        <Label kleur={statusKleur[c.status] ?? 'grijs'}>{c.status}</Label>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </>
                        )}
                    </div>

                    <div className="space-y-5">
                        {leads.length > 0 && (
                            <Paneel titel="Aanmeldingen van de website" sub="Neem een aanmelding over om het formulier te vullen.">
                                <ul className="divide-y" data-test="leads">
                                    {leads.map((l) => (
                                        <li key={l.id} className="flex items-center justify-between gap-3 py-2.5 text-sm">
                                            <span className="min-w-0">
                                                <span className="block font-semibold">{l.name}</span>
                                                <span className="block truncate text-muted-foreground">
                                                    {[l.city, uitData(l.data, 'instagram-naam') && `Instagram ${handle(uitData(l.data, 'instagram-naam'))}`, uitData(l.data, 'tiktok-naam') && `TikTok ${handle(uitData(l.data, 'tiktok-naam'))}`]
                                                        .filter(Boolean)
                                                        .join(' · ')}
                                                </span>
                                            </span>
                                            <Button variant="outline" size="sm" onClick={() => overnemen(l)}>
                                                Overnemen
                                            </Button>
                                        </li>
                                    ))}
                                </ul>
                            </Paneel>
                        )}

                        <Paneel id="creator-toevoegen" titel="Creator toevoegen" sub="De creator krijgt een uitnodiging per e-mail om zelf een wachtwoord te kiezen en staat daarna direct op actief.">
                            {gekozenLead && (
                                <p className="mb-3 flex items-center justify-between gap-2 rounded-xl bg-violet/5 p-3 text-sm">
                                    <span>
                                        Overgenomen van de aanmelding van <strong>{gekozenLead.name}</strong>.
                                    </span>
                                    <button type="button" className="text-xs underline" onClick={() => form.setData('lead_id', '')}>
                                        Loskoppelen
                                    </button>
                                </p>
                            )}
                            <form onSubmit={opslaan} className="space-y-3" data-test="creator-formulier">
                                {veld('name', 'Naam', { verplicht: true })}
                                {veld('email', 'E-mail', { type: 'email', verplicht: true })}
                                <Veld label="Niveau" htmlFor="c-level" fout={form.errors.level}>
                                    <select id="c-level" value={form.data.level} onChange={(e) => form.setData('level', e.target.value)} className={invoer} required>
                                        {levels.map((l) => (
                                            <option key={l.value} value={l.value}>
                                                {l.name}
                                            </option>
                                        ))}
                                    </select>
                                </Veld>
                                <div className="grid gap-3 sm:grid-cols-2">
                                    {veld('birthdate', 'Geboortedatum', { type: 'date', verplicht: true, hint: 'Minimaal 18 jaar.' })}
                                    {veld('city', 'Woonplaats')}
                                </div>
                                <div className="grid gap-3 sm:grid-cols-2">
                                    {veld('instagram', 'Instagram', { hint: 'Gebruikersnaam zonder @' })}
                                    {veld('tiktok', 'TikTok', { hint: 'Gebruikersnaam zonder @' })}
                                </div>
                                <Button type="submit" className="w-full bg-violet text-white hover:bg-violet/90" disabled={form.processing}>
                                    {form.processing ? 'Bezig...' : 'Creator toevoegen en uitnodigen'}
                                </Button>
                                {form.recentlySuccessful && <p className="text-sm text-emerald-700">Creator toegevoegd.</p>}
                            </form>
                        </Paneel>
                    </div>
                </div>
            </Pagina>
        </>
    );
}

Creators.layout = { breadcrumbs: [{ title: 'Creators', href: team.creators.url() }] };
