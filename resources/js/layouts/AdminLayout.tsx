import { Toaster } from '@/components/ui/sonner';
import { usePage } from '@inertiajs/react';
import { type PropsWithChildren } from 'react';
import { type PageProps } from '@/types';

export default function AdminLayout({ children }: PropsWithChildren): JSX.Element {
    const { auth } = usePage<PageProps>().props;

    return (
        <div className="min-h-screen bg-background font-sans text-foreground">
            <header className="border-b border-border">
                <div className="mx-auto flex h-14 max-w-7xl items-center justify-between px-4">
                    <span className="font-semibold">Volgjeraad — Beheer</span>
                    {auth.user && (
                        <span className="text-sm text-muted-foreground">{auth.user.name}</span>
                    )}
                </div>
            </header>
            <main className="mx-auto max-w-7xl px-4 py-8">{children}</main>
            <Toaster />
        </div>
    );
}
