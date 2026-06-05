<?php

use App\Ai\Agents\VideoMatchAgent;
use Laravel\Ai\Enums\Lab;

test('video match agent returns structured video_id, confidence and reason', function (): void {
    VideoMatchAgent::fake([[
        'video_id' => 'dQw4w9WgXcQ',
        'confidence' => 88,
        'reason' => 'Titel bevat "Raadsvergadering" en de datum komt overeen.',
    ]]);

    $agent = new VideoMatchAgent('gpt-4o-mini', 'v1');
    $response = $agent->prompt('meeting + kandidaten', provider: Lab::OpenAI, model: 'gpt-4o-mini');

    expect($response->structured['video_id'])->toBe('dQw4w9WgXcQ');
    expect($response->structured['confidence'])->toBe(88);
    expect($response->structured['reason'])->toBeString();
});
