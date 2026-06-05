import { Toaster } from '@/components/ui/sonner';
import { type PropsWithChildren } from 'react';

export default function PublicLayout({ children }: PropsWithChildren): JSX.Element {
    return (
        <div className="min-h-screen bg-background font-sans text-foreground">
            <header className="border-b border-border">
                <div className="mx-auto flex h-14 max-w-5xl items-center px-4">
                    <span className="font-semibold">Volgjeraad</span>
                </div>
            </header>
            <main className="mx-auto max-w-5xl px-4 py-8">{children}</main>
            <Toaster />
        </div>
    );
}
