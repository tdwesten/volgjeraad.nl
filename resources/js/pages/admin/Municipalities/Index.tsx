import AdminLayout from '@/layouts/AdminLayout';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import { router } from '@inertiajs/react';

interface MunicipalityItem {
    id: number;
    name: string;
    slug: string;
    active: boolean;
    meetings_count: number;
    confirmed_subscribers_count: number;
    published_summaries_count: number;
}

interface Props {
    municipalities: MunicipalityItem[];
}

export default function MunicipalitiesIndex({ municipalities }: Props): JSX.Element {
    return (
        <AdminLayout>
            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">Gemeenten</h1>
                        <p className="text-sm text-muted-foreground">{municipalities.length} gemeenten</p>
                    </div>
                </div>

                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Gemeente</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead className="text-right">Vergaderingen</TableHead>
                            <TableHead className="text-right">Abonnees</TableHead>
                            <TableHead className="text-right">Gepubliceerde samenvattingen</TableHead>
                            <TableHead></TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {municipalities.map((municipality) => (
                            <TableRow
                                key={municipality.id}
                                onClick={() => router.visit(`/admin/municipalities/${municipality.id}`)}
                                className="cursor-pointer"
                            >
                                <TableCell className="font-medium">{municipality.name}</TableCell>
                                <TableCell>
                                    {municipality.active ? (
                                        <Badge variant="outline" className="border-green-400 text-green-700">
                                            Actief
                                        </Badge>
                                    ) : (
                                        <Badge variant="outline" className="text-muted-foreground">
                                            Inactief
                                        </Badge>
                                    )}
                                </TableCell>
                                <TableCell className="text-right tabular-nums">
                                    {municipality.meetings_count.toLocaleString('nl-NL')}
                                </TableCell>
                                <TableCell className="text-right tabular-nums">
                                    {municipality.confirmed_subscribers_count.toLocaleString('nl-NL')}
                                </TableCell>
                                <TableCell className="text-right tabular-nums">
                                    {municipality.published_summaries_count.toLocaleString('nl-NL')}
                                </TableCell>
                                <TableCell className="text-right text-muted-foreground">&rarr;</TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </div>
        </AdminLayout>
    );
}
