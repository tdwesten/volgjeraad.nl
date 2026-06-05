<?php

use App\Enums\MeetingType;
use App\Enums\VideoStatus;
use App\Jobs\MatchMeetingVideosJob;
use App\Jobs\ProcessMeetingVideoJob;
use App\Models\Meeting;
use App\Models\MeetingVideo;
use App\Models\Municipality;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;

uses(RefreshDatabase::class);

function municipalityWithChannel(): Municipality
{
    return Municipality::factory()->create([
        'active' => true,
        'settings' => ['youtube_channel_id' => 'UCtest'],
    ]);
}

test('dispatches ProcessMeetingVideoJob for council meeting without a video record', function (): void {
    Bus::fake();

    $municipality = municipalityWithChannel();
    $meeting = Meeting::factory()->summarizable()->create([
        'municipality_id' => $municipality->id,
        'type' => MeetingType::Council,
        'starts_at' => now()->subDays(2),
        'summarized_at' => null,
    ]);

    (new MatchMeetingVideosJob)->handle();

    Bus::assertDispatched(ProcessMeetingVideoJob::class, fn ($job) => $job->meetingId === $meeting->id);
});

test('dispatches for meeting with Pending video within find window', function (): void {
    Bus::fake();

    $municipality = municipalityWithChannel();
    $meeting = Meeting::factory()->summarizable()->create([
        'municipality_id' => $municipality->id,
        'type' => MeetingType::Council,
        'starts_at' => now()->subDays(2),
        'summarized_at' => null,
    ]);
    MeetingVideo::factory()->create([
        'meeting_id' => $meeting->id,
        'status' => VideoStatus::Pending,
        'last_attempt_at' => now()->subDay(),
    ]);

    (new MatchMeetingVideosJob)->handle();

    Bus::assertDispatched(ProcessMeetingVideoJob::class, fn ($job) => $job->meetingId === $meeting->id);
});

test('dispatches for Matched video with transcript budget remaining', function (): void {
    Bus::fake();

    $municipality = municipalityWithChannel();
    $meeting = Meeting::factory()->summarizable()->create([
        'municipality_id' => $municipality->id,
        'type' => MeetingType::Council,
        'starts_at' => now()->subDays(2),
        'summarized_at' => null,
    ]);
    MeetingVideo::factory()->create([
        'meeting_id' => $meeting->id,
        'status' => VideoStatus::Matched,
        'youtube_video_id' => 'abc1234567',
        'transcript_attempts' => 1,
    ]);

    (new MatchMeetingVideosJob)->handle();

    Bus::assertDispatched(ProcessMeetingVideoJob::class, fn ($job) => $job->meetingId === $meeting->id);
});

test('skips Transcribed meeting', function (): void {
    Bus::fake();

    $municipality = municipalityWithChannel();
    $meeting = Meeting::factory()->summarizable()->create([
        'municipality_id' => $municipality->id,
        'type' => MeetingType::Council,
        'starts_at' => now()->subDays(2),
        'summarized_at' => null,
    ]);
    MeetingVideo::factory()->transcribed()->create(['meeting_id' => $meeting->id]);

    (new MatchMeetingVideosJob)->handle();

    Bus::assertNotDispatched(ProcessMeetingVideoJob::class);
});

test('skips already summarized meeting', function (): void {
    Bus::fake();

    $municipality = municipalityWithChannel();
    Meeting::factory()->summarizable()->create([
        'municipality_id' => $municipality->id,
        'type' => MeetingType::Council,
        'starts_at' => now()->subDays(2),
        'summarized_at' => now(),
    ]);

    (new MatchMeetingVideosJob)->handle();

    Bus::assertNotDispatched(ProcessMeetingVideoJob::class);
});

test('skips NeedsConfirmation video (human must act)', function (): void {
    Bus::fake();

    $municipality = municipalityWithChannel();
    $meeting = Meeting::factory()->summarizable()->create([
        'municipality_id' => $municipality->id,
        'type' => MeetingType::Council,
        'starts_at' => now()->subDays(2),
        'summarized_at' => null,
    ]);
    MeetingVideo::factory()->create([
        'meeting_id' => $meeting->id,
        'status' => VideoStatus::NeedsConfirmation,
    ]);

    (new MatchMeetingVideosJob)->handle();

    Bus::assertNotDispatched(ProcessMeetingVideoJob::class);
});

test('skips municipality without youtube_channel_id', function (): void {
    Bus::fake();

    $municipality = Municipality::factory()->create(['active' => true, 'settings' => []]);
    Meeting::factory()->summarizable()->create([
        'municipality_id' => $municipality->id,
        'type' => MeetingType::Council,
        'starts_at' => now()->subDays(2),
        'summarized_at' => null,
    ]);

    (new MatchMeetingVideosJob)->handle();

    Bus::assertNotDispatched(ProcessMeetingVideoJob::class);
});

test('dispatches for Failed video with youtube_video_id and remaining transcript budget', function (): void {
    Bus::fake();

    $municipality = municipalityWithChannel();
    $meeting = Meeting::factory()->summarizable()->create([
        'municipality_id' => $municipality->id,
        'type' => MeetingType::Council,
        'starts_at' => now()->subDays(2),
        'summarized_at' => null,
    ]);
    MeetingVideo::factory()->create([
        'meeting_id' => $meeting->id,
        'status' => VideoStatus::Failed,
        'youtube_video_id' => 'abc1234567',
        'transcript_attempts' => 2,
    ]);

    (new MatchMeetingVideosJob)->handle();

    Bus::assertDispatched(ProcessMeetingVideoJob::class, fn ($job) => $job->meetingId === $meeting->id);
});

test('skips Failed video at transcript attempt limit', function (): void {
    Bus::fake();

    $maxAttempts = (int) config('volgjeraad.youtube.max_transcript_attempts');
    $municipality = municipalityWithChannel();
    $meeting = Meeting::factory()->summarizable()->create([
        'municipality_id' => $municipality->id,
        'type' => MeetingType::Council,
        'starts_at' => now()->subDays(2),
        'summarized_at' => null,
    ]);
    MeetingVideo::factory()->create([
        'meeting_id' => $meeting->id,
        'status' => VideoStatus::Failed,
        'youtube_video_id' => 'abc1234567',
        'transcript_attempts' => $maxAttempts,
    ]);

    (new MatchMeetingVideosJob)->handle();

    Bus::assertNotDispatched(ProcessMeetingVideoJob::class);
});
