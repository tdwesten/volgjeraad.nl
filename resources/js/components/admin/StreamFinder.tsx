import { Button } from '@/components/ui/button';
import { useHttp } from '@inertiajs/react';
import { Loader2, Sparkles } from 'lucide-react';
import { useEffect, useState } from 'react';

export interface StreamResult {
    channel_id: string | null;
    channel_title: string | null;
    channel_url: string | null;
    confidence: number;
    reason: string;
}

function normalizeStream(response: unknown): StreamResult | null {
    if (response && typeof response === 'object') {
        const candidate = ('confidence' in response ? response : (response as { data?: unknown }).data) as StreamResult | undefined;
        if (candidate && typeof candidate === 'object' && 'confidence' in candidate) {
            return candidate;
        }
    }
    return null;
}

interface Props {
    name: string;
    onUse: (channelId: string) => void;
}

export default function StreamFinder({ name, onUse }: Props): JSX.Element {
    const [result, setResult] = useState<StreamResult | null>(null);
    const { setData, post, processing } = useHttp<{ name: string }, StreamResult>({ name });

    useEffect(() => {
        setData('name', name);
    }, [name, setData]);

    const search = (): void => {
        post('/admin/municipalities/find-stream', {
            onSuccess: (response: unknown) => setResult(normalizeStream(response)),
        });
    };

    return (
        <div className="space-y-3">
            <Button type="button" variant="outline" size="sm" onClick={search} disabled={processing || name.trim() === ''}>
                {processing ? <Loader2 className="h-4 w-4 animate-spin" /> : <Sparkles className="h-4 w-4" />}
                {processing ? 'Zoeken met AI…' : 'Zoek stream met AI'}
            </Button>
            {processing && (
                <p className="text-xs text-muted-foreground">Dit kan enkele seconden duren…</p>
            )}

            {result && !processing && (
                <div className="rounded-md border border-border p-3 text-sm">
                    {result.channel_id ? (
                        <div className="space-y-1.5">
                            <p className="font-medium">{result.channel_title ?? result.channel_id}</p>
                            {result.channel_url && (
                                <a
                                    href={result.channel_url}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="block text-primary hover:underline"
                                >
                                    {result.channel_url}
                                </a>
                            )}
                            <p className="text-muted-foreground">
                                Betrouwbaarheid: {result.confidence}%
                            </p>
                            {result.reason && <p className="text-muted-foreground">{result.reason}</p>}
                            <Button type="button" size="sm" onClick={() => onUse(result.channel_id as string)}>
                                Gebruik dit kanaal
                            </Button>
                        </div>
                    ) : (
                        <p className="text-muted-foreground">
                            Geen kanaal gevonden.{result.reason ? ` ${result.reason}` : ''}
                        </p>
                    )}
                </div>
            )}
        </div>
    );
}
