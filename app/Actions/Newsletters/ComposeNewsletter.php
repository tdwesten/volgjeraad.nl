<?php

namespace App\Actions\Newsletters;

use App\Actions\Logging\RecordProcessingEvent;
use App\Enums\NewsletterStatus;
use App\Enums\SummaryLevel;
use App\Mail\ReviewReadyMail;
use App\Models\Meeting;
use App\Models\Newsletter;
use App\Models\Summary;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

class ComposeNewsletter
{
    public function __construct(private RecordProcessingEvent $log) {}

    public function handle(Meeting $meeting): Newsletter
    {
        $subject = $meeting->name.' — '.($meeting->starts_at?->format('d-m-Y') ?? '');

        $newsletter = Newsletter::updateOrCreate(
            [
                'municipality_id' => $meeting->municipality_id,
                'meeting_id' => $meeting->id,
            ],
            [
                'subject' => $subject,
                'status' => NewsletterStatus::Draft->value,
            ],
        );

        // Attach the two long-form meeting summaries (standard at position 1, simple at
        // position 2). De korte plain-text teaser is voor overzichten, niet voor de mail.
        $summaries = Summary::where('meeting_id', $meeting->id)
            ->where('summarizable_type', $meeting->getMorphClass())
            ->whereIn('level', [SummaryLevel::Standard->value, SummaryLevel::Simple->value])
            ->get();

        $positionMap = [];
        foreach ($summaries as $summary) {
            $positionMap[$summary->id] = ['position' => match ($summary->level->value) {
                'standard' => 1,
                'simple' => 2,
                default => 99,
            }];
        }

        $newsletter->summaries()->sync($positionMap);

        $action = $newsletter->wasRecentlyCreated ? 'aangemaakt' : 'bijgewerkt';
        $this->log->handle($meeting, 'newsletter', 'success', "Newsletter-concept {$action}");

        if ($newsletter->wasRecentlyCreated) {
            $this->notifyAdmins($meeting);
        }

        return $newsletter;
    }

    private function notifyAdmins(Meeting $meeting): void
    {
        User::where('is_admin', true)->each(
            fn (User $admin) => Mail::to($admin->email)->send(new ReviewReadyMail($meeting))
        );
    }
}
