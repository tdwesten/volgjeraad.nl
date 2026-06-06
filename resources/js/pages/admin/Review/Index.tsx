import AdminLayout from '@/layouts/AdminLayout';
import { Badge } from '@/components/ui/badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Link } from '@inertiajs/react';

interface Municipality {
    id: number;
    name: string;
    slug: string;
}

interface Meeting {
    id: number;
    name: string | null;
    starts_at: string | null;
    municipality: Municipality;
}

interface NewsletterItem {
    id: number;
    subject: string;
    meeting: Meeting | null;
    low_confidence: boolean;
}

interface Props {
    newsletters: NewsletterItem[];
}

export default function ReviewIndex({ newsletters }: Props): JSX.Element {
    return (
        <AdminLayout>
            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-bold">Review-wachtrij</h1>
                    <span className="text-sm text-muted-foreground">{newsletters.length} draft</span>
                </div>

                {newsletters.length === 0 ? (
                    <p className="text-muted-foreground">Geen nieuwsbrieven in de wachtrij.</p>
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Onderwerp</TableHead>
                                <TableHead>Gemeente</TableHead>
                                <TableHead>Datum</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead></TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {newsletters.map((item) => (
                                <TableRow key={item.id}>
                                    <TableCell className="font-medium">{item.subject}</TableCell>
                                    <TableCell>{item.meeting?.municipality.name ?? '—'}</TableCell>
                                    <TableCell className="text-sm text-muted-foreground">
                                        {item.meeting?.starts_at
                                            ? new Date(item.meeting.starts_at).toLocaleDateString('nl-NL', {
                                                  day: 'numeric',
                                                  month: 'long',
                                                  year: 'numeric',
                                              })
                                            : '—'}
                                    </TableCell>
                                    <TableCell>
                                        {item.low_confidence && (
                                            <Badge variant="outline" className="border-yellow-400 text-yellow-700">
                                                Lage betrouwbaarheid
                                            </Badge>
                                        )}
                                    </TableCell>
                                    <TableCell>
                                        {item.meeting && (
                                            <Link
                                                href={`/admin/review/${item.meeting.id}`}
                                                className="text-sm text-primary hover:underline"
                                            >
                                                Bekijken &rarr;
                                            </Link>
                                        )}
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
