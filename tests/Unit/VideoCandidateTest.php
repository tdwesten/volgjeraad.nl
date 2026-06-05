<?php

use App\Services\YouTube\VideoCandidate;
use Carbon\CarbonImmutable;

test('video candidate holds id, title and published date', function (): void {
    $candidate = new VideoCandidate(
        videoId: 'dQw4w9WgXcQ',
        title: 'Raadsvergadering 4 juni 2026',
        publishedAt: CarbonImmutable::parse('2026-06-04T19:00:00Z'),
    );

    expect($candidate->videoId)->toBe('dQw4w9WgXcQ');
    expect($candidate->title)->toBe('Raadsvergadering 4 juni 2026');
    expect($candidate->publishedAt->toDateString())->toBe('2026-06-04');
});

test('video candidate serialises to array for storage and the agent', function (): void {
    $candidate = new VideoCandidate(
        videoId: 'dQw4w9WgXcQ',
        title: 'Raadsvergadering',
        publishedAt: CarbonImmutable::parse('2026-06-04T19:00:00Z'),
    );

    expect($candidate->toArray())->toBe([
        'videoId' => 'dQw4w9WgXcQ',
        'title' => 'Raadsvergadering',
        'publishedAt' => '2026-06-04T19:00:00+00:00',
    ]);
});
