<?php

namespace App\Http\Integrations\YouTube\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class SearchRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        public string $searchQuery,
        public string $type = 'channel',
        public int $maxResults = 5,
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
            'q' => $this->searchQuery,
            'type' => $this->type,
            'maxResults' => $this->maxResults,
        ];
    }
}
