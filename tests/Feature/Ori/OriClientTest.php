<?php

use App\Http\Integrations\Ori\OriConnector;
use App\Http\Integrations\Ori\Requests\FetchByIdsRequest;
use App\Http\Integrations\Ori\Requests\SearchMeetingsRequest;
use App\Models\Municipality;
use App\Services\Ori\OriClient;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

uses(RefreshDatabase::class);

test('searchMeetings sends correct endpoint and body', function (): void {
    $municipality = Municipality::factory()->create(['ori_index' => 'brummen_nl']);

    $from = CarbonImmutable::parse('2026-01-01');
    $to = CarbonImmutable::parse('2026-02-01');

    $mockClient = new MockClient([
        SearchMeetingsRequest::class => MockResponse::make([
            'hits' => ['hits' => [
                ['_id' => 'meeting-1', '_source' => ['@type' => 'Meeting', 'name' => 'Raadsvergadering']],
            ]],
        ], 200),
    ]);

    $connector = new OriConnector;
    $connector->withMockClient($mockClient);
    $client = new OriClient($connector);

    $hits = $client->searchMeetings($municipality, $from, $to);

    expect($hits)->toHaveCount(1);
    expect($hits[0]['_id'])->toBe('meeting-1');

    $mockClient->assertSent(SearchMeetingsRequest::class);
    $mockClient->assertSentCount(1);

    $sentRequest = $mockClient->getLastPendingRequest();
    expect($sentRequest->getUrl())->toContain('/brummen_nl/_search');
    $body = $sentRequest->body()->all();
    expect($body['query']['bool']['filter'])->toBeArray();
});

test('fetchByIds chunks and merges results', function (): void {
    $municipality = Municipality::factory()->create(['ori_index' => 'brummen_nl']);

    $ids = array_map(fn ($i) => "id-{$i}", range(1, 150));

    $chunk1Hits = array_map(fn ($i) => ['_id' => "id-{$i}", '_source' => ['name' => "Item {$i}"]], range(1, 100));
    $chunk2Hits = array_map(fn ($i) => ['_id' => "id-{$i}", '_source' => ['name' => "Item {$i}"]], range(101, 150));

    $mockClient = new MockClient([
        FetchByIdsRequest::class => MockResponse::fixture('fetch_ids'),
    ]);

    // Override with sequential responses
    $callCount = 0;
    $mockClient = new MockClient([
        FetchByIdsRequest::class => function () use (&$callCount, $chunk1Hits, $chunk2Hits) {
            $callCount++;

            return MockResponse::make([
                'hits' => ['hits' => $callCount === 1 ? $chunk1Hits : $chunk2Hits],
            ], 200);
        },
    ]);

    $connector = new OriConnector;
    $connector->withMockClient($mockClient);
    $client = new OriClient($connector);

    $result = $client->fetchByIds($municipality, $ids, 100);

    expect($result)->toHaveCount(150);
    expect($callCount)->toBe(2);
    expect($result['id-1'])->toHaveKey('name', 'Item 1');
    expect($result['id-150'])->toHaveKey('name', 'Item 150');
});

test('fetchByIds request body uses ids query', function (): void {
    $municipality = Municipality::factory()->create(['ori_index' => 'brummen_nl']);

    $mockClient = new MockClient([
        FetchByIdsRequest::class => MockResponse::make([
            'hits' => ['hits' => [
                ['_id' => 'agenda-1', '_source' => ['name' => 'Agenda punt 1']],
            ]],
        ], 200),
    ]);

    $connector = new OriConnector;
    $connector->withMockClient($mockClient);
    $client = new OriClient($connector);

    $client->fetchByIds($municipality, ['agenda-1'], 100);

    $sentRequest = $mockClient->getLastPendingRequest();
    $body = $sentRequest->body()->all();

    expect($body['query'])->toHaveKey('ids');
    expect($body['query']['ids']['values'])->toBe(['agenda-1']);
});
