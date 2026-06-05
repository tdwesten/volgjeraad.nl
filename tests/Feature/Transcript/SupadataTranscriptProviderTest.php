<?php

use App\Http\Integrations\Supadata\Requests\FetchTranscriptRequest;
use App\Http\Integrations\Supadata\Requests\GetTranscriptJobRequest;
use App\Http\Integrations\Supadata\SupadataConnector;
use App\Services\Transcript\SupadataTranscriptProvider;
use App\Services\Transcript\TranscriptJobFailedException;
use App\Services\Transcript\TranscriptProvider;
use Saloon\Exceptions\Request\Statuses\NotFoundException;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

beforeEach(function (): void {
    // Geen echte sleeps tussen poll-pogingen tijdens tests.
    config(['volgjeraad.transcript.supadata.poll_interval_ms' => 0]);
});

function supadataProvider(MockClient $mockClient): SupadataTranscriptProvider
{
    $connector = new SupadataConnector;
    $connector->withMockClient($mockClient);

    return new SupadataTranscriptProvider($connector);
}

test('synchronous 200 with text string returns transcript and youtu.be url query', function (): void {
    $mockClient = new MockClient([
        FetchTranscriptRequest::class => MockResponse::make([
            'content' => 'Voorzitter: ik open de vergadering.',
            'lang' => 'nl',
            'availableLangs' => ['nl'],
        ], 200),
    ]);

    $result = supadataProvider($mockClient)->fetch('dQw4w9WgXcQ', 'nl');

    expect($result->text)->toBe('Voorzitter: ik open de vergadering.');
    expect($result->source)->toBe('supadata:auto');
    expect($result->lang)->toBe('nl');
    expect($result->segments)->toBeNull();

    $sent = $mockClient->getLastPendingRequest();
    $query = $sent->query()->all();
    expect($query['url'])->toBe('https://youtu.be/dQw4w9WgXcQ');
    expect($query['lang'])->toBe('nl');
    expect($query['text'])->toBe('true');
    expect($query['mode'])->toBe('auto');
});

test('synchronous 200 with segment array joins text and keeps segments', function (): void {
    $mockClient = new MockClient([
        FetchTranscriptRequest::class => MockResponse::make([
            'content' => [
                ['offset' => 0, 'text' => 'Voorzitter: ik open'],
                ['offset' => 3000, 'text' => 'de vergadering.'],
            ],
            'lang' => 'nl',
        ], 200),
    ]);

    $result = supadataProvider($mockClient)->fetch('dQw4w9WgXcQ');

    expect($result->text)->toBe('Voorzitter: ik open de vergadering.');
    expect($result->segments)->toHaveCount(2);
});

test('async 202 with jobId polls until completed', function (): void {
    $mockClient = new MockClient([
        FetchTranscriptRequest::class => MockResponse::make(['jobId' => 'job-123'], 202),
        GetTranscriptJobRequest::class => MockResponse::make([
            'status' => 'completed',
            'content' => 'Async transcript klaar.',
            'lang' => 'nl',
        ], 200),
    ]);

    $result = supadataProvider($mockClient)->fetch('dQw4w9WgXcQ');

    expect($result->text)->toBe('Async transcript klaar.');
    expect($result->source)->toBe('supadata:auto');
    $mockClient->assertSent(GetTranscriptJobRequest::class);
});

test('async job that fails throws TranscriptJobFailedException', function (): void {
    $mockClient = new MockClient([
        FetchTranscriptRequest::class => MockResponse::make(['jobId' => 'job-err'], 202),
        GetTranscriptJobRequest::class => MockResponse::make(['status' => 'failed'], 200),
    ]);

    supadataProvider($mockClient)->fetch('dQw4w9WgXcQ');
})->throws(TranscriptJobFailedException::class);

test('404 from vendor bubbles up as a request exception', function (): void {
    $mockClient = new MockClient([
        FetchTranscriptRequest::class => MockResponse::make(['error' => 'not found'], 404),
    ]);

    supadataProvider($mockClient)->fetch('dQw4w9WgXcQ');
})->throws(NotFoundException::class);

test('empty content returns empty text without throwing', function (): void {
    $mockClient = new MockClient([
        FetchTranscriptRequest::class => MockResponse::make(['content' => '', 'lang' => 'nl'], 200),
    ]);

    $result = supadataProvider($mockClient)->fetch('dQw4w9WgXcQ');

    expect($result->text)->toBe('');
});

test('TranscriptProvider interface resolves to Supadata implementation', function (): void {
    expect(app(TranscriptProvider::class))->toBeInstanceOf(SupadataTranscriptProvider::class);
});
