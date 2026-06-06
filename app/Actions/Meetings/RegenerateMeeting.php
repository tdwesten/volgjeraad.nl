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

        // Verwijder de agendapunten (cascade verwijdert media_objects) zodat
        // IngestMeetingAgenda ze vers opnieuw ophaalt, de media opnieuw fetcht
        // en de samenvatting opnieuw laat genereren.
        $meeting->agendaItems()->delete();

        $this->log->handle($meeting, 'regenerate', 'info', 'Handmatig opnieuw verwerken gestart');

        IngestMeetingAgendaJob::dispatch($meeting->id);
    }
}
