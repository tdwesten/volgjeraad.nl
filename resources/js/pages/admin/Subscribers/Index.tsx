import AdminLayout from '@/layouts/AdminLayout';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Link, router, usePage } from '@inertiajs/react';
import { type PageProps } from '@/types';

interface Municipality {
    id: number;
    name: string;
}

interface SubscriberItem {
    id: number;
    email: string;
    level: string;
    language: string;
    municipality: Municipality;
    confirmed_at: string | null;
    unsubscribed_at: string | null;
    created_at: string;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedSubscribers {
    data: SubscriberItem[];
    links: PaginationLink[];
    total: number;
    per_page: number;
    current_page: number;
    last_page: number;
}

interface Props {
    subscribers: PaginatedSubscribers;
}

export default function SubscribersIndex({ subscribers }: Props): JSX.Element {
    const { flash } = usePage<PageProps>().props;

    const deleteSubscriber = (id: number): void => {
        if (!confirm('Weet je zeker dat je deze abonnee wilt verwijderen? Dit kan niet ongedaan worden gemaakt.')) {
            return;
        }
        router.delete(`/admin/subscribers/${id}`);
    };

    return (
        <AdminLayout>
            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">Abonnees</h1>
                        <p className="text-sm text-muted-foreground">{subscribers.total} abonnees totaal</p>
                    </div>
                    <a
                        href="/admin/subscribers/export"
                        className="inline-flex h-9 items-center justify-center rounded-md border border-input bg-background px-4 text-sm font-medium hover:bg-accent"
                    >
                        Exporteren (CSV)
                    </a>
                </div>

                {flash.success && (
                    <div className="rounded border border-green-200 bg-green-50 p-3 text-sm text-green-800">
                        {flash.success}
                    </div>
                )}

                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>E-mail</TableHead>
                            <TableHead>Gemeente</TableHead>
                            <TableHead>Niveau</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead>Aangemeld</TableHead>
                            <TableHead></TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {subscribers.data.map((subscriber) => (
                            <TableRow key={subscriber.id}>
                                <TableCell className="font-mono text-sm">{subscriber.email}</TableCell>
                                <TableCell>{subscriber.municipality.name}</TableCell>
                                <TableCell>{subscriber.level}</TableCell>
                                <TableCell>
                                    {subscriber.unsubscribed_at ? (
                                        <span className="text-sm text-muted-foreground">Uitgeschreven</span>
                                    ) : subscriber.confirmed_at ? (
                                        <span className="text-sm text-green-700">Actief</span>
                                    ) : (
                                        <span className="text-sm text-yellow-700">Niet bevestigd</span>
                                    )}
                                </TableCell>
                                <TableCell className="text-sm text-muted-foreground">
                                    {new Date(subscriber.created_at).toLocaleDateString('nl-NL')}
                                </TableCell>
                                <TableCell>
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        onClick={() => deleteSubscriber(subscriber.id)}
                                        className="text-destructive hover:text-destructive"
                                    >
                                        Verwijderen
                                    </Button>
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>

                {subscribers.last_page > 1 && (
                    <div className="flex gap-1">
                        {subscribers.links.map((link, i) => (
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
