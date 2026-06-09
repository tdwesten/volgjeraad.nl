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

        // Werkdag-deadline: hierna stoppen we met wachten op een (handmatig te
        // bevestigen) video en valt de meeting onvermijdelijk naar no_source.
        $deadline = $meeting->starts_at->copy()->addWeekdays(
            (int) config('volgjeraad.youtube.notule_recheck_working_days'),
        );
        $beforeDeadline = now()->lessThan($deadline);

        $channelId = $meeting->municipality->settings['youtube_channel_id'] ?? null;
        $isCouncilWithChannel = $meeting->type === MeetingType::Council && $channelId !== null;

        // 1) Transcript-pad
        if ($isCouncilWithChannel) {
            if (now()->lessThan($meeting->videoReadyAt())) {
                return; // video staat nog niet online (24u-venster blijft ongemoeid)
            }

            $video = $meeting->video;
            if ($video?->status === VideoStatus::Transcribed) {
                $this->summarizeWith($meeting, Meeting::SOURCE_TRANSCRIPT);

                return;
            }

            // Wacht op handmatige bevestiging — maar alléén tot de deadline, anders
            // blijft de meeting eeuwig hangen als niemand bevestigt.
            if ($video?->status === VideoStatus::NeedsConfirmation) {
                if ($beforeDeadline) {
                    return;
                }
                // deadline verstreken → stop met wachten, val door naar notule-pad
            } else {
                $maxAttempts = (int) config('volgjeraad.youtube.max_transcript_attempts');
                $exhausted = $video !== null && (
                    $video->status === VideoStatus::NotFound
                    || ($video->status === VideoStatus::Failed && $video->transcript_attempts >= $maxAttempts)
                );

                // Video/transcript loopt nog: opnieuw proberen, maar alléén vóór de
                // deadline; daarna geven we het video-pad op.
                if (! $exhausted && $beforeDeadline) {
                    ProcessMeetingVideoJob::dispatch($meeting->id);

                    return; // opnieuw geëvalueerd na de job
                }
                // video uitgeput of deadline verstreken → notule-pad
            }
        }

        // 2) Notule-pad
        if ($meeting->notule_detected_at !== null) {
            $this->summarizeWith($meeting, Meeting::SOURCE_NOTULE);

            return;
        }

        // Throttle: AI-detectie + agenda-redispatch hooguit eens per N uur draaien.
        $due = $this->notuleCheckDue($meeting);

        if ($due && $this->mediaComplete($meeting)) {
            $this->detectNotule->handle($meeting);
            $meeting->refresh();
            if ($meeting->notule_detected_at !== null) {
                $this->summarizeWith($meeting, Meeting::SOURCE_NOTULE);

                return;
            }
        }

        // 3) Geen bron → begrensde werkdag-rechecks
        if (now()->greaterThanOrEqualTo($deadline)) {
            $meeting->update(['summary_skipped_reason' => Meeting::SKIP_NO_SOURCE]);
            $this->log->handle($meeting, 'resolve', 'warning', 'Geen bron (transcript/notule) — meeting zonder samenvatting vastgelegd');

            return;
        }

        // Notule kan later in ORI verschijnen → agenda opnieuw ophalen, maar alleen
        // wanneer de recheck 'due' is (anders elke 15-min-sweep opnieuw).
        if ($due) {
            IngestMeetingAgendaJob::dispatch($meeting->id);
            // Markeer de recheck ook wanneer media nog niet compleet was (en
            // DetectMeetingNotule dus niet draaide).
            if ($meeting->notule_checked_at === null) {
                $meeting->update(['notule_checked_at' => now()]);
            }
        }
    }

    private function notuleCheckDue(Meeting $meeting): bool
    {
        if ($meeting->notule_checked_at === null) {
            return true;
        }

        $throttleHours = (int) config('volgjeraad.youtube.notule_recheck_throttle_hours');

        return $meeting->notule_checked_at->lessThan(now()->subHours($throttleHours));
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
