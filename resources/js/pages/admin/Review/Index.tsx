import AdminLayout from '@/layouts/AdminLayout';
import { Badge } from '@/components/ui/badge';
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
                    <div className="space-y-2">
                        {newsletters.map((item) => (
                            <div
                                key={item.id}
                                className={`flex items-center justify-between rounded-lg border p-4 ${
                                    item.low_confidence ? 'border-yellow-300 bg-yellow-50' : 'border-border'
                                }`}
                            >
                                <div className="space-y-1">
                                    <div className="flex items-center gap-2">
                                        <span className="font-medium">{item.subject}</span>
                                        {item.low_confidence && (
                                            <Badge variant="outline" className="border-yellow-400 text-yellow-700">
                                                Lage betrouwbaarheid
                                            </Badge>
                                        )}
                                    </div>
                                    {item.meeting && (
                                        <p className="text-sm text-muted-foreground">
                                            {item.meeting.municipality.name}
                                            {item.meeting.starts_at && (
                                                <>
                                                    {' '}
                                                    &middot;{' '}
                                                    {new Date(item.meeting.starts_at).toLocaleDateString('nl-NL', {
                                                        day: 'numeric',
                                                        month: 'long',
                                                        year: 'numeric',
                                                    })}
                                                </>
                                            )}
                                        </p>
                                    )}
                                </div>
                                {item.meeting && (
                                    <Link
                                        href={`/admin/review/${item.meeting.id}`}
                                        className="text-sm text-primary hover:underline"
                                    >
                                        Bekijken &rarr;
                                    </Link>
                                )}
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </AdminLayout>
    );
}
