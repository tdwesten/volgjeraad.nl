<?php

use App\Support\ModelPriceCatalog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Cache::flush();
    config([
        'volgjeraad.model_prices_remote.enabled' => true,
        'volgjeraad.model_prices_remote.url' => 'https://example.test/prices.json',
        'volgjeraad.model_prices' => [
            'gpt-4o-mini' => [15, 60],
        ],
    ]);
});

function fakeLiteLlm(array $models): void
{
    Http::fake([
        'https://example.test/prices.json' => Http::response($models),
    ]);
}

test('uses remote prices converted to cents per 1M tokens', function (): void {
    // $0.00000075/token input, $0.0000045/token output → 75 / 450 cents per 1M
    fakeLiteLlm([
        'gpt-5.4-mini' => [
            'input_cost_per_token' => 7.5e-07,
            'output_cost_per_token' => 4.5e-06,
        ],
    ]);

    $prices = (new ModelPriceCatalog)->pricesFor('gpt-5.4-mini');

    expect($prices[0])->toEqualWithDelta(75.0, 0.001)
        ->and($prices[1])->toEqualWithDelta(450.0, 0.001);
});

test('falls back to static config when model missing from remote', function (): void {
    fakeLiteLlm([
        'some-other-model' => [
            'input_cost_per_token' => 1.0e-06,
            'output_cost_per_token' => 1.0e-06,
        ],
    ]);

    $prices = (new ModelPriceCatalog)->pricesFor('gpt-4o-mini');

    expect($prices)->toBe([15, 60]);
});

test('falls back to static config when the remote fetch fails', function (): void {
    Http::fake([
        'https://example.test/prices.json' => Http::response('boom', 500),
    ]);

    $prices = (new ModelPriceCatalog)->pricesFor('gpt-4o-mini');

    expect($prices)->toBe([15, 60]);
});

test('returns null for a model in neither remote nor config', function (): void {
    fakeLiteLlm([]);

    expect((new ModelPriceCatalog)->pricesFor('nonexistent-model'))->toBeNull();
});

test('caches the remote response and does not refetch', function (): void {
    fakeLiteLlm([
        'gpt-5.4-mini' => [
            'input_cost_per_token' => 7.5e-07,
            'output_cost_per_token' => 4.5e-06,
        ],
    ]);

    $catalog = new ModelPriceCatalog;
    $catalog->pricesFor('gpt-5.4-mini');
    $catalog->pricesFor('gpt-5.4-mini');

    Http::assertSentCount(1);
});

test('does not fetch when remote pricing is disabled', function (): void {
    config(['volgjeraad.model_prices_remote.enabled' => false]);
    Http::fake();

    $prices = (new ModelPriceCatalog)->pricesFor('gpt-4o-mini');

    expect($prices)->toBe([15, 60]);
    Http::assertNothingSent();
});
