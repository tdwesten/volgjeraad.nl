<?php

namespace App\Http\Integrations\Ori;

use Saloon\Http\Connector;

class OriConnector extends Connector
{
    public ?int $tries = 3;

    public ?int $retryInterval = 250;

    public function resolveBaseUrl(): string
    {
        return rtrim((string) config('volgjeraad.ori.base_url'), '/');
    }

    protected function defaultHeaders(): array
    {
        return ['Accept' => 'application/json'];
    }

    protected function defaultConfig(): array
    {
        return [
            'timeout' => config('volgjeraad.ori.timeout'),
            'connect_timeout' => config('volgjeraad.ori.connect_timeout'),
        ];
    }
}
