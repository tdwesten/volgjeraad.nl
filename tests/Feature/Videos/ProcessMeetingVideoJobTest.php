<?php

use App\Actions\Logging\RecordProcessingEvent;
use App\Actions\Summaries\ResolveMeetingSummarySources;
use App\Actions\Videos\FetchMeetingTranscript;
use App\Actions\Videos\FindMeetingVideo;
use App\Enums\VideoStatus;
use App\Jobs\ProcessMeetingVideoJob;
use App\Models\Meeting;
use App\Models\MeetingVideo;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['volgjeraad.youtube.max_transcript_attempts' => 4]);
});

test('matched video goes straight to transcript fetch', function (): void {
    $meeting = Meeting::factory()->summarizable()->create();
    $video = MeetingVideo::factory()->create([
        'meeting_id' => $meeting->id,
        'youtube_video_id' => 'dQw4w9WgXcQ',
        'status' => VideoStatus::Matched->value,
        'transcript_attempts' => 0,
    ]);

    $this->mock(FindMeetingVideo::class)->shouldReceive('handle')->never();
    $fetch = $this->mock(FetchMeetingTranscript::class);
    $fetch->shouldReceive('handle')->once()->with(Mockery::on(fn ($v) => $v->id === $video->id));
    $dispatch = $this->mock(ResolveMeetingSummarySources::class);
    $dispatch->shouldReceive('handle')->never();

    app(ProcessMeetingVideoJob::class, ['meetingId' => $meeting->id])->handle($this->app->make(FindMeetingVideo::class), $fetch, $dispatch, app(RecordProcessingEvent::class));
});

test('failed transcript with a known video under the limit retries the fetch', function (): void {
    $meeting = Meeting::factory()->summarizable()->create();
    $video = MeetingVideo::factory()->create([
        'meeting_id' => $meeting->id,
        'youtube_video_id' => 'dQw4w9WgXcQ',
        'status' => VideoStatus::Failed->value,
        'transcript_attempts' => 1,
    ]);

    $this->mock(FindMeetingVideo::class)->shouldReceive('handle')->never();
    $fetch = $this->mock(FetchMeetingTranscript::class);
    $fetch->shouldReceive('handle')->once()->with(Mockery::on(fn ($v) => $v->id === $video->id));
    $dispatch = $this->mock(ResolveMeetingSummarySources::class);
    $dispatch->shouldReceive('handle')->never();

    app(ProcessMeetingVideoJob::class, ['meetingId' => $meeting->id])->handle($this->app->make(FindMeetingVideo::class), $fetch, $dispatch, app(RecordProcessingEvent::class));
});

test('failed transcript at the attempt limit is skipped and re-evaluates the gate', function (): void {
    $meeting = Meeting::factory()->summarizable()->create();
    MeetingVideo::factory()->create([
        'meeting_id' => $meeting->id,
        'youtube_video_id' => 'dQw4w9WgXcQ',
        'status' => VideoStatus::Failed->value,
        'transcript_attempts' => 4,
    ]);

    $this->mock(FindMeetingVideo::class)->shouldReceive('handle')->never();
    $this->mock(FetchMeetingTranscript::class)->shouldReceive('handle')->never();
    $dispatch = $this->mock(ResolveMeetingSummarySources::class);
    $dispatch->shouldReceive('handle')->once()->with(Mockery::on(fn ($m) => $m->id === $meeting->id));

    app(ProcessMeetingVideoJob::class, ['meetingId' => $meeting->id])
        ->handle($this->app->make(FindMeetingVideo::class), $this->app->make(FetchMeetingTranscript::class), $dispatch, app(RecordProcessingEvent::class));
});

test('needs_confirmation video awaits a human but still re-evaluates the gate', function (): void {
    $meeting = Meeting::factory()->summarizable()->create();
    MeetingVideo::factory()->create([
        'meeting_id' => $meeting->id,
        'youtube_video_id' => null,
        'status' => VideoStatus::NeedsConfirmation->value,
    ]);

    $this->mock(FindMeetingVideo::class)->shouldReceive('handle')->never();
    $this->mock(FetchMeetingTranscript::class)->shouldReceive('handle')->never();
    $dispatch = $this->mock(ResolveMeetingSummarySources::class);
    $dispatch->shouldReceive('handle')->once();

    app(ProcessMeetingVideoJob::class, ['meetingId' => $meeting->id])
        ->handle($this->app->make(FindMeetingVideo::class), $this->app->make(FetchMeetingTranscript::class), $dispatch, app(RecordProcessingEvent::class));
});

test('meeting without a video searches, and a fresh match is transcribed in the same run', function (): void {
    $meeting = Meeting::factory()->summarizable()->create();
    $matched = new MeetingVideo([
        'meeting_id' => $meeting->id,
        'youtube_video_id' => 'dQw4w9WgXcQ',
        'status' => VideoStatus::Matched->value,
    ]);

    $find = $this->mock(FindMeetingVideo::class);
    $find->shouldReceive('handle')->once()->with(Mockery::on(fn ($m) => $m->id === $meeting->id))->andReturn($matched);
    $fetch = $this->mock(FetchMeetingTranscript::class);
    $fetch->shouldReceive('handle')->once()->with($matched);
    $dispatch = $this->mock(ResolveMeetingSummarySources::class);
    $dispatch->shouldReceive('handle')->never();

    app(ProcessMeetingVideoJob::class, ['meetingId' => $meeting->id])->handle($find, $fetch, $dispatch, app(RecordProcessingEvent::class));
});

test('not_found video re-searches and re-evaluates the gate when no match is found', function (): void {
    $meeting = Meeting::factory()->summarizable()->create();
    MeetingVideo::factory()->create([
        'meeting_id' => $meeting->id,
        'youtube_video_id' => null,
        'status' => VideoStatus::NotFound->value,
        'match_attempts' => 1,
    ]);

    $find = $this->mock(FindMeetingVideo::class);
    $find->shouldReceive('handle')->once()->andReturnNull();
    $this->mock(FetchMeetingTranscript::class)->shouldReceive('handle')->never();
    $dispatch = $this->mock(ResolveMeetingSummarySources::class);
    $dispatch->shouldReceive('handle')->once();

    app(ProcessMeetingVideoJob::class, ['meetingId' => $meeting->id])
        ->handle($find, $this->app->make(FetchMeetingTranscript::class), $dispatch, app(RecordProcessingEvent::class));
});
