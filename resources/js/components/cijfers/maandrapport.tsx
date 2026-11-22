import { router } from '@inertiajs/react';
import { Printer } from 'lucide-react';
import {
    Kaart,
    KaartRaster,
    Kerncijfer,
    Leeg,
    MeerLijst,
    Rij,
    Sectie,
    TweeKolommen,
} from '@/components/pagina';
import { StatusBadge } from '@/components/samenwerking/status-badge';
import { Button } from '@/components/ui/button';
import type { Maand, Maandrapport, Metrieken } from '@/types/cijfers';
import { BronLabel, getal, PlatformTegels, platformNaam } from './cijfers';

/** Het maandrapport zoals team en ondernemer het zien, in de bouwdoos. De afdrukbare versie is een losse pagina (Blade). */
export function MaandrapportWeergave({
    r,
    maanden,
    metrics,
    maandUrl,
    afdrukUrl,
}: {
    r: Maandrapport;
    maanden: Maand[];
    metrics: Metrieken;
    maandUrl: (maand: string) => string;
    afdrukUrl: string;
}) {
    const beste = r.publicaties.find((p) => p.metrics.views);

    return (
        <>
            <div className="flex flex-wrap items-end gap-2">
                <label className="text-sm max-sm:flex-1">
                    <span className="mb-1 block font-semibold">Maand</span>
                    <select
                        className="h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                        value={r.maand}
                        onChange={(e) => router.visit(maandUrl(e.target.value))}
                        data-test="maand"
                    >
                        {maanden.map((m) => (
                            <option key={m.value} value={m.value}>
                                {m.label}
                            </option>
                        ))}
                    </select>
                </label>
                <Button
                    asChild
                    variant="outline"
                    className="h-10 max-sm:flex-1"
                >
                    <a
                        href={afdrukUrl}
                        target="_blank"
                        rel="noopener"
                        data-test="afdrukken"
                    >
                        <Printer /> Afdrukbare versie
                    </a>
                </Button>
            </div>

            <Sectie
                titel={`${r.bedrijf.name}, ${r.maand_label}`}
                omschrijving={
                    <>
                        {r.pakket && <>Pakket {r.pakket}. </>}
                        {r.tellingen.live}{' '}
                        {r.tellingen.live === 1 ? 'post' : 'posts'} live
                        {beste && (
                            <>
                                , de beste: {beste.title} van {beste.creator}{' '}
                                met {getal(beste.metrics.views?.value)} views op{' '}
                                {platformNaam(beste.platform)}
                            </>
                        )}
                        .{' '}
                        {r.lopend
                            ? 'Deze maand loopt nog: dit is een tussenstand.'
                            : ''}{' '}
                        Cijfers jonger dan 14 dagen zijn voorlopig.
                    </>
                }
            >
                <KaartRaster kolommen={4} className="grid-cols-2">
                    <Kerncijfer
                        label="Aangevraagd"
                        waarde={r.tellingen.aangevraagd}
                    />
                    <Kerncijfer label="Live gegaan" waarde={r.tellingen.live} />
                    <Kerncijfer
                        label="Afgerond"
                        waarde={r.tellingen.afgerond}
                    />
                    <Kerncijfer
                        label="Creators"
                        waarde={r.tellingen.creators}
                    />
                </KaartRaster>
            </Sectie>

            <Sectie
                titel="Cijfers per platform"
                omschrijving="Per platform opgeteld, niet over platforms heen: elk platform telt views anders."
            >
                {r.platforms.length === 0 ? (
                    <Leeg titel="Geen posts">
                        Deze maand ging er geen post live.
                    </Leeg>
                ) : (
                    <PlatformTegels platforms={r.platforms} metrics={metrics} />
                )}
            </Sectie>

            <TweeKolommen
                hoofd={
                    <>
                        <Sectie titel="Alle posts op views">
                            {r.publicaties.length === 0 ? (
                                <Leeg titel="Nog geen posts">
                                    Deze maand ging er geen post live.
                                </Leeg>
                            ) : (
                                <MeerLijst>
                                    {r.publicaties.map((p, i) => (
                                        <li key={p.id}>
                                            <Rij
                                                links={
                                                    <span className="inline-block w-6 font-heading font-extrabold text-violet tabular-nums">
                                                        {i + 1}
                                                    </span>
                                                }
                                                titel={p.title}
                                                onder={
                                                    <span className="inline-flex items-center gap-2">
                                                        {p.creator} ·{' '}
                                                        {platformNaam(
                                                            p.platform,
                                                        )}
                                                        <BronLabel
                                                            bronnen={p.sources}
                                                            schatting={
                                                                p.is_estimate
                                                            }
                                                        />
                                                    </span>
                                                }
                                                rechts={
                                                    <span className="text-right font-heading font-bold tabular-nums">
                                                        {p.metrics.views
                                                            ? getal(
                                                                  p.metrics
                                                                      .views
                                                                      .value,
                                                              )
                                                            : 'nog geen'}
                                                        <span className="block text-xs font-normal text-muted-foreground">
                                                            views
                                                        </span>
                                                    </span>
                                                }
                                            />
                                        </li>
                                    ))}
                                </MeerLijst>
                            )}
                        </Sectie>
                    </>
                }
                zij={
                    <>
                        <Sectie titel="Tegoed">
                            <Kaart>
                                <dl className="grid grid-cols-2 gap-3">
                                    {(
                                        [
                                            ['Toegekend', r.tegoed.toegekend],
                                            ['Verbruikt', r.tegoed.verbruikt],
                                            ['Terug', r.tegoed.vrijgegeven],
                                            [
                                                r.lopend
                                                    ? 'Saldo nu'
                                                    : 'Saldo einde maand',
                                                r.tegoed.saldo_einde,
                                            ],
                                        ] as const
                                    ).map(([l, w]) => (
                                        <div key={l}>
                                            <dt className="text-xs text-muted-foreground">
                                                {l}
                                            </dt>
                                            <dd className="font-heading text-xl font-extrabold tabular-nums">
                                                {w}{' '}
                                                <span className="text-xs font-normal text-muted-foreground">
                                                    punten
                                                </span>
                                            </dd>
                                        </div>
                                    ))}
                                </dl>
                                <p className="mt-3 text-xs text-muted-foreground">
                                    Ongebruikt tegoed aan het einde van de maand{' '}
                                    {r.ongebruikt_tegoed === 'vervallen'
                                        ? 'vervalt'
                                        : 'gaat mee naar de volgende maand'}
                                    . Dit is een voorstel, nog niet besloten.
                                </p>
                            </Kaart>
                        </Sectie>
                        <Sectie titel="Samenwerkingen">
                            {r.samenwerkingen.length === 0 ? (
                                <Leeg titel="Geen samenwerkingen">
                                    Geen samenwerkingen in deze maand.
                                </Leeg>
                            ) : (
                                <MeerLijst eerst={6}>
                                    {r.samenwerkingen.map((c) => (
                                        <li key={c.id}>
                                            <Rij
                                                titel={c.title}
                                                onder={`${c.location} · ${c.creator ?? 'nog geen creator'}`}
                                                rechts={
                                                    <StatusBadge
                                                        status={c.status}
                                                    />
                                                }
                                            />
                                        </li>
                                    ))}
                                </MeerLijst>
                            )}
                        </Sectie>
                    </>
                }
            />
        </>
    );
}
