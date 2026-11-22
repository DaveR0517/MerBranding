import { Head, Link, useForm } from '@inertiajs/react';
import {
    AlertTriangle,
    CheckCircle2,
    ExternalLink,
    PlugZap,
    XCircle,
} from 'lucide-react';
import { useState } from 'react';
import {
    Etiket,
    koppelingStatus,
    waarschuwingen,
} from '@/components/content/labels';
import type { KoppelingStatus } from '@/components/content/types';
import { Button } from '@/components/ui/button';
import { datum, datumTijd } from '@/lib/datum';
import { samenwerking } from '@/routes/team';
import content from '@/routes/team/content';
import { Leeg, Pagina, PaginaKop } from '@/components/pagina';
import { Paneel } from '../_onderdelen/basis';

type Monitor = {
    id: number;
    platform: string;
    handle: string | null;
    eigenaar: string;
    soort: 'creator' | 'ondernemer';
    status: KoppelingStatus;
    verloopt_op: string | null;
    laatste_sync: string | null;
    fout: string | null;
    toestemming_versie: string | null;
    toestemming_op: string | null;
    waarschuwing: 'verloopt' | 'verlopen' | 'fout' | null;
};

type Publicatie = {
    id: number;
    platform: string;
    url: string;
    posted_at: string;
    last_found_at: string | null;
    collaboration: {
        id: number;
        title: string;
        company: string;
        creator: string | null;
        measure_until: string | null;
    };
    checks: {
        found: boolean;
        by: string | null;
        at: string;
        note: string | null;
    }[];
};

type Props = {
    accounts: Monitor[];
    platforms: {
        platform: string;
        naam: string;
        ingesteld: boolean;
        token_dagen: number;
    }[];
    warn_days: number;
    publications: Publicatie[];
};

const filters = [
    { key: 'alle', naam: 'Alle accounts' },
    { key: 'waarschuwing', naam: 'Met waarschuwing' },
    { key: 'creator', naam: 'Creators' },
    { key: 'ondernemer', naam: 'Ondernemers' },
] as const;

