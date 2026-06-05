<?php

namespace App\Http\Integrations\Supadata;

use Saloon\Http\Connector;

class SupadataConnector extends Connector
{
    public ?int $tries = 3;

    public ?int $retryInterval = 500;

    public function resolveBaseUrl(): string
    {
        return rtrim((string) config('volgjeraad.transcript.supadata.base_url'), '/');
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultHeaders(): array
    {
        return [
            'Accept' => 'application/json',
            'x-api-key' => config('volgjeraad.transcript.supadata.api_key'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultConfig(): array
    {
        return [
            'timeout' => config('volgjeraad.transcript.supadata.timeout'),
            'connect_timeout' => config('volgjeraad.transcript.supadata.connect_timeout'),
        ];
    }
}
