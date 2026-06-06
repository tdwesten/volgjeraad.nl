<?php

namespace App\Mail;

use App\Models\Meeting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReviewReadyMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Meeting $meeting) {}

    public function envelope(): Envelope
    {
        $date = $this->meeting->starts_at?->format('d-m-Y') ?? '';

        return new Envelope(
            subject: "Samenvatting klaar voor review: {$this->meeting->name} ({$date})",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.review-ready',
            with: [
                'meeting' => $this->meeting,
                'reviewUrl' => route('admin.review.show', $this->meeting),
            ],
        );
    }
}
