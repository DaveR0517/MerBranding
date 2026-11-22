import { Link } from '@inertiajs/react';
import { BarChart3, MessageSquare } from 'lucide-react';
import * as creatorCijfers from '@/routes/creator/cijfers';
import { gesprek as creatorGesprek } from '@/routes/creator/communicatie/berichten';
import { gesprek as ondernemerGesprek } from '@/routes/ondernemer/communicatie/berichten';
import { gesprek as teamGesprek } from '@/routes/team/communicatie/berichten';

/** Links vanuit een samenwerking naar andere modules: berichten, en voor de creator de cijfers tijdens de meetperiode. */
export function Snelkoppelingen({
    omgeving,
    id,
    status,
}: {
    omgeving: 'team' | 'ondernemer' | 'creator';
    id: number;
    status: string;
}) {
    const berichten = {
        team: teamGesprek,
        ondernemer: ondernemerGesprek,
        creator: creatorGesprek,
    }[omgeving].url(id);
    const klasse =
        'inline-flex min-h-10 items-center gap-1.5 rounded-full border bg-card px-4 py-1.5 text-sm font-semibold hover:border-violet hover:text-violet';

    return (
        <div className="flex flex-wrap gap-2" data-test="snelkoppelingen">
            <Link href={berichten} className={klasse}>
                <MessageSquare className="size-4" aria-hidden /> Berichten
            </Link>
            {omgeving === 'creator' && status === 'live' && (
                <Link href={creatorCijfers.invoeren.url(id)} className={klasse}>
                    <BarChart3 className="size-4" aria-hidden /> Cijfers
                    invoeren
                </Link>
            )}
        </div>
    );
}
