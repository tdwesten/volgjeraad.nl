import AdminLayout from '@/layouts/AdminLayout';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import SummaryCard from '@/components/SummaryCard';
import { Link, useForm, router, usePage, usePoll } from '@inertiajs/react';
import { type PageProps } from '@/types';
import { useState } from 'react';
import { AlertTriangle, CheckCircle2, Clock, RefreshCw, Send, Tv, XCircle } from 'lucide-react';

interface SummaryItem {
    id: number;
    title: string;
    body: string;
    confidence: number | null;
}

interface Meeting {
    id: number;
    name: string | null;
    starts_at: string | null;
    municipality: { id: number; name: string; slug: string };
}

interface Newsletter {
    id: number;
    subject: string;
    status: string;
}

interface ProcessingLogItem {
    id: number;
    step: string;
    status: string;
    message: string;
    created_at: string;
}

interface Props {
    meeting: Meeting;
    newsletter: Newsletter | null;
    standardSummary: SummaryItem | null;
    simpleSummary: SummaryItem | null;
    logs: ProcessingLogItem[];
    video_url: string | null;
}

function EditableSummaryCard({
    summary,
    label,
}: {
    summary: SummaryItem;
    label: string;
}): JSX.Element {
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
                    <Textarea
                        value={data.body}
                        onChange={(e) => setData('body', e.target.value)}
                        rows={12}
                        className="text-sm"
                    />
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
        <SummaryCard label={label} title={summary.title} body={data.body} confidence={summary.confidence}>
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
                <li key={log.id} className={`flex items-start gap-3 rounded-md border px-3 py-2 text-sm ${statusStyles[log.status] ?? statusStyles.info}`}>
                    <span className="mt-0.5">{statusIcons[log.status] ?? statusIcons.info}</span>
                    <div className="min-w-0 flex-1">
                        <span className="font-mono text-xs opacity-60">[{log.step}]</span>{' '}
                        <span>{log.message}</span>
                    </div>
                    <time className="shrink-0 text-xs opacity-60" dateTime={log.created_at}>
                        {new Date(log.created_at).toLocaleTimeString('nl-NL', { hour: '2-digit', minute: '2-digit' })}
                    </time>
                </li>
            ))}
        </ol>
    );
}

export default function ReviewShow({ meeting, newsletter, standardSummary, simpleSummary, logs, video_url }: Props): JSX.Element {
    const { flash } = usePage<PageProps>().props;
    const { post: postRegenerate, processing: regenerating } = useForm({});

    // Live status: ververs het verwerkingslogboek (en het resultaat) periodiek
    // zodat de admin de pijplijn-status ziet meelopen zonder te herladen.
    usePoll(4000, { only: ['logs', 'standardSummary', 'simpleSummary', 'newsletter'] });

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

    return (
        <AdminLayout>
            <div className="space-y-6">
                <div className="flex items-start justify-between">
                    <div className="space-y-1">
                        <p className="text-sm text-muted-foreground">
                            <Link href="/admin/review" className="hover:underline">
                                Review-wachtrij
                            </Link>{' '}
                            &rsaquo; {meeting.municipality.name}
                        </p>
                        <h1 className="text-2xl font-bold">{meeting.name ?? 'Vergadering'}</h1>
                        {meeting.starts_at && (
                            <p className="text-sm text-muted-foreground">
                                {new Date(meeting.starts_at).toLocaleDateString('nl-NL', {
                                    day: 'numeric',
                                    month: 'long',
                                    year: 'numeric',
                                })}
                            </p>
                        )}
                    </div>
                    <div className="flex gap-2">
                        {video_url && (
                            <Button variant="outline" asChild>
                                <a href={video_url} target="_blank" rel="noopener noreferrer">
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
                    <div className="rounded border border-green-200 bg-green-50 p-3 text-sm text-green-800">
                        {flash.success}
                    </div>
                )}

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

                <div>
                    <h2 className="mb-3 flex items-center gap-2 text-sm font-semibold uppercase tracking-wide text-muted-foreground">
                        Verwerkingslogboek
                        <span className="inline-flex items-center gap-1.5 rounded-full bg-muted px-2 py-0.5 text-[10px] font-medium normal-case text-muted-foreground">
                            <span className="relative flex h-1.5 w-1.5">
                                <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-green-500 opacity-75" />
                                <span className="relative inline-flex h-1.5 w-1.5 rounded-full bg-green-500" />
                            </span>
                            live
                        </span>
                    </h2>
                    <ProcessingTimeline logs={logs} />
                </div>
            </div>
        </AdminLayout>
    );
}
