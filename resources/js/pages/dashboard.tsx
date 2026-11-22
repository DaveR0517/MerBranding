import { Head, usePage } from '@inertiajs/react';
import { dashboard } from '@/routes';

export default function Dashboard() {
    const { auth } = usePage().props;
    const voornaam = auth.user.name.split(' ')[0];

    return (
        <>
            <Head title="Overzicht" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <section className="rounded-xl bg-brand-gradient p-6 text-white">
                    <h1 className="font-heading text-2xl font-extrabold">
                        Welkom, {voornaam}
                    </h1>
                    <p className="mt-2 max-w-prose text-white/90">
                        Het fundament van het MEBRANDING-platform staat. Hier
                        komen straks de werkvoorraad, samenwerkingen en
                        instellingen, fase voor fase.
                    </p>
                </section>
                <section className="grid gap-4 md:grid-cols-3">
                    {[
                        ['Website', 'Aanmeldingen van ondernemers en creators komen binnen en worden gemaild naar het team.'],
                        ['Demo', 'De testversie staat los op demo.mebranding.nl voor gesprekken.'],
                        ['Volgende stap', 'Fase 1: accounts, bedrijven, creators screenen, team en instellingen.'],
                    ].map(([titel, tekst]) => (
                        <div key={titel} className="rounded-xl border bg-card p-5">
                            <h2 className="font-heading font-bold">{titel}</h2>
                            <p className="mt-1 text-sm text-muted-foreground">
                                {tekst}
                            </p>
                        </div>
                    ))}
                </section>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Overzicht',
            href: dashboard(),
        },
    ],
};
