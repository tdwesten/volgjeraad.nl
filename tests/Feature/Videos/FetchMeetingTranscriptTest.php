<?php

use App\Actions\Videos\FetchMeetingTranscript;
use App\Enums\SummaryLevel;
use App\Enums\VideoStatus;
use App\Jobs\SummarizeMeetingJob;
use App\Models\Meeting;
use App\Models\MeetingVideo;
use App\Models\Municipality;
use App\Services\Transcript\TranscriptProvider;
use App\Services\Transcript\TranscriptResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config([
        'volgjeraad.youtube.max_transcript_attempts' => 4,
        'volgjeraad.youtube.video_wait_hours' => 24,
        'volgjeraad.youtube.notule_recheck_working_days' => 2,
    ]);
});

/**
 * Raadsvergadering mét YouTube-kanaal, ruim voorbij het 24u-videovenster, zodat de
 * resolver het transcript als bron erkent zodra de video is getranscribeerd.
 */
function channelTranscriptMeeting(string $startsAt = '-2 days'): Meeting
{
    $muni = Municipality::factory()->create([
        'launch_date' => now()->subYear(),
        'settings' => ['youtube_channel_id' => 'UC123'],
    ]);

    return Meeting::factory()->council()->summarizable()->create([
        'municipality_id' => $muni->id,
        'starts_at' => now()->parse($startsAt),
    ]);
}

test('stores transcript, sets transcribed status and dispatches re-summarize per level', function (): void {
    Bus::fake();

    $this->mock(TranscriptProvider::class)
        ->shouldReceive('fetch')
        ->once()
        ->with('dQw4w9WgXcQ', 'nl')
        ->andReturn(new TranscriptResult('Voorzitter: open.', 'supadata:auto', 'nl'));

    $meeting = channelTranscriptMeeting();
    $video = MeetingVideo::factory()->create([
        'meeting_id' => $meeting->id,
        'youtube_video_id' => 'dQw4w9WgXcQ',
        'status' => VideoStatus::Matched->value,
        'transcript_attempts' => 0,
    ]);

    app(FetchMeetingTranscript::class)->handle($video);

    $video->refresh();
    expect($video->status)->toBe(VideoStatus::Transcribed);
    expect($video->transcript_text)->toBe('Voorzitter: open.');
    expect($video->transcript_source)->toBe('supadata:auto');
    expect($video->transcript_fetched_at)->not->toBeNull();
    expect($video->transcript_error)->toBeNull();
    expect($video->transcript_attempts)->toBe(1);

    // Transcript binnen → de resolver zet SOURCE_TRANSCRIPT en dispatcht per level.
    Bus::assertDispatched(SummarizeMeetingJob::class, fn ($job) => $job->level === SummaryLevel::Standard);
    Bus::assertDispatched(SummarizeMeetingJob::class, fn ($job) => $job->level === SummaryLevel::Simple);
    Bus::assertDispatchedTimes(SummarizeMeetingJob::class, count(SummaryLevel::cases()));
});

test('provider failure sets failed status with error, increments transcript_attempts, dispatches nothing', function (): void {
    Bus::fake();

    $this->mock(TranscriptProvider::class)
        ->shouldReceive('fetch')
        ->once()
        ->andThrow(new RuntimeException('vendor down'));

    // Council + recent → onder de attempt-limiet, dus nog geen bron geresolveerd.
    $meeting = channelTranscriptMeeting();
    $video = MeetingVideo::factory()->create([
        'meeting_id' => $meeting->id,
        'youtube_video_id' => 'dQw4w9WgXcQ',
        'status' => VideoStatus::Matched->value,
        'transcript_attempts' => 1,
    ]);

    app(FetchMeetingTranscript::class)->handle($video);

    $video->refresh();
    expect($video->status)->toBe(VideoStatus::Failed);
    expect($video->transcript_error)->toContain('vendor down');
    expect($video->transcript_attempts)->toBe(2);
    Bus::assertNotDispatched(SummarizeMeetingJob::class);
});

test('failed transcript at the attempt limit resolves no transcript source and dispatches no summary', function (): void {
    Bus::fake();

    $this->mock(TranscriptProvider::class)
        ->shouldReceive('fetch')
        ->once()
        ->andThrow(new RuntimeException('vendor down'));

    // Video uitgeput zonder transcript én zonder notule → geen bron → geen samenvatting.
    $meeting = channelTranscriptMeeting();
    $video = MeetingVideo::factory()->create([
        'meeting_id' => $meeting->id,
        'youtube_video_id' => 'dQw4w9WgXcQ',
        'status' => VideoStatus::Failed->value,
        'transcript_attempts' => 3, // wordt 4 = limiet → definitief opgegeven
    ]);

    app(FetchMeetingTranscript::class)->handle($video);

    expect($video->fresh()->transcript_attempts)->toBe(4);
    Bus::assertNotDispatched(SummarizeMeetingJob::class);
});

