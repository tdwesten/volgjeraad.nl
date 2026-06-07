<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MunicipalityRequestedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $municipalityName,
        public ?string $requesterEmail = null,
    ) {}

    public function envelope(): Envelope
    {
        $subjectName = trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $this->municipalityName));

        return new Envelope(
            subject: "Nieuwe gemeente-aanvraag: {$subjectName}",
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
