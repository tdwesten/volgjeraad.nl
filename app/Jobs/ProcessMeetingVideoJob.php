<?php

namespace App\Jobs;

use App\Actions\Logging\RecordProcessingEvent;
use App\Actions\Summaries\ResolveMeetingSummarySources;
use App\Actions\Videos\FetchMeetingTranscript;
use App\Actions\Videos\FindMeetingVideo;
use App\Enums\VideoStatus;
use App\Models\Meeting;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\Middleware\ThrottlesExceptions;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessMeetingVideoJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $meetingId) {}

    public function handle(
        FindMeetingVideo $find,
        FetchMeetingTranscript $fetch,
        ResolveMeetingSummarySources $resolve,
        RecordProcessingEvent $log,
    ): void {
        Log::info('ProcessMeetingVideoJob gestart', ['meeting_id' => $this->meetingId]);

        $meeting = Meeting::with('video')->findOrFail($this->meetingId);
        $video = $meeting->video;
        $maxAttempts = (int) config('volgjeraad.youtube.max_transcript_attempts');

        // Bekende video (matched, of failed-transcript mét video) met resterend
        // transcript-budget → (re)transcribe. FetchMeetingTranscript roept zelf de gate aan.
        if ($video !== null
            && $video->youtube_video_id !== null
            && in_array($video->status, [VideoStatus::Matched, VideoStatus::Failed], true)
            && $video->transcript_attempts < $maxAttempts) {
            $fetch->handle($video);

            return;
        }

        // Wacht op menselijke bevestiging; mogelijk is de wachttijd verstreken → gate.
        if ($video?->status === VideoStatus::NeedsConfirmation) {
            $log->handle($meeting, 'video_match', 'warning', 'Video wacht op handmatige bevestiging');
            $resolve->handle($meeting->fresh());

            return;
        }

        // Transcript definitief opgegeven (attempt-limiet) → gate (PDF-only indien klaar).
        if ($video?->status === VideoStatus::Failed && $video->transcript_attempts >= $maxAttempts) {
            $log->handle($meeting, 'video_match', 'warning', 'Transcript definitief opgegeven na '.$video->transcript_attempts.' pogingen');
            $resolve->handle($meeting->fresh());

            return;
        }

        // Nog geen bruikbare match (geen video / not_found / pending) → zoeken.
        $matched = $find->handle($meeting);
        if ($matched?->status === VideoStatus::Matched) {
            $log->handle($meeting, 'video_match', 'success', "YouTube-video gematcht: {$matched->youtube_video_id}");
            $fetch->handle($matched);

            return;
        }

        // Geen match → mogelijk wachttijd verstreken → gate (PDF-only indien klaar).
        $log->handle($meeting, 'video_match', 'info', 'Geen YouTube-video gevonden');
        $resolve->handle($meeting->fresh());
    }

    /**
     * @return array<int, mixed>
     */
    public function middleware(): array
    {
        return [
            new RateLimited('youtube'),
            (new ThrottlesExceptions(5, 300))->backoff(60)->by('youtube'),
        ];
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function failed(Throwable $exception): void
    {
        Log::error('ProcessMeetingVideoJob mislukt', [
            'meeting_id' => $this->meetingId,
            'exception' => $exception->getMessage(),
        ]);
    }
}
