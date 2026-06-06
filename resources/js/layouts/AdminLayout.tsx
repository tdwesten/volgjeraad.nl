import { Toaster } from '@/components/ui/sonner';
import { Link, usePage } from '@inertiajs/react';
import { type PropsWithChildren } from 'react';
import { type PageProps } from '@/types';

const navLinks = [
    { href: '/admin', label: 'Dashboard' },
    { href: '/admin/review', label: 'Review' },
    { href: '/admin/subscribers', label: 'Abonnees' },
    { href: '/admin/municipalities', label: 'Gemeenten' },
];

export default function AdminLayout({ children }: PropsWithChildren): JSX.Element {
    const { auth, ziggy } = usePage<PageProps & { ziggy?: { location?: string } }>().props;
    const currentPath = ziggy?.location ? new URL(ziggy.location).pathname : '';

    return (
        <div className="min-h-screen bg-background font-sans text-foreground">
            <header className="border-b border-border">
                <div className="mx-auto flex h-14 max-w-7xl items-center justify-between px-4">
                    <div className="flex items-center gap-6">
                        <span className="font-semibold">Volgjeraad — Beheer</span>
                        <nav className="flex items-center gap-1">
                            {navLinks.map((link) => (
                                <Link
                                    key={link.href}
                                    href={link.href}
                                    className={`rounded-md px-3 py-1.5 text-sm transition-colors hover:bg-accent ${
                                        currentPath === link.href
                                            ? 'font-medium text-foreground'
                                            : 'text-muted-foreground'
                                    }`}
                                >
                                    {link.label}
                                </Link>
                            ))}
                        </nav>
                    </div>
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
