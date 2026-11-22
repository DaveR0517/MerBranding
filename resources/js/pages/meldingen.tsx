import { Head, router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { datumTijd } from '@/lib/datum';
import { gelezen, alles } from '@/routes/meldingen';

type Melding = {
    id: string;
    title: string;
    body: string;
    url: string | null;
    read: boolean;
    at: string;
};

export default function Meldingen({ notifications }: { notifications: Melding[] }) {
    const ongelezen = notifications.filter((n) => !n.read).length;

    return (
        <>
            <Head title="Meldingen" />
            <div className="mx-auto w-full max-w-3xl space-y-4 p-4">
                <div className="flex items-center justify-between gap-3">
                    <h1 className="font-heading text-2xl font-extrabold">Meldingen</h1>
                    {ongelezen > 0 && (
                        <Button variant="outline" size="sm" onClick={() => router.post(alles.url())}>
                            Alles gelezen
                        </Button>
                    )}
                </div>
                {notifications.length === 0 && (
                    <p className="rounded-xl border bg-card p-6 text-muted-foreground">Nog geen meldingen.</p>
                )}
                <ul className="space-y-2">
                    {notifications.map((n) => (
                        <li key={n.id}>
                            <button
                                type="button"
                                onClick={() => router.post(gelezen.url(n.id))}
                                className={`w-full rounded-xl border p-4 text-left transition hover:border-violet ${n.read ? 'bg-card' : 'border-violet/40 bg-violet/5'}`}
                                data-test="melding"
                            >
                                <span className="flex items-start justify-between gap-3">
                                    <span className="font-semibold">{n.title}</span>
                                    {!n.read && <span className="mt-1.5 size-2 shrink-0 rounded-full bg-rood" aria-label="Ongelezen" />}
                                </span>
                                {n.body && <span className="mt-1 block text-sm text-muted-foreground">{n.body}</span>}
                                <span className="mt-2 block text-xs text-muted-foreground">{datumTijd(n.at)}</span>
                            </button>
                        </li>
                    ))}
                </ul>
            </div>
        </>
    );
}
