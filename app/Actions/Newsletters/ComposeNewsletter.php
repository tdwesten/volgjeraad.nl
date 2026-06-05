<?php

namespace App\Actions\Newsletters;

use App\Enums\NewsletterStatus;
use App\Models\AgendaItem;
use App\Models\Meeting;
use App\Models\Newsletter;
use App\Models\Summary;

class ComposeNewsletter
{
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

        // Attach all summaries for this meeting ordered by agenda item position
        $agendaItemIds = $meeting->agendaItems()->orderBy('position')->pluck('id')->toArray();

        $summaries = Summary::where('meeting_id', $meeting->id)->get();

        // Assign position based on agenda item order; meeting-level summaries get position 0
        $positionMap = [];
        foreach ($summaries as $summary) {
            if ($summary->summarizable_type === AgendaItem::class) {
                $agendaPos = array_search($summary->summarizable_id, $agendaItemIds);
                $positionMap[$summary->id] = ['position' => $agendaPos !== false ? $agendaPos + 1 : 999];
            } else {
                $positionMap[$summary->id] = ['position' => 0];
            }
        }

        $newsletter->summaries()->sync($positionMap);

        return $newsletter;
    }
}
