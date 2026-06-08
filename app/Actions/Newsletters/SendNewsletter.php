<?php

namespace App\Actions\Newsletters;

use App\Enums\NewsletterStatus;
use App\Enums\SummaryLevel;
use App\Mail\NewsletterMail;
use App\Models\Newsletter;
use App\Models\Subscriber;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendNewsletter
{
    public function handle(Newsletter $newsletter): void
    {
        if ($newsletter->status === NewsletterStatus::Sent) {
            Log::info('Nieuwsbrief al verzonden, overslaan', ['newsletter_id' => $newsletter->id]);

            return;
        }

        Log::info('Nieuwsbrief verzenden gestart', [
            'newsletter_id' => $newsletter->id,
            'municipality_id' => $newsletter->municipality_id,
        ]);

        $newsletter->update(['status' => NewsletterStatus::Sending->value]);

        $newsletter->load('summaries');

        $totalRecipients = 0;

        foreach (SummaryLevel::cases() as $level) {
            $summaries = $newsletter->summaries
                ->where('level', $level->value)
                ->sortBy(fn ($s) => $s->pivot->position)
                ->values();

            if ($summaries->isEmpty()) {
                continue;
            }

            $subscribers = Subscriber::confirmed()
                ->forLevel($level)
                ->where('municipality_id', $newsletter->municipality_id)
                ->get();

            foreach ($subscribers as $subscriber) {
                Mail::to($subscriber->email)->send(
                    new NewsletterMail($newsletter, $level, $subscriber, $summaries->all())
                );
                $totalRecipients++;
            }
        }

        $newsletter->update([
            'status' => NewsletterStatus::Sent->value,
            'sent_at' => now(),
            'recipients_count' => $totalRecipients,
        ]);

        Log::info('Nieuwsbrief verzonden', [
            'newsletter_id' => $newsletter->id,
            'recipients_count' => $totalRecipients,
        ]);
    }
}
