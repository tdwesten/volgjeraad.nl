import AdminLayout from '@/layouts/AdminLayout';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
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

    const rows = videos.flatMap((video) =>
        video.candidates.map((candidate) => ({ video, candidate })),
    );

    return (
        <AdminLayout>
            <div className="space-y-6">
                <h1 className="text-2xl font-bold">Video's bevestigen</h1>

                {videos.length === 0 ? (
                    <p className="flex items-center gap-2 text-sm text-muted-foreground">
                        <Check className="h-4 w-4" />
                        Niets te bevestigen.
                    </p>
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Vergadering</TableHead>
                                <TableHead>Gemeente</TableHead>
                                <TableHead>Confidence</TableHead>
                                <TableHead>Video</TableHead>
                                <TableHead>Gepubliceerd</TableHead>
                                <TableHead></TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {rows.map(({ video, candidate }) => (
                                <TableRow key={`${video.id}-${candidate.videoId}`}>
                                    <TableCell className="font-medium">
                                        {video.meeting?.name ?? 'Onbekende vergadering'}
                                    </TableCell>
                                    <TableCell>{video.meeting?.municipality.name ?? '—'}</TableCell>
                                    <TableCell className="text-sm text-muted-foreground">
                                        {video.match_confidence ?? '—'}
                                        {video.match_reason && (
                                            <span className="block text-xs">{video.match_reason}</span>
                                        )}
                                    </TableCell>
                                    <TableCell className="text-sm">{candidate.title}</TableCell>
                                    <TableCell className="text-sm text-muted-foreground">
                                        {candidate.publishedAt}
                                    </TableCell>
                                    <TableCell>
                                        <Button
                                            size="sm"
                                            onClick={() => confirm(video.id, candidate.videoId)}
                                        >
                                            <Check className="h-4 w-4" />
                                            Bevestig
                                        </Button>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}
            </div>
        </AdminLayout>
    );
}
