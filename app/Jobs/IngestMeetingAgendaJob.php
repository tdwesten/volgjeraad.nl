<?php

namespace App\Jobs;

use App\Actions\Ingest\IngestMeetingAgenda;
use App\Models\Meeting;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\Middleware\ThrottlesExceptions;
use Illuminate\Support\Facades\Log;
use Throwable;

class IngestMeetingAgendaJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $meetingId, public bool $forceMedia = false) {}

    public function handle(IngestMeetingAgenda $action): void
    {
        Log::info('IngestMeetingAgendaJob gestart', ['meeting_id' => $this->meetingId, 'force_media' => $this->forceMedia]);

        $meeting = Meeting::findOrFail($this->meetingId);
        $action->handle($meeting, $this->forceMedia);

        Log::info('IngestMeetingAgendaJob klaar', ['meeting_id' => $this->meetingId]);
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

    public function failed(Throwable $exception): void
    {
        Log::error('IngestMeetingAgendaJob mislukt', [
            'meeting_id' => $this->meetingId,
            'exception' => $exception->getMessage(),
        ]);
    }
}
