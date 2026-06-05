<?php

use App\Actions\Ingest\IngestMeetings;
use App\Enums\IngestMode;
use App\Enums\MeetingType;
use App\Jobs\IngestMeetingAgendaJob;
use App\Models\Meeting;
use App\Models\Municipality;
use App\Services\Ori\OriClient;
use App\Support\PayloadHasher;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;

uses(RefreshDatabase::class);

function mockOriClient(array $meetings, array $orgs = []): OriClient
{
    $mock = Mockery::mock(OriClient::class);
    $mock->shouldReceive('searchMeetings')->andReturn($meetings);
    $mock->shouldReceive('fetchByIds')->andReturn($orgs);

    return $mock;
}

test('council meeting on or after launch date gets Summarize mode', function (): void {
    Bus::fake();

    $municipality = Municipality::factory()->create([
        'ori_index' => 'brummen_nl',
        'raad_pattern' => 'raadsvergadering',
        'launch_date' => '2026-01-01',
        'backfill_recent_meetings' => 0,
    ]);

    $launchDate = CarbonImmutable::parse('2026-01-01');

    $hits = [[
        '_id' => 'meeting-council-1',
        '_source' => [
            '@type' => 'Meeting',
            'name' => 'Raadsvergadering 2026',
            'start_date' => $launchDate->toIso8601String(),
            'committee' => ['@id' => 'org-1', '@type' => 'Organization'],
        ],
    ]];

    $orgs = ['org-1' => ['name' => 'Raadsvergadering gemeente Brummen']];

    $client = mockOriClient($hits, $orgs);
    $action = app(IngestMeetings::class, ['client' => $client]);
    $action->handle($municipality);

    $meeting = Meeting::first();
    expect($meeting->ingest_mode)->toBe(IngestMode::Summarize);
    expect($meeting->type)->toBe(MeetingType::Council);

    Bus::assertDispatched(IngestMeetingAgendaJob::class);
});

test('unchanged hash does not redispatch', function (): void {
    Bus::fake();

    $municipality = Municipality::factory()->create([
        'ori_index' => 'brummen_nl',
        'raad_pattern' => 'raadsvergadering',
        'launch_date' => '2026-01-01',
    ]);

    $source = [
        '@type' => 'Meeting',
        'name' => 'Raadsvergadering',
        'start_date' => '2026-03-01T19:00:00+01:00',
        'committee' => ['@id' => 'org-1'],
    ];

    $hash = PayloadHasher::hash($source);
    Meeting::factory()->create([
        'municipality_id' => $municipality->id,
        'ori_id' => 'meeting-1',
        'raw_payload' => $source,
        'raw_payload_hash' => $hash,
        'ingest_mode' => IngestMode::Summarize->value,
    ]);

    $hits = [['_id' => 'meeting-1', '_source' => $source]];
    $client = mockOriClient($hits, ['org-1' => ['name' => 'Raadsvergadering gemeente Brummen']]);
    $action = app(IngestMeetings::class, ['client' => $client]);
    $action->handle($municipality);

    Bus::assertNotDispatched(IngestMeetingAgendaJob::class);
});

test('committee type gets MetadataOnly and no agenda dispatch', function (): void {
    Bus::fake();

    $municipality = Municipality::factory()->create([
        'ori_index' => 'brummen_nl',
        'raad_pattern' => 'raadsvergadering',
        'launch_date' => '2026-01-01',
    ]);

    $hits = [[
        '_id' => 'meeting-committee-1',
        '_source' => [
            '@type' => 'Meeting',
            'name' => 'Commissievergadering',
            'start_date' => '2026-03-01T19:00:00+01:00',
            'committee' => ['@id' => 'org-2'],
        ],
    ]];

    $orgs = ['org-2' => ['name' => 'Commissie Samenleving']];

    $client = mockOriClient($hits, $orgs);
    $action = app(IngestMeetings::class, ['client' => $client]);
    $action->handle($municipality);

    $meeting = Meeting::first();
    expect($meeting->ingest_mode)->toBe(IngestMode::MetadataOnly);

    Bus::assertNotDispatched(IngestMeetingAgendaJob::class);
});

test('council meeting before launch_date outside backfill window gets MetadataOnly', function (): void {
    Bus::fake();

    $municipality = Municipality::factory()->create([
        'ori_index' => 'brummen_nl',
        'raad_pattern' => 'raadsvergadering',
        'launch_date' => '2026-06-01',
        'backfill_recent_meetings' => 2,
    ]);

    // Old council meeting not in the last-2 list
    $hits = [[
        '_id' => 'old-meeting',
        '_source' => [
            '@type' => 'Meeting',
            'name' => 'Oude Raadsvergadering',
            'start_date' => '2025-01-01T19:00:00+01:00',
            'committee' => ['@id' => 'org-1'],
        ],
    ]];

    $client = mockOriClient($hits, ['org-1' => ['name' => 'Raadsvergadering gemeente Brummen']]);
    $action = app(IngestMeetings::class, ['client' => $client]);
    $action->handle($municipality);

    $meeting = Meeting::first();
    expect($meeting->ingest_mode)->toBe(IngestMode::MetadataOnly);

    Bus::assertNotDispatched(IngestMeetingAgendaJob::class);
});
