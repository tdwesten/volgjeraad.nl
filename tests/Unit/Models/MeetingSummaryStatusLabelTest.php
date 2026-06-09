<?php

use App\Enums\IngestMode;
use App\Enums\MeetingType;
use App\Enums\SummaryStatus;
use App\Models\Meeting;
use App\Models\Municipality;
use App\Models\Summary;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('returns Wacht op verwerking for a summarizable meeting awaiting a source', function (): void {
    $meeting = Meeting::factory()->create([
        'type' => MeetingType::Committee->value,
        'ingest_mode' => IngestMode::Summarize->value,
    ]);

    // Summarizable, nog geen bron geresolveerd en geen samenvatting → wacht op verwerking.
    expect($meeting->summaryStatusLabel())->toBe('Wacht op verwerking');
});

test('returns Geen when no summaries and the meeting is not summarizable', function (): void {
    $meeting = Meeting::factory()->create([
        'type' => MeetingType::Committee->value,
        'ingest_mode' => IngestMode::MetadataOnly->value,
    ]);

    expect($meeting->summaryStatusLabel())->toBe('Geen');
});

test('returns Gepubliceerd when a published summary exists', function (): void {
    $municipality = Municipality::factory()->create();
    $meeting = Meeting::factory()->create([
        'municipality_id' => $municipality->id,
        'ingest_mode' => IngestMode::Summarize->value,
    ]);
    Summary::factory()->published()->create([
        'summarizable_type' => Meeting::class,
        'summarizable_id' => $meeting->id,
        'municipality_id' => $municipality->id,
        'meeting_id' => $meeting->id,
    ]);

    expect($meeting->summaryStatusLabel())->toBe('Gepubliceerd');
});

test('returns Goedgekeurd when approved summary exists and none are published', function (): void {
    $municipality = Municipality::factory()->create();
    $meeting = Meeting::factory()->create([
        'municipality_id' => $municipality->id,
        'ingest_mode' => IngestMode::Summarize->value,
    ]);
    Summary::factory()->approved()->create([
        'summarizable_type' => Meeting::class,
        'summarizable_id' => $meeting->id,
        'municipality_id' => $municipality->id,
        'meeting_id' => $meeting->id,
    ]);

    expect($meeting->summaryStatusLabel())->toBe('Goedgekeurd');
});

test('returns Concept when only draft summaries exist', function (): void {
    $municipality = Municipality::factory()->create();
    $meeting = Meeting::factory()->create([
        'municipality_id' => $municipality->id,
        'ingest_mode' => IngestMode::Summarize->value,
    ]);
    Summary::factory()->create([
        'summarizable_type' => Meeting::class,
        'summarizable_id' => $meeting->id,
        'municipality_id' => $municipality->id,
        'meeting_id' => $meeting->id,
        'status' => SummaryStatus::Draft->value,
    ]);

    expect($meeting->summaryStatusLabel())->toBe('Concept');
});

test('returns Gepubliceerd when published summary exists alongside draft', function (): void {
    $municipality = Municipality::factory()->create();
    $meeting = Meeting::factory()->create([
        'municipality_id' => $municipality->id,
        'ingest_mode' => IngestMode::Summarize->value,
    ]);
    Summary::factory()->published()->create([
        'summarizable_type' => Meeting::class,
        'summarizable_id' => $meeting->id,
        'municipality_id' => $municipality->id,
        'meeting_id' => $meeting->id,
    ]);
    Summary::factory()->create([
        'summarizable_type' => Meeting::class,
        'summarizable_id' => $meeting->id,
        'municipality_id' => $municipality->id,
        'meeting_id' => $meeting->id,
        'status' => SummaryStatus::Draft->value,
    ]);

    expect($meeting->summaryStatusLabel())->toBe('Gepubliceerd');
});

test('returns Wacht op verwerking for council meeting with transcript not resolved', function (): void {
    $meeting = Meeting::factory()->create([
        'type' => MeetingType::Council->value,
        'ingest_mode' => IngestMode::Summarize->value,
        'starts_at' => now()->addDays(3),
    ]);

    // No video, no summaries, starts_at in the future → not resolved
    expect($meeting->summaryStatusLabel())->toBe('Wacht op verwerking');
});

test('uses loaded summaries relation when already loaded', function (): void {
    $municipality = Municipality::factory()->create();
    $meeting = Meeting::factory()->create([
        'municipality_id' => $municipality->id,
        'type' => MeetingType::Committee->value,
    ]);

    $meeting->setRelation('summaries', collect());

    expect($meeting->summaryStatusLabel())->toBe('Geen');
});
