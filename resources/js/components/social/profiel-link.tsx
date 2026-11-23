import type { SVGProps } from 'react';

export type SociaalPlatform =
    | 'instagram'
    | 'tiktok'
    | 'youtube'
    | 'facebook'
    | 'snapchat';

const NAMEN: Record<SociaalPlatform, string> = {
    instagram: 'Instagram',
    tiktok: 'TikTok',
    youtube: 'YouTube',
    facebook: 'Facebook',
    snapchat: 'Snapchat',
};

/** Gebruikersnaam opschonen: zonder @, spaties of volledige link. */
export function schoneHandle(invoer: string): string {
    const tekst = invoer.trim();
    const uitLink = tekst.match(
        /(?:instagram\.com|tiktok\.com\/@?|youtube\.com\/@?|facebook\.com|snapchat\.com\/add)\/?@?([A-Za-z0-9._-]+)/i,
    );

    return (uitLink ? uitLink[1] : tekst)
        .replace(/^@+/, '')
        .replace(/\s+/g, '')
        .replace(/\/+$/, '');
}

/** Openbaar profieladres op het platform. */
export function profielUrl(
    platform: SociaalPlatform,
    invoer: string,
): string | null {
    const handle = schoneHandle(invoer);
    if (!handle) {
        return null;
    }

    return {
        instagram: `https://www.instagram.com/${handle}/`,
        tiktok: `https://www.tiktok.com/@${handle}`,
        youtube: `https://www.youtube.com/@${handle}`,
        facebook: `https://www.facebook.com/${handle}`,
        snapchat: `https://www.snapchat.com/add/${handle}`,
    }[platform];
}

function InstagramIcoon(props: SVGProps<SVGSVGElement>) {
    return (
        <svg viewBox="0 0 24 24" aria-hidden {...props}>
            <defs>
                <linearGradient id="ig-verloop" x1="0" y1="1" x2="1" y2="0">
                    <stop offset="0" stopColor="#FEDA75" />
                    <stop offset="0.3" stopColor="#FA7E1E" />
                    <stop offset="0.6" stopColor="#D62976" />
                    <stop offset="1" stopColor="#4F5BD5" />
                </linearGradient>
            </defs>
            <rect
                x="2"
                y="2"
                width="20"
                height="20"
                rx="6"
                fill="url(#ig-verloop)"
            />
            <rect
                x="6.25"
                y="6.25"
                width="11.5"
                height="11.5"
                rx="3.6"
                fill="none"
                stroke="#fff"
                strokeWidth="1.8"
            />
            <circle
                cx="12"
                cy="12"
                r="2.8"
                fill="none"
                stroke="#fff"
                strokeWidth="1.8"
            />
            <circle cx="16.3" cy="7.7" r="1.05" fill="#fff" />
        </svg>
    );
}

function TikTokIcoon(props: SVGProps<SVGSVGElement>) {
    return (
        <svg viewBox="0 0 24 24" aria-hidden {...props}>
            <rect x="2" y="2" width="20" height="20" rx="6" fill="#111" />
            <path
                d="M14.2 5.5c.3 1.6 1.4 2.8 3 3v2.2c-1.1 0-2.1-.3-3-.9v4.6a4.1 4.1 0 1 1-4.1-4.1h.5v2.3a1.9 1.9 0 1 0 1.4 1.8V5.5h2.2Z"
                fill="#fff"
            />
            <path
                d="M14.2 5.5h-.6c.3 1.7 1.5 3 3.6 3.2V8.5c-1.6-.2-2.7-1.4-3-3Z"
                fill="#25F4EE"
                opacity=".8"
            />
        </svg>
    );
}

function AlgemeenIcoon({
    letter,
    ...props
}: SVGProps<SVGSVGElement> & { letter: string }) {
    return (
        <svg viewBox="0 0 24 24" aria-hidden {...props}>
            <rect x="2" y="2" width="20" height="20" rx="6" fill="#6D6794" />
            <text
                x="12"
                y="16"
                textAnchor="middle"
                fontSize="10"
                fontWeight="700"
                fill="#fff"
                fontFamily="Montserrat, sans-serif"
            >
                {letter}
            </text>
        </svg>
    );
}

/** Icoon van het platform plus de @-naam als link naar het openbare profiel (opent in een nieuw tabblad). */
export function ProfielLink({
    platform,
    handle,
    className = '',
}: {
    platform: SociaalPlatform;
    handle: string;
    className?: string;
}) {
    const url = profielUrl(platform, handle);
    const naam = schoneHandle(handle);
    const icoon =
        platform === 'instagram' ? (
            <InstagramIcoon className="size-5 shrink-0" />
        ) : platform === 'tiktok' ? (
            <TikTokIcoon className="size-5 shrink-0" />
        ) : (
            <AlgemeenIcoon
                letter={NAMEN[platform][0]}
                className="size-5 shrink-0"
            />
        );

    if (!url) {
        return null;
    }

    return (
        <a
            href={url}
            target="_blank"
            rel="noopener noreferrer"
            className={`inline-flex ${compact ? '' : 'min-h-10'} items-center gap-2 align-middle rounded-lg font-semibold text-foreground hover:text-violet hover:underline ${className}`}
            aria-label={`${NAMEN[platform]}-profiel @${naam} openen (nieuw tabblad)`}
        >
            {icoon}
            <span className="truncate">@{naam}</span>
        </a>
    );
}
