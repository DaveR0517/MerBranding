import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { LifeBuoy, Pencil, Plus } from 'lucide-react';
import { useState } from 'react';
import type { HelpProps } from '@/components/communicatie/help-scherm';
import { HelpScherm } from '@/components/communicatie/help-scherm';
import { tekstvak } from '@/components/communicatie/opmaak';
import type { Artikel } from '@/components/communicatie/types';
import { Button } from '@/components/ui/button';
import communicatie from '@/routes/team/communicatie';
import type { Auth } from '@/types/auth';
import { PaginaKop } from '@/components/pagina';
import { Paneel } from '../_onderdelen/basis';

type Props = HelpProps & { beheer?: { audiences: string[] } };

const doelgroepNaam: Record<string, string> = { ondernemer: 'Ondernemers', creator: 'Creators', team: 'Team' };

/** Helpcentrum voor het team. Met het recht support.beheren: alle artikelen, en ze toevoegen of bewerken. */
export default function Help(props: Props) {
    const { auth } = usePage().props as unknown as { auth: Auth };
    const magSupport = auth.is_super_admin || auth.permissions.includes('support.beheren');
    const [bewerk, setBewerk] = useState<Artikel | 'nieuw' | null>(null);

    return (
        <>
            <Head title="Help" />
            <HelpScherm
                {...props}
                kop={
                    <PaginaKop
                        titel="Help"
                        omschrijving={props.beheer ? 'Alle artikelen van het helpcentrum. Ondernemers en creators zien alleen de artikelen voor hun doelgroep.' : 'Antwoord op de meest gestelde vragen.'}
                    />
                }
                helpUrl={communicatie.help.url()}
                stellenUrl={communicatie.help.stellen.url()}
                vraagUrl={(id) => communicatie.help.vraag.url(id)}
                zonderVragen={!!props.beheer}
                extra={
                    <>
                        {magSupport && (
                            <Paneel titel="Vragen van ondernemers en creators" sub="Beantwoord ze in de werkbak Support.">
                                <Link href={communicatie.support.url()} className="inline-flex items-center gap-2 rounded-md bg-violet px-3 py-2 text-sm font-semibold text-white">
                                    <LifeBuoy className="size-4" aria-hidden /> Naar de werkbak Support
                                </Link>
                            </Paneel>
                        )}
                        {props.beheer &&
                            (bewerk ? (
                                <ArtikelFormulier key={bewerk === 'nieuw' ? 'nieuw' : bewerk.id} artikel={bewerk === 'nieuw' ? null : bewerk} doelgroepen={props.beheer.audiences} sluit={() => setBewerk(null)} />
                            ) : (
                                <Button onClick={() => setBewerk('nieuw')} className="w-full bg-violet hover:bg-violet/90" data-test="nieuw-artikel">
                                    <Plus className="size-4" aria-hidden /> Nieuw artikel
                                </Button>
                            ))}
                    </>
                }
                artikelActie={
                    props.beheer
                        ? (a) => (
                              <div className="flex flex-wrap items-center gap-2 border-t pt-3 text-xs text-muted-foreground">
                                  <span>Voor: {(a.audiences ?? []).map((d) => doelgroepNaam[d] ?? d).join(', ')}</span>
                                  <Button variant="outline" size="sm" className="ml-auto" onClick={() => setBewerk(a)}>
                                      <Pencil className="size-3.5" aria-hidden /> Bewerken
                                  </Button>
                              </div>
                          )
                        : undefined
                }
            />
        </>
    );
}

function ArtikelFormulier({ artikel, doelgroepen, sluit }: { artikel: Artikel | null; doelgroepen: string[]; sluit: () => void }) {
    const form = useForm({
        title: artikel?.title ?? '',
        category: artikel?.category ?? '',
        body: artikel?.body ?? '',
        audiences: artikel?.audiences ?? ['ondernemer', 'creator'],
        position: String(artikel?.position ?? 100),
    });

    const opslaan = (e: React.FormEvent) => {
        e.preventDefault();
        form.post(artikel ? communicatie.help.artikel.opslaan.url(artikel.id) : communicatie.help.artikel.nieuw.url(), { preserveScroll: true, onSuccess: sluit });
    };

    return (
        <Paneel titel={artikel ? 'Artikel bewerken' : 'Nieuw artikel'} sub="De tekst is markdown: **vet**, lijstjes met - of 1.">
            <form onSubmit={opslaan} className="space-y-3" data-test="artikel-formulier">
                <div className="space-y-1.5">
                    <label htmlFor="a-title" className="text-sm font-semibold">
                        Titel
                    </label>
                    <input id="a-title" required value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} className={tekstvak} />
                    {form.errors.title && <p className="text-sm text-rood">{form.errors.title}</p>}
                </div>
                <div className="grid grid-cols-[1fr_90px] gap-2">
                    <div className="space-y-1.5">
                        <label htmlFor="a-category" className="text-sm font-semibold">
                            Onderwerp
                        </label>
                        <input id="a-category" value={form.data.category} onChange={(e) => form.setData('category', e.target.value)} className={tekstvak} placeholder="Bijvoorbeeld Account" />
                    </div>
                    <div className="space-y-1.5">
                        <label htmlFor="a-position" className="text-sm font-semibold">
                            Volgorde
                        </label>
                        <input id="a-position" type="number" min={0} value={form.data.position} onChange={(e) => form.setData('position', e.target.value)} className={tekstvak} />
                    </div>
                </div>
                <fieldset className="space-y-1.5">
                    <legend className="text-sm font-semibold">Voor wie</legend>
                    <div className="flex flex-wrap gap-3">
                        {doelgroepen.map((d) => (
                            <label key={d} className="inline-flex items-center gap-1.5 text-sm">
                                <input
                                    type="checkbox"
                                    checked={form.data.audiences.includes(d)}
                                    onChange={(e) => form.setData('audiences', e.target.checked ? [...form.data.audiences, d] : form.data.audiences.filter((x) => x !== d))}
                                />
                                {doelgroepNaam[d] ?? d}
                            </label>
                        ))}
                    </div>
                    {form.errors.audiences && <p className="text-sm text-rood">{form.errors.audiences}</p>}
                </fieldset>
                <div className="space-y-1.5">
                    <label htmlFor="a-body" className="text-sm font-semibold">
                        Tekst
                    </label>
                    <textarea id="a-body" required rows={10} value={form.data.body} onChange={(e) => form.setData('body', e.target.value)} className={`${tekstvak} font-mono text-sm`} />
                    {form.errors.body && <p className="text-sm text-rood">{form.errors.body}</p>}
                </div>
                <div className="flex flex-wrap gap-2">
                    <Button type="submit" disabled={form.processing} className="bg-violet hover:bg-violet/90">
                        Opslaan
                    </Button>
                    <Button type="button" variant="outline" onClick={sluit}>
                        Annuleren
                    </Button>
                    {artikel && (
                        <Button
                            type="button"
                            variant="ghost"
                            className="ml-auto text-rood"
                            onClick={() => confirm('Dit artikel verwijderen?') && router.delete(communicatie.help.artikel.verwijderen.url(artikel.id), { preserveScroll: true, onSuccess: sluit })}
                        >
                            Verwijderen
                        </Button>
                    )}
                </div>
            </form>
        </Paneel>
    );
}
