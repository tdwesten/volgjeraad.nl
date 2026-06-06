<?php

namespace App\Mail;

use App\Models\Subscriber;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ConfirmSubscriptionMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Subscriber $subscriber) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Bevestig je aanmelding voor Volgjeraad',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.confirm-subscription',
            with: [
                'confirmUrl' => url('/bevestig/'.$this->subscriber->confirmation_token),
                'municipalityName' => $this->subscriber->municipality->name,
            ],
        );
    }
}
