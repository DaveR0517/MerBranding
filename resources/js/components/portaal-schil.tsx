import { Link, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { isActief, navigatie } from '@/lib/portaal';
import { terug } from '@/routes';
import type { Auth } from '@/types/auth';

/** Onderbalk op de telefoon (de zijbalk is daar verborgen). */
export function OnderBalk() {
    const page = usePage();
    const { auth, unread } = page.props as unknown as { auth: Auth; unread: number };
    const items = navigatie(auth.portal, auth.permissions).filter((i) => i.mobile);

    return (
        <nav
            aria-label="Hoofdmenu"
            className="fixed inset-x-0 bottom-0 z-40 flex border-t border-white/10 bg-aubergine-dark pb-[env(safe-area-inset-bottom)] md:hidden"
        >
            {items.map((item) => {
                const actief = isActief(item.href, page.url);

                return (
                    <Link
                        key={item.title}
                        href={item.href}
                        aria-current={actief ? 'page' : undefined}
                        className={`relative flex flex-1 flex-col items-center gap-1 py-2 text-[11px] font-semibold ${actief ? 'text-white' : 'text-white/65'}`}
                    >
                        <item.icon className="size-5" />
                        {item.title}
                        {item.title === 'Meldingen' && unread > 0 && (
                            <span className="absolute top-1 right-[calc(50%-18px)] min-w-4 rounded-full bg-rood px-1 text-[10px] leading-4 text-white">
                                {unread}
                            </span>
                        )}
                    </Link>
                );
            })}
        </nav>
    );
}

/** Berichtbalk na een actie ("Je aanvraag is verstuurd"). */
export function Flash() {
    const { flash } = usePage().props as unknown as { flash: { melding: string | null } };
    const [zichtbaar, setZichtbaar] = useState<string | null>(null);

    useEffect(() => {
        if (flash?.melding) {
            setZichtbaar(flash.melding);
            const t = setTimeout(() => setZichtbaar(null), 6000);

            return () => clearTimeout(t);
        }
    }, [flash]);

    if (!zichtbaar) {
        return null;
    }

    return (
        <div
            role="status"
            className="fixed inset-x-4 bottom-20 z-50 mx-auto max-w-md rounded-lg bg-aubergine-dark px-4 py-3 text-sm text-white shadow-lg md:bottom-6"
        >
            {zichtbaar}
        </div>
    );
}

/** Balk bovenaan als een Super Admin is ingelogd als iemand anders. */
export function InloggenAlsBalk() {
    const { auth } = usePage().props as unknown as { auth: Auth };

    if (!auth.impersonating) {
        return null;
    }

    return (
        <div className="flex items-center justify-between gap-3 bg-rood px-4 py-2 text-sm text-white">
            <span>
                Je bekijkt het platform als <strong>{auth.user.name}</strong>.
            </span>
            <button
                type="button"
                onClick={() => router.post(terug.url())}
                className="rounded bg-white/20 px-3 py-1 font-semibold hover:bg-white/30"
            >
                Terug naar je eigen account
            </button>
        </div>
    );
}
