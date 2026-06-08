import OriValidator, { type OriProbe } from '@/components/admin/OriValidator';
import StreamFinder from '@/components/admin/StreamFinder';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AdminLayout from '@/layouts/AdminLayout';
import { type PageProps } from '@/types';
import { Link, useForm, usePage } from '@inertiajs/react';
import { AlertTriangle, CheckCircle2, Clock, ExternalLink, FileText } from 'lucide-react';
import { useEffect, useRef } from 'react';

interface MunicipalityData {
    id: number;
    name: string;
    slug: string;
    active: boolean;
    ori_index: string;
    youtube_channel_id: string | null;
}

interface MeetingItem {
    id: number;
    name: string | null;
    type: 'council' | 'committee' | 'college' | 'other';
    starts_at: string | null;
    ingest_mode: 'summarize' | 'metadata_only';
    summary_status: string;
    is_summarizable: boolean;
    teaser: string | null;
}

interface Props {
    municipality: MunicipalityData;
    ori_status: OriProbe;
    meetings: MeetingItem[];
}

const meetingTypeLabel: Record<MeetingItem['type'], string> = {
    council: 'Raad',
    committee: 'Commissie',
    college: 'College',
    other: 'Anders',
};

function SummaryStatusBadge({ status }: { status: string }): JSX.Element {
    if (status === 'Gepubliceerd') {
        return (
            <Badge variant="outline" className="gap-1 border-green-400 text-green-700">
                <CheckCircle2 className="h-3.5 w-3.5" />
                {status}
            </Badge>
        );
    }
    if (status === 'Goedgekeurd') {
        return (
            <Badge variant="outline" className="gap-1 border-blue-400 text-blue-700">
                <CheckCircle2 className="h-3.5 w-3.5" />
                {status}
            </Badge>
        );
    }
    if (status === 'Concept') {
        return (
            <Badge variant="outline" className="gap-1 border-yellow-400 text-yellow-700">
                <FileText className="h-3.5 w-3.5" />
                {status}
            </Badge>
        );
    }
    if (status === 'Wacht op verwerking') {
        return (
            <Badge variant="outline" className="gap-1 border-orange-400 text-orange-700">
                <Clock className="h-3.5 w-3.5" />
                {status}
            </Badge>
        );
    }
    return (
        <span className="flex items-center gap-1 text-sm text-muted-foreground">
            <AlertTriangle className="h-3.5 w-3.5" />
            {status}
        </span>
    );
}

function ProcessMeetingButton({ municipalityId, meetingId }: { municipalityId: number; meetingId: number }): JSX.Element {
    const { post, processing } = useForm({});

    const start = (): void => {
        if (!window.confirm('Verwerking (opnieuw) starten? Bestaande samenvattingen voor deze vergadering worden vervangen.')) {
            return;
        }
        post(`/admin/municipalities/${municipalityId}/meetings/${meetingId}/process`, { preserveScroll: true });
    };

    return (
        <button
            type="button"
            onClick={start}
            disabled={processing}
            className="text-sm text-primary hover:underline disabled:opacity-50"
        >
            {processing ? 'Bezig…' : 'Verwerken'}
        </button>
    );
}

function ActiveToggle({ id, active }: { id: number; active: boolean }): JSX.Element {
    const { patch, processing } = useForm({});

    const toggle = (): void => {
        patch(`/admin/municipalities/${id}/active`, { preserveScroll: true });
    };

    return (
        <div className="flex items-center gap-2">
            <button
                type="button"
                onClick={toggle}
                disabled={processing}
                aria-pressed={active}
                aria-label={active ? 'Deactiveer gemeente' : 'Activeer gemeente'}
                className={`relative inline-flex h-5 w-9 shrink-0 items-center rounded-full transition-colors disabled:opacity-50 ${
                    active ? 'bg-green-500' : 'bg-muted-foreground/30'
                }`}
            >
                <span
                    className={`inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform ${
                        active ? 'translate-x-4' : 'translate-x-0.5'
                    }`}
                />
            </button>
            <span className="text-sm text-muted-foreground">{active ? 'Actief' : 'Inactief'}</span>
        </div>
    );
}

