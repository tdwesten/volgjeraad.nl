import PublicLayout from '@/layouts/PublicLayout';
import { Link, useForm, usePage } from '@inertiajs/react';
import { type PageProps } from '@/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface Summary {
    id: number;
    level: string;
    title: string;
    body: string;
}

interface Meeting {
    id: number;
    name: string | null;
    starts_at: string | null;
    summaries: Summary[];
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

function SubscribeForm({ municipalitySlug }: { municipalitySlug: string }): JSX.Element {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
        municipality_slug: municipalitySlug,
        level: 'standard',
    });

    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                post('/aanmelden');
            }}
            className="space-y-3"
        >
            <div className="space-y-1">
                <Label htmlFor="email">E-mailadres</Label>
                <Input
                    id="email"
                    type="email"
                    value={data.email}
                    onChange={(e) => setData('email', e.target.value)}
                    placeholder="jouw@email.nl"
                    required
                />
                {errors.email && <p className="text-sm text-destructive">{errors.email}</p>}
            </div>
            <Button type="submit" disabled={processing}>
                Aanmelden
            </Button>
        </form>
    );
}

export default function MunicipalityShow({ municipality, meetings }: Props): JSX.Element {
    const { flash } = usePage<PageProps>().props;

    return (
        <PublicLayout>
            <div className="space-y-8">
                <div className="space-y-1">
                    <p className="text-sm text-muted-foreground">
                        <Link href="/" className="hover:underline">
                            Volgjeraad
                        </Link>{' '}
                        &rsaquo; {municipality.name}
                    </p>
                    <h1 className="text-2xl font-bold">Gemeenteraad {municipality.name}</h1>
                </div>

                {flash.success && (
                    <div className="rounded border border-green-200 bg-green-50 p-3 text-sm text-green-800">
                        {flash.success}
                    </div>
                )}

                {meetings.length > 0 ? (
                    <div className="space-y-6">
                        <h2 className="text-lg font-semibold">Recente samenvattingen</h2>
                        {meetings.map((meeting) => {
                            const standardSummary = meeting.summaries.find((s) => s.level === 'standard');
                            return (
                                <div key={meeting.id} className="space-y-2 border-b border-border pb-6">
                                    <Link
                                        href={`/${municipality.slug}/vergadering/${meeting.id}`}
                                        className="text-lg font-medium hover:underline"
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
                                    {standardSummary && (
                                        <p className="line-clamp-3 text-sm text-muted-foreground">
                                            {standardSummary.body}
                                        </p>
                                    )}
                                    <Link
                                        href={`/${municipality.slug}/vergadering/${meeting.id}`}
                                        className="text-sm text-primary hover:underline"
                                    >
                                        Lees meer &rarr;
                                    </Link>
                                </div>
                            );
                        })}
                    </div>
                ) : (
                    <p className="text-muted-foreground">Nog geen gepubliceerde samenvattingen beschikbaar.</p>
                )}

                <div className="rounded-lg border border-border p-6">
                    <h2 className="mb-4 text-lg font-semibold">Blijf op de hoogte</h2>
                    <p className="mb-4 text-sm text-muted-foreground">
                        Ontvang een e-mailsamenvatting na elke raadsvergadering.
                    </p>
                    <SubscribeForm municipalitySlug={municipality.slug} />
                </div>

                <div>
                    <Link
                        href={`/${municipality.slug}/archief`}
                        className="text-sm text-muted-foreground hover:underline"
                    >
                        Bekijk alle vergaderingen &rarr;
                    </Link>
                </div>
            </div>
        </PublicLayout>
    );
}
