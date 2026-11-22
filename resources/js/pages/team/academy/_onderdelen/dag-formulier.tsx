import { useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { opslaan } from '@/routes/team/academy/cursusdagen';
import { bijwerken } from '@/routes/team/academy/cursusdag';
import type { Dag } from '../../../academy/_onderdelen/types';
import { Veld, invoer } from '../../_onderdelen/basis';

type Standaard = { price_cents: number; max_participants: number; exam_required: boolean };

/** Cursusdag plannen of bewerken. Prijs (VOORSTEL € 295) en praktijkexamen als voorwaarde (OPEN) zijn per dag instelbaar. */
export function DagFormulier({ dag, standaard }: { dag?: Dag; standaard?: Standaard }) {
    const form = useForm({
        title: dag?.title ?? 'Basis: video maken met je telefoon',
        date: dag?.date ?? '',
        starts_at: dag?.starts_at ?? '10:00',
        ends_at: dag?.ends_at ?? '16:00',
        location_name: dag?.location_name ?? '',
        address: dag?.address ?? '',
        city: dag?.city ?? '',
        max_participants: String(dag?.max_participants ?? standaard?.max_participants ?? 16),
        teacher_name: dag?.teacher_name ?? '',
        price_euro: String((dag?.price_cents ?? standaard?.price_cents ?? 29500) / 100),
        exam_required: dag?.exam_required ?? standaard?.exam_required ?? true,
        notes: dag?.notes ?? '',
    });
    type Tekstveld = Exclude<keyof typeof form.data, 'exam_required'>;

    const veld = (naam: Tekstveld, label: string, opties: { type?: string; verplicht?: boolean; hint?: string; className?: string } = {}) => (
        <Veld label={`${label}${opties.verplicht ? '' : ' (optioneel)'}`} htmlFor={`d-${dag?.id ?? 'n'}-${naam}`} fout={form.errors[naam]} hint={opties.hint} className={opties.className}>
            <input
                id={`d-${dag?.id ?? 'n'}-${naam}`}
                type={opties.type ?? 'text'}
                required={opties.verplicht}
                value={form.data[naam]}
                onChange={(e) => form.setData(naam, e.target.value)}
                className={invoer}
            />
        </Veld>
    );

    return (
        <form
            className="grid gap-3 sm:grid-cols-2"
            onSubmit={(e) => {
                e.preventDefault();
                if (dag) {
                    form.put(bijwerken.url(dag.id), { preserveScroll: true });
                } else {
                    form.post(opslaan.url(), { preserveScroll: true });
                }
            }}
        >
            {veld('title', 'Titel', { verplicht: true, className: 'sm:col-span-2' })}
            {veld('date', 'Datum', { type: 'date', verplicht: true })}
            <div className="grid grid-cols-2 gap-2">
                {veld('starts_at', 'Van', { type: 'time', verplicht: true })}
                {veld('ends_at', 'Tot', { type: 'time', verplicht: true })}
            </div>
            {veld('location_name', 'Locatie', { verplicht: true, hint: 'Bijvoorbeeld een klant: Hotel Wadzicht' })}
            {veld('city', 'Plaats', { verplicht: true })}
            {veld('address', 'Adres', { className: 'sm:col-span-2' })}
            {veld('max_participants', 'Maximaal aantal deelnemers', { type: 'number', verplicht: true, hint: dag ? 'Meer plekken: de wachtlijst schuift direct door.' : undefined })}
            {veld('price_euro', 'Prijs in euro, incl. btw', { type: 'number', verplicht: true, hint: 'VOORSTEL: € 295 (open punt)' })}
            {veld('teacher_name', 'Docent', { hint: 'Open punt: wie lesgeeft' })}
            <label className="flex items-start gap-2 self-end pb-2 text-sm">
                <input type="checkbox" className="mt-0.5 size-4 accent-violet" checked={form.data.exam_required} onChange={(e) => form.setData('exam_required', e.target.checked)} />
                <span>
                    <span className="font-semibold">Praktijkexamen als voorwaarde</span>
                    <span className="block text-xs text-muted-foreground">Open punt. Uit: het certificaat gaat uit bij Dag afsluiten.</span>
                </span>
            </label>
            <Veld label="Wat neem je mee, parkeren (optioneel)" htmlFor={`d-${dag?.id ?? 'n'}-notes`} fout={form.errors.notes} className="sm:col-span-2">
                <textarea id={`d-${dag?.id ?? 'n'}-notes`} rows={2} value={form.data.notes} onChange={(e) => form.setData('notes', e.target.value)} className={`${invoer} h-auto py-2`} />
            </Veld>
            <div className="sm:col-span-2">
                <Button type="submit" disabled={form.processing} className="bg-violet hover:bg-violet/90">{dag ? 'Opslaan' : 'Cursusdag plannen'}</Button>
            </div>
        </form>
    );
}
