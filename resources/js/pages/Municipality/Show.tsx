import PublicLayout from '@/layouts/PublicLayout';
import { Link, usePage } from '@inertiajs/react';
import { type PageProps } from '@/types';
import NewsletterSignup from '@/components/NewsletterSignup';

interface Summary {
    id: number;
    level: string;
    title: string;
    body: string;
}

interface Meeting {
    id: number;
    name: string | null;
    starts_at: string | null;
    summaries: Summary[];
}

interface Municipality {
    id: number;
    slug: string;
    name: string;
}

interface Props {
    municipality: Municipality;
    meetings: Meeting[];
}

export default function MunicipalityShow({ municipality, meetings }: Props): JSX.Element {
    const { flash } = usePage<PageProps>().props;

    return (
        <PublicLayout>
            <div className="space-y-8">
                <div className="space-y-1">
                    <p className="text-sm text-muted-foreground">
                        <Link href="/" className="font-semibold hover:underline">
                            Volg je raad
                        </Link>{' '}
                        &rsaquo; {municipality.name}
                    </p>
                    <h1 className="text-2xl font-bold">Gemeenteraad {municipality.name}</h1>
                </div>

                {flash.success && (
                    <div className="rounded border border-green-200 bg-green-50 p-3 text-sm text-green-800">
                        {flash.success}
                    </div>
                )}

                <NewsletterSignup municipalitySlug={municipality.slug} />

                {meetings.length > 0 ? (
                    <div className="space-y-6">
                        <h2 className="text-lg font-semibold">Recente samenvattingen</h2>
                        {meetings.map((meeting) => {
                            // Toon de korte plain-text teaser als die er is; anders de standaard-samenvatting.
                            const teaser = meeting.summaries.find((s) => s.level === 'plain');
                            const standardSummary = meeting.summaries.find((s) => s.level === 'standard');
                            const preview = teaser ?? standardSummary;
                            return (
                                <div key={meeting.id} className="space-y-2 border-b border-border pb-6">
                                    <Link
                                        href={`/${municipality.slug}/vergadering/${meeting.id}`}
                                        className="text-lg font-medium hover:underline"
                                    >
                                        {meeting.name ?? 'Vergadering'}
                                    </Link>
                                    {meeting.starts_at && (
                                        <p className="text-sm text-muted-foreground">
                                            {new Date(meeting.starts_at).toLocaleDateString('nl-NL', {
                                                day: 'numeric',
                                                month: 'long',
                                                year: 'numeric',
                                            })}
                                        </p>
                                    )}
                                    {preview && (
                                        <p className="line-clamp-3 text-sm text-muted-foreground">
                                            {preview.body}
                                        </p>
                                    )}
                                    <Link
                                        href={`/${municipality.slug}/vergadering/${meeting.id}`}
                                        className="text-sm text-primary hover:underline"
                                    >
                                        Lees meer &rarr;
                                    </Link>
                                </div>
                            );
                        })}
                    </div>
                ) : (
                    <p className="text-muted-foreground">Nog geen gepubliceerde samenvattingen beschikbaar.</p>
                )}

                <div>
                    <Link
                        href={`/${municipality.slug}/archief`}
                        className="text-sm text-muted-foreground hover:underline"
                    >
                        Bekijk alle vergaderingen &rarr;
                    </Link>
                </div>
            </div>
        </PublicLayout>
    );
}
