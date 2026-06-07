import PublicLayout from '@/layouts/PublicLayout';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Link } from '@inertiajs/react';

interface Meeting {
    id: number;
    name: string | null;
    starts_at: string | null;
    type: string;
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

export default function MunicipalityArchive({ municipality, meetings }: Props): JSX.Element {
    return (
        <PublicLayout>
            <div className="space-y-6">
                <div className="space-y-1">
                    <p className="text-sm text-muted-foreground">
                        <Link href="/" className="font-semibold hover:underline">
                            Volg je raad
                        </Link>{' '}
                        &rsaquo;{' '}
                        <Link href={`/${municipality.slug}`} className="hover:underline">
                            {municipality.name}
                        </Link>{' '}
                        &rsaquo; Archief
                    </p>
                    <h1 className="text-2xl font-bold">Archief — {municipality.name}</h1>
                    <p className="text-sm text-muted-foreground">Alle vergaderingen van de gemeenteraad.</p>
                </div>

                {meetings.length === 0 ? (
                    <p className="text-muted-foreground">Geen vergaderingen gevonden.</p>
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Vergadering</TableHead>
                                <TableHead>Datum</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {meetings.map((meeting) => (
                                <TableRow key={meeting.id}>
                                    <TableCell>
                                        <Link
                                            href={`/${municipality.slug}/vergadering/${meeting.id}`}
                                            className="font-medium hover:underline"
                                        >
                                            {meeting.name ?? 'Vergadering'}
                                        </Link>
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
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}
            </div>
        </PublicLayout>
    );
}
