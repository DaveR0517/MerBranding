import { Head } from '@inertiajs/react';

export default function GeenOmgeving() {
    return (
        <>
            <Head title="Welkom" />
            <div className="mx-auto max-w-xl p-6">
                <h1 className="font-heading text-2xl font-extrabold">Welkom bij MEBRANDING</h1>
                <p className="mt-2 text-muted-foreground">
                    Je account is nog niet gekoppeld aan een bedrijf, een creatorprofiel of het team. Neem contact op met
                    MEBRANDING, dan regelen we het.
                </p>
            </div>
        </>
    );
}
