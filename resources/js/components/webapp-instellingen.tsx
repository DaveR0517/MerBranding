import { BellRing, BellOff, Send, Smartphone } from 'lucide-react';
import { useEffect, useState } from 'react';
import {
    alsApp,
    meldingenAanzetten,
    meldingenUitzetten,
    opWebappWijziging,
    openWebappVenster,
    proefmelding,
    pushStand,
} from '@/lib/webapp';
import type { PushStand } from '@/lib/webapp';

const knop = 'inline-flex min-h-10 items-center gap-2 rounded-full border px-4 text-sm font-semibold transition hover:border-violet disabled:opacity-50';

/** Account: webapp op dit apparaat en pushmeldingen aan of uit, met een proefmelding. Hoort bij WebappVenster. */
export function WebappInstellingen() {
    const [stand, setStand] = useState<PushStand | null>(null);
    const [bezig, setBezig] = useState(false);
    const [bericht, setBericht] = useState<string | null>(null);

    useEffect(() => {
        const ververs = () => void pushStand().then(setStand);
        ververs();

        return opWebappWijziging(ververs);
    }, []);

    const doe = async (actie: () => Promise<unknown>, klaar?: string) => {
        setBezig(true);
        setBericht(null);
        try {
            await actie();
            if (klaar) {
                setBericht(klaar);
            }
        } finally {
            setBezig(false);
            setStand(await pushStand());
        }
    };

    return (
        <div className="space-y-3 text-sm" data-test="webapp-instellingen">
            <p className="text-muted-foreground">
                {alsApp() ? 'Je gebruikt MEBRANDING als app op dit apparaat.' : 'Zet MEBRANDING op je beginscherm, dan open je het met één tik.'}{' '}
                {stand === 'aan' && 'Meldingen staan aan op dit apparaat.'}
                {stand === 'uit' && 'Meldingen staan uit op dit apparaat.'}
                {stand === 'geweigerd' && 'Meldingen zijn geblokkeerd. Zet ze aan in de instellingen van je telefoon of browser.'}
                {stand === 'niet-mogelijk' && 'Op dit apparaat kunnen meldingen pas als MEBRANDING op je beginscherm staat.'}
            </p>
            <div className="flex flex-wrap gap-2">
                {!alsApp() && (
                    <button type="button" className={knop} onClick={openWebappVenster}>
                        <Smartphone className="size-4" aria-hidden /> Zet op je telefoon
                    </button>
                )}
                {stand === 'uit' && (
                    <button type="button" className={knop} disabled={bezig} onClick={() => doe(meldingenAanzetten)}>
                        <BellRing className="size-4" aria-hidden /> Meldingen aanzetten
                    </button>
                )}
                {stand === 'aan' && (
                    <>
                        <button
                            type="button"
                            className={knop}
                            disabled={bezig}
                            onClick={() => doe(proefmelding, 'Proefmelding verstuurd. Hij komt binnen een paar seconden.')}
                        >
                            <Send className="size-4" aria-hidden /> Stuur een proefmelding
                        </button>
                        <button type="button" className={knop} disabled={bezig} onClick={() => doe(meldingenUitzetten)}>
                            <BellOff className="size-4" aria-hidden /> Uitzetten op dit apparaat
                        </button>
                    </>
                )}
            </div>
            {bericht && (
                <p role="status" className="text-emerald-700 dark:text-emerald-400">
                    {bericht}
                </p>
            )}
        </div>
    );
}
