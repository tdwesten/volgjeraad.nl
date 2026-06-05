<?php

namespace App\Jobs;

use App\Actions\Summaries\DispatchMeetingSummariesIfReady;
use App\Actions\Videos\FetchMeetingTranscript;
use App\Actions\Videos\FindMeetingVideo;
use App\Enums\VideoStatus;
use App\Models\Meeting;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\Middleware\ThrottlesExceptions;
use Throwable;

class ProcessMeetingVideoJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $meetingId) {}

    public function handle(
        FindMeetingVideo $find,
        FetchMeetingTranscript $fetch,
        DispatchMeetingSummariesIfReady $dispatchSummaries,
    ): void {
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
            $dispatchSummaries->handle($meeting);

            return;
        }

        // Transcript definitief opgegeven (attempt-limiet) → gate (PDF-only indien klaar).
        if ($video?->status === VideoStatus::Failed && $video->transcript_attempts >= $maxAttempts) {
            $dispatchSummaries->handle($meeting);

            return;
        }

        // Nog geen bruikbare match (geen video / not_found / pending) → zoeken.
        $matched = $find->handle($meeting);
        if ($matched?->status === VideoStatus::Matched) {
            $fetch->handle($matched);

            return;
        }

        // Geen match → mogelijk wachttijd verstreken → gate (PDF-only indien klaar).
        $dispatchSummaries->handle($meeting);
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

    public function failed(Throwable $exception): void {}
}
