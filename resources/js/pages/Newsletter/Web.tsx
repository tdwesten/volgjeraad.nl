import PublicLayout from '@/layouts/PublicLayout';
import { Link } from '@inertiajs/react';
import ReactMarkdown from 'react-markdown';

interface Summary {
    id: number;
    level: string;
    title: string;
    body: string;
    position: number;
}

interface Municipality {
    id: number;
    slug: string;
    name: string;
}

interface Newsletter {
    id: number;
    subject: string;
    intro: string | null;
    municipality: Municipality;
    summaries: Summary[];
}

interface Props {
    newsletter: Newsletter;
}

export default function NewsletterWeb({ newsletter }: Props): JSX.Element {
    const standardSummaries = newsletter.summaries.filter((s) => s.level === 'standard');

    return (
        <PublicLayout>
            <div className="space-y-8">
                <div className="space-y-1">
                    <p className="text-sm text-muted-foreground">
                        <Link href={`/${newsletter.municipality.slug}`} className="hover:underline">
                            {newsletter.municipality.name}
                        </Link>{' '}
                        &rsaquo; Nieuwsbrief
                    </p>
                    <h1 className="text-2xl font-bold">{newsletter.subject}</h1>
                </div>

                {newsletter.intro && (
                    <div className="whitespace-pre-wrap text-muted-foreground">{newsletter.intro}</div>
                )}

                {standardSummaries.length > 0 ? (
                    <div className="space-y-8">
                        {standardSummaries.map((summary) => (
                            <div key={summary.id} className="space-y-3">
                                <h2 className="text-lg font-semibold">{summary.title}</h2>
                                <div className="prose prose-sm max-w-none dark:prose-invert prose-headings:font-semibold prose-h2:text-base prose-h2:mt-5 prose-h2:mb-1 prose-p:my-2">
                                    <ReactMarkdown>{summary.body}</ReactMarkdown>
                                </div>
                            </div>
                        ))}
                    </div>
                ) : (
                    <p className="text-muted-foreground">Geen samenvattingen beschikbaar.</p>
                )}

                <p className="border-t border-border pt-6 text-xs text-muted-foreground">
                    Dit is de webversie van de <strong>Volg je raad</strong> nieuwsbrief voor {newsletter.municipality.name}. Automatisch
                    samengevat door AI — controleer altijd de officiële bronnen.
                </p>
            </div>
        </PublicLayout>
    );
}
