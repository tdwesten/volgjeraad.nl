<?php

namespace App\Jobs;

use App\Actions\Newsletters\ComposeNewsletter;
use App\Models\Meeting;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ComposeNewsletterJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $meetingId) {}

    public function handle(ComposeNewsletter $action): void
    {
        $meeting = Meeting::findOrFail($this->meetingId);
        $action->handle($meeting);
    }

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function failed(Throwable $exception): void {}
}
