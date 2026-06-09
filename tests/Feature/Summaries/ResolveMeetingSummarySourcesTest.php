<?php

use App\Actions\Summaries\ResolveMeetingSummarySources;
use App\Ai\Agents\NotuleDetectionAgent;
use App\Enums\MeetingType;
use App\Enums\SummaryLevel;
use App\Enums\VideoStatus;
use App\Jobs\IngestMeetingAgendaJob;
use App\Jobs\ProcessMeetingVideoJob;
use App\Jobs\SummarizeMeetingJob;
use App\Models\AgendaItem;
use App\Models\MediaObject;
use App\Models\Meeting;
use App\Models\MeetingVideo;
use App\Models\Municipality;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;

uses(RefreshDatabase::class);

afterEach(fn () => Carbon::setTestNow());

beforeEach(function (): void {
    // Vast op een zondag zodat de werkdag-deadline (addWeekdays) deterministisch is:
    // -2 dagen valt vóór de 2-werkdagen-deadline, -10 dagen ruim erna.
    Carbon::setTestNow('2026-06-07 12:00:00');

    config([
        'volgjeraad.youtube.video_wait_hours' => 24,
        'volgjeraad.youtube.notule_recheck_working_days' => 2,
        'volgjeraad.youtube.max_transcript_attempts' => 4,
        'volgjeraad.youtube.notule_recheck_throttle_hours' => 20,
        'volgjeraad.ai.notule_confidence_threshold' => 70,
    ]);
});

function channelCouncilMeeting(string $startsAt): Meeting
{
    $muni = Municipality::factory()->create([
        'launch_date' => now()->subYear(),
        'settings' => ['youtube_channel_id' => 'UC123'],
    ]);
    $meeting = Meeting::factory()->summarizable()->create([
        'municipality_id' => $muni->id,
        'type' => MeetingType::Council->value,
        'starts_at' => now()->parse($startsAt),
        'agenda_ingested_at' => now(),
    ]);
    AgendaItem::factory()->create(['meeting_id' => $meeting->id, 'attachments_fetched_at' => now()]);

    return $meeting->fresh();
}

test('council+channel within 24h does nothing', function (): void {
    Bus::fake();
    $m = channelCouncilMeeting('-2 hours');
    app(ResolveMeetingSummarySources::class)->handle($m);
    Bus::assertNothingDispatched();
    expect($m->fresh()->summary_source)->toBeNull();
});

test('council+channel past 24h without a video dispatches the video job', function (): void {
    Bus::fake();
    $m = channelCouncilMeeting('-2 days');
    app(ResolveMeetingSummarySources::class)->handle($m);
    Bus::assertDispatched(ProcessMeetingVideoJob::class);
});

test('a transcribed video resolves the transcript source and dispatches summaries', function (): void {
    Bus::fake();
    $m = channelCouncilMeeting('-2 days');
    MeetingVideo::factory()->transcribed()->create(['meeting_id' => $m->id]);
    app(ResolveMeetingSummarySources::class)->handle($m->fresh());
    expect($m->fresh()->summary_source)->toBe(Meeting::SOURCE_TRANSCRIPT);
    Bus::assertDispatchedTimes(SummarizeMeetingJob::class, count(SummaryLevel::cases()));
});

test('a detected notule resolves the notule source', function (): void {
    Bus::fake();
    $m = channelCouncilMeeting('-2 days');
    $m->video()->create(['status' => VideoStatus::NotFound->value, 'transcript_attempts' => 0]);
    $item = $m->agendaItems()->first();
    $media = MediaObject::factory()->create(['agenda_item_id' => $item->id, 'name' => 'Besluitenlijst']);
    $m->update(['notule_detected_at' => now(), 'notule_media_object_id' => $media->id]);
    app(ResolveMeetingSummarySources::class)->handle($m->fresh());
    expect($m->fresh()->summary_source)->toBe(Meeting::SOURCE_NOTULE);
    Bus::assertDispatchedTimes(SummarizeMeetingJob::class, count(SummaryLevel::cases()));
});

test('no source before the recheck deadline re-ingests the agenda', function (): void {
    Bus::fake();
    $m = channelCouncilMeeting('-2 days'); // binnen 2 werkdagen
    $m->video()->create(['status' => VideoStatus::NotFound->value, 'transcript_attempts' => 0]);
    app(ResolveMeetingSummarySources::class)->handle($m->fresh());
    Bus::assertDispatched(IngestMeetingAgendaJob::class);
    expect($m->fresh()->summary_skipped_reason)->toBeNull();
});

test('no source past the recheck deadline marks no_source and dispatches nothing', function (): void {
    Bus::fake();
    $m = channelCouncilMeeting('-10 days'); // ruim voorbij 2 werkdagen
    $m->video()->create(['status' => VideoStatus::NotFound->value, 'transcript_attempts' => 0]);
    app(ResolveMeetingSummarySources::class)->handle($m->fresh());
    expect($m->fresh()->summary_skipped_reason)->toBe(Meeting::SKIP_NO_SOURCE);
    Bus::assertNotDispatched(SummarizeMeetingJob::class);
});

test('a future meeting is ignored', function (): void {
    Bus::fake();
    $m = channelCouncilMeeting('+1 day');
    app(ResolveMeetingSummarySources::class)->handle($m);
    Bus::assertNothingDispatched();
});

test('council+channel needing confirmation past the deadline ends as no_source instead of hanging', function (): void {
    Bus::fake();
    NotuleDetectionAgent::fake([[
        'is_notule_present' => false,
        'media_object_id' => null,
        'confidence' => 0,
    ]]);

    $m = channelCouncilMeeting('-10 days'); // ruim voorbij de werkdag-deadline
    $m->video()->create(['status' => VideoStatus::NeedsConfirmation->value, 'transcript_attempts' => 0]);

    app(ResolveMeetingSummarySources::class)->handle($m->fresh());

    expect($m->fresh()->summary_skipped_reason)->toBe(Meeting::SKIP_NO_SOURCE);
});

test('a second sweep within the throttle window does not re-run the notule check', function (): void {
    Bus::fake();
    NotuleDetectionAgent::fake([[
        'is_notule_present' => false,
        'media_object_id' => null,
        'confidence' => 0,
    ]]);

    // Raad zónder kanaal → direct notule-pad; -2 dagen valt vóór de deadline.
    $muni = Municipality::factory()->create(['launch_date' => now()->subYear(), 'settings' => []]);
    $m = Meeting::factory()->summarizable()->create([
        'municipality_id' => $muni->id,
        'type' => MeetingType::Council->value,
        'starts_at' => now()->subDays(2),
        'agenda_ingested_at' => now(),
    ]);
    $item = AgendaItem::factory()->create(['meeting_id' => $m->id, 'attachments_fetched_at' => now()]);
    MediaObject::factory()->create(['agenda_item_id' => $item->id, 'name' => 'Stuk']);

    app(ResolveMeetingSummarySources::class)->handle($m->fresh());
    $firstCheck = $m->fresh()->notule_checked_at;
    expect($firstCheck)->not->toBeNull();

    // Eén uur later opnieuw → binnen de 20u-throttle: geen nieuwe check.
    Carbon::setTestNow(now()->addHour());
    app(ResolveMeetingSummarySources::class)->handle($m->fresh());

    expect($m->fresh()->notule_checked_at->equalTo($firstCheck))->toBeTrue();
});
