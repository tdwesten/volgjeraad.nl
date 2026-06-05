<?php

namespace App\Actions\Newsletters;

use App\Enums\NewsletterStatus;
use App\Enums\SummaryStatus;
use App\Jobs\SendNewsletterJob;
use App\Models\Meeting;

class PublishMeetingSummaries
{
    public function __construct(private readonly ComposeNewsletter $composeNewsletter) {}

    public function handle(Meeting $meeting): void
    {
        $meeting->summaries()->whereIn('status', [
            SummaryStatus::Draft->value,
            SummaryStatus::Approved->value,
        ])->update([
            'status' => SummaryStatus::Published->value,
            'published_at' => now(),
        ]);

        $newsletter = $this->composeNewsletter->handle($meeting);

        $newsletter->update([
            'status' => NewsletterStatus::Approved->value,
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        SendNewsletterJob::dispatch($newsletter->id);
    }
}
