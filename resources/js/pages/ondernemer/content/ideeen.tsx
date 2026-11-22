import { Head, router } from '@inertiajs/react';
import { Pagina, PaginaKop } from '@/components/pagina';
import { Trash2 } from 'lucide-react';
import { IdeeenLijst, IdeeFormulier } from '@/components/content/ideeen-lijst';
import type { Idee } from '@/components/content/types';
import { Button } from '@/components/ui/button';
import content from '@/routes/ondernemer/content';

export default function Ideeen({
    company,
    ideas,
}: {
    company: { name: string };
    ideas: Idee[];
}) {
    return (
        <>
            <Head title="Video-ideeën" />
            <Pagina>
                <PaginaKop
                    titel="Video-ideeën"
                    omschrijving={`${company.name}. Bewaar ideeën voor video's. Het team kiest er een bij je volgende aanvraag en vult de briefing voor je in.`}
                />
                <div className="grid gap-5 lg:grid-cols-3 lg:items-start lg:gap-6">
                    <div className="order-2 min-w-0 lg:order-1 lg:col-span-2">
                        <IdeeenLijst
                            ideas={ideas}
                            actie={(i) =>
                                i.source === 'ondernemer' &&
                                i.status === 'idee' && (
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="ghost"
                                        className="text-muted-foreground"
                                        onClick={() =>
                                            window.confirm(
                                                'Dit idee verwijderen?',
                                            ) &&
                                            router.delete(
                                                content.ideeen.verwijderen.url(
                                                    i.id,
                                                ),
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        <Trash2 aria-hidden />
                                        Verwijderen
                                    </Button>
                                )
                            }
                        />
                    </div>
                    <div className="order-1 lg:sticky lg:top-4 lg:order-2">
                        <IdeeFormulier
                            url={content.ideeen.opslaan.url()}
                            uitleg="Een paar woorden is genoeg. Wij werken het uit tot een briefing."
                        />
                    </div>
                </div>
            </Pagina>
        </>
    );
}

Ideeen.layout = {
    breadcrumbs: [{ title: 'Video-ideeën', href: content.ideeen.url() }],
};
