<?php

use App\Services\Ori\OriNormalizer;

test('ids normalizes string to array', function (): void {
    expect(OriNormalizer::ids('abc-123'))->toBe(['abc-123']);
});

test('ids normalizes array of ref objects to ids', function (): void {
    $refs = [
        ['@id' => 'id-1', '@type' => 'AgendaItem'],
        ['@id' => 'id-2', '@type' => 'AgendaItem'],
    ];

    expect(OriNormalizer::ids($refs))->toBe(['id-1', 'id-2']);
});

test('ids normalizes @list wrapper to ids', function (): void {
    $refs = ['plain-id-1', 'plain-id-2'];

    expect(OriNormalizer::ids($refs))->toBe(['plain-id-1', 'plain-id-2']);
});

test('ids returns empty array for null', function (): void {
    expect(OriNormalizer::ids(null))->toBe([]);
});

test('mediaObject joins md_text array', function (): void {
    $source = [
        'md_text' => ['First page content.', 'Second page content.'],
        'text' => ['First page content.', 'Second page content.'],
    ];

    $result = OriNormalizer::mediaObject('media-1', $source);

    expect($result['md_text'])->toBe("First page content.\n\nSecond page content.");
    expect($result['has_text'])->toBeTrue();
});

test('mediaObject sets has_text false when md_text empty', function (): void {
    $source = [
        'md_text' => [],
        'text' => '',
    ];

    $result = OriNormalizer::mediaObject('media-2', $source);

    expect($result['has_text'])->toBeFalse();
    expect($result['text_missing_reason'])->toBe('ori_text_empty');
});

test('mediaObject accepts string md_text', function (): void {
    $source = ['md_text' => 'Some text here.'];

    $result = OriNormalizer::mediaObject('media-3', $source);

    expect($result['md_text'])->toBe('Some text here.');
    expect($result['has_text'])->toBeTrue();
});
