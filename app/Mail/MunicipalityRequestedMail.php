<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MunicipalityRequestedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $municipalityName,
        public ?string $requesterEmail = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Nieuwe gemeente-aanvraag: {$this->municipalityName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.municipality-requested',
            with: [
                'municipalityName' => $this->municipalityName,
                'requesterEmail' => $this->requesterEmail,
            ],
        );
    }
}
