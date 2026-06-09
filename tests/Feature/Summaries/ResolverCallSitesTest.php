<?php

use App\Actions\Ingest\IngestAgendaMediaObjects;
use App\Ai\Agents\NotuleDetectionAgent;
use App\Enums\SummaryLevel;
use App\Jobs\SummarizeMeetingJob;
use App\Models\AgendaItem;
use App\Models\MediaObject;
use App\Models\Meeting;
use App\Models\Municipality;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;

uses(RefreshDatabase::class);

beforeEach(fn () => config([
    'volgjeraad.youtube.video_wait_hours' => 24,
    'volgjeraad.youtube.notule_recheck_working_days' => 2,
    'volgjeraad.ai.notule_confidence_threshold' => 70,
]));

test('completing media on a no-channel meeting drives the resolver and (with a notule) summarizes', function (): void {
    Bus::fake([SummarizeMeetingJob::class]);
    $muni = Municipality::factory()->create(['launch_date' => now()->subYear(), 'settings' => []]);
    $meeting = Meeting::factory()->summarizable()->create([
        'municipality_id' => $muni->id,
        'starts_at' => now()->subDay(),
        'agenda_ingested_at' => now(),
        'notule_detected_at' => now(),
    ]);
    $item = AgendaItem::factory()->create(['meeting_id' => $meeting->id, 'attachments_fetched_at' => null]);

    app(IngestAgendaMediaObjects::class)->handle($item->fresh());

    Bus::assertDispatchedTimes(SummarizeMeetingJob::class, count(SummaryLevel::cases()));
});

test('a late notule arriving after a no_source skip is picked up when media completes', function (): void {
    Bus::fake([SummarizeMeetingJob::class]);
    NotuleDetectionAgent::fake([[
        'is_notule_present' => true,
        'media_object_id' => null,
        'confidence' => 95,
    ]]);

    $muni = Municipality::factory()->create(['launch_date' => now()->subYear(), 'settings' => []]);
    $meeting = Meeting::factory()->summarizable()->create([
        'municipality_id' => $muni->id,
        'starts_at' => now()->subDays(10),       // ruim voorbij de werkdag-deadline
        'agenda_ingested_at' => now(),
        'summary_skipped_reason' => 'no_source',  // eerder (vóór de notule binnen was) geskipt
        'notule_checked_at' => now()->subDay(),
    ]);
    $item = AgendaItem::factory()->create(['meeting_id' => $meeting->id, 'attachments_fetched_at' => null]);
    MediaObject::factory()->create(['agenda_item_id' => $item->id, 'name' => 'Besluitenlijst']);

    app(IngestAgendaMediaObjects::class)->handle($item->fresh());

    expect($meeting->fresh()->summary_skipped_reason)->toBeNull();
    expect($meeting->fresh()->summary_source)->toBe(Meeting::SOURCE_NOTULE);
    Bus::assertDispatchedTimes(SummarizeMeetingJob::class, count(SummaryLevel::cases()));
});
