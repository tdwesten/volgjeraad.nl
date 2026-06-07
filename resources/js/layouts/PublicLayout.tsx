import { Toaster } from '@/components/ui/sonner';
import { Link } from '@inertiajs/react';
import { type PropsWithChildren } from 'react';

export default function PublicLayout({ children }: PropsWithChildren): JSX.Element {
    return (
        <div className="flex min-h-screen flex-col bg-background font-sans text-foreground">
            <header className="border-b border-border">
                <div className="mx-auto flex max-w-5xl items-center px-4 py-3">
                    <Link href="/" className="group flex flex-col leading-tight">
                        <span className="font-semibold transition-colors group-hover:text-primary">Volg je Raad</span>
                        <span className="text-xs text-muted-foreground">
                            Volg wat er speelt in de gemeenteraad — automatisch samengevat met AI, helder geschreven.
                        </span>
                    </Link>
                </div>
            </header>
            <main className="mx-auto w-full max-w-5xl flex-1 px-4 py-8">{children}</main>
            <footer className="border-t border-border">
                <div className="mx-auto flex max-w-5xl flex-col gap-2 px-4 py-6 text-sm text-muted-foreground">
                    <p>
                        <Link href="/" className="font-semibold hover:underline">
                            Volg je Raad
                        </Link>{' '}
                        — samenvattingen van gemeenteraadsvergaderingen
                    </p>
                    <p>
                        <a
                            href="https://github.com/tdwesten/volgjeraad.nl"
                            target="_blank"
                            rel="noopener noreferrer"
                            className="hover:underline"
                        >
                            Open source
                        </a>{' '}
                        · Een tool van Thomas van der Westen —{' '}
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
    );
}
