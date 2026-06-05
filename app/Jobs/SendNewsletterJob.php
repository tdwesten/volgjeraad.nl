<?php

namespace App\Jobs;

use App\Actions\Newsletters\SendNewsletter;
use App\Models\Newsletter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SendNewsletterJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $newsletterId) {}

    public function handle(SendNewsletter $action): void
    {
        $newsletter = Newsletter::findOrFail($this->newsletterId);
        $action->handle($newsletter);
    }

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function failed(Throwable $exception): void {}
}
