<?php

use App\Actions\Municipalities\FindMunicipalityStream;
use App\Ai\Agents\StreamFinderAgent;

test('returns the channel the agent found on the happy path', function (): void {
    StreamFinderAgent::fake([[
        'channel_id' => 'UC_brummen',
        'channel_title' => 'Gemeente Brummen',
        'channel_url' => 'https://www.youtube.com/channel/UC_brummen',
        'confidence' => 91,
        'reason' => 'Officieel kanaal van de gemeente met raadsvergaderingen.',
    ]]);

    $result = (new FindMunicipalityStream)->handle('Brummen');

    expect($result)->toBe([
        'channel_id' => 'UC_brummen',
        'channel_title' => 'Gemeente Brummen',
        'channel_url' => 'https://www.youtube.com/channel/UC_brummen',
        'confidence' => 91,
        'reason' => 'Officieel kanaal van de gemeente met raadsvergaderingen.',
    ]);

    StreamFinderAgent::assertPrompted(fn ($prompt) => str_contains($prompt->prompt, 'Brummen'));
});

test('returns null channel fields and low confidence when nothing fits', function (): void {
    StreamFinderAgent::fake([[
        'channel_id' => '',
        'channel_title' => '',
        'channel_url' => '',
        'confidence' => 20,
        'reason' => 'Geen duidelijk officieel raadskanaal gevonden.',
    ]]);

    $result = (new FindMunicipalityStream)->handle('Onbekenddorp');

    expect($result['channel_id'])->toBeNull();
    expect($result['channel_title'])->toBeNull();
    expect($result['channel_url'])->toBeNull();
    expect($result['confidence'])->toBe(20);
    expect($result['reason'])->toBe('Geen duidelijk officieel raadskanaal gevonden.');
});