/** AdminApi: monitor van alle koppelingen en de handmatige controle "post verwijderd" (W8). */
export default function Koppelingen({
    accounts,
    platforms,
    warn_days,
    publications,
}: Props) {
    const [filter, setFilter] =
        useState<(typeof filters)[number]['key']>('alle');
    const metWaarschuwing = accounts.filter((a) => a.waarschuwing);
    const zichtbaar = accounts.filter(
        (a) =>
            filter === 'alle' ||
            (filter === 'waarschuwing' ? a.waarschuwing : a.soort === filter),
    );
    const naamVan = (p: string) =>
        platforms.find((x) => x.platform === p)?.naam ?? p;

    return (
        <>
            <Head title="API-status" />
            <Pagina>
                <PaginaKop
                    titel="API-status"
                    omschrijving="Koppelingen met Instagram en TikTok van creators en ondernemers."
                />

                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    {platforms.map((p) => (
                        <div
                            key={p.platform}
                            className="rounded-2xl border bg-card p-4"
                        >
                            <p className="flex items-center gap-2 font-semibold">
                                <PlugZap
                                    className="size-4 text-violet"
                                    aria-hidden
                                />
                                {p.naam}
                            </p>
                            <p className="mt-1 text-sm text-muted-foreground">
                                {p.ingesteld
                                    ? 'Sleutels ingesteld'
                                    : 'Niet ingesteld: handmatige modus'}
                            </p>
                            <p className="text-xs text-muted-foreground">
                                Token geldig {p.token_dagen} dagen
                            </p>
                        </div>
                    ))}
                    <div className="rounded-2xl border bg-card p-4">
                        <p className="font-heading text-2xl font-extrabold">
                            {accounts.length}
                        </p>
                        <p className="text-sm text-muted-foreground">
                            verbonden accounts
                        </p>
                    </div>
                    <div
                        className={`rounded-2xl border p-4 ${metWaarschuwing.length ? 'border-rood/40 bg-rood/5' : 'bg-card'}`}
                    >
                        <p className="font-heading text-2xl font-extrabold">
                            {metWaarschuwing.length}
                        </p>
                        <p className="text-sm text-muted-foreground">
                            met waarschuwing (fout, verlopen of binnen{' '}
                            {warn_days} dagen)
                        </p>
                    </div>
                </div>

                <Paneel
                    titel="Verbonden accounts"
                    sub={`Bij ${warn_days} dagen voor verlopen moet de eigenaar opnieuw inloggen.`}
                >
                    <div
                        className="-mx-1 mb-3 flex gap-2 overflow-x-auto px-1 pb-1"
                        role="group"
                        aria-label="Filter accounts"
                    >
                        {filters.map((f) => (
                            <button
                                key={f.key}
                                type="button"
                                aria-pressed={filter === f.key}
                                onClick={() => setFilter(f.key)}
                                className={`shrink-0 rounded-full border px-3 py-1.5 text-sm font-semibold ${filter === f.key ? 'border-aubergine bg-aubergine text-white' : 'bg-card hover:border-violet'}`}
                            >
                                {f.naam}
                            </button>
                        ))}
                    </div>
                    {zichtbaar.length === 0 ? (
                        <Leeg titel="Geen accounts in dit filter." />
                    ) : (
                        <ul className="divide-y" data-test="accounts">
                            {zichtbaar.map((a) => (
                                <li
                                    key={a.id}
                                    className="flex flex-wrap items-start justify-between gap-2 py-3"
                                >
                                    <div className="min-w-0">
                                        <p className="font-semibold break-words">
                                            {a.eigenaar}{' '}
                                            <span className="font-normal text-muted-foreground">
                                                {a.handle}
                                            </span>
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            {naamVan(a.platform)},{' '}
                                            {a.soort === 'creator'
                                                ? 'creator'
                                                : 'ondernemer'}
                                            {a.laatste_sync
                                                ? `, laatste synchronisatie ${datumTijd(a.laatste_sync)}`
                                                : ', nog niet gesynchroniseerd'}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {a.toestemming_op
                                                ? `Toestemming ${datum(a.toestemming_op)}, versie ${a.toestemming_versie}`
                                                : 'Geen geldige toestemming'}
                                            {a.verloopt_op &&
                                                `. Verloopt ${datum(a.verloopt_op)}`}
                                        </p>
                                        {a.fout && (
                                            <p className="text-xs text-rood">
                                                {a.fout}
                                            </p>
                                        )}
                                    </div>
                                    <div className="flex flex-wrap gap-1">
                                        <Etiket
                                            kleur={
                                                koppelingStatus[a.status]?.kleur
                                            }
                                        >
                                            {koppelingStatus[a.status]?.naam ??
                                                a.status}
                                        </Etiket>
                                        {a.waarschuwing === 'verloopt' && (
                                            <Etiket
                                                kleur={
                                                    waarschuwingen.verloopt
                                                        .kleur
                                                }
                                            >
                                                {waarschuwingen.verloopt.naam}
                                            </Etiket>
                                        )}
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </Paneel>

                <Paneel
                    titel="Controle post verwijderd"
                    sub="Posts in de meetperiode (status 10). Leg vast of de post nog te vinden is. Een gearchiveerde Instagram-post telt als verwijderd. Geen strafpunten: de regeling is nog te bespreken."
                >
                    {publications.length === 0 ? (
                        <Leeg titel="Geen posts in de meetperiode." />
                    ) : (
                        <ul className="space-y-3">
                            {publications.map((p) => (
                                <PostControle
                                    key={p.id}
                                    publicatie={p}
                                    platform={naamVan(p.platform)}
                                />
                            ))}
                        </ul>
                    )}
                </Paneel>
            </Pagina>
        </>
    );
}

function PostControle({
    publicatie: p,
    platform,
}: {
    publicatie: Publicatie;
    platform: string;
}) {
    const form = useForm({ found: true, note: '', melden: false });
    const [nietGevonden, setNietGevonden] = useState(false);
    const vastleggen = (found: boolean) => {
        form.transform((d) => ({ ...d, found, melden: !found && d.melden }));
        form.post(content.publicaties.controle.url(p.id), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setNietGevonden(false);
            },
        });
    };

    return (
        <li className="rounded-xl border p-3" data-test="publicatie">
            <div className="flex flex-wrap items-start justify-between gap-2">
                <div className="min-w-0">
                    <Link
                        href={samenwerking.url(p.collaboration.id)}
                        className="font-semibold hover:text-violet"
                    >
                        {p.collaboration.title}, {p.collaboration.company}
                    </Link>
                    <p className="text-sm text-muted-foreground">
                        {p.collaboration.creator}, {platform}
                        {p.collaboration.measure_until &&
                            `, meetperiode tot ${datum(p.collaboration.measure_until)}`}
                    </p>
                    <a
                        href={p.url}
                        target="_blank"
                        rel="noreferrer noopener"
                        className="inline-flex items-center gap-1 text-sm break-all text-violet underline-offset-2 hover:underline"
                    >
                        Post openen{' '}
                        <ExternalLink className="size-3.5" aria-hidden />
                    </a>
                </div>
                <p className="text-sm">
                    {p.last_found_at
                        ? `Laatst gevonden ${datumTijd(p.last_found_at)}`
                        : 'Nog niet gecontroleerd'}
                </p>
            </div>

            {p.checks.length > 0 && (
                <ul className="mt-2 space-y-0.5 text-xs text-muted-foreground">
                    {p.checks.map((c, i) => (
                        <li key={i} className="flex items-center gap-1.5">
                            {c.found ? (
                                <CheckCircle2
                                    className="size-3.5 text-emerald-600"
                                    aria-hidden
                                />
                            ) : (
                                <XCircle
                                    className="size-3.5 text-rood"
                                    aria-hidden
                                />
                            )}
                            {c.found ? 'Gevonden' : 'Niet gevonden'} op{' '}
                            {datumTijd(c.at)} door {c.by ?? 'het systeem'}
                            {c.note && `: ${c.note}`}
                        </li>
                    ))}
                </ul>
            )}

            {nietGevonden ? (
                <div className="mt-3 space-y-2 rounded-lg bg-rood/5 p-3">
                    <label
                        htmlFor={`notitie-${p.id}`}
                        className="text-sm font-semibold"
                    >
                        Toelichting (optioneel)
                    </label>
                    <input
                        id={`notitie-${p.id}`}
                        value={form.data.note}
                        onChange={(e) => form.setData('note', e.target.value)}
                        maxLength={255}
                        placeholder="Bijvoorbeeld: gearchiveerd"
                        className="h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                    />
                    <label className="flex items-start gap-2 text-sm">
                        <input
                            type="checkbox"
                            className="mt-0.5 size-4 accent-violet"
                            checked={form.data.melden}
                            onChange={(e) =>
                                form.setData('melden', e.target.checked)
                            }
                        />
                        Creator een melding sturen: zet de post binnen 24 uur
                        terug
                    </label>
                    <div className="flex flex-wrap gap-2">
                        <Button
                            type="button"
                            size="sm"
                            variant="destructive"
                            disabled={form.processing}
                            onClick={() => vastleggen(false)}
                        >
                            Vastleggen: niet gevonden
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant="ghost"
                            onClick={() => setNietGevonden(false)}
                        >
                            Annuleren
                        </Button>
                    </div>
                </div>
            ) : (
                <div className="mt-3 flex flex-wrap gap-2">
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        disabled={form.processing}
                        onClick={() => vastleggen(true)}
                    >
                        <CheckCircle2 aria-hidden />
                        Gevonden
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        className="text-rood"
                        onClick={() => setNietGevonden(true)}
                    >
                        <AlertTriangle aria-hidden />
                        Niet gevonden
                    </Button>
                </div>
            )}
        </li>
    );
}

Koppelingen.layout = {
    breadcrumbs: [{ title: 'API-status', href: content.koppelingen.url() }],
};
