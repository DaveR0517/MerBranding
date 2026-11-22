import { useForm } from '@inertiajs/react';
import { Upload } from 'lucide-react';
import { useState } from 'react';
import { indienen } from '@/routes/creator';
import type { SamenwerkingVol } from '@/types/samenwerking';
import { Fout, Uitkomst, groteKnop, veld } from './opmaak';

/** Zelfde regel als Samenwerking::controleerReclame op de server: het bijschrift begint met de vermelding. */
export function bijschriftGoed(bijschrift: string): boolean {
    const begin = bijschrift.trimStart().toLowerCase();

    return (
        begin.startsWith('advertentie') ||
        begin.startsWith('betaalde samenwerking')
    );
}

/** Video aanleveren met bijschrift en de twee verplichte bevestigingen van de reclamevermelding. */
export function UploadFormulier({
    samenwerking: s,
    maxMb,
}: {
    samenwerking: SamenwerkingVol;
    maxMb: number;
}) {
    const voorvoegsel = `Betaalde samenwerking met ${s.company}. `;
    const form = useForm<{
        video: File | null;
        caption: string;
        label_platform: boolean;
        label_beeld: boolean;
    }>({ video: null, caption: '', label_platform: false, label_beeld: false });
    const [clientFout, setClientFout] = useState<string | null>(null);
    const fouten = form.errors as Record<string, string | undefined>;
    const versie = s.versions.length + 1;
    const captionGoed = bijschriftGoed(form.data.caption);
    const teGroot =
        !!form.data.video && form.data.video.size > maxMb * 1024 * 1024;

    function verstuur(e: React.FormEvent) {
        e.preventDefault();
        const mist = [
            !form.data.video && 'kies je video',
            teGroot && `je video is groter dan ${maxMb} MB`,
            !captionGoed &&
                'laat je bijschrift beginnen met "Advertentie" of "Betaalde samenwerking"',
            !form.data.label_platform &&
                'bevestig het label op Instagram of TikTok',
            !form.data.label_beeld &&
                'bevestig de vermelding als tekst in beeld',
        ].filter(Boolean);

        if (mist.length > 0) {
            setClientFout(`Nog niet compleet: ${mist.join('; ')}.`);

            return;
        }

        setClientFout(null);
        form.post(indienen.url(s.id), {
            forceFormData: true,
            preserveScroll: true,
        });
    }

    return (
        <form onSubmit={verstuur} className="space-y-5" noValidate>
            <div>
                <label
                    htmlFor="video"
                    className="mb-1 block text-sm font-semibold"
                >
                    Je video (versie {versie})
                </label>
                <label
                    htmlFor="video"
                    className="flex min-h-24 cursor-pointer flex-col items-center justify-center gap-1 rounded-xl border-2 border-dashed border-violet/50 bg-violet/5 p-4 text-center hover:bg-violet/10"
                >
                    <Upload className="size-6 text-violet" aria-hidden />
                    <span className="font-semibold break-all text-violet">
                        {form.data.video
                            ? form.data.video.name
                            : 'Kies een video'}
                    </span>
                    <span className="text-xs text-muted-foreground">
                        {form.data.video
                            ? `${(form.data.video.size / 1024 / 1024).toFixed(1)} MB`
                            : `Staand 9:16, mp4, mov of webm, maximaal ${maxMb} MB`}
                    </span>
                </label>
                <input
                    id="video"
                    name="video"
                    type="file"
                    accept="video/*"
                    className="sr-only"
                    onChange={(e) =>
                        form.setData('video', e.target.files?.[0] ?? null)
                    }
                />
                {teGroot && (
                    <div className="mt-2">
                        <Fout tekst={`Deze video is groter dan ${maxMb} MB.`} />
                    </div>
                )}
                <div className="mt-2">
                    <Fout tekst={fouten.video} />
                </div>
            </div>

            <div>
                <label
                    htmlFor="caption"
                    className="mb-1 block text-sm font-semibold"
                >
                    Bijschrift
                </label>
                <p
                    id="caption-hulp"
                    className="mb-2 text-sm text-muted-foreground"
                >
                    Begin met <strong>Advertentie</strong> of{' '}
                    <strong>Betaalde samenwerking met {s.company}</strong>.
                    Alleen #adv is niet genoeg. Dit bijschrift gebruik je straks
                    ook bij het plaatsen.
                </p>
                <textarea
                    id="caption"
                    name="caption"
                    rows={5}
                    maxLength={2200}
                    value={form.data.caption}
                    onChange={(e) => form.setData('caption', e.target.value)}
                    className={veld}
                    aria-describedby="caption-hulp caption-controle"
                    aria-invalid={!!form.data.caption && !captionGoed}
                />
                {!form.data.caption
                    .toLowerCase()
                    .trimStart()
                    .startsWith('betaalde samenwerking') && (
                    <button
                        type="button"
                        onClick={() =>
                            form.setData(
                                'caption',
                                voorvoegsel + form.data.caption.trimStart(),
                            )
                        }
                        className="mt-2 inline-flex min-h-11 items-center rounded-xl border-2 border-violet px-4 text-sm font-semibold text-violet hover:bg-violet/5"
                    >
                        Zet "Betaalde samenwerking met {s.company}." vooraan
                    </button>
                )}
                <div id="caption-controle" className="mt-2" aria-live="polite">
                    {form.data.caption.trim() !== '' &&
                        (captionGoed ? (
                            <Uitkomst goed>
                                Geslaagd: je bijschrift begint met de
                                reclamevermelding.
                            </Uitkomst>
                        ) : (
                            <Uitkomst goed={false}>
                                Nog niet goed: je bijschrift moet beginnen met
                                "Advertentie" of "Betaalde samenwerking". Een
                                #adv ergens in de tekst telt niet. Gebruik de
                                knop hierboven.
                            </Uitkomst>
                        ))}
                </div>
                <div className="mt-2 space-y-2">
                    <Fout tekst={fouten.caption} />
                    <Fout tekst={fouten.bijschrift} />
                </div>
            </div>

            <fieldset className="space-y-2">
                <legend className="mb-1 text-sm font-semibold">
                    Bevestig de reclamevermelding
                </legend>
                <Vinkje
                    naam="label_platform"
                    aan={form.data.label_platform}
                    zet={(v) => form.setData('label_platform', v)}
                    tekst="Ik zet het label Betaalde samenwerking aan op Instagram of de schakelaar commerciële content op TikTok"
                />
                <Vinkje
                    naam="label_beeld"
                    aan={form.data.label_beeld}
                    zet={(v) => form.setData('label_beeld', v)}
                    tekst="De vermelding staat ook als tekst in beeld"
                />
                <Fout tekst={fouten.label_platform} />
                <Fout tekst={fouten.label_beeld} />
            </fieldset>

            <Fout tekst={clientFout} />

            {form.progress && (
                <div aria-live="polite">
                    <div className="mb-1 flex justify-between text-sm font-semibold">
                        <span>Uploaden</span>
                        <span>
                            {Math.round(form.progress.percentage ?? 0)}%
                        </span>
                    </div>
                    <progress
                        value={form.progress.percentage ?? 0}
                        max={100}
                        className="h-3 w-full overflow-hidden rounded-full [&::-moz-progress-bar]:bg-violet [&::-webkit-progress-bar]:bg-muted [&::-webkit-progress-value]:bg-violet"
                    />
                </div>
            )}

            <button
                type="submit"
                className={groteKnop}
                disabled={form.processing}
            >
                {form.processing
                    ? 'Bezig met uploaden...'
                    : `Versie ${versie} indienen`}
            </button>
            <p className="text-sm text-muted-foreground">
                Het team controleert je video dezelfde werkdag. De ondernemer
                kijkt mee met een versie met watermerk.
            </p>
        </form>
    );
}

function Vinkje({
    naam,
    aan,
    zet,
    tekst,
}: {
    naam: string;
    aan: boolean;
    zet: (v: boolean) => void;
    tekst: string;
}) {
    return (
        <label className="flex cursor-pointer items-start gap-3 rounded-xl border-2 border-border p-3 has-[:checked]:border-violet has-[:checked]:bg-violet/5">
            <input
                type="checkbox"
                name={naam}
                checked={aan}
                onChange={(e) => zet(e.target.checked)}
                className="mt-0.5 size-5 shrink-0 accent-violet"
                required
            />
            <span className="text-sm">{tekst}</span>
        </label>
    );
}
