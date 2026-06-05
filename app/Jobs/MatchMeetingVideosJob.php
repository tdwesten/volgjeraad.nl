<?php

namespace App\Jobs;

use App\Enums\MeetingType;
use App\Enums\VideoStatus;
use App\Models\Meeting;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class MatchMeetingVideosJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        $maxFindDays = (int) config('volgjeraad.youtube.max_find_days');
        $maxTranscriptAttempts = (int) config('volgjeraad.youtube.max_transcript_attempts');
        $cutoff = now()->subDays($maxFindDays)->startOfDay();

        Meeting::query()
            ->where('type', MeetingType::Council)
            ->whereNotNull('starts_at')
            ->whereHas('municipality', fn ($q) => $q->whereNotNull('settings->youtube_channel_id'))
            ->where(function ($query) use ($cutoff, $maxTranscriptAttempts): void {
                // No video yet — only within the find window
                $query->where(function ($q) use ($cutoff): void {
                    $q->whereDoesntHave('video')
                        ->where('starts_at', '>=', $cutoff);
                })
                // Has a video in an eligible status
                    ->orWhereHas('video', function ($q) use ($cutoff, $maxTranscriptAttempts): void {
                        $q->where(function ($inner) use ($cutoff): void {
                            // Search still pending/not found, within find window
                            $inner->whereIn('status', [VideoStatus::Pending->value, VideoStatus::NotFound->value])
                                ->where(function ($d) use ($cutoff): void {
                                    $d->whereNull('last_attempt_at')
                                        ->orWhere('last_attempt_at', '>=', $cutoff);
                                });
                        })->orWhere(function ($inner) use ($maxTranscriptAttempts): void {
                            // Matched or failed-with-video, still has transcript budget
                            $inner->whereIn('status', [VideoStatus::Matched->value, VideoStatus::Failed->value])
                                ->whereNotNull('youtube_video_id')
                                ->whereColumn('transcript_attempts', '<', $maxTranscriptAttempts);
                        });
                    });
            })
            ->whereNull('summarized_at')
            ->select('id')
            ->chunkById(100, function ($meetings): void {
                foreach ($meetings as $meeting) {
                    ProcessMeetingVideoJob::dispatch($meeting->id);
                }
            });
    }
}
