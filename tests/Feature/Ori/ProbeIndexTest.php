<?php

use App\Http\Integrations\Ori\OriConnector;
use App\Http\Integrations\Ori\Requests\ProbeIndexRequest;
use App\Services\Ori\OriClient;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

function probeClient(MockClient $mockClient): OriClient
{
    $connector = new OriConnector;
    $connector->withMockClient($mockClient);

    return new OriClient($connector);
}

test('probeIndex returns stats for an existing index with meetings', function (): void {
    $mockClient = new MockClient([
        ProbeIndexRequest::class => MockResponse::make([
            'hits' => [
                'total' => ['value' => 42],
                'hits' => [
                    [
                        '_id' => 'meeting-1',
                        '_source' => [
                            '@type' => 'Meeting',
                            'name' => 'Raadsvergadering',
                            'start_date' => '2026-05-20T19:30:00',
                        ],
                    ],
                ],
            ],
        ], 200),
    ]);

    $result = probeClient($mockClient)->probeIndex('ori_brummen');

    expect($result['exists'])->toBeTrue();
    expect($result['meeting_count'])->toBe(42);
    expect($result['latest_meeting'])->toBe([
        'name' => 'Raadsvergadering',
        'date' => '2026-05-20T19:30:00',
    ]);
    expect($result['error'])->toBeNull();

    $sentRequest = $mockClient->getLastPendingRequest();
    expect($sentRequest->getUrl())->toContain('/ori_brummen/_search');
    $body = $sentRequest->body()->all();
    expect($body['size'])->toBe(1);
    expect($body['track_total_hits'])->toBeTrue();
    expect($body['sort'])->toBe([['start_date' => ['order' => 'desc']]]);
    expect($body['query']['bool']['filter'])->toContain(['term' => ['@type' => 'Meeting']]);
});

test('probeIndex reports an existing but empty index', function (): void {
    $mockClient = new MockClient([
        ProbeIndexRequest::class => MockResponse::make([
            'hits' => [
                'total' => ['value' => 0],
                'hits' => [],
            ],
        ], 200),
    ]);

    $result = probeClient($mockClient)->probeIndex('ori_leeg');

    expect($result['exists'])->toBeTrue();
    expect($result['meeting_count'])->toBe(0);
    expect($result['latest_meeting'])->toBeNull();
    expect($result['error'])->toBeNull();
});

test('probeIndex returns exists false on index_not_found (404)', function (): void {
    $mockClient = new MockClient([
        ProbeIndexRequest::class => MockResponse::make([
            'error' => [
                'type' => 'index_not_found_exception',
                'reason' => 'no such index [ori_bestaatniet]',
            ],
            'status' => 404,
        ], 404),
    ]);

    $result = probeClient($mockClient)->probeIndex('ori_bestaatniet');

    expect($result['exists'])->toBeFalse();
    expect($result['meeting_count'])->toBeNull();
    expect($result['latest_meeting'])->toBeNull();
    expect($result['error'])->toBeNull();

    // Een verwachte 404 mag niet 3x geretried worden.
    $mockClient->assertSentCount(1);
});

test('probeIndex returns exists false with error on other failures', function (): void {
    $mockClient = new MockClient([
        ProbeIndexRequest::class => MockResponse::make([
            'error' => ['type' => 'search_phase_execution_exception'],
            'status' => 500,
        ], 500),
    ]);

    $result = probeClient($mockClient)->probeIndex('ori_kapot');

    expect($result['exists'])->toBeFalse();
    expect($result['meeting_count'])->toBeNull();
    expect($result['latest_meeting'])->toBeNull();
    expect($result['error'])->not->toBeNull();
    expect($result['error'])->toContain('500');
});
