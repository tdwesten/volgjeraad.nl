import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AdminLayout from '@/layouts/AdminLayout';
import { Link, router, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';

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

function ActiveToggle({ id, active }: { id: number; active: boolean }): JSX.Element {
    const { patch, processing } = useForm({});

    const toggle = (e: React.MouseEvent): void => {
        e.stopPropagation();
        patch(`/admin/municipalities/${id}/active`, { preserveScroll: true });
    };

    return (
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
    );
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
                    <Button asChild>
                        <Link href="/admin/municipalities/create">
                            <Plus className="h-4 w-4" />
                            Nieuwe gemeente
                        </Link>
                    </Button>
                </div>

                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Gemeente</TableHead>
                            <TableHead>Actief</TableHead>
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
                                <TableCell onClick={(e) => e.stopPropagation()}>
                                    <ActiveToggle id={municipality.id} active={municipality.active} />
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