test('empty transcript flags empty_transcript and leaves the meeting unsummarized', function (): void {
    Bus::fake();

    $this->mock(TranscriptProvider::class)
        ->shouldReceive('fetch')
        ->once()
        ->andReturn(new TranscriptResult('', 'supadata:auto', 'nl'));

    $meeting = channelTranscriptMeeting();
    $video = MeetingVideo::factory()->create([
        'meeting_id' => $meeting->id,
        'youtube_video_id' => 'dQw4w9WgXcQ',
        'status' => VideoStatus::Matched->value,
        'transcript_attempts' => 0,
    ]);

    app(FetchMeetingTranscript::class)->handle($video);

    $video->refresh();
    expect($video->status)->toBe(VideoStatus::Failed);
    expect($video->transcript_error)->toBe('empty_transcript');
    expect($video->transcript_text)->toBeNull();
    expect($video->transcript_attempts)->toBe(1);
    Bus::assertNotDispatched(SummarizeMeetingJob::class);
});

test('reuses a cached transcript for the same youtube_video_id without calling the provider', function (): void {
    Bus::fake();

    // Provider mag NIET worden aangeroepen: het transcript bestaat al elders.
    $this->mock(TranscriptProvider::class)->shouldReceive('fetch')->never();

    $earlierMeeting = channelTranscriptMeeting('-1 week');
    MeetingVideo::factory()->create([
        'meeting_id' => $earlierMeeting->id,
        'youtube_video_id' => 'dQw4w9WgXcQ',
        'status' => VideoStatus::Transcribed->value,
        'transcript_text' => 'Voorzitter: hergebruikt.',
        'transcript_source' => 'supadata:auto',
        'transcript_fetched_at' => now()->subWeek(),
        'transcript_attempts' => 1,
    ]);

    $meeting = channelTranscriptMeeting();
    $video = MeetingVideo::factory()->create([
        'meeting_id' => $meeting->id,
        'youtube_video_id' => 'dQw4w9WgXcQ',
        'status' => VideoStatus::Matched->value,
        'transcript_attempts' => 0,
    ]);

    app(FetchMeetingTranscript::class)->handle($video);

    $video->refresh();
    expect($video->status)->toBe(VideoStatus::Transcribed);
    expect($video->transcript_text)->toBe('Voorzitter: hergebruikt.');
    // Geen Supadata-aanroep → geen extra poging geteld.
    expect($video->transcript_attempts)->toBe(0);
    Bus::assertDispatchedTimes(SummarizeMeetingJob::class, count(SummaryLevel::cases()));
});

test('reuses the rows own transcript without calling the provider', function (): void {
    Bus::fake();

    $this->mock(TranscriptProvider::class)->shouldReceive('fetch')->never();

    $meeting = channelTranscriptMeeting();
    $video = MeetingVideo::factory()->create([
        'meeting_id' => $meeting->id,
        'youtube_video_id' => 'dQw4w9WgXcQ',
        'status' => VideoStatus::Matched->value,
        'transcript_text' => 'Voorzitter: al opgehaald.',
        'transcript_source' => 'supadata:auto',
        'transcript_attempts' => 1,
    ]);

    app(FetchMeetingTranscript::class)->handle($video);

    $video->refresh();
    expect($video->status)->toBe(VideoStatus::Transcribed);
    expect($video->transcript_attempts)->toBe(1);
    Bus::assertDispatchedTimes(SummarizeMeetingJob::class, count(SummaryLevel::cases()));
});

test('no youtube_video_id is a no-op', function (): void {
    Bus::fake();
    $this->mock(TranscriptProvider::class)->shouldReceive('fetch')->never();

    $meeting = channelTranscriptMeeting();
    $video = MeetingVideo::factory()->create([
        'meeting_id' => $meeting->id,
        'youtube_video_id' => null,
        'status' => VideoStatus::NeedsConfirmation->value,
    ]);

    app(FetchMeetingTranscript::class)->handle($video);

    expect($video->fresh()->status)->toBe(VideoStatus::NeedsConfirmation);
    Bus::assertNotDispatched(SummarizeMeetingJob::class);
});
