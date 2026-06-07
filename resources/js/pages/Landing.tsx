import PublicLayout from '@/layouts/PublicLayout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Link, useForm } from '@inertiajs/react';
import { useEffect, useMemo, useState, type FormEvent } from 'react';
import { ArrowRight, Building2, CheckCircle2, FileText, Mail, Search, Sparkles, Video } from 'lucide-react';

interface Municipality {
    id: number;
    slug: string;
    name: string;
}

interface FeaturedMeeting {
    id: number;
    name: string | null;
    starts_at: string | null;
    teaser: string | null;
    municipality: { slug: string; name: string };
}

interface Props {
    municipalities: Municipality[];
    featuredMeeting: FeaturedMeeting | null;
}

function formatDate(iso: string | null): string {
    if (!iso) {
        return '';
    }
    return new Date(iso).toLocaleDateString('nl-NL', { day: 'numeric', month: 'long', year: 'numeric' });
}

function RequestMunicipalityForm({ defaultName }: { defaultName: string }): JSX.Element {
    const { data, setData, post, processing, errors, reset, wasSuccessful } = useForm({
        municipality: defaultName,
        email: '',
    });

    // Houd de gemeentenaam gelijk aan wat iemand in de zoekbalk typt, maar laat een
    // ingevuld e-mailadres staan.
    useEffect(() => {
        setData('municipality', defaultName);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [defaultName]);

    const submit = (e: FormEvent): void => {
        e.preventDefault();
        post('/gemeente-aanvragen', {
            preserveScroll: true,
            onSuccess: () => reset('email'),
        });
    };

    if (wasSuccessful) {
        return (
            <div className="flex items-start gap-2 rounded-md border border-green-200 bg-green-50 p-4 text-sm text-green-800">
                <CheckCircle2 className="mt-0.5 h-4 w-4 shrink-0" />
                <span>
                    Bedankt! We hebben je aanvraag ontvangen en bekijken of we deze gemeente kunnen toevoegen.
                </span>
            </div>
        );
    }

    return (
        <form onSubmit={submit} className="space-y-3 rounded-lg border border-border bg-muted/40 p-4">
            <div className="space-y-1">
                <Label htmlFor="request-municipality">Gemeente</Label>
                <Input
                    id="request-municipality"
                    type="text"
                    value={data.municipality}
                    onChange={(e) => setData('municipality', e.target.value)}
                    placeholder="Naam van je gemeente"
                    required
                />
                {errors.municipality && <p className="text-sm text-destructive">{errors.municipality}</p>}
            </div>
            <div className="space-y-1">
                <Label htmlFor="request-email">E-mailadres (optioneel)</Label>
                <Input
                    id="request-email"
                    type="email"
                    value={data.email}
                    onChange={(e) => setData('email', e.target.value)}
                    placeholder="jouw@email.nl — om je op de hoogte te houden"
                />
                {errors.email && <p className="text-sm text-destructive">{errors.email}</p>}
            </div>
            <Button type="submit" disabled={processing}>
                <Mail className="h-4 w-4" />
                {processing ? 'Bezig…' : 'Gemeente aanvragen'}
            </Button>
        </form>
    );
}

export default function Landing({ municipalities, featuredMeeting }: Props): JSX.Element {
    const [query, setQuery] = useState('');

    const trimmed = query.trim();
    const results = useMemo(() => {
        if (trimmed === '') {
            return municipalities;
        }
        const needle = trimmed.toLowerCase();
        return municipalities.filter((m) => m.name.toLowerCase().includes(needle));
    }, [municipalities, trimmed]);

    const noResults = trimmed !== '' && results.length === 0;

    return (
        <PublicLayout>
            <div className="space-y-12">
                {/* Hero */}
                <section className="space-y-4">
                    <h1 className="text-3xl font-bold tracking-tight sm:text-4xl">
                        Volg wat er speelt in jouw gemeenteraad
                    </h1>
                    <p className="max-w-2xl text-lg text-muted-foreground">
                        Gemeentepolitiek raakt je dagelijks leven — van woningbouw tot afvalbeleid — maar de
                        vergaderingen zijn lang en de stukken taai. <strong>Volg je raad</strong> vat elke
                        raadsvergadering voor je samen in begrijpelijke taal, met een link naar de officiële bronnen.
                    </p>
                </section>

                {/* AI-transparantie */}
                <section className="space-y-4 rounded-lg border border-border bg-muted/40 p-6">
                    <div className="flex items-center gap-2">
                        <Sparkles className="h-5 w-5 text-primary" />
                        <h2 className="text-lg font-semibold">We gebruiken AI — en verstoppen dat niet</h2>
                    </div>
                    <p className="max-w-2xl text-sm text-muted-foreground">
                        De samenvattingen worden gemaakt door een AI-taalmodel, op basis van de officiële
                        vergaderstukken en — waar beschikbaar — de video-opname van het debat. Daarna leest een mens
                        elke samenvatting na en keurt die goed vóór publicatie. AI kan fouten maken; controleer bij
                        twijfel altijd de bron.
                    </p>
                    <div className="grid gap-3 sm:grid-cols-3">
                        <div className="flex items-start gap-2 text-sm">
                            <FileText className="mt-0.5 h-4 w-4 shrink-0 text-primary" />
                            <span>Officiële stukken als bron — agenda, besluitenlijst en raadsstukken.</span>
                        </div>
                        <div className="flex items-start gap-2 text-sm">
                            <Video className="mt-0.5 h-4 w-4 shrink-0 text-primary" />
                            <span>Video-opname omgezet naar tekst, zodat ook het debat meetelt.</span>
                        </div>
                        <div className="flex items-start gap-2 text-sm">
                            <CheckCircle2 className="mt-0.5 h-4 w-4 shrink-0 text-primary" />
                            <span>Een mens controleert en keurt elke samenvatting goed.</span>
                        </div>
                    </div>
                    <p className="text-sm text-muted-foreground">
                        Volg je raad is open source: de code en de gebruikte AI-instructies (prompts) zijn{' '}
                        <a
                            href="https://github.com/tdwesten/volgjeraad.nl"
                            target="_blank"
                            rel="noopener noreferrer"
                            className="text-primary hover:underline"
                        >
                            openbaar in te zien op GitHub
                        </a>
                        .
                    </p>
                </section>

                {/* Voorbeeld-vergadering */}
                {featuredMeeting && (
                    <section className="space-y-3">
                        <h2 className="text-lg font-semibold">Bekijk een voorbeeld</h2>
                        <Link
                            href={`/${featuredMeeting.municipality.slug}/vergadering/${featuredMeeting.id}`}
                            className="group block rounded-lg border border-border p-6 transition-colors hover:border-primary hover:bg-muted/40"
                        >
                            <p className="text-sm text-muted-foreground">
                                Gemeente {featuredMeeting.municipality.name}
                                {featuredMeeting.starts_at && <> &middot; {formatDate(featuredMeeting.starts_at)}</>}
                            </p>
                            <h3 className="mt-1 text-lg font-medium group-hover:text-primary">
                                {featuredMeeting.name ?? 'Raadsvergadering'}
                            </h3>
                            {featuredMeeting.teaser && (
                                <p className="mt-2 line-clamp-3 text-sm text-muted-foreground">
                                    {featuredMeeting.teaser}
                                </p>
                            )}
                            <span className="mt-3 inline-flex items-center gap-1 text-sm font-medium text-primary">
                                Lees de samenvatting
                                <ArrowRight className="h-4 w-4 transition-transform group-hover:translate-x-0.5" />
                            </span>
                        </Link>
                    </section>
                )}

                {/* Zoek je gemeente */}
                <section className="space-y-4">
                    <div className="space-y-1">
                        <h2 className="text-lg font-semibold">Zoek je gemeente</h2>
                        <p className="text-sm text-muted-foreground">
                            Kijk of we jouw gemeente al volgen. Staat die er nog niet bij? Vraag 'm aan.
                        </p>
                    </div>

                    <div className="relative max-w-md">
                        <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            type="text"
                            value={query}
                            onChange={(e) => setQuery(e.target.value)}
                            placeholder="Bijv. Brummen"
                            className="pl-9"
                            aria-label="Zoek je gemeente"
                        />
                    </div>

                    {results.length > 0 && (
                        <ul className="divide-y divide-border rounded-lg border border-border">
                            {results.map((municipality) => (
                                <li key={municipality.id}>
                                    <Link
                                        href={`/${municipality.slug}`}
                                        className="flex items-center justify-between gap-3 px-4 py-3 transition-colors hover:bg-muted/50"
                                    >
                                        <span className="flex items-center gap-2 font-medium">
                                            <Building2 className="h-4 w-4 text-muted-foreground" />
                                            {municipality.name}
                                        </span>
                                        <ArrowRight className="h-4 w-4 text-muted-foreground" />
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}

                    {noResults && (
                        <div className="space-y-3">
                            <p className="text-sm text-muted-foreground">
                                We volgen <strong>{trimmed}</strong> nog niet. Vraag deze gemeente aan, dan laten we het
                                weten zodra die beschikbaar is.
                            </p>
                            <RequestMunicipalityForm defaultName={trimmed} />
                        </div>
                    )}

                    {municipalities.length === 0 && trimmed === '' && (
                        <p className="text-sm text-muted-foreground">Er zijn nog geen gemeenten beschikbaar.</p>
                    )}
                </section>

                {/* Gemeente aanvragen — verborgen wanneer de zoekopdracht al een aanvraagformulier toont. */}
                {!noResults && (
                <section className="space-y-3">
                    <h2 className="text-lg font-semibold">Staat jouw gemeente er niet bij?</h2>
                    <p className="max-w-2xl text-sm text-muted-foreground">
                        Laat weten welke gemeente je graag wilt volgen. We bekijken per aanvraag of we die kunnen
                        toevoegen.
                    </p>
                    <div className="max-w-md">
                        <RequestMunicipalityForm defaultName="" />
                    </div>
                </section>
                )}
            </div>
        </PublicLayout>
    );
}
