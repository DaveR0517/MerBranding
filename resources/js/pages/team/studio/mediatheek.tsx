import { Head, router } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { useState } from 'react';
import { Leeg, MeerLijst, Pagina, PaginaKop, Rij, Tabs } from '@/components/pagina';
import { RechtenWaarschuwing, UploadKnop } from '@/components/studio/bronkiezer';
import type { CreatorVideo, Media } from '@/components/studio/typen';
import { bestandsgrootte } from '@/components/studio/typen';
import { datum } from '@/lib/datum';
import mediatheek from '@/routes/team/studio/mediatheek';

type Props = { media: Media[]; creatorVideos: CreatorVideo[]; maxUploadMb: number };

const SOORTEN = { alles: 'Alles', foto: "Foto's", video: "Video's", logo: "Logo's" } as const;

/** MeStudio, Mediatheek: eigen foto's, video's en logo's, plus goedgekeurde video's van creators (met waarschuwing over rechten). */
export default function Mediatheek({ media, creatorVideos, maxUploadMb }: Props) {
    const [tab, setTab] = useState<'eigen' | 'creators'>('eigen');
    const [soort, setSoort] = useState<keyof typeof SOORTEN>('alles');
    const lijst = media.filter((m) => soort === 'alles' || m.kind === soort);

    return (
        <>
            <Head title="Mediatheek" />
            <Pagina>
                <PaginaKop
                    boven="MeStudio"
                    titel="Mediatheek"
                    omschrijving={`Foto's, video's en logo's voor de studio. Privé opgeslagen, hooguit ${maxUploadMb} MB per bestand.`}
                    acties={<UploadKnop maxUploadMb={maxUploadMb} label="Bestand uploaden" onKlaar={() => router.reload({ only: ['media'] })} />}
                    extra={
                        <Tabs
                            tabs={[
                                { waarde: 'eigen', label: 'Eigen bestanden', aantal: media.length },
                                { waarde: 'creators', label: "Video's van creators", aantal: creatorVideos.length },
                            ]}
                            actief={tab}
                            onKies={setTab}
                        />
                    }
                />

                {tab === 'eigen' ? (
                    <>
                        <div className="flex flex-wrap gap-1.5" role="group" aria-label="Soort">
                            {Object.entries(SOORTEN).map(([k, label]) => (
                                <button
                                    key={k}
                                    type="button"
                                    aria-pressed={soort === k}
                                    onClick={() => setSoort(k as keyof typeof SOORTEN)}
                                    className={`h-10 rounded-full border px-4 text-sm font-semibold ${soort === k ? 'border-violet bg-violet text-white' : 'bg-card hover:border-violet'}`}
                                >
                                    {label}
                                </button>
                            ))}
                        </div>
                        {lijst.length === 0 ? (
                            <Leeg titel="Nog niets hier">Upload foto's, video's of logo's om in je ontwerpen te gebruiken.</Leeg>
                        ) : (
                            <MeerLijst eerst={18} label="Meer bestanden" className="grid grid-cols-2 gap-3 space-y-0 sm:grid-cols-3 md:gap-4 lg:grid-cols-4 xl:grid-cols-6">
                                {lijst.map((m) => (
                                    <li key={m.id} className="min-w-0 overflow-hidden rounded-xl border bg-card">
                                        {m.kind === 'video' ? (
                                            <video src={m.url} controls muted preload="metadata" className="aspect-square w-full bg-black object-cover" />
                                        ) : (
                                            <img src={m.url} alt="" loading="lazy" className={`aspect-square w-full ${m.kind === 'logo' ? 'bg-[repeating-conic-gradient(#e5e5e5_0_25%,#fff_0_50%)] bg-[length:16px_16px] object-contain p-3' : 'bg-muted object-cover'}`} />
                                        )}
                                        <div className="flex items-center gap-1 p-2">
                                            <div className="min-w-0 flex-1">
                                                <p className="truncate text-sm font-semibold" title={m.name}>
                                                    {m.name}
                                                </p>
                                                <p className="truncate text-xs text-muted-foreground">
                                                    {bestandsgrootte(m.size)}
                                                    {m.width ? `, ${m.width} x ${m.height}` : ''}, {datum(m.created_at)}
                                                </p>
                                            </div>
                                            <button
                                                type="button"
                                                aria-label={`${m.name} verwijderen`}
                                                onClick={() => confirm(`"${m.name}" verwijderen? Ontwerpen die het gebruiken tonen dan een leeg vak.`) && router.delete(mediatheek.verwijderen.url(m.id), { preserveScroll: true })}
                                                className="grid size-9 shrink-0 place-items-center rounded-md text-muted-foreground hover:bg-muted hover:text-rood"
                                            >
                                                <Trash2 className="size-4" />
                                            </button>
                                        </div>
                                    </li>
                                ))}
                            </MeerLijst>
                        )}
                    </>
                ) : (
                    <>
                        <RechtenWaarschuwing />
                        {creatorVideos.length === 0 ? (
                            <Leeg titel="Nog geen goedgekeurde video's">Video's verschijnen hier zodra een ondernemer ze heeft goedgekeurd.</Leeg>
                        ) : (
                            <MeerLijst eerst={8} label="Meer video's">
                                {creatorVideos.map((v) => (
                                    <li key={v.id}>
                                        <Rij
                                            links={<video src={v.url} muted preload="metadata" className="size-14 rounded-lg bg-black object-cover" />}
                                            titel={v.titel ?? v.nummer}
                                            onder={`${v.creator ?? 'Creator'} bij ${v.bedrijf ?? 'onbekend bedrijf'}, ${
                                                v.gebruiksrechten.length
                                                    ? v.gebruiksrechten.map((r) => `${r.kind} tot ${r.ends_on ? datum(r.ends_on) : 'onbekend'}${r.is_proposal ? ' (VOORSTEL)' : ''}`).join(', ')
                                                    : 'geen gebruiksrechten vastgelegd'
                                            }`}
                                            rechts={
                                                <a href={`${v.url}?download=1`} className="h-10 rounded-lg border px-3 text-sm leading-10 font-semibold hover:border-violet">
                                                    Download
                                                </a>
                                            }
                                        />
                                    </li>
                                ))}
                            </MeerLijst>
                        )}
                    </>
                )}
            </Pagina>
        </>
    );
}
