import { Button } from '@/components/ui/button';
import { useHttp } from '@inertiajs/react';
import { AlertTriangle, CheckCircle2, Loader2, Search, XCircle } from 'lucide-react';
import { useEffect, useState } from 'react';

export interface OriProbe {
    exists: boolean;
    meeting_count: number | null;
    latest_meeting: { name: string | null; date: string | null } | null;
    error: string | null;
}

function normalizeProbe(response: unknown): OriProbe | null {
    if (response && typeof response === 'object') {
        const candidate = ('exists' in response ? response : (response as { data?: unknown }).data) as OriProbe | undefined;
        if (candidate && typeof candidate === 'object' && 'exists' in candidate) {
            return candidate;
        }
    }
    return null;
}

function formatDate(date: string | null): string {
    if (!date) {
        return '';
    }
    const parsed = new Date(date);
    if (Number.isNaN(parsed.getTime())) {
        return date;
    }
    return parsed.toLocaleDateString('nl-NL', { day: 'numeric', month: 'long', year: 'numeric' });
}

interface Props {
    oriIndex: string;
    initialResult?: OriProbe | null;
}

export default function OriValidator({ oriIndex, initialResult }: Props): JSX.Element {
    const [result, setResult] = useState<OriProbe | null>(initialResult ?? null);
    const { setData, post, processing } = useHttp<{ ori_index: string }, OriProbe>({ ori_index: oriIndex });

    useEffect(() => {
        setData('ori_index', oriIndex);
    }, [oriIndex, setData]);

    const validate = (): void => {
        post('/admin/municipalities/validate-ori', {
            onSuccess: (response: unknown) => setResult(normalizeProbe(response)),
        });
    };

    return (
        <div className="space-y-3">
            <Button type="button" variant="outline" size="sm" onClick={validate} disabled={processing || oriIndex.trim() === ''}>
                {processing ? <Loader2 className="h-4 w-4 animate-spin" /> : <Search className="h-4 w-4" />}
                {processing ? 'Valideren…' : 'Valideer ORI'}
            </Button>

            {result && (
                <div className="text-sm">
                    {result.error ? (
                        <p className="flex items-center gap-1.5 text-red-700">
                            <XCircle className="h-4 w-4" />
                            {result.error}
                        </p>
                    ) : result.exists ? (
                        <div className="space-y-0.5 text-green-700">
                            <p className="flex items-center gap-1.5">
                                <CheckCircle2 className="h-4 w-4" />
                                Index gevonden{result.meeting_count !== null ? ` — ${result.meeting_count.toLocaleString('nl-NL')} vergaderingen` : ''}
                            </p>
                            {result.latest_meeting && (
                                <p className="pl-5.5 text-muted-foreground">
                                    Laatste: {result.latest_meeting.name ?? 'Onbekend'}
                                    {result.latest_meeting.date ? ` (${formatDate(result.latest_meeting.date)})` : ''}
                                </p>
                            )}
                        </div>
                    ) : (
                        <p className="flex items-center gap-1.5 text-yellow-700">
                            <AlertTriangle className="h-4 w-4" />
                            Index niet gevonden.
                        </p>
                    )}
                </div>
            )}
        </div>
    );
}
