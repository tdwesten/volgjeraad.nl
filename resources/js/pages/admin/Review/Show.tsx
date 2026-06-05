import AdminLayout from '@/layouts/AdminLayout';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { Link, useForm, router, usePage } from '@inertiajs/react';
import { type PageProps } from '@/types';
import { useState } from 'react';

interface SummaryItem {
    id: number;
    title: string;
    body: string;
    confidence: number | null;
    position: number;
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

interface Props {
    meeting: Meeting;
    newsletter: Newsletter;
    standardSummaries: SummaryItem[];
    simpleSummaries: SummaryItem[];
}

function SummaryCard({
    summary,
    label,
}: {
    summary: SummaryItem;
    label: string;
}): JSX.Element {
    const [editing, setEditing] = useState(false);
    const { data, setData, patch, processing } = useForm({ body: summary.body });
    const isLowConfidence = summary.confidence !== null && summary.confidence < 60;

    const save = (): void => {
        patch(`/admin/summary/${summary.id}`, {
            onSuccess: () => setEditing(false),
        });
    };

    return (
        <div className={`rounded-lg border p-4 ${isLowConfidence ? 'border-yellow-300' : 'border-border'}`}>
            <div className="mb-2 flex items-center justify-between">
                <span className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">{label}</span>
                <div className="flex items-center gap-2">
                    {summary.confidence !== null && (
                        <Badge
                            variant="outline"
                            className={isLowConfidence ? 'border-yellow-400 text-yellow-700' : ''}
                        >
                            {summary.confidence}% betrouwbaar
                        </Badge>
                    )}
                </div>
            </div>

            <h3 className="mb-2 font-medium">{summary.title}</h3>

            {editing ? (
                <div className="space-y-2">
                    <Textarea
                        value={data.body}
                        onChange={(e) => setData('body', e.target.value)}
                        rows={10}
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
            ) : (
                <div className="space-y-2">
                    <p className="whitespace-pre-wrap text-sm">{data.body}</p>
                    <Button size="sm" variant="outline" onClick={() => setEditing(true)}>
                        Bewerken
                    </Button>
                </div>
            )}
        </div>
    );
}

export default function ReviewShow({ meeting, newsletter, standardSummaries, simpleSummaries }: Props): JSX.Element {
    const { flash } = usePage<PageProps>().props;
    const positions = [...new Set([...standardSummaries, ...simpleSummaries].map((s) => s.position))].sort(
        (a, b) => a - b,
    );

    const approve = (): void => {
        router.post(`/admin/review/${meeting.id}/approve`);
    };

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
                    <Button onClick={approve} disabled={newsletter.status !== 'draft'}>
                        {newsletter.status === 'draft' ? 'Goedkeuren & versturen' : 'Verstuurd'}
                    </Button>
                </div>

                {flash.success && (
                    <div className="rounded border border-green-200 bg-green-50 p-3 text-sm text-green-800">
                        {flash.success}
                    </div>
                )}

                {positions.length === 0 ? (
                    <p className="text-muted-foreground">Geen samenvattingen beschikbaar.</p>
                ) : (
                    <div className="space-y-8">
                        {positions.map((pos) => {
                            const std = standardSummaries.find((s) => s.position === pos);
                            const sim = simpleSummaries.find((s) => s.position === pos);

                            return (
                                <div key={pos} className="grid gap-4 md:grid-cols-2">
                                    {std ? (
                                        <SummaryCard summary={std} label="Standaard" />
                                    ) : (
                                        <div className="rounded-lg border border-dashed border-border p-4 text-sm text-muted-foreground">
                                            Geen standaard samenvatting
                                        </div>
                                    )}
                                    {sim ? (
                                        <SummaryCard summary={sim} label="Eenvoudig (B1)" />
                                    ) : (
                                        <div className="rounded-lg border border-dashed border-border p-4 text-sm text-muted-foreground">
                                            Geen B1-samenvatting
                                        </div>
                                    )}
                                </div>
                            );
                        })}
                    </div>
                )}
            </div>
        </AdminLayout>
    );
}
