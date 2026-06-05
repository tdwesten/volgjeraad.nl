<?php

namespace App\Http\Integrations\Ori\Requests;

use Carbon\CarbonImmutable;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

class SearchMeetingsRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    public function __construct(
        public string $oriIndex,
        public CarbonImmutable $from,
        public CarbonImmutable $to,
        public int $size = 500,
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
                'bool' => [
                    'filter' => [
                        ['term' => ['@type' => 'Meeting']],
                        ['range' => [
                            'start_date' => [
                                'gte' => $this->from->toIso8601String(),
                                'lte' => $this->to->toIso8601String(),
                            ],
                        ]],
                    ],
                ],
            ],
            'sort' => [
                ['start_date' => ['order' => 'asc']],
            ],
        ];
    }
}
