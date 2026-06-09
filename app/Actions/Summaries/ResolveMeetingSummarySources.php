<?php

namespace App\Actions\Summaries;

use App\Actions\Logging\RecordProcessingEvent;
use App\Enums\MeetingType;
use App\Enums\VideoStatus;
use App\Jobs\IngestMeetingAgendaJob;
use App\Jobs\ProcessMeetingVideoJob;
use App\Models\Meeting;

class ResolveMeetingSummarySources
{
    public function __construct(
        private DetectMeetingNotule $detectNotule,
        private DispatchMeetingSummariesIfReady $dispatchSummaries,
        private RecordProcessingEvent $log,
    ) {}

    public function handle(Meeting $meeting): void
    {
        if (! $meeting->shouldSummarize()
            || $meeting->summarized_at !== null
            || $meeting->summary_skipped_reason !== null
            || $meeting->starts_at === null
            || now()->lessThan($meeting->starts_at)) {
            return;
        }

        $channelId = $meeting->municipality->settings['youtube_channel_id'] ?? null;
        $isCouncilWithChannel = $meeting->type === MeetingType::Council && $channelId !== null;

        // 1) Transcript-pad
        if ($isCouncilWithChannel) {
            if (now()->lessThan($meeting->videoReadyAt())) {
                return; // video staat nog niet online
            }

            $video = $meeting->video;
            if ($video?->status === VideoStatus::Transcribed) {
                $this->summarizeWith($meeting, Meeting::SOURCE_TRANSCRIPT);

                return;
            }

            if ($video?->status === VideoStatus::NeedsConfirmation) {
                return; // wacht op handmatige bevestiging
            }

            $maxAttempts = (int) config('volgjeraad.youtube.max_transcript_attempts');
            $exhausted = $video !== null && (
                $video->status === VideoStatus::NotFound
                || ($video->status === VideoStatus::Failed && $video->transcript_attempts >= $maxAttempts)
            );

            if (! $exhausted) {
                ProcessMeetingVideoJob::dispatch($meeting->id);

                return; // video/transcript loopt; opnieuw geëvalueerd na de job
            }
            // video uitgeput → notule-pad
        }

        // 2) Notule-pad
        if ($meeting->notule_detected_at !== null) {
            $this->summarizeWith($meeting, Meeting::SOURCE_NOTULE);

            return;
        }

        if ($this->mediaComplete($meeting)) {
            $this->detectNotule->handle($meeting);
            $meeting->refresh();
            if ($meeting->notule_detected_at !== null) {
                $this->summarizeWith($meeting, Meeting::SOURCE_NOTULE);

                return;
            }
        }

        // 3) Geen bron → begrensde werkdag-rechecks
        $deadline = $meeting->starts_at->copy()->addWeekdays(
            (int) config('volgjeraad.youtube.notule_recheck_working_days'),
        );

        if (now()->greaterThanOrEqualTo($deadline)) {
            $meeting->update(['summary_skipped_reason' => Meeting::SKIP_NO_SOURCE]);
            $this->log->handle($meeting, 'resolve', 'warning', 'Geen bron (transcript/notule) — meeting zonder samenvatting vastgelegd');

            return;
        }

        // Notule kan later in ORI verschijnen → agenda opnieuw ophalen.
        IngestMeetingAgendaJob::dispatch($meeting->id);
    }

    private function summarizeWith(Meeting $meeting, string $source): void
    {
        if ($meeting->summary_source === null) {
            $meeting->update(['summary_source' => $source, 'source_resolved_at' => now()]);
        }

        $this->dispatchSummaries->handle($meeting->fresh());
    }

    private function mediaComplete(Meeting $meeting): bool
    {
        if ($meeting->agenda_ingested_at === null) {
            return false;
        }

        return $meeting->agendaItems()->whereNull('attachments_fetched_at')->count() === 0;
    }
}
