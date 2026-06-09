import SummaryCard from '@/components/SummaryCard';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import AdminLayout from '@/layouts/AdminLayout';
import { type PageProps } from '@/types';
import { Link, router, useForm, usePage, usePoll } from '@inertiajs/react';
import {
    AlertTriangle,
    CheckCircle2,
    Clock,
    FileText,
    Mic,
    RefreshCw,
    Send,
    Tv,
    XCircle,
} from 'lucide-react';
import { useEffect, useState } from 'react';

interface SummaryItem {
    id: number;
    title: string;
    body: string;
    confidence: number | null;
    status: string;
}

interface Municipality {
    id: number;
    name: string;
    slug: string;
}

interface MeetingData {
    id: number;
    name: string | null;
    type: 'council' | 'committee' | 'college' | 'other';
    starts_at: string | null;
    municipality: Municipality;
    processing_status: string;
    processing_label: string;
    summarized_at: string | null;
    source_resolved_at: string | null;
}

interface Newsletter {
    id: number;
    subject: string;
    status: string;
}

interface MediaObjectItem {
    id: number;
    name: string | null;
    url: string | null;
    original_url: string | null;
}

interface AgendaItemData {
    id: number;
    name: string | null;
    position: number;
    mediaObjects: MediaObjectItem[];
}

interface VideoData {
    status: string;
    youtube_video_id: string | null;
    video_url: string | null;
    has_transcript: boolean;
    transcript_source: string | null;
}

interface Sources {
    summary_source: string | null;
    summary_skipped_reason: string | null;
    notule: MediaObjectItem | null;
    has_transcript: boolean;
    has_video: boolean;
}

interface ProcessingLogItem {
    id: number;
    step: string;
    status: string;
    message: string;
    created_at: string;
}

interface Props {
    meeting: MeetingData;
    standardSummary: SummaryItem | null;
    simpleSummary: SummaryItem | null;
    newsletter: Newsletter | null;
    sources: Sources;
    agendaItems: AgendaItemData[];
    video: VideoData | null;
    logs: ProcessingLogItem[];
}

const meetingTypeLabel: Record<MeetingData['type'], string> = {
    council: 'Raad',
    committee: 'Commissie',
    college: 'College',
    other: 'Anders',
};

const sourceLabel: Record<string, string> = {
    transcript: 'Transcript (video)',
    notule: 'Notule / besluitenlijst',
};

function EditableSummaryCard({ summary, label }: { summary: SummaryItem; label: string }): JSX.Element {
    const [editing, setEditing] = useState(false);
    const { data, setData, patch, processing } = useForm({ body: summary.body });

    const save = (): void => {
        patch(`/admin/summary/${summary.id}`, {
            onSuccess: () => setEditing(false),
        });
    };

    if (editing) {
        return (
            <div className="rounded-lg border border-border p-4">
                <div className="mb-2">
                    <span className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">{label}</span>
                </div>
                <h3 className="mb-2 font-medium">{summary.title}</h3>
                <div className="space-y-2">
                    <Textarea value={data.body} onChange={(e) => setData('body', e.target.value)} rows={12} className="text-sm" />
                    <div className="flex gap-2">
                        <Button size="sm" onClick={save} disabled={processing}>
                            Opslaan
                        </Button>
                        <Button size="sm" variant="outline" onClick={() => setEditing(false)}>
                            Annuleren
                        </Button>
                    </div>
                </div>
            </div>
        );
    }

    return (
        <SummaryCard label={`${label} · ${summary.status}`} title={summary.title} body={data.body} confidence={summary.confidence}>
            <Button size="sm" variant="outline" onClick={() => setEditing(true)}>
                Bewerken
            </Button>
        </SummaryCard>
    );
}

const statusStyles: Record<string, string> = {
    info: 'border-blue-200 bg-blue-50 text-blue-700',
    success: 'border-green-200 bg-green-50 text-green-700',
    warning: 'border-yellow-200 bg-yellow-50 text-yellow-700',
    error: 'border-red-200 bg-red-50 text-red-700',
};

const statusIcons: Record<string, JSX.Element> = {
    info: <Clock className="h-3.5 w-3.5 shrink-0 opacity-70" />,
    success: <CheckCircle2 className="h-3.5 w-3.5 shrink-0 text-green-500" />,
    warning: <AlertTriangle className="h-3.5 w-3.5 shrink-0 text-yellow-500" />,
    error: <XCircle className="h-3.5 w-3.5 shrink-0 text-red-500" />,
};

function ProcessingTimeline({ logs }: { logs: ProcessingLogItem[] }): JSX.Element {
    if (logs.length === 0) {
        return (
            <p className="flex items-center gap-2 text-sm text-muted-foreground">
                <Clock className="h-4 w-4" />
                Geen verwerkingslogboek beschikbaar.
            </p>
        );
    }

    return (
        <ol className="space-y-2">
            {logs.map((log) => (
                <li
                    key={log.id}
                    className={`flex items-start gap-3 rounded-md border px-3 py-2 text-sm ${statusStyles[log.status] ?? statusStyles.info}`}
                >
                    <span className="mt-0.5">{statusIcons[log.status] ?? statusIcons.info}</span>
                    <div className="min-w-0 flex-1">
                        <span className="font-mono text-xs opacity-60">[{log.step}]</span> <span>{log.message}</span>
                    </div>
                    <time className="shrink-0 text-xs opacity-60" dateTime={log.created_at}>
                        {new Date(log.created_at).toLocaleTimeString('nl-NL', { hour: '2-digit', minute: '2-digit' })}
                    </time>
                </li>
            ))}
        </ol>
    );
}

