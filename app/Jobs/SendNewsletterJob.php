<?php

namespace App\Jobs;

use App\Actions\Newsletters\SendNewsletter;
use App\Models\Newsletter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendNewsletterJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $newsletterId) {}

    public function handle(SendNewsletter $action): void
    {
        Log::info('SendNewsletterJob gestart', ['newsletter_id' => $this->newsletterId]);

        $newsletter = Newsletter::findOrFail($this->newsletterId);
        $action->handle($newsletter);

        Log::info('SendNewsletterJob klaar', ['newsletter_id' => $this->newsletterId]);
    }

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function failed(Throwable $exception): void
    {
        Log::error('SendNewsletterJob mislukt', [
            'newsletter_id' => $this->newsletterId,
            'exception' => $exception->getMessage(),
        ]);
    }
}
