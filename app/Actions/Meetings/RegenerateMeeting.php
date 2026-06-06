<?php

namespace App\Actions\Meetings;

use App\Actions\Logging\RecordProcessingEvent;
use App\Jobs\IngestMeetingAgendaJob;
use App\Models\Meeting;

class RegenerateMeeting
{
    public function __construct(private RecordProcessingEvent $log) {}

    public function handle(Meeting $meeting): void
    {
        // Delete meeting-level summaries (cascade deletes newsletter_summary pivot)
        $meeting->summaries()->delete();

        // Delete newsletter draft (cascade deletes newsletter_summary pivot)
        $meeting->newsletter()->delete();

        // Reset processing flags so the pipeline gates will fire again
        $meeting->update([
            'summarized_at' => null,
            'agenda_ingested_at' => null,
        ]);

        // Invalidate agenda item hashes so IngestMeetingAgenda treats them as changed
        // and re-dispatches IngestAgendaMediaObjectsJob for each item
        $meeting->agendaItems()->update([
            'raw_payload_hash' => null,
            'attachments_fetched_at' => null,
        ]);

        $this->log->handle($meeting, 'regenerate', 'info', 'Handmatig opnieuw verwerken gestart');

        IngestMeetingAgendaJob::dispatch($meeting->id);
    }
}