function MediaLink({ media }: { media: MediaObjectItem }): JSX.Element {
    const href = media.original_url ?? media.url;
    if (!href) {
        return <span className="text-sm text-muted-foreground">{media.name ?? 'Document'}</span>;
    }
    return (
        <a href={href} target="_blank" rel="noopener noreferrer" className="text-sm text-primary hover:underline">
            {media.name ?? 'Document'}
        </a>
    );
}

function SourcesSection({ sources, video }: { sources: Sources; video: VideoData | null }): JSX.Element {
    return (
        <div className="space-y-3 rounded-lg border border-border p-4">
            <div className="flex flex-wrap items-center gap-2 text-sm">
                <span className="font-medium">Gebruikte bron:</span>
                {sources.summary_source ? (
                    <span className="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700">
                        {sourceLabel[sources.summary_source] ?? sources.summary_source}
                    </span>
                ) : sources.summary_skipped_reason ? (
                    <span className="inline-flex items-center rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700">
                        Geen bron ({sources.summary_skipped_reason})
                    </span>
                ) : (
                    <span className="text-muted-foreground">Nog niet bepaald</span>
                )}
            </div>

            <ul className="space-y-1.5 text-sm">
                <li className="flex items-center gap-2">
                    <Tv className="h-4 w-4 shrink-0 text-muted-foreground" />
                    {sources.has_video ? (
                        <span>
                            Video aanwezig
                            {sources.has_transcript
                                ? ` · transcript beschikbaar${video?.transcript_source ? ` (${video.transcript_source})` : ''}`
                                : ' · nog geen transcript'}
                        </span>
                    ) : (
                        <span className="text-muted-foreground">Geen video gevonden</span>
                    )}
                </li>
                <li className="flex items-center gap-2">
                    <FileText className="h-4 w-4 shrink-0 text-muted-foreground" />
                    {sources.notule ? (
                        <span className="flex items-center gap-1">
                            Notule / besluitenlijst: <MediaLink media={sources.notule} />
                        </span>
                    ) : (
                        <span className="text-muted-foreground">Geen notule gedetecteerd</span>
                    )}
                </li>
            </ul>
        </div>
    );
}

