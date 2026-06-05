<?php

namespace App\Jobs;

use App\Actions\Ingest\IngestMeetingAgenda;
use App\Models\Meeting;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\Middleware\ThrottlesExceptions;
use Throwable;

class IngestMeetingAgendaJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $meetingId) {}

    public function handle(IngestMeetingAgenda $action): void
    {
        $meeting = Meeting::findOrFail($this->meetingId);
        $action->handle($meeting);
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
