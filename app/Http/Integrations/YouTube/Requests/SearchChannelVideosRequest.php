<?php

namespace App\Http\Integrations\YouTube\Requests;

use Carbon\CarbonImmutable;
use Saloon\Enums\Method;
use Saloon\Http\Request;

class SearchChannelVideosRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        public string $channelId,
        public CarbonImmutable $publishedAfter,
        public CarbonImmutable $publishedBefore,
        public int $maxResults = 25,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/search';
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultQuery(): array
    {
        return [
            'part' => 'snippet',
            'type' => 'video',
            'order' => 'date',
            'channelId' => $this->channelId,
            'maxResults' => $this->maxResults,
            'publishedAfter' => $this->publishedAfter->toIso8601ZuluString(),
            'publishedBefore' => $this->publishedBefore->toIso8601ZuluString(),
        ];
    }
}
