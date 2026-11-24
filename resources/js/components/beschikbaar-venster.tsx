import { router, usePage } from '@inertiajs/react';
import { MapPin, Navigation, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { alsApp, laterGekozen, pushStand, telefoon } from '@/lib/webapp';
import * as beschikbaar from '@/routes/creator/beschikbaar';

type Beschikbaarheid = { actief: boolean; tot: string | null; plek: boolean } | null;

const GEVRAAGD = 'mebranding-beschikbaar-gevraagd';

function uur(iso: string): string {
    return new Date(iso).toLocaleTimeString('nl-NL', { timeZone: 'Europe/Amsterdam', hour: '2-digit', minute: '2-digit' });
}

/** Plek van de telefoon, één keer, met toestemming. Geen toestemming of te traag: zonder plek (dan telt de woonplaats). */
function plek(): Promise<{ lat: number; lng: number } | null> {
    return new Promise((klaar) => {
        if (!('geolocation' in navigator)) {
            klaar(null);

            return;
        }
        navigator.geolocation.getCurrentPosition(
            (p) => klaar({ lat: p.coords.latitude, lng: p.coords.longitude }),
            () => klaar(null),
            { enableHighAccuracy: false, timeout: 8000, maximumAge: 300000 },
        );
    });
}

/**
 * "Ben je beschikbaar?": bij het openen van de app (als webapp op de telefoon), één keer per keer openen.
 * Alleen als de creatorkant aan staat (de server stuurt dan 'beschikbaarheid' mee) en niet tegelijk met het webapp-venster.
 */
export function BeschikbaarVenster() {
    const { beschikbaarheid } = usePage().props as unknown as { beschikbaarheid: Beschikbaarheid };
    const [open, setOpen] = useState(false);
    const [bezig, setBezig] = useState(false);

    useEffect(() => {
        if (!beschikbaarheid || beschikbaarheid.actief || !alsApp() || !telefoon()) {
            return;
        }
        try {
            if (sessionStorage.getItem(GEVRAAGD)) {
                return;
            }
        } catch {
            /* zonder opslag gewoon vragen */
        }
        // Eerst het webapp-venster (meldingen aanzetten); daarna pas deze vraag.
        void pushStand().then((s) => {
            if (s === 'aan' || s === 'niet-mogelijk' || laterGekozen()) {
                setOpen(true);
            }
        });
        // Alleen bij het openen van de app; niet opnieuw na elke pagina.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    if (!open) {
        return null;
    }

    const onthoud = () => {
        try {
            sessionStorage.setItem(GEVRAAGD, '1');
        } catch {
            /* geen opslag: dan vraagt de app het de volgende keer weer */
        }
    };

    const kies = async (duur: '2' | '4' | 'dag') => {
        setBezig(true);
        const p = await plek();
        onthoud();
        router.post(beschikbaar.aan.url(), { duur, lat: p?.lat ?? null, lng: p?.lng ?? null }, { preserveScroll: true, onFinish: () => setOpen(false) });
    };

    const nee = () => {
        onthoud();
        setOpen(false);
    };

    return (
        <div className="fixed inset-0 z-50 flex items-end justify-center bg-[#16141f]/45 md:items-center" role="presentation" onClick={nee}>
            <section
                role="dialog"
                aria-modal="true"
                aria-labelledby="beschikbaar-titel"
                data-test="beschikbaar-venster"
                onClick={(e) => e.stopPropagation()}
                className="w-full rounded-t-3xl bg-white p-5 pb-[max(1.5rem,env(safe-area-inset-bottom))] text-aubergine shadow-2xl md:max-w-md md:rounded-3xl"
            >
                <div className="mx-auto mb-4 h-1.5 w-10 rounded-full bg-[#DAD6E6] md:hidden" aria-hidden />
                <button type="button" onClick={nee} className="float-right -mt-1 -mr-1 grid size-10 place-items-center rounded-full text-[#8A86A3] hover:bg-muted" aria-label="Sluiten">
                    <X className="size-5" aria-hidden />
                </button>
                <div className="mb-4 flex items-center gap-3 pr-8">
                    <span className="grid size-13 shrink-0 place-items-center rounded-2xl bg-emerald-100 text-emerald-700">
                        <Navigation className="size-6" aria-hidden />
                    </span>
                    <div>
                        <h2 id="beschikbaar-titel" className="font-heading text-lg leading-tight font-extrabold">
                            Ben je beschikbaar voor een klus in de buurt?
                        </h2>
                        <p className="text-[13px] text-[#5E5A78]">Dan krijg je bericht als er iets dichtbij is.</p>
                    </div>
                </div>

                <div className="grid grid-cols-3 gap-2" role="group" aria-label="Hoe lang">
                    {(
                        [
                            ['2', '2 uur'],
                            ['4', '4 uur'],
                            ['dag', 'Rest van de dag'],
                        ] as const
                    ).map(([duur, tekst]) => (
                        <button
                            key={duur}
                            type="button"
                            disabled={bezig}
                            onClick={() => kies(duur)}
                            className="min-h-14 rounded-2xl border-2 border-violet/30 px-2 font-heading text-sm font-bold text-aubergine transition hover:border-violet hover:bg-violet/5 disabled:opacity-50"
                        >
                            {tekst}
                        </button>
                    ))}
                </div>

                <p className="mt-4 flex gap-2 rounded-2xl bg-[#F1EFF8] p-3 text-xs leading-relaxed text-[#4A4664]">
                    <MapPin className="mt-0.5 size-4 shrink-0 text-violet" aria-hidden />
                    {bezig
                        ? 'Je plek wordt opgevraagd...'
                        : 'Je telefoon vraagt om je plek. Die delen we alleen zolang je beschikbaar bent, afgerond op ongeveer 100 meter, met het team en voor klussen in de buurt. Daarna wissen we hem. Geen toestemming? Dan rekenen we met je woonplaats.'}
                </p>

                <button type="button" onClick={nee} className="mt-3 w-full py-2 text-center text-sm font-semibold text-[#8A86A3]">
                    Nu niet
                </button>
            </section>
        </div>
    );
}

/** Balk bovenaan zolang de creator beschikbaar is, met Stoppen. */
export function BeschikbaarBalk() {
    const { beschikbaarheid } = usePage().props as unknown as { beschikbaarheid: Beschikbaarheid };
    if (!beschikbaarheid?.actief || !beschikbaarheid.tot) {
        return null;
    }

    return (
        <div className="mx-4 mt-4 flex items-center justify-between gap-3 rounded-xl border border-emerald-300 bg-emerald-50 px-4 py-2.5 text-sm text-emerald-900 md:mx-6 dark:bg-emerald-950 dark:text-emerald-100" data-test="beschikbaar-balk">
            <span className="flex items-center gap-2">
                <span className="relative flex size-2.5" aria-hidden>
                    <span className="absolute inline-flex size-full animate-ping rounded-full bg-emerald-400 opacity-75 motion-reduce:hidden" />
                    <span className="relative inline-flex size-2.5 rounded-full bg-emerald-500" />
                </span>
                <span>
                    Je bent beschikbaar tot <strong>{uur(beschikbaarheid.tot)}</strong>
                    {beschikbaarheid.plek ? '' : ' (met je woonplaats)'}
                </span>
            </span>
            <button
                type="button"
                onClick={() => router.post(beschikbaar.uit.url(), {}, { preserveScroll: true })}
                className="min-h-9 shrink-0 rounded-full border border-emerald-400 px-3 font-semibold hover:bg-emerald-100 dark:hover:bg-emerald-900"
            >
                Stoppen
            </button>
        </div>
    );
}
