import { usePage } from '@inertiajs/react';
import { BellRing, Check, ChevronLeft, ChevronRight, Download, MoreHorizontal, Plus, X } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import type { ReactNode } from 'react';
import {
    OPEN_WEBAPP_VENSTER,
    alsApp,
    installeren,
    kanInstalleren,
    kiesLater,
    laterGekozen,
    meldingenAanzetten,
    opWebappWijziging,
    platform,
    pushStand,
    telefoon,
} from '@/lib/webapp';
import type { PushStand } from '@/lib/webapp';
import { cn } from '@/lib/utils';
import type { Auth } from '@/types/auth';

type Stap = 'installeren' | 'ios' | 'android-menu' | 'meldingen' | 'klaar' | 'geweigerd';

/** Welke inhoud het venster nu toont, op basis van apparaat, installatie en meldingen. */
function bepaal(stand: PushStand): Stap | null {
    if (stand === 'aan') {
        return null;
    }
    if (stand === 'geweigerd') {
        return 'geweigerd';
    }
    if (alsApp()) {
        return 'meldingen';
    }
    const p = platform();
    if (p === 'ios') {
        return 'ios';
    }
    if (kanInstalleren()) {
        return 'installeren';
    }

    return p === 'android' ? 'android-menu' : 'meldingen';
}

/**
 * "Zet MEBRANDING op je telefoon": eenmalig voor creators op de telefoon na het inloggen.
 * Android: installeren met de knop van de browser, daarna meldingen aan. iPhone: uitleg in drie plaatjes,
 * want daar kan het alleen via Deel, Zet op beginscherm; meldingen pas vanuit de webapp. Account opent het opnieuw.
 */
