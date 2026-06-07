<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Levert per model de prijs in cents per 1M tokens: [input, output].
 *
 * Bron-volgorde: eerst de (gecachte) remote LiteLLM-prijslijst, anders de
 * statische `volgjeraad.model_prices` fallback. Mislukte fetches worden niet
 * gecached zodat een tijdelijke storing de cache niet voor een week vergiftigt.
 */
class ModelPriceCatalog
{
    private const CACHE_KEY = 'volgjeraad:model_prices:remote';

    /**
     * @return array{0: float|int, 1: float|int}|null
     */
    public function pricesFor(string $model): ?array
    {
        $remote = $this->remotePrices();

        if (isset($remote[$model])) {
            return $remote[$model];
        }

        $fallback = config('volgjeraad.model_prices', []);

        return $fallback[$model] ?? null;
    }

    /**
     * @return array<string, array{0: float, 1: float}>
     */
    private function remotePrices(): array
    {
        if (! config('volgjeraad.model_prices_remote.enabled', false)) {
            return [];
        }

        $cached = Cache::get(self::CACHE_KEY);
        if (is_array($cached)) {
            return $cached;
        }

        $prices = $this->fetch();

        if ($prices !== []) {
            $ttlHours = (int) config('volgjeraad.model_prices_remote.cache_ttl_hours', 168);
            Cache::put(self::CACHE_KEY, $prices, now()->addHours($ttlHours));
        }

        return $prices;
    }

    /**
     * @return array<string, array{0: float, 1: float}>
     */
    private function fetch(): array
    {
        try {
            $url = (string) config('volgjeraad.model_prices_remote.url');
            $response = Http::timeout(10)->get($url);

            if (! $response->successful()) {
                Log::warning('Model price fetch returned non-2xx', ['status' => $response->status()]);

                return [];
            }

            return $this->normalize($response->json());
        } catch (Throwable $e) {
            Log::warning('Model price fetch failed', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * Zet de LiteLLM-structuur ($ per token) om naar cents per 1M tokens.
     *
     * @param  mixed  $data
     * @return array<string, array{0: float, 1: float}>
     */
    private function normalize($data): array
    {
        if (! is_array($data)) {
            return [];
        }

        $prices = [];

        foreach ($data as $model => $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $input = $entry['input_cost_per_token'] ?? null;
            $output = $entry['output_cost_per_token'] ?? null;

            if (! is_numeric($input) || ! is_numeric($output)) {
                continue;
            }

            $prices[$model] = [$input * 1_000_000 * 100, $output * 1_000_000 * 100];
        }

        return $prices;
    }
}
