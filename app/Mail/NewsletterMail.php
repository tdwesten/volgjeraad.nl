<?php

namespace App\Mail;

use App\Enums\SummaryLevel;
use App\Models\Newsletter;
use App\Models\Subscriber;
use App\Models\Summary;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

class NewsletterMail extends Mailable
{
    use Queueable, SerializesModels;

    /** @param array<int, Summary> $summaries */
    public function __construct(
        public Newsletter $newsletter,
        public SummaryLevel $level,
        public Subscriber $subscriber,
        public array $summaries,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->newsletter->subject,
        );
    }

    public function headers(): Headers
    {
        $unsubscribeUrl = route('subscription.unsubscribe', $this->subscriber->unsubscribe_token);

        return new Headers(
            text: [
                'List-Unsubscribe' => '<'.$unsubscribeUrl.'>',
                'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
            ],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.newsletter',
            with: [
                'newsletter' => $this->newsletter,
                'level' => $this->level,
                'subscriber' => $this->subscriber,
                'summaries' => $this->summaries,
                'unsubscribeUrl' => route('subscription.unsubscribe', $this->subscriber->unsubscribe_token),
                'webUrl' => route('newsletter.web', $this->newsletter->id),
                'municipalityUrl' => $this->newsletter->municipality
                    ? route('municipality.show', $this->newsletter->municipality->slug)
                    : '/',
            ],
        );
    }
}
