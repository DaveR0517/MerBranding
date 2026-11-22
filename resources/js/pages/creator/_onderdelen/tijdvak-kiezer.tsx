import { useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { tijdvak } from '@/routes/creator';
import type { SamenwerkingVol } from '@/types/samenwerking';
import { Fout, groteKnop } from './opmaak';

type Venster = SamenwerkingVol['visit_windows'][number];
type Dag = { sleutel: string; datum: Date; tijden: string[] };

/** Datum en tijd nu in Nederland, als losse delen. */
function nuInNederland(): {
    jaar: number;
    maand: number;
    dag: number;
    tijd: string;
} {
    const delen = Object.fromEntries(
        new Intl.DateTimeFormat('en-GB', {
            timeZone: 'Europe/Amsterdam',
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            hourCycle: 'h23',
        })
            .formatToParts(new Date())
            .map((p) => [p.type, p.value]),
    );

    return {
        jaar: Number(delen.year),
        maand: Number(delen.month),
        dag: Number(delen.day),
        tijd: `${delen.hour}:${delen.minute}`,
    };
}

function minuten(hhmm: string): number {
    const [u, m] = hhmm.split(':').map(Number);

    return u * 60 + m;
}

function hhmm(min: number): string {
    return `${String(Math.floor(min / 60)).padStart(2, '0')}:${String(min % 60).padStart(2, '0')}`;
}

/** De komende 14 dagen (vandaag meegeteld) met een venster, en per dag de tijden in stappen van 30 minuten. */
export function dagenMetTijden(vensters: Venster[]): Dag[] {
    const nu = nuInNederland();
    const dagen: Dag[] = [];

    for (let i = 0; i < 14; i++) {
        const d = new Date(Date.UTC(nu.jaar, nu.maand - 1, nu.dag + i));
        const weekdag = d.getUTCDay() === 0 ? 7 : d.getUTCDay();
        const tijden = new Set<string>();

        vensters
            .filter((v) => v.weekday === weekdag)
            .forEach((v) => {
                for (
                    let m = minuten(v.starts_at);
                    m < minuten(v.ends_at);
                    m += 30
                ) {
                    if (i > 0 || m > minuten(nu.tijd)) {
                        tijden.add(hhmm(m));
                    }
                }
            });

        if (tijden.size > 0) {
            dagen.push({
                sleutel: d.toISOString().slice(0, 10),
                datum: d,
                tijden: [...tijden].sort(),
            });
        }
    }

    return dagen;
}

export function TijdvakKiezer({
    samenwerking,
}: {
    samenwerking: SamenwerkingVol;
}) {
    const dagen = useMemo(
        () => dagenMetTijden(samenwerking.visit_windows),
        [samenwerking.visit_windows],
    );
    const [dag, setDag] = useState<string | null>(dagen[0]?.sleutel ?? null);
    const form = useForm({ moment: '' });
    const gekozenDag = dagen.find((d) => d.sleutel === dag);
    const [, tijd] = form.data.moment.split('T');

    if (dagen.length === 0) {
        return (
            <p className="text-muted-foreground">
                Deze vestiging heeft de komende 14 dagen geen vrije tijden. Het
                team neemt contact met je op.
            </p>
        );
    }

    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                form.post(tijdvak.url(samenwerking.id), {
                    preserveScroll: true,
                });
            }}
            className="space-y-4"
        >
            <fieldset>
                <legend className="mb-2 text-sm font-semibold">
                    1. Kies een dag
                </legend>
                <div
                    className="-mx-1 flex snap-x gap-2 overflow-x-auto px-1 pb-2"
                    role="radiogroup"
                    aria-label="Dag"
                >
                    {dagen.map((d) => {
                        const actief = d.sleutel === dag;

                        return (
                            <button
                                key={d.sleutel}
                                type="button"
                                role="radio"
                                aria-checked={actief}
                                onClick={() => {
                                    setDag(d.sleutel);
                                    form.setData('moment', '');
                                }}
                                className={`flex min-w-16 shrink-0 snap-start flex-col items-center rounded-xl border-2 px-3 py-2 ${actief ? 'border-violet bg-violet text-white' : 'border-border bg-card hover:border-violet'}`}
                                data-test="dag"
                            >
                                <span className="text-xs font-semibold uppercase">
                                    {d.datum.toLocaleDateString('nl-NL', {
                                        timeZone: 'UTC',
                                        weekday: 'short',
                                    })}
                                </span>
                                <span className="font-heading text-xl font-extrabold">
                                    {d.datum.getUTCDate()}
                                </span>
                                <span className="text-xs">
                                    {d.datum.toLocaleDateString('nl-NL', {
                                        timeZone: 'UTC',
                                        month: 'short',
                                    })}
                                </span>
                            </button>
                        );
                    })}
                </div>
            </fieldset>

            {gekozenDag && (
                <fieldset>
                    <legend className="mb-2 text-sm font-semibold">
                        2. Kies een tijd op{' '}
                        {gekozenDag.datum.toLocaleDateString('nl-NL', {
                            timeZone: 'UTC',
                            weekday: 'long',
                            day: 'numeric',
                            month: 'long',
                        })}
                    </legend>
                    <div
                        className="grid grid-cols-3 gap-2 sm:grid-cols-4 md:grid-cols-6"
                        role="radiogroup"
                        aria-label="Tijd"
                    >
                        {gekozenDag.tijden.map((t) => {
                            const actief =
                                form.data.moment ===
                                `${gekozenDag.sleutel}T${t}`;

                            return (
                                <button
                                    key={t}
                                    type="button"
                                    role="radio"
                                    aria-checked={actief}
                                    onClick={() =>
                                        form.setData(
                                            'moment',
                                            `${gekozenDag.sleutel}T${t}`,
                                        )
                                    }
                                    className={`min-h-11 rounded-xl border-2 text-base font-semibold tabular-nums ${actief ? 'border-violet bg-violet text-white' : 'border-border bg-card hover:border-violet'}`}
                                    data-test="tijd"
                                >
                                    {t}
                                </button>
                            );
                        })}
                    </div>
                </fieldset>
            )}

            <Fout tekst={form.errors.moment} />

            <button
                type="submit"
                className={groteKnop}
                disabled={!form.data.moment || form.processing}
            >
                {form.data.moment && gekozenDag
                    ? `Bevestig ${gekozenDag.datum.toLocaleDateString('nl-NL', { timeZone: 'UTC', weekday: 'short', day: 'numeric', month: 'short' })}, ${tijd}`
                    : 'Kies eerst een dag en tijd'}
            </button>
            <p className="text-sm text-muted-foreground">
                Je keuze staat direct vast en {samenwerking.company} krijgt
                meteen bericht.
            </p>
        </form>
    );
}
