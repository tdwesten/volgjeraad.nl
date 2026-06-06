import PublicLayout from '@/layouts/PublicLayout';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import SummaryCard from '@/components/SummaryCard';
import { Link } from '@inertiajs/react';

interface SummaryData {
    id: number;
    title: string;
    body: string;
}

interface MediaObject {
    id: number;
    name: string | null;
    url: string | null;
    original_url: string | null;
}

interface AgendaItem {
    id: number;
    name: string | null;
    position: number;
    mediaObjects: MediaObject[];
}

interface Meeting {
    id: number;
    name: string | null;
    starts_at: string | null;
    standard_summary: SummaryData | null;
    simple_summary: SummaryData | null;
}

interface Municipality {
    id: number;
    slug: string;
    name: string;
}

interface Props {
    municipality: Municipality;
    meeting: Meeting;
    agendaItems: AgendaItem[];
}

function SummaryWithCard({ summary, label }: { summary: SummaryData; label: string }): JSX.Element {
    return <SummaryCard label={label} title={summary.title} body={summary.body} />;
}

export default function MeetingShow({ municipality, meeting, agendaItems }: Props): JSX.Element {
    const hasStandard = meeting.standard_summary !== null;
    const hasSimple = meeting.simple_summary !== null;
    const hasSummaries = hasStandard || hasSimple;

    const defaultTab = hasStandard ? 'standard' : 'simple';

    const sourceLinks = agendaItems.flatMap((item) =>
        item.mediaObjects
            .filter((m) => m.url || m.original_url)
            .map((m) => ({ ...m, itemName: item.name })),
    );

    return (
        <PublicLayout>
            <div className="space-y-8">
                <div className="space-y-1">
                    <p className="text-sm text-muted-foreground">
                        <Link href="/" className="hover:underline">
                            Volgjeraad
                        </Link>{' '}
                        &rsaquo;{' '}
                        <Link href={`/${municipality.slug}`} className="hover:underline">
                            {municipality.name}
                        </Link>{' '}
                        &rsaquo; Vergadering
                    </p>
                    <h1 className="text-2xl font-bold">{meeting.name ?? 'Vergadering'}</h1>
                    {meeting.starts_at && (
                        <p className="text-sm text-muted-foreground">
                            {new Date(meeting.starts_at).toLocaleDateString('nl-NL', {
                                weekday: 'long',
                                day: 'numeric',
                                month: 'long',
                                year: 'numeric',
                            })}
                        </p>
                    )}
                </div>

                {hasSummaries ? (
                    <div className="space-y-4">
                        <div className="flex items-start gap-2 rounded-md border border-border bg-muted/50 px-3 py-2 text-sm text-muted-foreground">
                            <span>Automatisch samengevat door AI. Controleer altijd de bronnen voor officiële informatie.</span>
                        </div>
                        <Tabs defaultValue={defaultTab}>
                            <TabsList>
                                {hasStandard && <TabsTrigger value="standard">Standaard</TabsTrigger>}
                                {hasSimple && <TabsTrigger value="simple">Eenvoudig (B1)</TabsTrigger>}
                            </TabsList>
                            {hasStandard && (
                                <TabsContent value="standard" className="mt-4">
                                    <SummaryWithCard summary={meeting.standard_summary!} label="Standaard" />
                                </TabsContent>
                            )}
                            {hasSimple && (
                                <TabsContent value="simple" className="mt-4">
                                    <SummaryWithCard summary={meeting.simple_summary!} label="Eenvoudig (B1)" />
                                </TabsContent>
                            )}
                        </Tabs>
                    </div>
                ) : (
                    <p className="text-muted-foreground">Nog geen samenvatting beschikbaar voor deze vergadering.</p>
                )}

                {sourceLinks.length > 0 && (
                    <div className="space-y-2">
                        <h3 className="text-sm font-semibold">Bronnen</h3>
                        <ul className="space-y-1">
                            {sourceLinks.map((media) => (
                                <li key={media.id} className="text-sm">
                                    <a
                                        href={media.original_url ?? media.url ?? '#'}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="text-primary hover:underline"
                                    >
                                        {media.name ?? media.itemName ?? 'Document'}
                                    </a>
                                </li>
                            ))}
                        </ul>
                    </div>
                )}
            </div>
        </PublicLayout>
    );
}