export default function AdminMeetingShow({
    meeting,
    standardSummary,
    simpleSummary,
    newsletter,
    sources,
    agendaItems,
    video,
    logs,
}: Props): JSX.Element {
    const { flash } = usePage<PageProps>().props;
    const { post: postRegenerate, processing: regenerating } = useForm({});

    const isProcessing = newsletter === null && sources.summary_skipped_reason === null;
    const { start, stop } = usePoll(4000, { only: ['logs', 'standardSummary', 'simpleSummary', 'newsletter', 'meeting', 'sources', 'video', 'agendaItems'] }, { autoStart: false });

    useEffect(() => {
        if (isProcessing) {
            start();
        } else {
            stop();
        }
    }, [isProcessing, start, stop]);

    const approve = (): void => {
        router.post(`/admin/review/${meeting.id}/approve`);
    };

    const handleRegenerate = (): void => {
        if (!confirm('Weet je zeker dat je deze vergadering opnieuw wil verwerken? Bestaande samenvattingen en het nieuwsbrief-concept worden verwijderd.')) {
            return;
        }
        postRegenerate(`/admin/review/${meeting.id}/regenerate`);
    };

    const hasSummaries = standardSummary !== null || simpleSummary !== null;
    const sourceLinks = agendaItems.flatMap((item) => item.mediaObjects.filter((m) => m.url || m.original_url));

    return (
        <AdminLayout>
            <div className="space-y-6">
                <div className="flex items-start justify-between gap-4">
                    <div className="space-y-1">
                        <p className="text-sm text-muted-foreground">
                            <Link href="/admin/municipalities" className="hover:underline">
                                Gemeenten
                            </Link>{' '}
                            &rsaquo;{' '}
                            <Link href={`/admin/municipalities/${meeting.municipality.id}`} className="hover:underline">
                                {meeting.municipality.name}
                            </Link>
                        </p>
                        <h1 className="text-2xl font-bold">{meeting.name ?? 'Vergadering'}</h1>
                        <div className="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                            {meeting.starts_at && (
                                <span>
                                    {new Date(meeting.starts_at).toLocaleDateString('nl-NL', {
                                        day: 'numeric',
                                        month: 'long',
                                        year: 'numeric',
                                    })}
                                </span>
                            )}
                            <span>·</span>
                            <span>{meetingTypeLabel[meeting.type]}</span>
                            <span className="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700">
                                {meeting.processing_label}
                            </span>
                        </div>
                    </div>
                    <div className="flex shrink-0 gap-2">
                        {video?.video_url && (
                            <Button variant="outline" asChild>
                                <a href={video.video_url} target="_blank" rel="noopener noreferrer">
                                    <Tv className="h-4 w-4" />
                                    Bekijk de uitzending
                                </a>
                            </Button>
                        )}
                        <Button variant="outline" onClick={handleRegenerate} disabled={regenerating}>
                            <RefreshCw className="h-4 w-4" />
                            {regenerating ? 'Bezig...' : 'Opnieuw verwerken'}
                        </Button>
                        {newsletter && (
                            <Button onClick={approve} disabled={newsletter.status !== 'draft'}>
                                <Send className="h-4 w-4" />
                                {newsletter.status === 'draft' ? 'Goedkeuren & versturen' : 'Verstuurd'}
                            </Button>
                        )}
                    </div>
                </div>

                {flash.success && (
                    <div className="rounded border border-green-200 bg-green-50 p-3 text-sm text-green-800">{flash.success}</div>
                )}

                <section className="space-y-3">
                    <h2 className="text-sm font-semibold uppercase tracking-wide text-muted-foreground">Bronnen</h2>
                    <SourcesSection sources={sources} video={video} />
                </section>

                <section className="space-y-3">
                    <h2 className="text-sm font-semibold uppercase tracking-wide text-muted-foreground">Samenvattingen (incl. concept)</h2>
                    {hasSummaries ? (
                        <div className="grid gap-4 md:grid-cols-2">
                            {standardSummary ? (
                                <EditableSummaryCard summary={standardSummary} label="Standaard" />
                            ) : (
                                <div className="rounded-lg border border-dashed border-border p-4 text-sm text-muted-foreground">
                                    Geen standaard samenvatting
                                </div>
                            )}
                            {simpleSummary ? (
                                <EditableSummaryCard summary={simpleSummary} label="Eenvoudig (B1)" />
                            ) : (
                                <div className="rounded-lg border border-dashed border-border p-4 text-sm text-muted-foreground">
                                    Geen B1-samenvatting
                                </div>
                            )}
                        </div>
                    ) : (
                        <p className="text-muted-foreground">Geen samenvattingen beschikbaar.</p>
                    )}
                </section>

                {video && (
                    <section className="space-y-2">
                        <h2 className="flex items-center gap-2 text-sm font-semibold uppercase tracking-wide text-muted-foreground">
                            <Mic className="h-4 w-4" />
                            Video &amp; transcript
                        </h2>
                        <div className="rounded-lg border border-border p-4 text-sm">
                            <p>
                                Status: <span className="font-mono text-xs">{video.status}</span>
                            </p>
                            <p>{video.has_transcript ? 'Transcript beschikbaar' : 'Nog geen transcript'}</p>
                            {video.youtube_video_id && (
                                <a
                                    href={`https://www.youtube.com/watch?v=${video.youtube_video_id}`}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="text-primary hover:underline"
                                >
                                    Bekijk op YouTube
                                </a>
                            )}
                        </div>
                    </section>
                )}

                <section className="space-y-2">
                    <h2 className="text-sm font-semibold uppercase tracking-wide text-muted-foreground">Agenda &amp; documenten</h2>
                    {agendaItems.length === 0 ? (
                        <p className="text-sm text-muted-foreground">Nog geen agenda opgehaald.</p>
                    ) : (
                        <ul className="space-y-3">
                            {agendaItems.map((item) => (
                                <li key={item.id} className="rounded-lg border border-border p-3">
                                    <p className="text-sm font-medium">{item.name ?? 'Agendapunt'}</p>
                                    {item.mediaObjects.length > 0 && (
                                        <ul className="mt-1 space-y-1 pl-4">
                                            {item.mediaObjects.map((media) => (
                                                <li key={media.id} className="list-disc">
                                                    <MediaLink media={media} />
                                                </li>
                                            ))}
                                        </ul>
                                    )}
                                </li>
                            ))}
                        </ul>
                    )}
                    {sourceLinks.length === 0 && agendaItems.length > 0 && (
                        <p className="text-xs text-muted-foreground">Geen downloadbare documenten bij deze agenda.</p>
                    )}
                </section>

                <section>
                    <h2 className="mb-3 flex items-center gap-2 text-sm font-semibold uppercase tracking-wide text-muted-foreground">
                        Verwerkingslogboek
                        {isProcessing && (
                            <span className="inline-flex items-center gap-1.5 rounded-full bg-muted px-2 py-0.5 text-[10px] font-medium normal-case text-muted-foreground">
                                <span className="relative flex h-1.5 w-1.5">
                                    <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-green-500 opacity-75" />
                                    <span className="relative inline-flex h-1.5 w-1.5 rounded-full bg-green-500" />
                                </span>
                                live
                            </span>
                        )}
                    </h2>
                    <ProcessingTimeline logs={logs} />
                </section>
            </div>
        </AdminLayout>
    );
}
