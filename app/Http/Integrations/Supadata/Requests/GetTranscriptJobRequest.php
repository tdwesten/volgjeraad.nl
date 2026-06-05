<?php

namespace App\Http\Integrations\Supadata\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class GetTranscriptJobRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(public string $jobId) {}

    public function resolveEndpoint(): string
    {
        return "/transcript/{$this->jobId}";
    }
}
