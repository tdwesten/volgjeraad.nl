import PublicLayout from '@/layouts/PublicLayout';
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
                        <Link href="/" className="hover:underline">
                            Volgjeraad
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

                {meetings.length > 0 ? (
                    <ul className="divide-y divide-border">
                        {meetings.map((meeting) => (
                            <li key={meeting.id} className="flex items-center justify-between py-3">
                                <div>
                                    <Link
                                        href={`/${municipality.slug}/vergadering/${meeting.id}`}
                                        className="font-medium hover:underline"
                                    >
                                        {meeting.name ?? 'Vergadering'}
                                    </Link>
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
                            </li>
                        ))}
                    </ul>
                ) : (
                    <p className="text-muted-foreground">Geen vergaderingen gevonden.</p>
                )}
            </div>
        </PublicLayout>
    );
}
