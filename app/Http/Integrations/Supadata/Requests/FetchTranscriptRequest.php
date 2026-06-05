<?php

namespace App\Http\Integrations\Supadata\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class FetchTranscriptRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        public string $youtubeVideoId,
        public string $language = 'nl',
        public string $mode = 'auto',
    ) {}

    public function resolveEndpoint(): string
    {
        return '/transcript';
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultQuery(): array
    {
        return [
            'url' => "https://youtu.be/{$this->youtubeVideoId}",
            'lang' => $this->language,
            'mode' => $this->mode,
            // Literal 'true' zoals de Supadata-querycontract verwacht (platte tekst i.p.v. segmenten).
            'text' => 'true',
        ];
    }
}
