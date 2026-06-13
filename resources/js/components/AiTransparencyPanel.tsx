import { Badge } from '@/components/ui/badge';
import { FileText, Mail, Sparkles, Video } from 'lucide-react';

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

export default function AiTransparencyPanel(): JSX.Element {
    return (
        <section className="space-y-4 rounded-lg border border-border bg-muted/40 p-6 text-center">
            <div className="flex flex-wrap items-center justify-center gap-2">
                <Sparkles className="h-5 w-5 text-primary" />
                <h2 className="text-lg font-semibold">We gebruiken AI om dit te maken</h2>
                <Badge variant="secondary" className="uppercase tracking-wide">
                    Beta
                </Badge>
            </div>
            <p className="mx-auto max-w-2xl text-sm text-muted-foreground">
                De samenvattingen worden gemaakt door een AI-taalmodel, op basis van de officiële vergaderstukken en —
                waar beschikbaar — de video-opname van het debat. Dit is nog volop in ontwikkeling (beta): AI kan
                fouten maken, dus controleer bij twijfel altijd de bron.
            </p>

            <div className="relative">
                <ol className="space-y-6 sm:grid sm:grid-cols-4 sm:gap-4 sm:space-y-0">
                    {processSteps.map((step, index) => {
                        const StepIcon = step.icon;
                        const isLast = index === processSteps.length - 1;

                        return (
                            <li
                                key={step.title}
                                className="relative flex gap-4 sm:flex-col sm:items-center sm:gap-3 sm:text-center"
                            >
                                {!isLast && (
                                    <span
                                        aria-hidden="true"
                                        className="absolute left-[1.125rem] top-9 h-[calc(100%-0.75rem)] w-px -translate-x-1/2 bg-border sm:hidden"
                                    />
                                )}
                                {!isLast && (
                                    <span
                                        aria-hidden="true"
                                        className="absolute left-1/2 top-[1.125rem] hidden h-px w-[calc(100%+1rem)] bg-border sm:block"
                                    />
                                )}
                                <div className="relative z-10 flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-primary bg-background font-semibold text-primary">
                                    {index + 1}
                                </div>
                                <div className="space-y-1 pb-1 sm:pb-0">
                                    <div className="flex items-center gap-2 font-medium sm:justify-center">
                                        <StepIcon className="h-4 w-4 text-primary" />
                                        {step.title}
                                    </div>
                                    <p className="text-sm text-muted-foreground">{step.description}</p>
                                </div>
                            </li>
                        );
                    })}
                </ol>
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
    );
}
