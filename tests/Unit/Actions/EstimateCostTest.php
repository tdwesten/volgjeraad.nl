<?php

use App\Actions\Summaries\EstimateCost;

test('estimates cost for gpt-4o-mini correctly', function (): void {
    $action = new EstimateCost;

    // 1M input tokens at 15 cents, 1M output tokens at 60 cents
    // 1000 input = 0.015 cents ≈ 0, 1000 output = 0.06 cents ≈ 0
    // 100_000 input = 1.5 cents ≈ 2, 100_000 output = 6 cents
    $cost = $action->handle('gpt-4o-mini', 100_000, 100_000);

    expect($cost)->toBe(8); // 2 + 6
});

test('estimates cost for gpt-4o correctly', function (): void {
    $action = new EstimateCost;

    // 1M input at 250 cents, 1M output at 1000 cents
    // 100_000 input = 25 cents, 100_000 output = 100 cents
    $cost = $action->handle('gpt-4o', 100_000, 100_000);

    expect($cost)->toBe(125);
});

test('throws for unknown model', function (): void {
    $action = new EstimateCost;

    expect(fn () => $action->handle('unknown-model', 100, 100))
        ->toThrow(InvalidArgumentException::class);
});

test('zero tokens costs zero', function (): void {
    $action = new EstimateCost;

    expect($action->handle('gpt-4o-mini', 0, 0))->toBe(0);
});
