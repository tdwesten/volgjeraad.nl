import AdminLayout from '@/layouts/AdminLayout';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Link } from '@inertiajs/react';

interface MunicipalityRequestItem {
    id: number;
    municipality: string;
    email: string | null;
    created_at: string;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedRequests {
    data: MunicipalityRequestItem[];
    links: PaginationLink[];
    total: number;
    per_page: number;
    current_page: number;
    last_page: number;
}

interface Props {
    requests: PaginatedRequests;
}

export default function MunicipalityRequestsIndex({ requests }: Props): JSX.Element {
    return (
        <AdminLayout>
            <div className="space-y-6">
                <div>
                    <h1 className="text-2xl font-bold">Aanvragen</h1>
                    <p className="text-sm text-muted-foreground">{requests.total} gemeente-aanvragen totaal</p>
                </div>

                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Gemeente</TableHead>
                            <TableHead>E-mail</TableHead>
                            <TableHead>Aangevraagd op</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {requests.data.map((request) => (
                            <TableRow key={request.id}>
                                <TableCell>{request.municipality}</TableCell>
                                <TableCell className="font-mono text-sm">{request.email ?? '—'}</TableCell>
                                <TableCell className="text-sm text-muted-foreground">
                                    {new Date(request.created_at).toLocaleDateString('nl-NL')}
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>

                {requests.last_page > 1 && (
                    <div className="flex gap-1">
                        {requests.links.map((link, i) => (
                            <Link
                                key={i}
                                href={link.url ?? '#'}
                                className={`rounded border px-3 py-1 text-sm ${
                                    link.active
                                        ? 'border-primary bg-primary text-primary-foreground'
                                        : 'border-border hover:bg-accent'
                                } ${!link.url ? 'pointer-events-none opacity-50' : ''}`}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ))}
                    </div>
                )}
            </div>
        </AdminLayout>
    );
}
