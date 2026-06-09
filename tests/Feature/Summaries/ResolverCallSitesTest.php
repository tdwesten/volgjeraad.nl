<?php

use App\Actions\Ingest\IngestAgendaMediaObjects;
use App\Enums\SummaryLevel;
use App\Jobs\SummarizeMeetingJob;
use App\Models\AgendaItem;
use App\Models\Meeting;
use App\Models\Municipality;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;

uses(RefreshDatabase::class);

beforeEach(fn () => config([
    'volgjeraad.youtube.video_wait_hours' => 24,
    'volgjeraad.youtube.notule_recheck_working_days' => 2,
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
