<?php

namespace App\Actions\Videos;

use App\Enums\VideoStatus;
use App\Jobs\ProcessMeetingVideoJob;
use App\Models\MeetingVideo;
use InvalidArgumentException;

class ConfirmMeetingVideo
{
    public function handle(MeetingVideo $video, string $videoId): MeetingVideo
    {
        $candidateIds = array_map(
            fn (array $candidate): string => (string) ($candidate['videoId'] ?? ''),
            $video->candidates ?? [],
        );

        if (! in_array($videoId, $candidateIds, true)) {
            throw new InvalidArgumentException('Gekozen video_id zit niet in de kandidatenlijst.');
        }

        $video->update([
            'youtube_video_id' => $videoId,
            'video_url' => "https://www.youtube.com/watch?v={$videoId}",
            'status' => VideoStatus::Matched->value,
            'confirmed_at' => now(),
            'match_reason' => 'Handmatig bevestigd door reviewer.',
        ]);

        ProcessMeetingVideoJob::dispatch($video->meeting_id);

        return $video->refresh();
    }
}
