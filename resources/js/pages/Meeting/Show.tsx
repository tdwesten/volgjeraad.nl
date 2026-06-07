import PublicLayout from '@/layouts/PublicLayout';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import NewsletterSignup from '@/components/NewsletterSignup';
import SummaryCard from '@/components/SummaryCard';
import { Link } from '@inertiajs/react';
import { ChevronDown, Clock, FileText, PlayCircle, Sparkles } from 'lucide-react';

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

interface VideoData {
    youtube_video_id: string;
    video_url: string | null;
}

interface Props {
    municipality: Municipality;
    meeting: Meeting;
    agendaItems: AgendaItem[];
    video: VideoData | null;
}

function SummaryWithCard({ summary, label }: { summary: SummaryData; label: string }): JSX.Element {
    return <SummaryCard label={label} title={summary.title} body={summary.body} />;
}

export default function MeetingShow({ municipality, meeting, agendaItems, video }: Props): JSX.Element {
    const hasStandard = meeting.standard_summary !== null;
    const hasSimple = meeting.simple_summary !== null;
    const hasSummaries = hasStandard || hasSimple;

    const defaultTab = hasStandard ? 'standard' : 'simple';

    const scrollToVideo = (): void => {
        document.getElementById('video')?.scrollIntoView({ behavior: 'smooth' });
    };

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
                        <Link href="/" className="font-semibold hover:underline">
                            Volg je raad
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

                {video && (
                    <button
                        type="button"
                        onClick={scrollToVideo}
                        className="flex w-full cursor-pointer items-center gap-3 rounded-md border border-border bg-muted/50 px-4 py-3 text-left text-sm transition-colors hover:bg-muted"
                    >
                        <PlayCircle className="h-5 w-5 shrink-0 text-primary" />
                        <span className="flex-1">
                            Van deze vergadering is ook een video-uitzending beschikbaar.
                        </span>
                        <span className="shrink-0 font-medium text-primary">Bekijk de video &darr;</span>
                    </button>
                )}

                {hasSummaries ? (
                    <div className="space-y-4">
                        <details className="group rounded-md border border-border bg-muted/50 text-sm text-muted-foreground">
                            <summary className="flex cursor-pointer list-none items-start gap-2 px-3 py-2 [&::-webkit-details-marker]:hidden">
                                <Sparkles className="mt-0.5 h-4 w-4 shrink-0" />
                                <span className="flex-1">
                                    Automatisch samengevat door AI. Controleer altijd de bronnen voor officiële informatie.
                                </span>
                                <span className="mt-0.5 flex shrink-0 items-center gap-1 whitespace-nowrap font-medium text-foreground">
                                    <span className="group-open:hidden">Meer informatie</span>
                                    <span className="hidden group-open:inline">Minder informatie</span>
                                    <ChevronDown className="h-4 w-4 transition-transform group-open:rotate-180" />
                                </span>
                            </summary>
                            <div className="space-y-3 border-t border-border px-3 py-3">
                                <p>
                                    Deze samenvatting is automatisch gemaakt op basis van de officiële stukken van deze
                                    vergadering. Zo gaat dat stap voor stap:
                                </p>
                                <ol className="list-decimal space-y-1.5 pl-5">
                                    <li>
                                        <strong>Stukken ophalen.</strong> We halen de officiële documenten van deze vergadering op —
                                        de agenda, de besluitenlijst en de raadsstukken (pdf&apos;s) — uit het landelijke systeem{' '}
                                        <em>Open Raadsinformatie</em>.
                                    </li>
                                    <li>
                                        <strong>Video en transcript.</strong> Is er een video-opname van de vergadering, dan zoeken
                                        we die erbij en zetten we het gesproken woord automatisch om in tekst (een <em>transcript</em>).
                                        Zo wordt ook het debat zelf meegenomen, niet alleen de papieren stukken.
                                    </li>
                                    <li>
                                        <strong>Samenvatten.</strong> Een AI-taalmodel leest alle stukken en het transcript en schrijft
                                        daar een samenvatting van: een <strong>standaard</strong> versie en een{' '}
                                        <strong>eenvoudige (B1)</strong> versie in begrijpelijke taal.
                                    </li>
                                    <li>
                                        <strong>Controle door een mens.</strong> Een redacteur leest het concept na, kan het aanpassen
                                        en keurt het pas daarna goed. Wat je hier leest, is dus door een mens gecontroleerd.
                                    </li>
                                </ol>
                                <p>De tools die we hiervoor gebruiken:</p>
                                <ul className="list-disc space-y-1.5 pl-5">
                                    <li>
                                        <strong>Open Raadsinformatie</strong> — de landelijke, openbare bron met de officiële
                                        vergaderstukken.
                                    </li>
                                    <li>
                                        <strong>YouTube</strong> — voor de video-opname van de vergadering.
                                    </li>
                                    <li>
                                        <strong>Supadata</strong> — zet het gesproken woord uit de video om in tekst.
                                    </li>
                                    <li>
                                        <strong>OpenAI</strong> — het AI-taalmodel dat de samenvatting schrijft.
                                    </li>
                                    <li>
                                        <strong>GitHub</strong> — alle code en de gebruikte AI-instructies (prompts) zijn openbaar.{' '}
                                        <strong>Volg je raad</strong> is open source en{' '}
                                        <a
                                            href="https://github.com/tdwesten/volgjeraad.nl"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            className="text-primary hover:underline"
                                        >
                                            in te zien op GitHub
                                        </a>
                                        .
                                    </li>
                                </ul>
                                <p>
                                    AI kan fouten maken. Raadpleeg voor beslissingen altijd de officiële bronnen — je vindt ze
                                    onderaan deze pagina onder <strong>Bronnen</strong>.
                                </p>
                            </div>
                        </details>
                        <div className="space-y-2">
                            <Tabs defaultValue={defaultTab}>
                                <TabsList>
                                    {hasStandard && <TabsTrigger value="standard">Standaard</TabsTrigger>}
                                    {hasSimple && <TabsTrigger value="simple">Eenvoudig (B1)</TabsTrigger>}
                                </TabsList>
                                {hasStandard && hasSimple && (
                                    <p className="mt-2 text-sm text-muted-foreground">
                                        Kies hoe je de samenvatting wilt lezen. <strong>Standaard</strong> volgt de inhoud en toon van de
                                        vergadering. <strong>Eenvoudig (B1)</strong> is herschreven in begrijpelijke taal, met kortere zinnen
                                        en zonder vakjargon.
                                    </p>
                                )}
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
                    </div>
                ) : (
                    <p className="flex items-center gap-2 text-muted-foreground">
                        <Clock className="h-4 w-4" />
                        Nog geen samenvatting beschikbaar voor deze vergadering.
                    </p>
                )}

                <NewsletterSignup municipalitySlug={municipality.slug} municipalityName={municipality.name} />

                {video && (
                    <div id="video" className="space-y-2 scroll-mt-20">
                        <h3 className="flex items-center gap-2 text-sm font-semibold">
                            <PlayCircle className="h-4 w-4" />
                            Video-uitzending
                        </h3>
                        <div className="aspect-video w-full overflow-hidden rounded-lg border border-border">
                            <iframe
                                src={`https://www.youtube.com/embed/${video.youtube_video_id}`}
                                title="Uitzending vergadering"
                                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                allowFullScreen
                                className="h-full w-full"
                            />
                        </div>
                        {video.video_url && (
                            <a
                                href={video.video_url}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="text-sm text-primary hover:underline"
                            >
                                Bekijk de uitzending op YouTube
                            </a>
                        )}
                    </div>
                )}

                {sourceLinks.length > 0 && (
                    <div className="space-y-2">
                        <h3 className="flex items-center gap-2 text-sm font-semibold">
                            <FileText className="h-4 w-4" />
                            Bronnen
                        </h3>
                        <p className="text-sm text-muted-foreground">
                            De officiële documenten van deze vergadering. Raadpleeg deze voor de volledige en gecontroleerde
                            informatie.
                        </p>
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
