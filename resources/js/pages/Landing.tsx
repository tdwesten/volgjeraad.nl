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

const processSteps = [
    {
        icon: FileText,
        title: 'Officiële stukken',
        description: 'Agenda, besluitenlijst en raadsstukken vormen de bron.',
    },
    {
        icon: Video,
        title: 'Video naar tekst',
        description: 'De opname van het debat wordt automatisch omgezet naar tekst.',
    },
    {
        icon: Sparkles,
        title: 'AI-samenvatting',
        description: 'Een AI-taalmodel schrijft een heldere samenvatting.',
    },
    {
        icon: Mail,
        title: 'E-mailnieuwsbrief',
        description: 'Je ontvangt de samenvatting in je inbox.',
    },
] as const;

function formatDate(iso: string | null): string {
    if (!iso) {
        return '';
    }
    return new Date(iso).toLocaleDateString('nl-NL', { day: 'numeric', month: 'long', year: 'numeric' });
}

/**
 * Normaliseer een gemeentenaam of zoekterm: verwijder diacritics, een eventuele
 * "gemeente"-prefix en hoofdletters, zodat "Voorst", "VOORST" en "gemeente voorst" matchen.
 */
function normalizeMunicipality(value: string): string {
    return value
        .normalize('NFD')
        .replace(/\p{Diacritic}/gu, '')
        .toLowerCase()
        .replace(/^gemeente\s+/, '')
        .trim();
}

function RequestMunicipalityForm({ defaultName }: { defaultName: string }): JSX.Element {
    const { data, setData, post, processing, errors, reset, wasSuccessful } = useForm({
        municipality: defaultName,
        email: '',
        website: '',
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
            {/* Honeypot — onzichtbaar voor mensen, vult een bot dit dan negeert de backend de aanvraag. */}
            <input
                type="text"
                name="website"
                value={data.website}
                onChange={(e) => setData('website', e.target.value)}
                tabIndex={-1}
                autoComplete="off"
                aria-hidden="true"
                className="sr-only"
            />
            <div className="space-y-1">
                <Label htmlFor="request-municipality">Gemeente</Label>
                <Input
                    id="request-municipality"
                    type="text"
                    value={data.municipality}
                    onChange={(e) => setData('municipality', e.target.value)}
                    placeholder="Naam van je gemeente"
                    required
                    aria-invalid={errors.municipality ? true : undefined}
                    aria-describedby={errors.municipality ? 'request-municipality-error' : undefined}
                />
                {errors.municipality && (
                    <p id="request-municipality-error" className="text-sm text-destructive">
                        {errors.municipality}
                    </p>
                )}
            </div>
            <div className="space-y-1">
                <Label htmlFor="request-email">E-mailadres (optioneel)</Label>
                <Input
                    id="request-email"
                    type="email"
                    value={data.email}
                    onChange={(e) => setData('email', e.target.value)}
                    placeholder="jouw@email.nl — om je op de hoogte te houden"
                    aria-invalid={errors.email ? true : undefined}
                    aria-describedby={errors.email ? 'request-email-error' : undefined}
                />
                {errors.email && (
                    <p id="request-email-error" className="text-sm text-destructive">
                        {errors.email}
                    </p>
                )}
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
        const needle = normalizeMunicipality(trimmed);
        return municipalities.filter((m) => normalizeMunicipality(m.name).includes(needle));
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
                        Na elke raadsvergadering krijg je een heldere samenvatting in je inbox, met links naar de
                        officiële bronnen. <strong>Volg je raad</strong> doet het werk: geen lange vergaderingen of
                        taaie stukken meer doorworstelen.
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
                        vergaderstukken en — waar beschikbaar — de video-opname van het debat. AI kan fouten maken;
                        controleer bij twijfel altijd de bron.
                    </p>

                    {/* Process-timeline: van officiële stukken naar e-mailnieuwsbrief. */}
                    <ol className="relative space-y-6 sm:flex sm:space-y-0">
                        {processSteps.map((step, index) => {
                            const StepIcon = step.icon;
                            const isLast = index === processSteps.length - 1;

                            return (
                                <li key={step.title} className="relative flex gap-4 sm:flex-1 sm:flex-col sm:gap-3">
                                    {/* Verbindende lijn naar de volgende stap. */}
                                    {!isLast && (
                                        <span
                                            aria-hidden="true"
                                            className="absolute left-[1.125rem] top-10 h-[calc(100%-1rem)] w-px bg-border sm:left-auto sm:top-[1.125rem] sm:h-px sm:w-full sm:translate-x-1/2"
                                        />
                                    )}
                                    <div className="relative z-10 flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-primary bg-background font-semibold text-primary">
                                        {index + 1}
                                    </div>
                                    <div className="space-y-1 pb-1">
                                        <div className="flex items-center gap-2 font-medium">
                                            <StepIcon className="h-4 w-4 text-primary" />
                                            {step.title}
                                        </div>
                                        <p className="text-sm text-muted-foreground">{step.description}</p>
                                    </div>
                                </li>
                            );
                        })}
                    </ol>
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
                                {featuredMeeting.municipality.name}
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

                    <div aria-live="polite" className="space-y-4">
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
                                    We volgen <strong>{trimmed}</strong> nog niet. Vraag deze gemeente aan, dan laten we
                                    het weten zodra die beschikbaar is.
                                </p>
                                <RequestMunicipalityForm defaultName={trimmed} />
                            </div>
                        )}
                    </div>

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
                    <RequestMunicipalityForm defaultName="" />
                    {/* fullwidth: geen max-w-md-begrenzing meer */}
                </section>
                )}
            </div>
        </PublicLayout>
    );
}
