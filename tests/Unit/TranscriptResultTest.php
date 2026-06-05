<?php

use App\Services\Transcript\TranscriptResult;

test('transcript result holds text, source, lang and optional segments', function (): void {
    $result = new TranscriptResult(
        text: 'Voorzitter: ik open de vergadering.',
        source: 'supadata:auto',
        lang: 'nl',
        segments: [['start' => 0, 'text' => 'Voorzitter']],
    );

    expect($result->text)->toBe('Voorzitter: ik open de vergadering.');
    expect($result->source)->toBe('supadata:auto');
    expect($result->lang)->toBe('nl');
    expect($result->segments)->toBe([['start' => 0, 'text' => 'Voorzitter']]);
});

test('transcript result lang and segments default to null', function (): void {
    $result = new TranscriptResult(text: 'tekst', source: 'supadata:auto');

    expect($result->lang)->toBeNull();
    expect($result->segments)->toBeNull();
});