function ChannelPanel({
    municipalityId,
    name,
    currentChannelId,
}: {
    municipalityId: number;
    name: string;
    currentChannelId: string | null;
}): JSX.Element {
    const { data, setData, post, processing, recentlySuccessful } = useForm({
        youtube_channel_id: currentChannelId ?? '',
    });
    const pendingSave = useRef(false);

    const save = (): void => {
        post(`/admin/municipalities/${municipalityId}/channel`, { preserveScroll: true });
    };

    // Auto-save when a channel is picked from the AI result.
    useEffect(() => {
        if (pendingSave.current) {
            pendingSave.current = false;
            save();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [data.youtube_channel_id]);

    const useChannel = (channelId: string): void => {
        pendingSave.current = true;
        setData('youtube_channel_id', channelId);
    };

    return (
        <div className="space-y-3">
            {currentChannelId ? (
                <a
                    href={`https://www.youtube.com/channel/${currentChannelId}`}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="inline-flex items-center gap-1.5 text-sm text-primary hover:underline"
                >
                    <ExternalLink className="h-3.5 w-3.5" />
                    {currentChannelId}
                </a>
            ) : (
                <p className="text-sm text-muted-foreground">Nog geen kanaal ingesteld.</p>
            )}

            <div className="space-y-1.5">
                <Label htmlFor="youtube_channel_id">YouTube-kanaal-ID</Label>
                <div className="flex gap-2">
                    <Input
                        id="youtube_channel_id"
                        value={data.youtube_channel_id}
                        onChange={(e) => setData('youtube_channel_id', e.target.value)}
                        placeholder="UCxxxxxxxxxxxxxxxxxxxxxx"
                    />
                    <Button type="button" onClick={save} disabled={processing}>
                        Opslaan
                    </Button>
                </div>
                {recentlySuccessful && <p className="text-xs text-green-700">Opgeslagen.</p>}
            </div>

            <StreamFinder name={name} onUse={useChannel} />
        </div>
    );
}

export default function MunicipalitiesShow({ municipality, ori_status, meetings }: Props): JSX.Element {
    const { flash } = usePage<PageProps>().props;

    return (
        <AdminLayout>
            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">{municipality.name}</h1>
                        <p className="text-sm text-muted-foreground">{meetings.length} vergaderingen</p>
                    </div>
                    <Link href="/admin/municipalities" className="text-sm text-muted-foreground hover:underline">
                        &larr; Alle gemeenten
                    </Link>
                </div>

                {flash.success && (
                    <div className="rounded border border-green-200 bg-green-50 p-3 text-sm text-green-800">
                        {flash.success}
                    </div>
                )}

                <Card>
                    <CardHeader className="flex flex-row items-center justify-between">
                        <CardTitle>Beheer</CardTitle>
                        <ActiveToggle id={municipality.id} active={municipality.active} />
                    </CardHeader>
                    <CardContent className="grid gap-8 md:grid-cols-2">
                        <div className="space-y-2">
                            <h3 className="text-sm font-semibold uppercase tracking-wide text-muted-foreground">ORI-index</h3>
                            <p className="font-mono text-sm">{municipality.ori_index}</p>
                            <OriValidator oriIndex={municipality.ori_index} initialResult={ori_status} />
                        </div>
                        <div className="space-y-2">
                            <h3 className="text-sm font-semibold uppercase tracking-wide text-muted-foreground">YouTube-kanaal</h3>
                            <ChannelPanel
                                municipalityId={municipality.id}
                                name={municipality.name}
                                currentChannelId={municipality.youtube_channel_id}
                            />
                        </div>
                    </CardContent>
                </Card>

                {meetings.length === 0 ? (
                    <p className="flex items-center gap-2 text-muted-foreground">
                        <AlertTriangle className="h-4 w-4" />
                        Geen vergaderingen gevonden.
                    </p>
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Vergadering</TableHead>
                                <TableHead>Type</TableHead>
                                <TableHead>Datum</TableHead>
                                <TableHead>Ingest</TableHead>
                                <TableHead>Samenvatting</TableHead>
                                <TableHead></TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {meetings.map((meeting) => (
                                <TableRow key={meeting.id}>
                                    <TableCell className="max-w-md font-medium">
                                        {meeting.name ?? <span className="text-muted-foreground italic">Naamloos</span>}
                                        {meeting.teaser && (
                                            <p className="mt-1 line-clamp-2 text-sm font-normal text-muted-foreground">
                                                {meeting.teaser}
                                            </p>
                                        )}
                                    </TableCell>
                                    <TableCell className="text-sm text-muted-foreground">
                                        {meetingTypeLabel[meeting.type]}
                                    </TableCell>
                                    <TableCell className="text-sm text-muted-foreground">
                                        {meeting.starts_at
                                            ? new Date(meeting.starts_at).toLocaleDateString('nl-NL', {
                                                  day: 'numeric',
                                                  month: 'long',
                                                  year: 'numeric',
                                              })
                                            : '—'}
                                    </TableCell>
                                    <TableCell className="text-sm text-muted-foreground">
                                        {meeting.ingest_mode === 'summarize' ? 'Samenvatten' : 'Metadata only'}
                                    </TableCell>
                                    <TableCell>
                                        <SummaryStatusBadge status={meeting.summary_status} />
                                    </TableCell>
                                    <TableCell>
                                        <div className="flex gap-3">
                                            {meeting.is_summarizable && (
                                                <Link
                                                    href={`/admin/review/${meeting.id}`}
                                                    className="text-sm text-primary hover:underline"
                                                >
                                                    Review
                                                </Link>
                                            )}
                                            <ProcessMeetingButton
                                                municipalityId={municipality.id}
                                                meetingId={meeting.id}
                                            />
                                            <Link
                                                href={`/${municipality.slug}/vergadering/${meeting.id}`}
                                                className="text-sm text-muted-foreground hover:underline"
                                            >
                                                Publiek
                                            </Link>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}
            </div>
        </AdminLayout>
    );
}
