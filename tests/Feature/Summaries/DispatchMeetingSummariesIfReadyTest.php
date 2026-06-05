<?php

use App\Actions\Summaries\DispatchMeetingSummariesIfReady;
use App\Enums\SummaryLevel;
use App\Jobs\SummarizeMeetingJob;
use App\Models\AgendaItem;
use App\Models\Meeting;
use App\Models\MeetingVideo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config([
        'volgjeraad.youtube.max_transcript_attempts' => 4,
        'volgjeraad.youtube.transcript_wait_days' => 7,
    ]);
});

/** Maak een raadsvergadering met alle media binnen (agendapunt mét attachments_fetched_at). */
function readyCouncilMeeting(string $startsAt = '-1 day'): Meeting
{
    $meeting = Meeting::factory()->council()->summarizable()->create([
        'starts_at' => now()->parse($startsAt),
    ]);
    AgendaItem::factory()->create([
        'meeting_id' => $meeting->id,
        'attachments_fetched_at' => now(),
    ]);

    return $meeting->fresh();
}

test('does not dispatch while the transcript is still unresolved', function (): void {
    Bus::fake();
    $meeting = readyCouncilMeeting('-2 days'); // binnen wachttijd, geen video

    app(DispatchMeetingSummariesIfReady::class)->handle($meeting);

    Bus::assertNotDispatched(SummarizeMeetingJob::class);
});

test('dispatches one job per level once the transcript is transcribed', function (): void {
    Bus::fake();
    $meeting = readyCouncilMeeting('-2 days');
    MeetingVideo::factory()->transcribed()->create(['meeting_id' => $meeting->id]);

    app(DispatchMeetingSummariesIfReady::class)->handle($meeting->fresh());

    Bus::assertDispatchedTimes(SummarizeMeetingJob::class, count(SummaryLevel::cases()));
});

test('dispatches without a transcript once the wait window elapses', function (): void {
    Bus::fake();
    $meeting = readyCouncilMeeting('-8 days'); // wachttijd verstreken, nog steeds geen video

    app(DispatchMeetingSummariesIfReady::class)->handle($meeting);

    Bus::assertDispatchedTimes(SummarizeMeetingJob::class, count(SummaryLevel::cases()));
});

test('a non-council meeting is resolved immediately (no transcript expected)', function (): void {
    Bus::fake();
    $meeting = Meeting::factory()->summarizable()->create([
        'type' => 'committee',
        'starts_at' => now()->subDay(),
    ]);
    AgendaItem::factory()->create(['meeting_id' => $meeting->id, 'attachments_fetched_at' => now()]);

    app(DispatchMeetingSummariesIfReady::class)->handle($meeting->fresh());

    Bus::assertDispatchedTimes(SummarizeMeetingJob::class, count(SummaryLevel::cases()));
});

test('waits for all media before dispatching, even with a transcript present', function (): void {
    Bus::fake();
    $meeting = Meeting::factory()->council()->summarizable()->create(['starts_at' => now()->subDay()]);
    AgendaItem::factory()->create(['meeting_id' => $meeting->id, 'attachments_fetched_at' => null]);
    MeetingVideo::factory()->transcribed()->create(['meeting_id' => $meeting->id]);

    app(DispatchMeetingSummariesIfReady::class)->handle($meeting->fresh());

    Bus::assertNotDispatched(SummarizeMeetingJob::class);
});

test('an already summarized meeting is not dispatched again', function (): void {
    Bus::fake();
    $meeting = readyCouncilMeeting('-8 days');
    $meeting->update(['summarized_at' => now()]);

    app(DispatchMeetingSummariesIfReady::class)->handle($meeting->fresh());

    Bus::assertNotDispatched(SummarizeMeetingJob::class);
});

test('a non-summarizable meeting is skipped', function (): void {
    Bus::fake();
    $meeting = Meeting::factory()->council()->create([
        'ingest_mode' => 'metadata_only',
        'starts_at' => now()->subDays(8),
    ]);
    AgendaItem::factory()->create(['meeting_id' => $meeting->id, 'attachments_fetched_at' => now()]);

    app(DispatchMeetingSummariesIfReady::class)->handle($meeting->fresh());

    Bus::assertNotDispatched(SummarizeMeetingJob::class);
});
