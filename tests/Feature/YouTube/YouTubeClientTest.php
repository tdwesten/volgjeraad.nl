<?php

use App\Http\Integrations\YouTube\Requests\SearchChannelVideosRequest;
use App\Http\Integrations\YouTube\YouTubeConnector;
use App\Services\YouTube\YouTubeClient;
use Carbon\CarbonImmutable;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

test('searchChannel sends channel + date query and parses candidates', function (): void {
    $from = CarbonImmutable::parse('2026-06-01T00:00:00Z');
    $to = CarbonImmutable::parse('2026-06-07T00:00:00Z');

    $mockClient = new MockClient([
        SearchChannelVideosRequest::class => MockResponse::make([
            'items' => [
                [
                    'id' => ['videoId' => 'dQw4w9WgXcQ'],
                    'snippet' => [
                        'title' => 'Raadsvergadering 4 juni 2026',
                        'publishedAt' => '2026-06-04T19:30:00Z',
                    ],
                ],
            ],
        ], 200),
    ]);

    $connector = new YouTubeConnector;
    $connector->withMockClient($mockClient);
    $client = new YouTubeClient($connector);

    $candidates = $client->searchChannel('UC_brummen', $from, $to);

    expect($candidates)->toHaveCount(1);
    expect($candidates[0]->videoId)->toBe('dQw4w9WgXcQ');
    expect($candidates[0]->title)->toBe('Raadsvergadering 4 juni 2026');
    expect($candidates[0]->publishedAt->toDateString())->toBe('2026-06-04');

    $mockClient->assertSent(SearchChannelVideosRequest::class);
    $sent = $mockClient->getLastPendingRequest();
    $query = $sent->query()->all();
    expect($query['channelId'])->toBe('UC_brummen');
    expect($query['publishedAfter'])->toBe('2026-06-01T00:00:00Z');
    expect($query['publishedBefore'])->toBe('2026-06-07T00:00:00Z');
});

test('searchChannel returns empty array when no items', function (): void {
    $mockClient = new MockClient([
        SearchChannelVideosRequest::class => MockResponse::make(['items' => []], 200),
    ]);

    $connector = new YouTubeConnector;
    $connector->withMockClient($mockClient);
    $client = new YouTubeClient($connector);

    $candidates = $client->searchChannel(
        'UC_brummen',
        CarbonImmutable::parse('2026-06-01T00:00:00Z'),
        CarbonImmutable::parse('2026-06-07T00:00:00Z'),
    );

    expect($candidates)->toBe([]);
});

test('searchChannel skips items without a videoId', function (): void {
    $mockClient = new MockClient([
        SearchChannelVideosRequest::class => MockResponse::make([
            'items' => [
                ['id' => ['kind' => 'youtube#channel'], 'snippet' => ['title' => 'Kanaal', 'publishedAt' => '2026-06-04T19:30:00Z']],
                ['id' => ['videoId' => 'abc12345678'], 'snippet' => ['title' => 'Raad', 'publishedAt' => '2026-06-04T19:30:00Z']],
            ],
        ], 200),
    ]);

    $connector = new YouTubeConnector;
    $connector->withMockClient($mockClient);
    $client = new YouTubeClient($connector);

    $candidates = $client->searchChannel(
        'UC_brummen',
        CarbonImmutable::parse('2026-06-01T00:00:00Z'),
        CarbonImmutable::parse('2026-06-07T00:00:00Z'),
    );

    expect($candidates)->toHaveCount(1);
    expect($candidates[0]->videoId)->toBe('abc12345678');
});
