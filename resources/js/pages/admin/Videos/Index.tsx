import AdminLayout from '@/layouts/AdminLayout';
import { router } from '@inertiajs/react';
import { Check } from 'lucide-react';

interface Candidate {
    videoId: string;
    title: string;
    publishedAt: string;
}

interface VideoRow {
    id: number;
    match_confidence: number | null;
    match_reason: string | null;
    candidates: Candidate[];
    meeting: {
        id: number;
        name: string;
        starts_at: string | null;
        municipality: { id: number; name: string; slug: string };
    } | null;
}

interface Props {
    videos: VideoRow[];
}

export default function Index({ videos }: Props): JSX.Element {
    function confirm(videoRowId: number, videoId: string): void {
        router.post(`/admin/videos/${videoRowId}/confirm`, { video_id: videoId });
    }

    return (
        <AdminLayout>
            <div className="space-y-8">
                <h1 className="text-2xl font-bold">Video's bevestigen</h1>

                {videos.length === 0 && (
                    <p className="flex items-center gap-2 text-sm text-muted-foreground">
                        <Check className="h-4 w-4" />
                        Niets te bevestigen.
                    </p>
                )}

                <ul className="space-y-6">
                    {videos.map((video) => (
                        <li key={video.id} className="rounded-lg border border-border p-4">
                            <p className="font-semibold">{video.meeting?.name ?? 'Onbekende vergadering'}</p>
                            <p className="text-xs text-muted-foreground">
                                {video.meeting?.municipality.name} · confidence {video.match_confidence ?? '—'}
                            </p>
                            {video.match_reason && <p className="mt-1 text-sm">{video.match_reason}</p>}

                            <ul className="mt-3 space-y-2">
                                {video.candidates.map((candidate) => (
                                    <li key={candidate.videoId} className="flex items-center justify-between gap-4">
                                        <span className="text-sm">
                                            {candidate.title} ({candidate.publishedAt})
                                        </span>
                                        <button
                                            type="button"
                                            onClick={() => confirm(video.id, candidate.videoId)}
                                            className="inline-flex items-center gap-1.5 rounded-md bg-primary px-3 py-1 text-sm text-primary-foreground hover:opacity-90"
                                        >
                                            <Check className="h-4 w-4" />
                                            Bevestig
                                        </button>
                                    </li>
                                ))}
                            </ul>
                        </li>
                    ))}
                </ul>
            </div>
        </AdminLayout>
    );
}
