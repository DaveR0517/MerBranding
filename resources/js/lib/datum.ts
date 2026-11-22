/** Datums in het Nederlands, in de tijdzone van Nederland. */
const tz = 'Europe/Amsterdam';

export function datum(iso: string | null): string {
    return iso ? new Date(iso).toLocaleDateString('nl-NL', { timeZone: tz, weekday: 'short', day: 'numeric', month: 'long' }) : '';
}

export function datumTijd(iso: string | null): string {
    return iso
        ? new Date(iso).toLocaleString('nl-NL', { timeZone: tz, weekday: 'short', day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' })
        : '';
}

export function euro(centen: number): string {
    return (centen / 100).toLocaleString('nl-NL', { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 });
}
