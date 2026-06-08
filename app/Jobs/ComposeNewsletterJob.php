<?php

namespace App\Jobs;

use App\Actions\Newsletters\ComposeNewsletter;
use App\Models\Meeting;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class ComposeNewsletterJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $meetingId) {}

    public function handle(ComposeNewsletter $action): void
    {
        Log::info('ComposeNewsletterJob gestart', ['meeting_id' => $this->meetingId]);

        $meeting = Meeting::findOrFail($this->meetingId);
        $action->handle($meeting);

        Log::info('ComposeNewsletterJob klaar', ['meeting_id' => $this->meetingId]);
    }

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function failed(Throwable $exception): void
    {
        Log::error('ComposeNewsletterJob mislukt', [
            'meeting_id' => $this->meetingId,
            'exception' => $exception->getMessage(),
        ]);
    }
}
