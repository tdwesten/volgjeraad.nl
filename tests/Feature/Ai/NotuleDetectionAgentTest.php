<?php

use App\Ai\Agents\NotuleDetectionAgent;
use Laravel\Ai\Enums\Lab;

test('notule detection agent returns structured presence, id and confidence', function (): void {
    NotuleDetectionAgent::fake([[
        'is_notule_present' => true,
        'media_object_id' => 42,
        'confidence' => 91,
    ]]);

    $agent = new NotuleDetectionAgent('gpt-5.4-mini', 'v2');
    $response = $agent->prompt('documentenlijst', provider: Lab::OpenAI, model: 'gpt-5.4-mini');

    expect($response->structured['is_notule_present'])->toBeTrue();
    expect($response->structured['media_object_id'])->toBe(42);
    expect($response->structured['confidence'])->toBe(91);
});
