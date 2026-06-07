<?php

namespace App\Actions\Videos;

use App\Actions\Logging\RecordProcessingEvent;
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
        private RecordProcessingEvent $log,
    ) {}

    public function handle(MeetingVideo $video): void
    {
        if ($video->youtube_video_id === null) {
            return;
        }

        // Cache: is dit YouTube-transcript al eerder opgehaald? Hergebruik het dan
        // i.p.v. opnieuw Supadata aan te roepen — dat scheelt credits.
        if ($this->reuseCachedTranscript($video)) {
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

            $this->log->handle($video->meeting, 'transcript', 'error', "Transcript ophalen mislukt: {$e->getMessage()}");

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

            $this->log->handle($video->meeting, 'transcript', 'warning', 'Transcript leeg ontvangen');

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

        $this->log->handle($video->meeting, 'transcript', 'success', "Transcript opgehaald via {$result->source}");

        // Transcript binnen → resolutie klaar → meeting-samenvattingen (mét transcript).
        $this->dispatchMeetingSummaries->handle($video->meeting);
    }

    /**
     * Hergebruik een reeds opgehaald transcript voor hetzelfde YouTube-id (deze
     * rij zelf, of een andere vergadering met dezelfde uitzending) zonder een
     * nieuwe Supadata-aanroep. Geeft true als er hergebruikt is.
     */
    private function reuseCachedTranscript(MeetingVideo $video): bool
    {
        $cached = $this->findCachedTranscript($video);

        if ($cached === null) {
            return false;
        }

        $video->update([
            'transcript_text' => $cached->transcript_text,
            'transcript_source' => $cached->transcript_source,
            'transcript_error' => null,
            'transcript_fetched_at' => $cached->transcript_fetched_at ?? now(),
            'status' => VideoStatus::Transcribed->value,
            'last_attempt_at' => now(),
        ]);

        $this->log->handle($video->meeting, 'transcript', 'success', 'Transcript hergebruikt uit cache (geen Supadata-credit)');

        $this->dispatchMeetingSummaries->handle($video->meeting);

        return true;
    }

    private function findCachedTranscript(MeetingVideo $video): ?MeetingVideo
    {
        // Eigen rij heeft al een transcript (bv. her-verwerken zonder video te wissen).
        if (filled($video->transcript_text)) {
            return $video;
        }

        return MeetingVideo::query()
            ->where('youtube_video_id', $video->youtube_video_id)
            ->whereKeyNot($video->getKey())
            ->whereNotNull('transcript_text')
            ->where('transcript_text', '!=', '')
            ->orderByDesc('transcript_fetched_at')
            ->first();
    }
}
