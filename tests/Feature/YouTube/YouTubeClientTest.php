<?php

use App\Http\Integrations\YouTube\Requests\SearchChannelVideosRequest;
use App\Http\Integrations\YouTube\Requests\SearchRequest;
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

test('search sends query + type and parses channel results', function (): void {
    $mockClient = new MockClient([
        SearchRequest::class => MockResponse::make([
            'items' => [
                [
                    'id' => ['kind' => 'youtube#channel', 'channelId' => 'UC_brummen'],
                    'snippet' => [
                        'title' => 'Gemeente Brummen',
                        'description' => 'Officieel kanaal van de gemeente Brummen.',
                    ],
                ],
            ],
        ], 200),
    ]);

    $connector = new YouTubeConnector;
    $connector->withMockClient($mockClient);
    $client = new YouTubeClient($connector);

    $results = $client->search('gemeente Brummen raad');

    expect($results)->toBe([[
        'id' => 'UC_brummen',
        'title' => 'Gemeente Brummen',
        'description' => 'Officieel kanaal van de gemeente Brummen.',
        'url' => 'https://www.youtube.com/channel/UC_brummen',
    ]]);

    $mockClient->assertSent(SearchRequest::class);
    $query = $mockClient->getLastPendingRequest()->query()->all();
    expect($query['q'])->toBe('gemeente Brummen raad');
    expect($query['type'])->toBe('channel');
    expect($query['part'])->toBe('snippet');
    expect($query['maxResults'])->toBe(5);
});

test('search builds video urls and skips items without a usable id', function (): void {
    $mockClient = new MockClient([
        SearchRequest::class => MockResponse::make([
            'items' => [
                ['id' => ['kind' => 'youtube#video', 'videoId' => 'abc12345678'], 'snippet' => ['title' => 'Raad', 'description' => '']],
                ['id' => ['kind' => 'youtube#playlist', 'playlistId' => 'PL123'], 'snippet' => ['title' => 'Playlist']],
            ],
        ], 200),
    ]);

    $connector = new YouTubeConnector;
    $connector->withMockClient($mockClient);
    $client = new YouTubeClient($connector);

    $results = $client->search('Brummen raadsvergadering', 'video', 3);

    expect($results)->toHaveCount(1);
    expect($results[0]['id'])->toBe('abc12345678');
    expect($results[0]['url'])->toBe('https://www.youtube.com/watch?v=abc12345678');

    $query = $mockClient->getLastPendingRequest()->query()->all();
    expect($query['type'])->toBe('video');
    expect($query['maxResults'])->toBe(3);
});
