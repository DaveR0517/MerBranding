import { Head, useForm } from '@inertiajs/react';
import { Building2, MapPin } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { euro } from '@/lib/datum';
import * as team from '@/routes/team';
import { opslaan as opslaanRoute } from '@/routes/team/bedrijven';
import { Kop, Label, Leeg, Pagina, Paneel, Veld, invoer } from './_onderdelen/basis';

type Bedrijf = {
    id: number;
    name: string;
    branche: string | null;
    owner: { name: string; email: string } | null;
    locations: string[];
    plan: string | null;
    available_points: number;
};
type Pakket = { id: number; name: string; points: number | null; price_cents: number | null };
type Kennismaking = { id: number; name: string; email: string; phone: string | null; company: string | null; city: string | null };

export default function Bedrijven({ companies, plans, leads }: { companies: Bedrijf[]; plans: Pakket[]; leads: Kennismaking[] }) {
    const form = useForm({
        name: '',
        branche: '',
        owner_name: '',
        owner_email: '',
        location_name: '',
        location_address: '',
        location_city: '',
        experience: '',
        plan_id: plans[0] ? String(plans[0].id) : '',
        lead_id: '' as string,
    });

    const overnemen = (l: Kennismaking) => {
        form.setData({
            ...form.data,
            name: l.company ?? '',
            owner_name: l.name,
            owner_email: l.email,
            location_name: l.company ?? '',
            location_city: l.city ?? '',
            lead_id: String(l.id),
        });
        document.getElementById('bedrijf-toevoegen')?.scrollIntoView({ behavior: 'smooth' });
    };

    const opslaan = (e: React.FormEvent) => {
        e.preventDefault();
        form.post(opslaanRoute.url(), { preserveScroll: true, onSuccess: () => form.reset() });
    };

    const veld = (naam: keyof typeof form.data, label: string, opties: { type?: string; verplicht?: boolean; hint?: string; className?: string } = {}) => (
        <Veld label={`${label}${opties.verplicht ? '' : ' (optioneel)'}`} htmlFor={`b-${naam}`} fout={form.errors[naam]} hint={opties.hint} className={opties.className}>
            <input
                id={`b-${naam}`}
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
            <Head title="Bedrijven" />
            <Pagina breed>
                <Kop titel="Bedrijven" sub={`${companies.length} ${companies.length === 1 ? 'bedrijf' : 'bedrijven'}`} />

                <div className="grid gap-5 lg:grid-cols-[minmax(0,1fr)_minmax(0,420px)]">
                    <div className="space-y-5">
                        {companies.length === 0 ? (
                            <Leeg>Nog geen bedrijven. Voeg het eerste toe met het formulier.</Leeg>
                        ) : (
                            <ul className="grid gap-2 md:grid-cols-2" data-test="bedrijven">
                                {companies.map((b) => (
                                    <li key={b.id} className="rounded-2xl border bg-card p-4">
                                        <div className="flex items-start gap-3">
                                            <span className="grid size-10 shrink-0 place-items-center rounded-xl bg-violet/10 text-violet" aria-hidden>
                                                <Building2 className="size-5" />
                                            </span>
                                            <div className="min-w-0 flex-1">
                                                <p className="font-semibold">{b.name}</p>
                                                {b.branche && <p className="text-sm text-muted-foreground capitalize">{b.branche}</p>}
                                            </div>
                                            <span className="text-right">
                                                <span className="block font-heading text-xl font-extrabold text-violet">{b.available_points}</span>
                                                <span className="block text-xs text-muted-foreground">punten tegoed</span>
                                            </span>
                                        </div>
                                        <dl className="mt-3 space-y-1 text-sm">
                                            <div className="flex gap-2">
                                                <dt className="w-24 shrink-0 text-muted-foreground">Eigenaar</dt>
                                                <dd className="min-w-0">
                                                    {b.owner ? (
                                                        <>
                                                            {b.owner.name}
                                                            <span className="block truncate text-muted-foreground">{b.owner.email}</span>
                                                        </>
                                                    ) : (
                                                        <span className="text-muted-foreground">Geen</span>
                                                    )}
                                                </dd>
                                            </div>
                                            <div className="flex gap-2">
                                                <dt className="w-24 shrink-0 text-muted-foreground">Vestigingen</dt>
                                                <dd className="flex flex-wrap gap-1">
                                                    {b.locations.map((l) => (
                                                        <span key={l} className="inline-flex items-center gap-1">
                                                            <MapPin className="size-3.5 text-muted-foreground" aria-hidden />
                                                            {l}
                                                        </span>
                                                    ))}
                                                </dd>
                                            </div>
                                            <div className="flex gap-2">
                                                <dt className="w-24 shrink-0 text-muted-foreground">Pakket</dt>
                                                <dd>{b.plan ?? <span className="text-muted-foreground">Geen</span>}</dd>
                                            </div>
                                        </dl>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>

                    <div className="space-y-5">
                        {leads.length > 0 && (
                            <Paneel titel="Kennismakingen van de website" sub="Neem een aanvraag over om het formulier te vullen.">
                                <ul className="divide-y" data-test="leads">
                                    {leads.map((l) => (
                                        <li key={l.id} className="flex items-center justify-between gap-3 py-2.5 text-sm">
                                            <span className="min-w-0">
                                                <span className="block font-semibold">{l.company ?? l.name}</span>
                                                <span className="block truncate text-muted-foreground">
                                                    {[l.name, l.city, l.phone].filter(Boolean).join(' · ')}
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

                        <Paneel id="bedrijf-toevoegen" titel="Bedrijf toevoegen" sub="De eigenaar krijgt een uitnodiging per e-mail om zelf een wachtwoord te kiezen.">
                            {gekozenLead && (
                                <p className="mb-3 flex items-center justify-between gap-2 rounded-xl bg-violet/5 p-3 text-sm">
                                    <span>
                                        Overgenomen van de kennismaking van <strong>{gekozenLead.name}</strong>.
                                    </span>
                                    <button type="button" className="text-xs underline" onClick={() => form.setData('lead_id', '')}>
                                        Loskoppelen
                                    </button>
                                </p>
                            )}
                            <form onSubmit={opslaan} className="space-y-3" data-test="bedrijf-formulier">
                                {veld('name', 'Bedrijfsnaam', { verplicht: true })}
                                {veld('branche', 'Branche', { hint: 'Bijvoorbeeld lunchroom, restaurant of hotel.' })}
                                <div className="grid gap-3 sm:grid-cols-2">
                                    {veld('owner_name', 'Naam eigenaar', { verplicht: true })}
                                    {veld('owner_email', 'E-mail eigenaar', { type: 'email', verplicht: true })}
                                </div>
                                <h3 className="pt-2 text-sm font-bold">Eerste vestiging</h3>
                                {veld('location_name', 'Naam vestiging', { verplicht: true })}
                                <div className="grid gap-3 sm:grid-cols-2">
                                    {veld('location_address', 'Adres')}
                                    {veld('location_city', 'Plaats', { verplicht: true })}
                                </div>
                                <Veld label="Wat de creator beleeft (optioneel)" htmlFor="b-experience" fout={form.errors.experience}>
                                    <textarea
                                        id="b-experience"
                                        rows={3}
                                        value={form.data.experience}
                                        onChange={(e) => form.setData('experience', e.target.value)}
                                        className={`${invoer} h-auto py-2`}
                                        placeholder="Bijvoorbeeld: lunch voor twee met koffie."
                                    />
                                </Veld>
                                <Veld
                                    label="Pakket"
                                    htmlFor="b-plan_id"
                                    fout={form.errors.plan_id}
                                    hint="Het tegoed van deze maand wordt direct toegekend. Standaard bezoektijden: ma t/m vr 10:00 tot 16:00."
                                >
                                    <select
                                        id="b-plan_id"
                                        value={form.data.plan_id}
                                        onChange={(e) => form.setData('plan_id', e.target.value)}
                                        className={invoer}
                                        required
                                    >
                                        {plans.map((p) => (
                                            <option key={p.id} value={p.id}>
                                                {p.name}: {p.points ?? 0} punten per maand
                                                {p.price_cents !== null ? `, ${euro(p.price_cents)} per maand` : ''}
                                            </option>
                                        ))}
                                    </select>
                                    <span className="mt-1 inline-block">
                                        <Label kleur="amber">VOORSTEL: punten en prijzen zijn nog niet definitief</Label>
                                    </span>
                                </Veld>
                                <Button type="submit" className="w-full bg-violet text-white hover:bg-violet/90" disabled={form.processing}>
                                    {form.processing ? 'Bezig...' : 'Bedrijf toevoegen en eigenaar uitnodigen'}
                                </Button>
                                {form.recentlySuccessful && <p className="text-sm text-emerald-700">Bedrijf toegevoegd.</p>}
                            </form>
                        </Paneel>
                    </div>
                </div>
            </Pagina>
        </>
    );
}

Bedrijven.layout = { breadcrumbs: [{ title: 'Bedrijven', href: team.bedrijven.url() }] };
