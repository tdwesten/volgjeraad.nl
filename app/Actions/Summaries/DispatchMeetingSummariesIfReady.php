<?php

namespace App\Actions\Summaries;

use App\Enums\SummaryLevel;
use App\Jobs\SummarizeMeetingJob;
use App\Models\Meeting;

class DispatchMeetingSummariesIfReady
{
    /**
     * Dispatcht de meeting-samenvattingen uitsluitend wanneer (a) alle media binnen
     * is én (b) een bron is geresolveerd (transcript óf notule) door
     * ResolveMeetingSummarySources, zichtbaar als een gezette `summary_source`.
     * Re-entrant en idempotent: zowel de media-ingest als de video-pijplijn lopen via
     * de resolver, die deze gate aanroept; pas wanneer beide condities waar zijn
     * dispatcht het, één keer per SummaryLevel. De exact-één-keer-garantie ná dispatch
     * komt van `summarized_at`: zodra alle niveaus klaar zijn zet SummarizeMeetingJob
     * dat veld, waarna deze gate (en de 15-min-sweep) niets meer dispatchen.
     */
    public function handle(Meeting $meeting): void
    {
        if (! $meeting->shouldSummarize()) {
            return;
        }

        // (b) Een bron is geresolveerd (transcript óf notule) door ResolveMeetingSummarySources.
        if ($meeting->summary_source === null) {
            return;
        }

        // (a) Media compleet — vereist voor het notule-pad: de notule en agenda-tekst
        // zitten ín de bijlagen. Een transcript is een zelfstandige bron en blokkeert
        // NIET op nog-ontbrekende bijlagen; we summariseren met de beschikbare
        // agenda-tekst plus het transcript.
        if ($meeting->summary_source !== Meeting::SOURCE_TRANSCRIPT) {
            $pendingMedia = $meeting->agendaItems()
                ->whereNull('attachments_fetched_at')
                ->count();
            if ($pendingMedia > 0) {
                return;
            }
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
