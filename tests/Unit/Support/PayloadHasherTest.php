<?php

use App\Support\PayloadHasher;

test('hash is key-order independent', function (): void {
    $a = ['z' => 1, 'a' => 2, 'm' => 3];
    $b = ['a' => 2, 'm' => 3, 'z' => 1];

    expect(PayloadHasher::hash($a))->toBe(PayloadHasher::hash($b));
});

test('hash is unicode stable', function (): void {
    $payload = ['name' => 'Gemeente Brummen — Raadsvergadering'];

    $hash1 = PayloadHasher::hash($payload);
    $hash2 = PayloadHasher::hash($payload);

    expect($hash1)->toBe($hash2);
});

test('nested arrays produce different hash than flat', function (): void {
    $flat = ['a' => 1, 'b' => 2];
    $nested = ['a' => 1, 'b' => ['c' => 2]];

    expect(PayloadHasher::hash($flat))->not->toBe(PayloadHasher::hash($nested));
});

test('nested keys are also sorted', function (): void {
    $a = ['outer' => ['z' => 1, 'a' => 2]];
    $b = ['outer' => ['a' => 2, 'z' => 1]];

    expect(PayloadHasher::hash($a))->toBe(PayloadHasher::hash($b));
});

test('different payloads produce different hashes', function (): void {
    $a = ['name' => 'foo'];
    $b = ['name' => 'bar'];

    expect(PayloadHasher::hash($a))->not->toBe(PayloadHasher::hash($b));
});
