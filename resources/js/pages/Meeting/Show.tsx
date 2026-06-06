import PublicLayout from '@/layouts/PublicLayout';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Link } from '@inertiajs/react';
import ReactMarkdown from 'react-markdown';

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

function SummaryBody({ summary }: { summary: SummaryData }): JSX.Element {
    return (
        <div className="space-y-4">
            <h2 className="text-xl font-semibold">{summary.title}</h2>
            <div className="prose prose-sm max-w-none text-foreground">
                <ReactMarkdown>{summary.body}</ReactMarkdown>
            </div>
            <p className="text-xs text-muted-foreground italic">
                Automatisch samengevat door AI. Controleer altijd de bronnen voor officiële informatie.
            </p>
        </div>
    );
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
                    <Tabs defaultValue={defaultTab}>
                        <TabsList>
                            {hasStandard && <TabsTrigger value="standard">Standaard</TabsTrigger>}
                            {hasSimple && <TabsTrigger value="simple">Eenvoudig (B1)</TabsTrigger>}
                        </TabsList>
                        {hasStandard && (
                            <TabsContent value="standard" className="mt-4">
                                <SummaryBody summary={meeting.standard_summary!} />
                            </TabsContent>
                        )}
                        {hasSimple && (
                            <TabsContent value="simple" className="mt-4">
                                <SummaryBody summary={meeting.simple_summary!} />
                            </TabsContent>
                        )}
                    </Tabs>
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