export function WebappVenster() {
    const { auth } = usePage().props as unknown as { auth: Auth };
    const [open, setOpen] = useState(false);
    const [stap, setStap] = useState<Stap | null>(null);
    const [bezig, setBezig] = useState(false);
    const [uitleg, setUitleg] = useState(false);
    const creator = auth.portal === 'creator';

    const ververs = useCallback(async (automatisch: boolean) => {
        const nieuw = bepaal(await pushStand());
        setStap(nieuw);
        if (automatisch) {
            setOpen(nieuw !== null && nieuw !== 'geweigerd' && !laterGekozen() && telefoon());
        }
    }, []);

    useEffect(() => {
        if (!creator) {
            return;
        }
        void ververs(true);
        const handmatig = () => {
            void ververs(false).then(() => setOpen(true));
        };
        window.addEventListener(OPEN_WEBAPP_VENSTER, handmatig);
        const stop = opWebappWijziging(() => void ververs(false));

        return () => {
            window.removeEventListener(OPEN_WEBAPP_VENSTER, handmatig);
            stop();
        };
    }, [creator, ververs]);

    if (!open || !stap) {
        return null;
    }

    const sluit = () => {
        kiesLater();
        setOpen(false);
        setUitleg(false);
    };

    const zetMeldingenAan = async () => {
        setBezig(true);
        const uitkomst = await meldingenAanzetten().catch(() => 'uit' as PushStand);
        setBezig(false);
        setStap(uitkomst === 'aan' ? 'klaar' : uitkomst === 'geweigerd' ? 'geweigerd' : 'meldingen');
    };

    const installeer = async () => {
        setBezig(true);
        const gelukt = await installeren();
        setBezig(false);
        if (gelukt) {
            setStap('meldingen');
        }
    };

    return (
        <div className="fixed inset-0 z-50 flex items-end justify-center bg-[#16141f]/45 md:items-center" role="presentation" onClick={sluit}>
            <section
                role="dialog"
                aria-modal="true"
                aria-labelledby="webapp-titel"
                data-test="webapp-venster"
                onClick={(e) => e.stopPropagation()}
                className="max-h-[92dvh] w-full overflow-y-auto rounded-t-3xl bg-white p-5 pb-[max(1.5rem,env(safe-area-inset-bottom))] text-aubergine shadow-2xl md:max-w-md md:rounded-3xl"
            >
                <div className="mx-auto mb-4 h-1.5 w-10 rounded-full bg-[#DAD6E6] md:hidden" aria-hidden />
                <button
                    type="button"
                    onClick={sluit}
                    className="float-right -mt-1 -mr-1 grid size-10 place-items-center rounded-full text-[#8A86A3] hover:bg-muted"
                    aria-label="Sluiten"
                >
                    <X className="size-5" aria-hidden />
                </button>

                {uitleg ? (
                    <IosUitleg terug={() => setUitleg(false)} />
                ) : (
                    <>
                        <Kop
                            titel={
                                stap === 'klaar'
                                    ? 'Meldingen staan aan'
                                    : stap === 'meldingen' || stap === 'geweigerd'
                                      ? 'Zet meldingen aan'
                                      : platform() === 'ios'
                                        ? 'Zet MEBRANDING op je iPhone'
                                        : 'Zet MEBRANDING op je telefoon'
                            }
                            sub={
                                stap === 'klaar'
                                    ? 'Je krijgt nu direct bericht bij een nieuwe klus.'
                                    : stap === 'meldingen' || stap === 'geweigerd'
                                      ? 'Mis geen uitnodiging meer'
                                      : stap === 'ios'
                                        ? 'Daarna kun je meldingen aanzetten'
                                        : 'Gratis, geen app store nodig'
                            }
                        />

                        {stap === 'installeren' && (
                            <>
                                <Voordelen />
                                <StapRegel nr={1} titel="Installeer de webapp">
                                    MEBRANDING komt als app op je beginscherm.
                                </StapRegel>
                                <StapRegel nr={2} titel="Zet meldingen aan">
                                    Na het installeren vragen we het meteen. Zo mis je geen uitnodiging: je hebt 48 uur om te reageren.
                                </StapRegel>
                                <Knop onClick={installeer} bezig={bezig} icoon={<Download className="size-5" aria-hidden />}>
                                    Installeren
                                </Knop>
                            </>
                        )}

                        {stap === 'android-menu' && (
                            <>
                                <StapRegel nr={1} titel="Open het menu van je browser">
                                    De drie puntjes <MoreHorizontal className="inline size-4 rotate-90" aria-label="menu" /> rechtsboven.
                                </StapRegel>
                                <StapRegel nr={2} titel='Kies "App installeren"'>
                                    Of "Toevoegen aan startscherm". MEBRANDING komt dan op je beginscherm.
                                </StapRegel>
                                <p className="mb-2 text-sm text-[#5E5A78]">Meldingen kun je ook nu al aanzetten:</p>
                                <Knop onClick={zetMeldingenAan} bezig={bezig} icoon={<BellRing className="size-5" aria-hidden />}>
                                    Meldingen aanzetten
                                </Knop>
                            </>
                        )}

                        {stap === 'ios' && (
                            <>
                                <StapRegel nr={1} titel="Tik op Deel">
                                    Het vierkantje met de pijl <DeelIcoon /> in Safari. Zie je hem niet? Tik eerst op <b>•••</b>.
                                </StapRegel>
                                <StapRegel nr={2} titel='Kies "Zet op beginscherm"'>
                                    Tik daarna rechtsboven op Voeg toe.
                                </StapRegel>
                                <StapRegel nr={3} titel="Open MEBRANDING vanaf je beginscherm">
                                    Je krijgt dan de vraag om meldingen aan te zetten.
                                </StapRegel>
                                <Knop onClick={() => setUitleg(true)} donker>
                                    Laat zien hoe
                                </Knop>
                            </>
                        )}

                        {stap === 'meldingen' && (
                            <>
                                <p className="mb-4 text-[15px] leading-relaxed text-[#4A4664]">
                                    Krijg direct een melding bij een nieuwe uitnodiging, feedback op je video of een uitbetaling. Je hebt 48 uur om op een uitnodiging te reageren.
                                </p>
                                <Knop onClick={zetMeldingenAan} bezig={bezig} icoon={<BellRing className="size-5" aria-hidden />}>
                                    Meldingen aanzetten
                                </Knop>
                            </>
                        )}

                        {stap === 'geweigerd' && (
                            <p className="mb-2 rounded-2xl bg-[#F1EFF8] p-4 text-sm leading-relaxed text-[#4A4664]">
                                Meldingen staan uit voor MEBRANDING op dit apparaat. Zet ze aan in de instellingen van je telefoon: zoek MEBRANDING op (of je browser) en kies Meldingen, Toestaan.
                            </p>
                        )}

                        {stap === 'klaar' ? (
                            <Knop onClick={() => setOpen(false)} icoon={<Check className="size-5" aria-hidden />}>
                                Klaar
                            </Knop>
                        ) : (
                            <button type="button" onClick={sluit} className="mt-3 w-full py-2 text-center text-sm font-semibold text-[#8A86A3]">
                                Later
                            </button>
                        )}
                    </>
                )}
            </section>
        </div>
    );
}

