import { Toaster } from '@/components/ui/sonner';
import { Badge } from '@/components/ui/badge';
import { type PageProps } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { type PropsWithChildren } from 'react';

export default function PublicLayout({ children }: PropsWithChildren): JSX.Element {
    const { pageTitle } = usePage<PageProps>().props;

    return (
        <>
            <Head title={pageTitle ?? ''} />
            <div className="flex min-h-screen flex-col bg-background font-sans text-foreground">
                <header className="border-b border-border">
                    <div className="mx-auto flex max-w-5xl items-center px-4 py-3">
                        <Link href="/" className="group flex flex-col leading-tight">
                            <span className="flex items-center gap-2 font-semibold transition-colors group-hover:text-primary">
                                Volg je raad
                                <Badge variant="secondary" className="uppercase tracking-wide">
                                    Beta
                                </Badge>
                            </span>
                            <span className="text-xs text-muted-foreground">
                                Volg wat er speelt in de gemeenteraad — automatisch samengevat met AI, helder geschreven.
                            </span>
                        </Link>
                    </div>
                </header>
                <main className="mx-auto w-full max-w-5xl flex-1 px-4 py-8">{children}</main>
                <footer className="border-t border-border">
                    <div className="mx-auto flex max-w-5xl flex-col gap-2 px-4 py-6 text-sm text-muted-foreground">
                        <p className="flex flex-wrap items-center gap-2">
                            <Badge variant="secondary" className="uppercase tracking-wide">
                                Beta
                            </Badge>
                            <span>
                                Volg je raad is nog in ontwikkeling (beta). Functies en samenvattingen kunnen veranderen
                                of fouten bevatten — controleer bij twijfel altijd de officiële bronnen.
                            </span>
                        </p>
                        <p>
                            <Link href="/" className="font-semibold hover:underline">
                                Volg je raad
                            </Link>{' '}
                            — samenvattingen van gemeenteraadsvergaderingen
                        </p>
                        <p>
                            Een{' '}
                            <a
                                href="https://github.com/tdwesten/volgjeraad.nl"
                                target="_blank"
                                rel="noopener noreferrer"
                                className="hover:underline"
                            >
                                open source
                            </a>{' '}
                            tool gemaakt door Thomas van der Westen —{' '}
                            <a
                                href="https://codesmiths.nl"
                                target="_blank"
                                rel="noopener noreferrer"
                                className="hover:underline"
                            >
                                Codesmiths.nl
                            </a>
                        </p>
                    </div>
                </footer>
                <Toaster />
            </div>
        </>
    );
}
