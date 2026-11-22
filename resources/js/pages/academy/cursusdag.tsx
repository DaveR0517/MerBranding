import { Head, Link, useForm } from '@inertiajs/react';
import { Kaart, Pagina, PaginaKop, TweeKolommen } from '@/components/pagina';
import { overzicht } from '@/routes/academy';
import { inschrijven } from '@/routes/academy/cursusdag';
import {
    AanZet,
    Fout,
    TitelKaart,
    groteKnop,
    rustigeKnop,
    veld,
} from '../creator/_onderdelen/opmaak';
import { DagRegel, Plekken, Prijs } from './_onderdelen/onderdelen';
import type { Dag, Inschrijving } from './_onderdelen/types';

type Props = {
    day: Dag;
    enrollment: Inschrijving | null;
    prefill: {
        phone: string;
        city: string;
        tiktok: string;
        instagram: string;
        birthdate: string;
    };
    min_age: number;
};

export default function Cursusdag({
    day,
    enrollment,
    prefill,
    min_age,
}: Props) {
    const form = useForm({ ...prefill, akkoord: false });
    const fouten = form.errors as Record<string, string | undefined>;
    const vol = day.free === 0;
    const kanInschrijven = day.status === 'open' && !day.is_past && !enrollment;

    const veldje = (
        naam: 'phone' | 'city' | 'tiktok' | 'instagram' | 'birthdate',
        label: string,
        opties: { type?: string; verplicht?: boolean; auto?: string } = {},
    ) => (
        <div>
            <label
                htmlFor={`i-${naam}`}
                className="mb-1 block text-sm font-semibold"
            >
                {label}
                {opties.verplicht ? '' : ' (optioneel)'}
            </label>
            <input
                id={`i-${naam}`}
                type={opties.type ?? 'text'}
                required={opties.verplicht}
                autoComplete={opties.auto}
                value={form.data[naam]}
                onChange={(e) => form.setData(naam, e.target.value)}
                className={veld}
                aria-invalid={!!fouten[naam]}
            />
            {fouten[naam] && (
                <p className="mt-1 text-sm text-rood">{fouten[naam]}</p>
            )}
        </div>
    );

    return (
        <>
            <Head title={`Cursusdag ${day.city}`} />
            <Pagina>
                <PaginaKop
                    boven={
                        <Link
                            href={overzicht.url()}
                            className="hover:text-violet"
                        >
                            Mijn academy
                        </Link>
                    }
                    titel={`Cursusdag ${day.city}`}
                    omschrijving={<DagRegel dag={day} />}
                />
                <TweeKolommen
                    hoofd={
                        <>
                            {enrollment?.status === 'wachtlijst' && (
                                <Kaart className="border-violet">
                                    <AanZet>
                                        Op de wachtlijst, plek{' '}
                                        {enrollment.place}
                                    </AanZet>
                                    <h2 className="font-heading text-lg font-bold">
                                        Deze cursusdag is vol
                                    </h2>
                                    <p className="mt-1 text-sm">
                                        Je staat op de wachtlijst en betaalt nog
                                        niets. Komt er een plek vrij, dan krijg
                                        je een bericht en heb je 24 uur om te
                                        betalen (voorstel). Daarna gaat de plek
                                        naar de volgende.
                                    </p>
                                </Kaart>
                            )}
                            {enrollment &&
                                enrollment.status !== 'wachtlijst' && (
                                    <Kaart className="border-violet">
                                        <AanZet>
                                            {enrollment.status_name}
                                        </AanZet>
                                        <h2 className="font-heading text-lg font-bold">
                                            Je staat erbij. Tot{' '}
                                            {new Date(
                                                `${day.date}T12:00:00`,
                                            ).toLocaleDateString('nl-NL', {
                                                weekday: 'long',
                                                day: 'numeric',
                                                month: 'long',
                                            })}
                                            !
                                        </h2>
                                        <p className="mt-1 text-sm">
                                            Je ticket met QR-code staat in Mijn
                                            academy, met ook het lesmateriaal
                                            dat je vooraf kunt bekijken.
                                        </p>
                                        <Link
                                            href={overzicht.url()}
                                            className={`${rustigeKnop} mt-3`}
                                        >
                                            Naar Mijn academy
                                        </Link>
                                    </Kaart>
                                )}

                            <TitelKaart titel="Over deze dag">
                                <p className="text-sm">
                                    {day.title}. Een dag op locatie bij{' '}
                                    {day.location_name}: theorie in de ochtend,
                                    zelf filmen en monteren in de middag.
                                </p>
                                <dl className="mt-3 grid gap-3 text-sm sm:grid-cols-2">
                                    <div>
                                        <dt className="font-semibold">
                                            Locatie
                                        </dt>
                                        <dd className="text-muted-foreground">
                                            {day.location_name}
                                            {day.address
                                                ? `, ${day.address}`
                                                : ''}
                                            , {day.city}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="font-semibold">Tijd</dt>
                                        <dd className="text-muted-foreground">
                                            {day.starts_at} - {day.ends_at}
                                        </dd>
                                    </div>
                                    {day.teacher_name && (
                                        <div>
                                            <dt className="font-semibold">
                                                Je docent
                                            </dt>
                                            <dd className="text-muted-foreground">
                                                {day.teacher_name}
                                            </dd>
                                        </div>
                                    )}
                                    <div>
                                        <dt className="font-semibold">
                                            Praktijkexamen
                                        </dt>
                                        <dd className="text-muted-foreground">
                                            {day.exam_required
                                                ? 'Ja, 3 korte video’s na de dag, voor je certificaat'
                                                : 'Nee, het certificaat krijg je op de dag'}
                                        </dd>
                                    </div>
                                </dl>
                                {day.notes && (
                                    <div className="mt-3 border-t pt-3 text-sm">
                                        <h3 className="font-semibold">
                                            Wat je meeneemt
                                        </h3>
                                        <p className="text-muted-foreground">
                                            {day.notes}
                                        </p>
                                    </div>
                                )}
                            </TitelKaart>
                        </>
                    }
                    zij={
                        <TitelKaart titel="Inschrijven">
                            <p className="-mt-1 flex flex-wrap justify-between gap-2 text-sm">
                                <Prijs dag={day} /> <Plekken dag={day} />
                            </p>
                            {kanInschrijven ? (
                                <form
                                    className="mt-4 space-y-3"
                                    onSubmit={(e) => {
                                        e.preventDefault();
                                        form.post(inschrijven.url(day.id), {
                                            preserveScroll: true,
                                        });
                                    }}
                                >
                                    {veldje('phone', 'Telefoon', {
                                        type: 'tel',
                                        verplicht: true,
                                        auto: 'tel',
                                    })}
                                    {veldje('city', 'Woonplaats', {
                                        verplicht: true,
                                        auto: 'address-level2',
                                    })}
                                    {veldje('tiktok', 'TikTok-naam')}
                                    {veldje('instagram', 'Instagram-naam')}
                                    {veldje(
                                        'birthdate',
                                        `Geboortedatum (minimaal ${min_age} jaar)`,
                                        {
                                            type: 'date',
                                            verplicht: true,
                                            auto: 'bday',
                                        },
                                    )}
                                    <label className="flex items-start gap-2 text-sm">
                                        <input
                                            type="checkbox"
                                            className="mt-1 size-4 accent-violet"
                                            checked={form.data.akkoord}
                                            onChange={(e) =>
                                                form.setData(
                                                    'akkoord',
                                                    e.target.checked,
                                                )
                                            }
                                        />
                                        <span>
                                            Ik ga akkoord met de voorwaarden en
                                            de annuleringsregeling (placeholder,
                                            versie 0.1).
                                        </span>
                                    </label>
                                    <Fout
                                        tekst={fouten.akkoord ?? fouten.dag}
                                    />
                                    <button
                                        type="submit"
                                        className={groteKnop}
                                        disabled={form.processing}
                                    >
                                        {vol
                                            ? 'Op de wachtlijst'
                                            : 'Inschrijven'}
                                    </button>
                                    <p className="text-xs text-muted-foreground">
                                        {vol
                                            ? 'Vol? Dan kom je op de wachtlijst. Je betaalt pas als er een plek vrijkomt.'
                                            : 'Online betalen komt er zodra de betaalpartij gekozen is. Tot die tijd staat je inschrijving op "nog te betalen".'}
                                    </p>
                                </form>
                            ) : (
                                !enrollment && (
                                    <p className="mt-3 text-sm text-muted-foreground">
                                        Voor deze cursusdag kun je je niet meer
                                        inschrijven.
                                    </p>
                                )
                            )}
                        </TitelKaart>
                    }
                />
            </Pagina>
        </>
    );
}
