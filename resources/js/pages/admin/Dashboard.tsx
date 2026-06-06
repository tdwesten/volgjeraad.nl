import AdminLayout from '@/layouts/AdminLayout';
import { Link, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useState } from 'react';

interface Props {
    totalCostCents: number;
    totalAiCalls: number;
    newslettersSent: number;
    drafts: number;
}

export default function Dashboard({ totalCostCents, totalAiCalls, newslettersSent, drafts }: Props): JSX.Element {
    const [searching, setSearching] = useState(false);

    function searchMeetings(): void {
        setSearching(true);
        router.post(
            '/admin/ingest',
            {},
            {
                preserveScroll: true,
                onFinish: () => setSearching(false),
            },
        );
    }

    return (
        <AdminLayout>
            <div className="space-y-8">
                <div className="flex items-center justify-between gap-4">
                    <h1 className="text-2xl font-bold">Dashboard</h1>
                    <button
                        type="button"
                        onClick={searchMeetings}
                        disabled={searching}
                        className="inline-flex items-center gap-2 rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition hover:opacity-90 disabled:opacity-50"
                    >
                        <Search className="h-4 w-4" />
                        {searching ? 'Bezig met zoeken…' : 'Zoek naar nieuwe vergaderingen'}
                    </button>
                </div>

                <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                    <StatCard label="AI-calls" value={totalAiCalls.toLocaleString('nl-NL')} />
                    <StatCard
                        label="AI-kosten"
                        value={`€ ${(totalCostCents / 100).toLocaleString('nl-NL', { minimumFractionDigits: 2 })}`}
                    />
                    <StatCard label="Verzonden nieuwsbrieven" value={newslettersSent.toLocaleString('nl-NL')} />
                    <StatCard label="Wachten op review" value={drafts.toLocaleString('nl-NL')} />
                </div>

                <div className="space-y-2">
                    <h2 className="text-lg font-semibold">Acties</h2>
                    <ul className="space-y-1 text-sm">
                        <li>
                            <Link href="/admin/review" className="text-primary hover:underline">
                                Review-wachtrij ({drafts} draft)
                            </Link>
                        </li>
                        <li>
                            <Link href="/admin/subscribers" className="text-primary hover:underline">
                                Abonnees beheren
                            </Link>
                        </li>
                    </ul>
                </div>
            </div>
        </AdminLayout>
    );
}

function StatCard({ label, value }: { label: string; value: string }): JSX.Element {
    return (
        <div className="rounded-lg border border-border p-4">
            <p className="text-xs text-muted-foreground">{label}</p>
            <p className="mt-1 text-xl font-semibold">{value}</p>
        </div>
    );
}
