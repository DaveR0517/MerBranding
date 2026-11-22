import { Head } from '@inertiajs/react';
import { Pagina, PaginaKop } from '@/components/pagina';

export default function GeenOmgeving() {
    return (
        <>
            <Head title="Welkom" />
            <Pagina smal>
                <PaginaKop
                    titel="Welkom bij MEBRANDING"
                    omschrijving="Je account is nog niet gekoppeld aan een bedrijf, een creatorprofiel of het team. Neem contact op met MEBRANDING, dan regelen we het."
                />
            </Pagina>
        </>
    );
}
