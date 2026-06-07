<?php

namespace App\Actions\Summaries;

use App\Support\ModelPriceCatalog;
use InvalidArgumentException;

class EstimateCost
{
    public function __construct(private ModelPriceCatalog $catalog = new ModelPriceCatalog) {}

    public function handle(string $model, int $inputTokens, int $outputTokens): int
    {
        $prices = $this->catalog->pricesFor($model);

        if ($prices === null) {
            throw new InvalidArgumentException("Unknown model: {$model}");
        }

        [$inputPricePerMillion, $outputPricePerMillion] = $prices;

        $inputCents = (int) round($inputTokens * $inputPricePerMillion / 1_000_000);
        $outputCents = (int) round($outputTokens * $outputPricePerMillion / 1_000_000);

        return $inputCents + $outputCents;
    }
}
