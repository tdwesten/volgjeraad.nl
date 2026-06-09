<?php

use App\Actions\Summaries\DispatchMeetingSummariesIfReady;
use App\Enums\SummaryLevel;
use App\Jobs\SummarizeMeetingJob;
use App\Models\AgendaItem;
use App\Models\Meeting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;

uses(RefreshDatabase::class);

function readyMeeting(?string $source = 'notule'): Meeting
{
    $meeting = Meeting::factory()->summarizable()->create([
        'starts_at' => now()->subDay(),
        'summary_source' => $source,
    ]);
    AgendaItem::factory()->create(['meeting_id' => $meeting->id, 'attachments_fetched_at' => now()]);

    return $meeting->fresh();
}

test('does not dispatch while no source has been resolved', function (): void {
    Bus::fake();
    app(DispatchMeetingSummariesIfReady::class)->handle(readyMeeting(null));
    Bus::assertNotDispatched(SummarizeMeetingJob::class);
});

test('dispatches one job per level once a source is resolved', function (): void {
    Bus::fake();
    app(DispatchMeetingSummariesIfReady::class)->handle(readyMeeting('notule'));
    Bus::assertDispatchedTimes(SummarizeMeetingJob::class, count(SummaryLevel::cases()));
});

test('waits for all media before dispatching', function (): void {
    Bus::fake();
    $meeting = Meeting::factory()->summarizable()->create([
        'starts_at' => now()->subDay(), 'summary_source' => 'notule',
    ]);
    AgendaItem::factory()->create(['meeting_id' => $meeting->id, 'attachments_fetched_at' => null]);
    app(DispatchMeetingSummariesIfReady::class)->handle($meeting->fresh());
    Bus::assertNotDispatched(SummarizeMeetingJob::class);
});

test('an already summarized meeting is not dispatched again', function (): void {
    Bus::fake();
    $meeting = readyMeeting('notule');
    $meeting->update(['summarized_at' => now()]);
    app(DispatchMeetingSummariesIfReady::class)->handle($meeting->fresh());
    Bus::assertNotDispatched(SummarizeMeetingJob::class);
});

test('a non-summarizable meeting is skipped', function (): void {
    Bus::fake();
    $meeting = Meeting::factory()->create([
        'ingest_mode' => 'metadata_only', 'starts_at' => now()->subDay(), 'summary_source' => 'notule',
    ]);
    app(DispatchMeetingSummariesIfReady::class)->handle($meeting->fresh());
    Bus::assertNotDispatched(SummarizeMeetingJob::class);
});
