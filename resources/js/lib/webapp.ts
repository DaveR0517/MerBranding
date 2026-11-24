import * as push from '@/routes/push';
import * as webapp from '@/routes/webapp';

/**
 * Webapp en pushmeldingen in de browser: service worker, het installatiemoment van Android
 * (beforeinstallprompt) en het pushabonnement van dit apparaat. Server: WebappController.
 */

type InstallPrompt = Event & {
    prompt: () => Promise<void>;
    userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>;
};

let installPrompt: InstallPrompt | null = null;
const luisteraars = new Set<() => void>();
const meld = () => luisteraars.forEach((f) => f());

/** Eén keer bij het laden: Android geeft het installatiemoment maar één keer, dus vroeg opvangen. */
export function startWebapp(): void {
    if (typeof window === 'undefined') {
        return;
    }
    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        installPrompt = e as InstallPrompt;
        meld();
    });
    window.addEventListener('appinstalled', () => {
        installPrompt = null;
        meld();
    });
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register(webapp.sw.url()).catch(() => {});
    }
}

export function opWebappWijziging(f: () => void): () => void {
    luisteraars.add(f);

    return () => luisteraars.delete(f);
}

export type Platform = 'ios' | 'android' | 'anders';

export function platform(): Platform {
    const ua = navigator.userAgent;
    // iPad met iPadOS meldt zich als Mac, maar heeft een touchscreen.
    if (/iPhone|iPad|iPod/.test(ua) || (/Macintosh/.test(ua) && navigator.maxTouchPoints > 1)) {
        return 'ios';
    }

    return /Android/.test(ua) ? 'android' : 'anders';
}

/** Draait als geïnstalleerde webapp (vanaf het beginscherm geopend). */
export function alsApp(): boolean {
    return window.matchMedia('(display-mode: standalone)').matches || (navigator as Navigator & { standalone?: boolean }).standalone === true;
}

export function telefoon(): boolean {
    return window.matchMedia('(max-width: 767px)').matches || window.matchMedia('(pointer: coarse)').matches;
}

export function kanInstalleren(): boolean {
    return installPrompt !== null;
}

/** Android: het eigen installatievenster van de browser. */
export async function installeren(): Promise<boolean> {
    if (!installPrompt) {
        return false;
    }
    const p = installPrompt;
    await p.prompt();
    const { outcome } = await p.userChoice;
    installPrompt = null;
    meld();

    return outcome === 'accepted';
}

/** Kan dit apparaat nu pushmeldingen krijgen? Op de iPhone alleen als webapp op het beginscherm. */
export function pushMogelijk(): boolean {
    return 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
}

export type PushStand = 'aan' | 'uit' | 'geweigerd' | 'niet-mogelijk';

export async function pushStand(): Promise<PushStand> {
    if (!pushMogelijk()) {
        return 'niet-mogelijk';
    }
    if (Notification.permission === 'denied') {
        return 'geweigerd';
    }
    const reg = await navigator.serviceWorker.getRegistration();
    const abo = await reg?.pushManager.getSubscription();

    return abo && Notification.permission === 'granted' ? 'aan' : 'uit';
}

function xsrf(): string {
    const m = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/);

    return m ? decodeURIComponent(m[1]) : '';
}

async function post(url: string, body: unknown): Promise<Response> {
    return fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-XSRF-TOKEN': xsrf() },
        body: JSON.stringify(body),
    });
}

function sleutelBytes(base64: string): Uint8Array {
    const padding = '='.repeat((4 - (base64.length % 4)) % 4);
    const ruw = atob((base64 + padding).replace(/-/g, '+').replace(/_/g, '/'));

    return Uint8Array.from(ruw, (c) => c.charCodeAt(0));
}

/** Vraagt toestemming en meldt dit apparaat aan. Moet vanuit een tik van de gebruiker. */
export async function meldingenAanzetten(): Promise<PushStand> {
    if (!pushMogelijk()) {
        return 'niet-mogelijk';
    }
    const toestemming = await Notification.requestPermission();
    if (toestemming !== 'granted') {
        return toestemming === 'denied' ? 'geweigerd' : 'uit';
    }
    const reg = await navigator.serviceWorker.ready;
    const { key } = await (await fetch(push.sleutel.url(), { headers: { Accept: 'application/json' }, credentials: 'same-origin' })).json();
    const abo =
        (await reg.pushManager.getSubscription()) ??
        (await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: sleutelBytes(key) as BufferSource }));
    const json = abo.toJSON();
    const antwoord = await post(push.abonneren.url(), {
        endpoint: json.endpoint,
        keys: json.keys,
        contentEncoding: (PushManager as unknown as { supportedContentEncodings?: string[] }).supportedContentEncodings?.includes('aes128gcm') ? 'aes128gcm' : 'aesgcm',
    });
    meld();

    return antwoord.ok ? 'aan' : 'uit';
}

export async function meldingenUitzetten(): Promise<void> {
    const reg = await navigator.serviceWorker.getRegistration();
    const abo = await reg?.pushManager.getSubscription();
    if (abo) {
        await post(push.opzeggen.url(), { endpoint: abo.endpoint });
        await abo.unsubscribe();
    }
    meld();
}

export async function proefmelding(): Promise<number> {
    const antwoord = await post(push.proef.url(), {});

    return antwoord.ok ? ((await antwoord.json()).verstuurd as number) : 0;
}

/* Het venster "Zet MEBRANDING op je telefoon": eenmalig, "Later" onthoudt dit apparaat; Account opent het opnieuw. */
const LATER = 'mebranding-webapp-later';

export function laterGekozen(): boolean {
    try {
        return localStorage.getItem(LATER) !== null;
    } catch {
        return false;
    }
}

export function kiesLater(): void {
    try {
        localStorage.setItem(LATER, new Date().toISOString());
    } catch {
        /* privévenster: dan komt het venster de volgende keer terug */
    }
}

export const OPEN_WEBAPP_VENSTER = 'mebranding:webapp-venster';

export function openWebappVenster(): void {
    window.dispatchEvent(new Event(OPEN_WEBAPP_VENSTER));
}
