import { Head, router } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { IdeeenLijst, IdeeFormulier } from '@/components/content/ideeen-lijst';
import type { Idee } from '@/components/content/types';
import { Button } from '@/components/ui/button';
import content from '@/routes/ondernemer/content';
import { Kop } from '../_onderdelen/opmaak';

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
            <div className="mx-auto w-full max-w-5xl space-y-6 p-4 md:p-6">
                <Kop
                    onder={`${company.name}. Bewaar ideeën voor video's. Het team kiest er een bij je volgende aanvraag en vult de briefing voor je in.`}
                >
                    Video-ideeën
                </Kop>
                <div className="grid gap-6 lg:grid-cols-[1fr_360px] lg:items-start">
                    <div className="order-2 min-w-0 lg:order-1">
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
            </div>
        </>
    );
}

Ideeen.layout = {
    breadcrumbs: [{ title: 'Video-ideeën', href: content.ideeen.url() }],
};
