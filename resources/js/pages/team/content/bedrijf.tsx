import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import {
    IdeeenLijst,
    IdeeFormulier,
    ideeStatus,
} from '@/components/content/ideeen-lijst';
import { Voorstel } from '@/components/content/labels';
import { Maandkalender } from '@/components/content/maandkalender';
import { RechtenLijst, soortNaam } from '@/components/content/rechten-lijst';
import type {
    Idee,
    Kalender,
    Recht,
    StandaardRechten,
} from '@/components/content/types';
import { Button } from '@/components/ui/button';
import { samenwerking } from '@/routes/team';
import content from '@/routes/team/content';
import { Leeg, Pagina, PaginaKop } from '@/components/pagina';
import { Fouten, invoer, Paneel, Veld } from '../_onderdelen/basis';

type Props = {
    companies: { id: number; name: string }[];
    company: { id: number; name: string } | null;
    kalender: Kalender | null;
    ideas: Idee[];
    rights: Recht[];
    collaborations: { id: number; label: string }[];
    standaard: StandaardRechten;
    can: { beheren: boolean };
};

const tabs = [
    { key: 'kalender', naam: 'Contentkalender' },
    { key: 'ideeen', naam: 'Video-ideeën' },
    { key: 'rechten', naam: 'Gebruiksrechten' },
] as const;

/** Content per bedrijf voor het team: kalender, video-ideeën en gebruiksrechten. */
export default function Bedrijf({
    companies,
    company,
    kalender,
    ideas,
    rights,
    collaborations,
    standaard,
    can,
}: Props) {
    const [tab, setTab] = useState<(typeof tabs)[number]['key']>('kalender');

    if (!company || !kalender) {
        return (
            <Pagina>
                <PaginaKop titel="Content per bedrijf" />
                <Leeg titel="Nog geen bedrijven." />
            </Pagina>
        );
    }

    return (
        <>
            <Head title={`Content ${company.name}`} />
            <Pagina>
                <PaginaKop
                    titel="Content per bedrijf"
                    omschrijving={company.name}
                    acties={
                        <label className="grid gap-1 text-xs font-semibold">
                            Bedrijf
                            <select
                                className={invoer}
                                value={company.id}
                                onChange={(e) =>
                                    router.get(
                                        content.bedrijf.url(
                                            Number(e.target.value),
                                        ),
                                    )
                                }
                            >
                                {companies.map((c) => (
                                    <option key={c.id} value={c.id}>
                                        {c.name}
                                    </option>
                                ))}
                            </select>
                        </label>
                    }
                />
                <Fouten />

                <div
                    className="flex gap-2 overflow-x-auto pb-1"
                    role="tablist"
                    aria-label="Onderdelen"
                >
                    {tabs.map((t) => (
                        <button
                            key={t.key}
                            type="button"
                            role="tab"
                            aria-selected={tab === t.key}
                            onClick={() => setTab(t.key)}
                            className={`shrink-0 rounded-full border px-3 py-1.5 text-sm font-semibold ${tab === t.key ? 'border-aubergine bg-aubergine text-white' : 'bg-card hover:border-violet'}`}
                        >
                            {t.naam}
                        </button>
                    ))}
                </div>

                {tab === 'kalender' && (
                    <Maandkalender
                        kalender={kalender}
                        maandUrl={(m) =>
                            content.bedrijf.url(company.id, {
                                query: { maand: m },
                            })
                        }
                        samenwerkingUrl={(id) => samenwerking.url(id)}
                        notitieUrl={
                            can.beheren
                                ? content.notities.opslaan.url(company.id)
                                : undefined
                        }
                        verwijderUrl={
                            can.beheren
                                ? (id) => content.notities.verwijderen.url(id)
                                : undefined
                        }
                    />
                )}

                {tab === 'ideeen' && (
                    <div className="grid gap-5 lg:grid-cols-[1fr_360px] lg:items-start">
                        <div className="order-2 min-w-0 lg:order-1">
                            <IdeeenLijst
                                ideas={ideas}
                                team
                                actie={
                                    can.beheren
                                        ? (i) => (
                                              <IdeeBijwerken
                                                  idee={i}
                                                  collaborations={
                                                      collaborations
                                                  }
                                              />
                                          )
                                        : undefined
                                }
                            />
                        </div>
                        {can.beheren && (
                            <div className="order-1 lg:order-2">
                                <IdeeFormulier
                                    url={content.ideeen.opslaan.url(company.id)}
                                    uitleg="Ideeën van het team ziet de ondernemer ook. Verwijs ernaar bij een aanvraag."
                                />
                            </div>
                        )}
                    </div>
                )}

                {tab === 'rechten' && (
                    <div className="space-y-5">
                        <p className="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                            <Voorstel /> Standaard bij Live: organisch{' '}
                            {standaard.organisch} maanden, advertenties{' '}
                            {standaard.advertentie} maanden. Meldingen{' '}
                            {standaard.melden.join(' en ')} dagen vooraf.
                            Rechten per pakket zijn nog niet besloten.
                        </p>
                        {rights.length === 0 ? (
                            <Leeg titel="Nog geen gebruiksrechten." />
                        ) : (
                            <RechtenLijst
                                rights={rights}
                                actie={
                                    can.beheren
                                        ? (r) => <RechtBijwerken recht={r} />
                                        : undefined
                                }
                            />
                        )}
                        {can.beheren && collaborations.length > 0 && (
                            <NieuwRecht
                                bedrijfId={company.id}
                                collaborations={collaborations}
                                standaard={standaard}
                            />
                        )}
                    </div>
                )}
            </Pagina>
        </>
    );
}

