<?php

use App\Enums\IngestMode;
use App\Enums\MeetingProcessingStatus;
use App\Enums\MeetingType;
use App\Enums\SummaryStatus;
use App\Models\AgendaItem;
use App\Models\Meeting;
use App\Models\Municipality;
use App\Models\Summary;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => config(['volgjeraad.youtube.video_wait_hours' => 24]));

function channelMunicipality(): Municipality
{
    return Municipality::factory()->create([
        'launch_date' => now()->subYear(),
        'settings' => ['youtube_channel_id' => 'UC123'],
    ]);
}

test('a future summarizable meeting is Scheduled', function (): void {
    $m = Meeting::factory()->summarizable()->create([
        'municipality_id' => channelMunicipality()->id,
        'starts_at' => now()->addDay(),
    ]);
    expect($m->processingStatus())->toBe(MeetingProcessingStatus::Scheduled);
});

test('a council+channel meeting within 24h is AwaitingVideo', function (): void {
    $m = Meeting::factory()->summarizable()->create([
        'municipality_id' => channelMunicipality()->id,
        'type' => MeetingType::Council->value,
        'starts_at' => now()->subHours(2),
    ]);
    expect($m->processingStatus())->toBe(MeetingProcessingStatus::AwaitingVideo);
});

test('a meeting with a published summary is Published', function (): void {
    $m = Meeting::factory()->summarizable()->create([
        'municipality_id' => channelMunicipality()->id,
        'starts_at' => now()->subDays(3),
        'summarized_at' => now(),
    ]);
    Summary::factory()->create([
        'summarizable_type' => $m->getMorphClass(),
        'summarizable_id' => $m->id,
        'meeting_id' => $m->id,
        'municipality_id' => $m->municipality_id,
        'status' => SummaryStatus::Published->value,
    ]);
    expect($m->fresh()->processingStatus())->toBe(MeetingProcessingStatus::Published);
});

test('a summarized but unpublished meeting is InReview', function (): void {
    $m = Meeting::factory()->summarizable()->create([
        'municipality_id' => channelMunicipality()->id,
        'starts_at' => now()->subDays(3),
        'summarized_at' => now(),
        'summary_source' => 'notule',
    ]);
    expect($m->processingStatus())->toBe(MeetingProcessingStatus::InReview);
});

test('a skipped meeting is NoSource', function (): void {
    $m = Meeting::factory()->summarizable()->create([
        'municipality_id' => channelMunicipality()->id,
        'starts_at' => now()->subDays(3),
        'summary_skipped_reason' => 'no_source',
    ]);
    expect($m->processingStatus())->toBe(MeetingProcessingStatus::NoSource);
});

test('a pre-launch metadata-only meeting is PreLaunch', function (): void {
    $muni = Municipality::factory()->create(['launch_date' => now()->subMonth()]);
    $m = Meeting::factory()->create([
        'municipality_id' => $muni->id,
        'ingest_mode' => IngestMode::MetadataOnly->value,
        'starts_at' => now()->subMonths(2),
    ]);
    expect($m->processingStatus())->toBe(MeetingProcessingStatus::PreLaunch);
});

test('a no-channel meeting past 24h with complete media awaiting notule is AwaitingNotule', function (): void {
    $muni = Municipality::factory()->create(['launch_date' => now()->subYear(), 'settings' => []]);
    $m = Meeting::factory()->summarizable()->create([
        'municipality_id' => $muni->id,
        'type' => MeetingType::Council->value,
        'starts_at' => now()->subDays(1),
        'agenda_ingested_at' => now(),
    ]);
    AgendaItem::factory()->create(['meeting_id' => $m->id, 'attachments_fetched_at' => now()]);
    expect($m->fresh()->processingStatus())->toBe(MeetingProcessingStatus::AwaitingNotule);
});
