import AdminLayout from '@/layouts/AdminLayout';
import { Link } from '@inertiajs/react';

interface Props {
    totalCostCents: number;
    totalAiCalls: number;
    newslettersSent: number;
    drafts: number;
}

export default function Dashboard({ totalCostCents, totalAiCalls, newslettersSent, drafts }: Props): JSX.Element {
    return (
        <AdminLayout>
            <div className="space-y-8">
                <h1 className="text-2xl font-bold">Dashboard</h1>

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
