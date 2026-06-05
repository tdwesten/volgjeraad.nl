<?php

namespace App\Actions\Summaries;

use InvalidArgumentException;

class EstimateCost
{
    public function handle(string $model, int $inputTokens, int $outputTokens): int
    {
        $prices = config('volgjeraad.model_prices');

        if (! isset($prices[$model])) {
            throw new InvalidArgumentException("Unknown model: {$model}");
        }

        [$inputPricePerMillion, $outputPricePerMillion] = $prices[$model];

        $inputCents = (int) round($inputTokens * $inputPricePerMillion / 1_000_000);
        $outputCents = (int) round($outputTokens * $outputPricePerMillion / 1_000_000);

        return $inputCents + $outputCents;
    }
}
