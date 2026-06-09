<?php

namespace App\Actions\Meetings;

use App\Actions\Logging\RecordProcessingEvent;
use App\Enums\IngestMode;
use App\Jobs\IngestMeetingAgendaJob;
use App\Jobs\ProcessMeetingVideoJob;
use App\Models\Meeting;
use Illuminate\Support\Facades\Log;

class RegenerateMeeting
{
    public function __construct(private RecordProcessingEvent $log) {}

    public function handle(Meeting $meeting): void
    {
        Log::info('Handmatige (her)verwerking gestart', ['meeting_id' => $meeting->id]);

        // Delete meeting-level summaries (cascade deletes newsletter_summary pivot)
        $meeting->summaries()->delete();

        // Delete newsletter draft (cascade deletes newsletter_summary pivot)
        $meeting->newsletter()->delete();

        // Forceer samenvatten en reset de processing-flags zodat de pipeline-gates
        // opnieuw vuren. Zonder Summarize-mode slaat DispatchMeetingSummariesIfReady over.
        $meeting->update([
            'ingest_mode' => IngestMode::Summarize->value,
            'summarized_at' => null,
            'agenda_ingested_at' => null,
            'summary_source' => null,
            'summary_skipped_reason' => null,
            'notule_detected_at' => null,
            'notule_checked_at' => null,
            'notule_media_object_id' => null,
        ]);

        // NIET-destructief: agendapunten en media blijven staan en worden in-place
        // ververst. De queue is async, dus zou verwijderen-en-opnieuw-ophalen de
        // documenten laten verdwijnen tot de her-ingest klaar is. Met forceMedia
        // worden de bijlagen van álle agendapunten opnieuw opgehaald (updateOrCreate),
        // zodat ook eerder ontbrekende documenten betrouwbaar terugkomen.
        $this->log->handle($meeting, 'regenerate', 'info', 'Handmatig opnieuw verwerken gestart');

        // Trap zowel de agenda-ingest (PDF-bronnen) als de video-pipeline meteen aan,
        // zodat verwerking direct begint i.p.v. te wachten op de 15-min-sweep.
        IngestMeetingAgendaJob::dispatch($meeting->id, forceMedia: true);
        ProcessMeetingVideoJob::dispatch($meeting->id);
    }
}
