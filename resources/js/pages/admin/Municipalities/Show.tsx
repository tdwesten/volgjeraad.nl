import AdminLayout from '@/layouts/AdminLayout';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import { Link } from '@inertiajs/react';
import { AlertTriangle, CheckCircle2, Clock, FileText } from 'lucide-react';

interface MunicipalityData {
    id: number;
    name: string;
    slug: string;
    active: boolean;
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

export default function MunicipalitiesShow({ municipality, meetings }: Props): JSX.Element {
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