function IdeeBijwerken({
    idee,
    collaborations,
}: {
    idee: Idee;
    collaborations: Props['collaborations'];
}) {
    const form = useForm({
        status: idee.status as string,
        collaboration_id: idee.collaboration
            ? String(idee.collaboration.id)
            : '',
    });

    function opslaan(e: FormEvent) {
        e.preventDefault();
        form.patch(content.ideeen.bijwerken.url(idee.id), {
            preserveScroll: true,
        });
    }

    return (
        <form
            onSubmit={opslaan}
            className="grid gap-2 sm:grid-cols-[140px_1fr_auto] sm:items-end"
        >
            <label className="grid gap-1 text-xs font-semibold">
                Status
                <select
                    className={invoer}
                    value={form.data.status}
                    onChange={(e) => form.setData('status', e.target.value)}
                >
                    {Object.entries(ideeStatus).map(([k, s]) => (
                        <option key={k} value={k}>
                            {s.naam}
                        </option>
                    ))}
                </select>
            </label>
            <label className="grid min-w-0 gap-1 text-xs font-semibold">
                Gebruikt bij aanvraag
                <select
                    className={invoer}
                    value={form.data.collaboration_id}
                    onChange={(e) =>
                        form.setData('collaboration_id', e.target.value)
                    }
                >
                    <option value="">Nog niet</option>
                    {collaborations.map((c) => (
                        <option key={c.id} value={c.id}>
                            {c.label}
                        </option>
                    ))}
                </select>
            </label>
            <Button
                type="submit"
                size="sm"
                variant="outline"
                className="h-10"
                disabled={form.processing || !form.isDirty}
            >
                Opslaan
            </Button>
        </form>
    );
}

function RechtBijwerken({ recht }: { recht: Recht }) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        ends_on: recht.ends_on,
        ad_code: recht.ad_code ?? '',
        status: recht.status as string,
    });

    if (!open) {
        return (
            <Button
                type="button"
                size="sm"
                variant="ghost"
                onClick={() => setOpen(true)}
                aria-label={`${soortNaam[recht.kind]} van ${recht.collaboration.title} wijzigen`}
            >
                Wijzigen
            </Button>
        );
    }

    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                form.patch(content.rechten.bijwerken.url(recht.id), {
                    preserveScroll: true,
                    onSuccess: () => setOpen(false),
                });
            }}
            className="grid w-full gap-2 sm:grid-cols-3"
        >
            <label className="grid gap-1 text-xs font-semibold">
                Einddatum
                <input
                    type="date"
                    className={invoer}
                    value={form.data.ends_on}
                    onChange={(e) => form.setData('ends_on', e.target.value)}
                />
            </label>
            <label className="grid gap-1 text-xs font-semibold">
                Advertentiecode
                <input
                    className={invoer}
                    value={form.data.ad_code}
                    onChange={(e) => form.setData('ad_code', e.target.value)}
                    placeholder="Partnership-code"
                />
            </label>
            <label className="grid gap-1 text-xs font-semibold">
                Status
                <select
                    className={invoer}
                    value={form.data.status}
                    onChange={(e) => form.setData('status', e.target.value)}
                >
                    <option value="actief">Actief</option>
                    <option value="verlopen">Verlopen</option>
                    <option value="ingetrokken">Ingetrokken</option>
                </select>
            </label>
            <div className="flex gap-2 sm:col-span-3">
                <Button
                    type="submit"
                    size="sm"
                    className="bg-violet hover:bg-violet/90"
                    disabled={form.processing}
                >
                    Opslaan
                </Button>
                <Button
                    type="button"
                    size="sm"
                    variant="ghost"
                    onClick={() => setOpen(false)}
                >
                    Annuleren
                </Button>
            </div>
        </form>
    );
}

