<?php

namespace App\Jobs;

use App\Actions\Ingest\IngestMeetings;
use App\Models\Municipality;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\Middleware\ThrottlesExceptions;
use Throwable;

class IngestMunicipalityMeetingsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $municipalityId) {}

    public function handle(IngestMeetings $action): void
    {
        $municipality = Municipality::findOrFail($this->municipalityId);
        $action->handle($municipality);
    }

    /** @return array<int, mixed> */
    public function middleware(): array
    {
        return [
            new RateLimited('ori'),
            (new ThrottlesExceptions(5, 300))->backoff(60)->by('ori'),
        ];
    }

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function failed(Throwable $exception): void {}
}
