<?php

namespace App\Http\Integrations\YouTube;

use Saloon\Http\Connector;

class YouTubeConnector extends Connector
{
    public ?int $tries = 3;

    public ?int $retryInterval = 250;

    public function resolveBaseUrl(): string
    {
        return rtrim((string) config('volgjeraad.youtube.base_url'), '/');
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultHeaders(): array
    {
        return ['Accept' => 'application/json'];
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultQuery(): array
    {
        // Stuur de API-key alleen mee wanneer die geconfigureerd is; geen stille `key=null`.
        $key = config('volgjeraad.youtube.api_key');

        return $key !== null ? ['key' => $key] : [];
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultConfig(): array
    {
        return [
            'timeout' => config('volgjeraad.youtube.timeout'),
            'connect_timeout' => config('volgjeraad.youtube.connect_timeout'),
        ];
    }
}
