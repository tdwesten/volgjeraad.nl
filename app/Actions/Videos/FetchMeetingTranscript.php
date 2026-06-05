<?php

namespace App\Actions\Videos;

use App\Actions\Summaries\DispatchMeetingSummariesIfReady;
use App\Enums\VideoStatus;
use App\Models\MeetingVideo;
use App\Services\Transcript\TranscriptProvider;
use Illuminate\Support\Facades\Log;
use Throwable;

class FetchMeetingTranscript
{
    public function __construct(
        private TranscriptProvider $transcriptProvider,
        private DispatchMeetingSummariesIfReady $dispatchMeetingSummaries,
    ) {}

    public function handle(MeetingVideo $video): void
    {
        if ($video->youtube_video_id === null) {
            return;
        }

        try {
            $result = $this->transcriptProvider->fetch($video->youtube_video_id, 'nl');
        } catch (Throwable $e) {
            Log::warning('fetch_meeting_transcript failed', [
                'meeting_video_id' => $video->id,
                'error' => $e->getMessage(),
                'class' => get_class($e),
            ]);
            $video->update([
                'status' => VideoStatus::Failed->value,
                'transcript_error' => $e->getMessage(),
                'transcript_attempts' => $video->transcript_attempts + 1,
                'last_attempt_at' => now(),
            ]);
            // Mogelijk definitief opgegeven (attempt-limiet) → laat de gate beslissen.
            $this->dispatchMeetingSummaries->handle($video->meeting);

            return;
        }

        if (trim($result->text) === '') {
            $video->update([
                'status' => VideoStatus::Failed->value,
                'transcript_error' => 'empty_transcript',
                'transcript_attempts' => $video->transcript_attempts + 1,
                'last_attempt_at' => now(),
            ]);
            $this->dispatchMeetingSummaries->handle($video->meeting);

            return;
        }

        $video->update([
            'transcript_text' => $result->text,
            'transcript_source' => $result->source,
            'transcript_error' => null,
            'transcript_fetched_at' => now(),
            'status' => VideoStatus::Transcribed->value,
            'transcript_attempts' => $video->transcript_attempts + 1,
            'last_attempt_at' => now(),
        ]);

        // Transcript binnen → resolutie klaar → meeting-samenvattingen (mét transcript).
        $this->dispatchMeetingSummaries->handle($video->meeting);
    }
}
