<?php

namespace App\Actions\Summaries;

use App\Enums\SummaryLevel;
use App\Jobs\SummarizeMeetingJob;
use App\Models\Meeting;

class DispatchMeetingSummariesIfReady
{
    /**
     * Dispatcht de meeting-samenvattingen uitsluitend wanneer (a) alle media binnen
     * is én (b) de transcript-resolutie klaar is (transcript binnen, of definitief
     * opgegeven). Re-entrant en idempotent: zowel de media-ingest als de
     * video-pijplijn roepen dit aan; pas wanneer beide condities waar zijn dispatcht
     * het, één keer per SummaryLevel. De exact-één-keer-garantie ná dispatch leeft
     * in de bestaande samenvat-dispatch-fix (die `summarized_at` zet); deze gate
     * voegt daar de transcript-resolutie-conditie aan toe.
     */
    public function handle(Meeting $meeting): void
    {
        if (! $meeting->shouldSummarize()) {
            return;
        }

        // (a) Media compleet: geen agendapunt zonder opgehaalde bijlagen.
        $pendingMedia = $meeting->agendaItems()
            ->whereNull('attachments_fetched_at')
            ->count();
        if ($pendingMedia > 0) {
            return;
        }

        // (b) Transcript-resolutie klaar (transcript binnen of definitief opgegeven).
        if (! $meeting->transcriptResolved()) {
            return;
        }

        // Idempotency: niet opnieuw dispatchen als de meeting al samengevat is.
        if ($meeting->summarized_at !== null) {
            return;
        }

        foreach (SummaryLevel::cases() as $level) {
            dispatch(new SummarizeMeetingJob($meeting->id, $level));
        }
    }
}
