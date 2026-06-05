<?php

use App\Actions\Ingest\IngestMeetingAgenda;
use App\Enums\IngestMode;
use App\Enums\MeetingType;
use App\Jobs\IngestAgendaMediaObjectsJob;
use App\Models\AgendaItem;
use App\Models\Meeting;
use App\Models\Municipality;
use App\Services\Ori\OriClient;
use App\Support\PayloadHasher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;

uses(RefreshDatabase::class);

// Real ORI meeting payload shape: agenda is {"@list": ["id1", "id2"]}
function meetingWithAgendaList(Municipality $municipality, array $agendaIds): Meeting
{
    return Meeting::factory()->create([
        'municipality_id' => $municipality->id,
        'type' => MeetingType::Council->value,
        'ingest_mode' => IngestMode::Summarize->value,
        'raw_payload' => [
            '@type' => 'Meeting',
            'name' => 'Raadsvergadering',
            'start_date' => '2026-06-03T19:30:00+02:00',
            'agenda' => ['@list' => $agendaIds],
        ],
    ]);
}

// Real ORI agenda item payload: attachment is a string or plain array (no @list)
function agendaItemSource(string $attachmentId): array
{
    return [
        '@type' => 'AgendaItem',
        'name' => 'Agendapunt',
        'position' => 1,
        'attachment' => $attachmentId,
    ];
}

test('extracts agenda ids from @list shape and upserts agenda items', function (): void {
    Bus::fake();

    $municipality = Municipality::factory()->create();
    $meeting = meetingWithAgendaList($municipality, ['agenda-item-1', 'agenda-item-2']);

    $itemSources = [
        'agenda-item-1' => agendaItemSource('att-1'),
        'agenda-item-2' => agendaItemSource('att-2'),
    ];

    $client = Mockery::mock(OriClient::class);
    $client->shouldReceive('fetchByIds')->once()->andReturn($itemSources);

    $action = new IngestMeetingAgenda($client);
    $action->handle($meeting);

    expect(AgendaItem::count())->toBe(2);
    expect(AgendaItem::where('ori_id', 'agenda-item-1')->exists())->toBeTrue();
    expect(AgendaItem::where('ori_id', 'agenda-item-2')->exists())->toBeTrue();

    Bus::assertDispatchedTimes(IngestAgendaMediaObjectsJob::class, 2);

    expect($meeting->fresh()->agenda_ingested_at)->not->toBeNull();
});

test('dispatches media objects job only for changed items on re-run', function (): void {
    Bus::fake();

    $municipality = Municipality::factory()->create();
    $meeting = meetingWithAgendaList($municipality, ['agenda-item-1']);

    $source = agendaItemSource('att-1');
    $hash = PayloadHasher::hash($source);

    // Pre-existing item with same hash (unchanged)
    AgendaItem::factory()->create([
        'meeting_id' => $meeting->id,
        'ori_id' => 'agenda-item-1',
        'raw_payload' => $source,
        'raw_payload_hash' => $hash,
    ]);

    $client = Mockery::mock(OriClient::class);
    $client->shouldReceive('fetchByIds')->once()->andReturn(['agenda-item-1' => $source]);

    $action = new IngestMeetingAgenda($client);
    $action->handle($meeting);

    expect(AgendaItem::count())->toBe(1);
    Bus::assertNotDispatched(IngestAgendaMediaObjectsJob::class);
});

test('empty @list marks agenda_ingested_at without fetching', function (): void {
    Bus::fake();

    $municipality = Municipality::factory()->create();
    $meeting = meetingWithAgendaList($municipality, []);

    $client = Mockery::mock(OriClient::class);
    $client->shouldNotReceive('fetchByIds');

    $action = new IngestMeetingAgenda($client);
    $action->handle($meeting);

    expect(AgendaItem::count())->toBe(0);
    expect($meeting->fresh()->agenda_ingested_at)->not->toBeNull();
});