function Kop({ titel, sub }: { titel: string; sub: string }) {
    return (
        <div className="mb-4 flex items-center gap-3 pr-8">
            <img src="/brand/app-icoon-192.png" alt="" className="size-13 shrink-0 rounded-2xl" />
            <div>
                <h2 id="webapp-titel" className="font-heading text-lg leading-tight font-extrabold">
                    {titel}
                </h2>
                <p className="text-[13px] text-[#5E5A78]">{sub}</p>
            </div>
        </div>
    );
}

function Voordelen() {
    return (
        <div className="mb-3 flex flex-wrap gap-1.5">
            {['Direct een melding bij een nieuwe klus', 'Eén tik vanaf je beginscherm'].map((v) => (
                <span key={v} className="rounded-full bg-[#F1EFF8] px-2.5 py-1 text-xs font-semibold text-[#4A4664]">
                    {v}
                </span>
            ))}
        </div>
    );
}

function StapRegel({ nr, titel, children }: { nr: number; titel: string; children: ReactNode }) {
    return (
        <div className="mb-2.5 flex gap-3 rounded-2xl border-[1.5px] border-[#E3E0EC] p-3 text-sm leading-snug text-[#4A4664]">
            <span className="grid size-6 shrink-0 place-items-center rounded-full bg-aubergine font-heading text-xs font-extrabold text-white">{nr}</span>
            <span>
                <b className="block font-heading text-[14px] text-aubergine">{titel}</b>
                {children}
            </span>
        </div>
    );
}

function Knop({ children, onClick, bezig = false, donker = false, icoon }: { children: ReactNode; onClick: () => void; bezig?: boolean; donker?: boolean; icoon?: ReactNode }) {
    return (
        <button
            type="button"
            onClick={onClick}
            disabled={bezig}
            className={cn(
                'mt-2 flex h-12 w-full items-center justify-center gap-2 rounded-full font-heading text-[15px] font-bold text-white transition disabled:opacity-60',
                donker ? 'bg-aubergine hover:bg-aubergine-dark' : 'bg-violet hover:bg-violet/90',
            )}
        >
            {icoon}
            {bezig ? 'Even geduld...' : children}
        </button>
    );
}

function DeelIcoon({ groot = false }: { groot?: boolean }) {
    return (
        <span className={cn('inline-grid place-items-center rounded-md bg-[#EAF1FF] align-middle', groot ? 'size-9' : 'mx-0.5 size-5')} aria-label="Deel">
            <svg viewBox="0 0 24 24" className={groot ? 'size-5' : 'size-3.5'} fill="none" stroke="#1d6ff2" strokeWidth="2.4" aria-hidden>
                <path d="M12 3v12M7 8l5-5 5 5" />
                <path d="M5 12v8h14v-8" />
            </svg>
        </span>
    );
}

/** Drie plaatjes voor de iPhone: Deel, Zet op beginscherm, openen vanaf het beginscherm. */
function IosUitleg({ terug }: { terug: () => void }) {
    const [i, setI] = useState(0);
    const plaatjes = [
        {
            titel: '1. Tik op Deel',
            tekst: 'Onderin (of bovenin) Safari. Zie je hem niet? Tik eerst op ••• en dan op Deel.',
            beeld: <SafariBeeld />,
        },
        {
            titel: '2. Zet op beginscherm',
            tekst: 'Scroll in het deelmenu naar "Zet op beginscherm" en tik rechtsboven op Voeg toe.',
            beeld: <DeelmenuBeeld />,
        },
        {
            titel: '3. Open MEBRANDING',
            tekst: 'Tik op het MEBRANDING-icoon op je beginscherm. Daar zetten we samen de meldingen aan.',
            beeld: <BeginschermBeeld />,
        },
    ];
    const p = plaatjes[i];

    return (
        <div data-test="ios-uitleg">
            <button type="button" onClick={terug} className="mb-2 inline-flex items-center gap-1 text-sm font-semibold text-violet">
                <ChevronLeft className="size-4" aria-hidden /> Terug
            </button>
            <div className="overflow-hidden rounded-2xl bg-[#F1EFF8]" aria-live="polite">
                <div className="flex h-56 items-center justify-center p-4">{p.beeld}</div>
            </div>
            <h2 id="webapp-titel" className="mt-4 font-heading text-lg font-extrabold">
                {p.titel}
            </h2>
            <p className="mt-1 min-h-12 text-sm leading-relaxed text-[#4A4664]">{p.tekst}</p>
            <div className="mt-3 flex items-center justify-between">
                <button
                    type="button"
                    onClick={() => setI(Math.max(0, i - 1))}
                    disabled={i === 0}
                    className="grid size-11 place-items-center rounded-full border text-aubergine disabled:opacity-30"
                    aria-label="Vorige"
                >
                    <ChevronLeft className="size-5" aria-hidden />
                </button>
                <div className="flex gap-1.5" aria-label={`Stap ${i + 1} van 3`}>
                    {plaatjes.map((_, n) => (
                        <span key={n} className={cn('h-2 rounded-full transition-all', n === i ? 'w-6 bg-violet' : 'w-2 bg-[#DAD6E6]')} />
                    ))}
                </div>
                {i < plaatjes.length - 1 ? (
                    <button type="button" onClick={() => setI(i + 1)} className="grid size-11 place-items-center rounded-full bg-violet text-white" aria-label="Volgende">
                        <ChevronRight className="size-5" aria-hidden />
                    </button>
                ) : (
                    <button type="button" onClick={terug} className="h-11 rounded-full bg-violet px-5 font-heading text-sm font-bold text-white">
                        Begrepen
                    </button>
                )}
            </div>
        </div>
    );
}

