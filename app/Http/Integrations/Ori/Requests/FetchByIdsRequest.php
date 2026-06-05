<?php

namespace App\Http\Integrations\Ori\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

class FetchByIdsRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    public function __construct(
        public string $oriIndex,
        public array $oriIds,
        public int $size,
    ) {}

    public function resolveEndpoint(): string
    {
        return "/{$this->oriIndex}/_search";
    }

    protected function defaultBody(): array
    {
        return [
            'size' => $this->size,
            'query' => [
                'ids' => [
                    'values' => array_values($this->oriIds),
                ],
            ],
        ];
    }
}
