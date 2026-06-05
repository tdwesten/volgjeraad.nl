<?php

use App\Actions\Videos\ConfirmMeetingVideo;
use App\Enums\VideoStatus;
use App\Jobs\ProcessMeetingVideoJob;
use App\Models\Meeting;
use App\Models\MeetingVideo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;

uses(RefreshDatabase::class);

test('confirming a candidate matches the video, sets confirmed_at and dispatches processing', function (): void {
    Bus::fake();

    $meeting = Meeting::factory()->summarizable()->create();
    $video = MeetingVideo::factory()->create([
        'meeting_id' => $meeting->id,
        'youtube_video_id' => null,
        'status' => VideoStatus::NeedsConfirmation->value,
        'candidates' => [
            ['videoId' => 'aaa11111111', 'title' => 'Raad A', 'publishedAt' => '2026-06-04T19:00:00+00:00'],
            ['videoId' => 'bbb22222222', 'title' => 'Raad B', 'publishedAt' => '2026-06-04T20:00:00+00:00'],
        ],
    ]);

    $confirmed = app(ConfirmMeetingVideo::class)->handle($video, 'bbb22222222');

    expect($confirmed->status)->toBe(VideoStatus::Matched);
    expect($confirmed->youtube_video_id)->toBe('bbb22222222');
    expect($confirmed->confirmed_at)->not->toBeNull();
    expect($confirmed->video_url)->toBe('https://www.youtube.com/watch?v=bbb22222222');

    Bus::assertDispatched(ProcessMeetingVideoJob::class, fn ($job) => $job->meetingId === $meeting->id);
});

test('confirming a video_id outside the candidate list is rejected', function (): void {
    Bus::fake();

    $meeting = Meeting::factory()->summarizable()->create();
    $video = MeetingVideo::factory()->create([
        'meeting_id' => $meeting->id,
        'status' => VideoStatus::NeedsConfirmation->value,
        'candidates' => [['videoId' => 'aaa11111111', 'title' => 'Raad A', 'publishedAt' => '2026-06-04T19:00:00+00:00']],
    ]);

    expect(fn () => app(ConfirmMeetingVideo::class)->handle($video, 'zzz99999999'))
        ->toThrow(InvalidArgumentException::class);

    Bus::assertNotDispatched(ProcessMeetingVideoJob::class);
});