function Ring({ children }: { children: ReactNode }) {
    return <span className="relative inline-grid place-items-center rounded-xl ring-4 ring-rood ring-offset-2 ring-offset-white">{children}</span>;
}

function SafariBeeld() {
    return (
        <div className="w-60 overflow-hidden rounded-2xl border bg-white shadow-sm" aria-hidden>
            <div className="h-24 bg-gradient-to-b from-[#F5F4FA] to-white p-3">
                <div className="h-3 w-24 rounded bg-[#E3E0EC]" />
                <div className="mt-2 h-3 w-36 rounded bg-[#EEEBF5]" />
            </div>
            <div className="mx-3 mb-2 rounded-xl bg-[#F1F1F4] px-3 py-1.5 text-center text-[11px] text-[#5E5A78]">mebranding.nl</div>
            <div className="flex items-center justify-around border-t px-2 py-2.5 text-[#1d6ff2]">
                <ChevronLeft className="size-5" />
                <ChevronRight className="size-5 opacity-40" />
                <Ring>
                    <DeelIcoon groot />
                </Ring>
                <span className="text-lg leading-none">⧉</span>
                <MoreHorizontal className="size-5" />
            </div>
        </div>
    );
}

function DeelmenuBeeld() {
    return (
        <div className="w-64 overflow-hidden rounded-2xl border bg-white text-[12px] shadow-sm" aria-hidden>
            <div className="flex items-center justify-between gap-2 border-b px-3 py-2 text-[11px] font-semibold whitespace-nowrap">
                <span className="text-[#8A86A3]">Annuleer</span>
                <span>Beginscherm</span>
                <Ring>
                    <span className="px-1.5 py-0.5 font-bold text-[#1d6ff2]">Voeg toe</span>
                </Ring>
            </div>
            {['Kopieer', 'Voeg bladwijzer toe'].map((r) => (
                <div key={r} className="border-b px-3 py-2 text-[#4A4664]">
                    {r}
                </div>
            ))}
            <div className="p-1.5">
                <Ring>
                    <span className="flex w-56 items-center justify-between px-2 py-1.5 font-semibold">
                        Zet op beginscherm <Plus className="size-4" />
                    </span>
                </Ring>
            </div>
        </div>
    );
}

function BeginschermBeeld() {
    return (
        <div className="grid w-60 grid-cols-4 gap-3 rounded-2xl bg-gradient-to-br from-[#3a2f6b] via-aubergine to-[#5a2340] p-4" aria-hidden>
            {Array.from({ length: 7 }).map((_, n) =>
                n === 5 ? (
                    <span key={n} className="flex flex-col items-center gap-1">
                        <Ring>
                            <img src="/brand/app-icoon-192.png" alt="" className="size-10 rounded-[10px]" />
                        </Ring>
                        <span className="text-[8px] text-white">MEBRANDING</span>
                    </span>
                ) : (
                    <span key={n} className="flex flex-col items-center gap-1">
                        <span className="size-10 rounded-[10px] bg-white/20" />
                        <span className="h-1.5 w-8 rounded bg-white/25" />
                    </span>
                ),
            )}
        </div>
    );
}