function plusMaanden(iso: string, maanden: number): string {
    const d = new Date(`${iso}T12:00:00Z`);
    d.setUTCMonth(d.getUTCMonth() + maanden);

    return d.toISOString().slice(0, 10);
}

function NieuwRecht({
    bedrijfId,
    collaborations,
    standaard,
}: {
    bedrijfId: number;
    collaborations: Props['collaborations'];
    standaard: StandaardRechten;
}) {
    const vandaag = new Date().toLocaleDateString('sv-SE', {
        timeZone: 'Europe/Amsterdam',
    });
    const form = useForm({
        collaboration_id: String(collaborations[0].id),
        kind: 'advertentie',
        starts_on: vandaag,
        ends_on: plusMaanden(vandaag, standaard.advertentie),
        ad_code: '',
    });
    const kiesSoort = (kind: string) => {
        form.setData((d) => ({
            ...d,
            kind,
            ends_on: plusMaanden(
                d.starts_on,
                kind === 'organisch'
                    ? standaard.organisch
                    : standaard.advertentie,
            ),
        }));
    };

    return (
        <Paneel
            titel="Gebruiksrecht vastleggen"
            sub="Bijvoorbeeld een advertentiecode of afwijkende duur. Standaardrechten ontstaan vanzelf bij Live."
        >
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post(content.rechten.opslaan.url(bedrijfId), {
                        preserveScroll: true,
                        onSuccess: () => form.reset('ad_code'),
                    });
                }}
                className="grid gap-3 md:grid-cols-2 xl:grid-cols-5 xl:items-end"
                noValidate
            >
                <Veld
                    label="Samenwerking"
                    htmlFor="recht-samenwerking"
                    fout={form.errors.collaboration_id}
                    className="md:col-span-2 xl:col-span-1"
                >
                    <select
                        id="recht-samenwerking"
                        className={invoer}
                        value={form.data.collaboration_id}
                        onChange={(e) =>
                            form.setData('collaboration_id', e.target.value)
                        }
                    >
                        {collaborations.map((c) => (
                            <option key={c.id} value={c.id}>
                                {c.label}
                            </option>
                        ))}
                    </select>
                </Veld>
                <Veld
                    label="Soort"
                    htmlFor="recht-soort"
                    fout={form.errors.kind}
                >
                    <select
                        id="recht-soort"
                        className={invoer}
                        value={form.data.kind}
                        onChange={(e) => kiesSoort(e.target.value)}
                    >
                        <option value="advertentie">Advertenties</option>
                        <option value="organisch">Organisch en website</option>
                    </select>
                </Veld>
                <Veld
                    label="Start"
                    htmlFor="recht-start"
                    fout={form.errors.starts_on}
                >
                    <input
                        id="recht-start"
                        type="date"
                        className={invoer}
                        value={form.data.starts_on}
                        onChange={(e) =>
                            form.setData('starts_on', e.target.value)
                        }
                    />
                </Veld>
                <Veld
                    label="Einde"
                    htmlFor="recht-eind"
                    fout={form.errors.ends_on}
                >
                    <input
                        id="recht-eind"
                        type="date"
                        className={invoer}
                        value={form.data.ends_on}
                        onChange={(e) =>
                            form.setData('ends_on', e.target.value)
                        }
                    />
                </Veld>
                <Veld
                    label="Advertentiecode (optioneel)"
                    htmlFor="recht-code"
                    fout={form.errors.ad_code}
                >
                    <input
                        id="recht-code"
                        className={invoer}
                        value={form.data.ad_code}
                        onChange={(e) =>
                            form.setData('ad_code', e.target.value)
                        }
                        placeholder="Partnership-code"
                    />
                </Veld>
                <Button
                    type="submit"
                    className="min-h-10 bg-violet hover:bg-violet/90 md:col-span-2 md:justify-self-start xl:col-span-5"
                    disabled={form.processing}
                >
                    Vastleggen
                </Button>
            </form>
        </Paneel>
    );
}

Bedrijf.layout = {
    breadcrumbs: [
        { title: 'Content per bedrijf', href: content.bedrijf.url() },
    ],
};
