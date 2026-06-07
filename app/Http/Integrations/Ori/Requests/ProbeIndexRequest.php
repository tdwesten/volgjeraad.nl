<?php

namespace App\Http\Integrations\Ori\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

class ProbeIndexRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    public function __construct(
        public string $oriIndex,
    ) {}

    public function resolveEndpoint(): string
    {
        return "/{$this->oriIndex}/_search";
    }

    protected function defaultBody(): array
    {
        return [
            'size' => 1,
            'track_total_hits' => true,
            'query' => [
                'bool' => [
                    'filter' => [
                        ['term' => ['@type' => 'Meeting']],
                    ],
                ],
            ],
            'sort' => [
                ['start_date' => ['order' => 'desc']],
            ],
        ];
    }
}
