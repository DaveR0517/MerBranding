import { Head, router } from '@inertiajs/react';
import { Leeg, MeerLijst, Pagina, PaginaKop } from '@/components/pagina';
import { Button } from '@/components/ui/button';
import { datumTijd } from '@/lib/datum';
import { cn } from '@/lib/utils';
import { gelezen, alles } from '@/routes/meldingen';

type Melding = {
    id: string;
    title: string;
    body: string;
    url: string | null;
    read: boolean;
    at: string;
};

export default function Meldingen({
    notifications,
}: {
    notifications: Melding[];
}) {
    const ongelezen = notifications.filter((n) => !n.read).length;

    return (
        <>
            <Head title="Meldingen" />
            <Pagina smal>
                <PaginaKop
                    titel="Meldingen"
                    omschrijving={
                        ongelezen > 0
                            ? `${ongelezen} ongelezen`
                            : 'Alles gelezen'
                    }
                    acties={
                        ongelezen > 0 && (
                            <Button
                                variant="outline"
                                className="min-h-10"
                                onClick={() => router.post(alles.url())}
                            >
                                Alles gelezen
                            </Button>
                        )
                    }
                />
                {notifications.length === 0 ? (
                    <Leeg titel="Nog geen meldingen" />
                ) : (
                    <MeerLijst eerst={10}>
                        {notifications.map((n) => (
                            <li key={n.id}>
                                <button
                                    type="button"
                                    onClick={() =>
                                        router.post(gelezen.url(n.id))
                                    }
                                    className={cn(
                                        'flex w-full items-start gap-3 rounded-xl border px-3 py-2.5 text-left transition hover:border-violet md:px-4',
                                        n.read
                                            ? 'bg-card'
                                            : 'border-violet/40 bg-violet/5',
                                    )}
                                    data-test="melding"
                                >
                                    <span className="min-w-0 flex-1">
                                        <span className="block font-semibold">
                                            {n.title}
                                        </span>
                                        {n.body && (
                                            <span className="block text-sm text-muted-foreground">
                                                {n.body}
                                            </span>
                                        )}
                                    </span>
                                    <span className="flex shrink-0 items-center gap-2 pt-0.5">
                                        <span className="text-xs text-muted-foreground tabular-nums">
                                            {datumTijd(n.at)}
                                        </span>
                                        {!n.read && (
                                            <span
                                                className="size-2 rounded-full bg-rood"
                                                aria-label="Ongelezen"
                                            />
                                        )}
                                    </span>
                                </button>
                            </li>
                        ))}
                    </MeerLijst>
                )}
            </Pagina>
        </>
    );
}
