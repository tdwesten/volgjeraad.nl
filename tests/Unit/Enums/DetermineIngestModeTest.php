<?php

use App\Actions\Ingest\DetermineIngestMode;
use App\Enums\IngestMode;
use App\Enums\MeetingType;
use App\Models\Meeting;
use App\Models\Municipality;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('with default config all meeting types within launch window get Summarize', function (): void {
    $municipality = Municipality::factory()->create([
        'launch_date' => '2026-01-01',
        'backfill_recent_meetings' => 0,
        'settings' => null,
    ]);

    $action = new DetermineIngestMode;

    foreach (MeetingType::cases() as $type) {
        expect($action->handle($municipality, CarbonImmutable::parse('2026-03-01'), $type))
            ->toBe(IngestMode::Summarize);
    }
});

test('with summarize_types council only non-council types get MetadataOnly', function (): void {
    $municipality = Municipality::factory()->create([
        'launch_date' => '2026-01-01',
        'backfill_recent_meetings' => 10,
        'settings' => ['summarize_types' => ['council']],
    ]);

    $action = new DetermineIngestMode;

    foreach ([MeetingType::Committee, MeetingType::College, MeetingType::Other] as $type) {
        expect($action->handle($municipality, CarbonImmutable::parse('2026-03-01'), $type))
            ->toBe(IngestMode::MetadataOnly);
    }
});

test('council after launch_date gets Summarize', function (): void {
    $municipality = Municipality::factory()->create([
        'launch_date' => '2026-01-01',
        'backfill_recent_meetings' => 0,
    ]);

    $action = new DetermineIngestMode;
    $result = $action->handle($municipality, CarbonImmutable::parse('2026-03-01'), MeetingType::Council);

    expect($result)->toBe(IngestMode::Summarize);
});

test('council without launch_date gets MetadataOnly', function (): void {
    $municipality = Municipality::factory()->create(['launch_date' => null]);

    $action = new DetermineIngestMode;
    $result = $action->handle($municipality, CarbonImmutable::parse('2026-03-01'), MeetingType::Council);

    expect($result)->toBe(IngestMode::MetadataOnly);
});

test('council before launch_date within backfill window gets Summarize', function (): void {
    $municipality = Municipality::factory()->create([
        'launch_date' => '2026-06-01',
        'backfill_recent_meetings' => 2,
    ]);

    // Create 3 past council meetings — the 2 most recent should qualify
    Meeting::factory()->create([
        'municipality_id' => $municipality->id,
        'type' => MeetingType::Council->value,
        'starts_at' => '2026-05-15 19:00:00',
    ]);
    Meeting::factory()->create([
        'municipality_id' => $municipality->id,
        'type' => MeetingType::Council->value,
        'starts_at' => '2026-04-01 19:00:00',
    ]);
    Meeting::factory()->create([
        'municipality_id' => $municipality->id,
        'type' => MeetingType::Council->value,
        'starts_at' => '2026-02-01 19:00:00',
    ]);

    $action = new DetermineIngestMode;

    // 2026-05-15 is in the last-2, so Summarize
    $result = $action->handle($municipality, CarbonImmutable::parse('2026-05-15 19:00:00'), MeetingType::Council);
    expect($result)->toBe(IngestMode::Summarize);

    // 2026-02-01 is NOT in the last-2, so MetadataOnly
    $result2 = $action->handle($municipality, CarbonImmutable::parse('2026-02-01 19:00:00'), MeetingType::Council);
    expect($result2)->toBe(IngestMode::MetadataOnly);
});

test('council before launch_date outside backfill window gets MetadataOnly', function (): void {
    $municipality = Municipality::factory()->create([
        'launch_date' => '2026-06-01',
        'backfill_recent_meetings' => 2,
    ]);

    $action = new DetermineIngestMode;
    $result = $action->handle($municipality, CarbonImmutable::parse('2025-01-01'), MeetingType::Council);

    expect($result)->toBe(IngestMode::MetadataOnly);
});

test('backfill window spans all configured types when multiple types configured', function (): void {
    $municipality = Municipality::factory()->create([
        'launch_date' => '2026-06-01',
        'backfill_recent_meetings' => 2,
        'settings' => ['summarize_types' => ['council', 'committee']],
    ]);

    // Create mixed past meetings (council + committee)
    Meeting::factory()->create([
        'municipality_id' => $municipality->id,
        'type' => MeetingType::Council->value,
        'starts_at' => '2026-05-15 19:00:00',
    ]);
    Meeting::factory()->create([
        'municipality_id' => $municipality->id,
        'type' => MeetingType::Committee->value,
        'starts_at' => '2026-04-01 19:00:00',
    ]);
    // Older council meeting — displaced from top-2 by the two above
    Meeting::factory()->create([
        'municipality_id' => $municipality->id,
        'type' => MeetingType::Council->value,
        'starts_at' => '2026-02-01 19:00:00',
    ]);

    $action = new DetermineIngestMode;

    // The council on 05-15 is #1 in the top-2 → Summarize
    expect($action->handle($municipality, CarbonImmutable::parse('2026-05-15 19:00:00'), MeetingType::Council))
        ->toBe(IngestMode::Summarize);

    // The committee on 04-01 is #2 in the top-2 → Summarize
    expect($action->handle($municipality, CarbonImmutable::parse('2026-04-01 19:00:00'), MeetingType::Committee))
        ->toBe(IngestMode::Summarize);

    // The council on 02-01 is displaced (top-2 already taken) → MetadataOnly
    expect($action->handle($municipality, CarbonImmutable::parse('2026-02-01 19:00:00'), MeetingType::Council))
        ->toBe(IngestMode::MetadataOnly);
});
