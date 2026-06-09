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

        // 0) Transcript-wint: een reeds getranscribeerde video is een geldige bron voor
        // ELKE summarizable meeting, ongeacht type of kanaal. Dit staat bewust boven
        // het council+channel-blok zodat ook commissie-/'other'-meetings samenvatten.
        if ($meeting->video?->status === VideoStatus::Transcribed) {
            $this->resolveWithSource($meeting, Meeting::SOURCE_TRANSCRIPT);

            return;
        }

        $channelId = $meeting->municipality->settings['youtube_channel_id'] ?? null;
        $isCouncilWithChannel = $meeting->type === MeetingType::Council && $channelId !== null;

        // 1) Transcript-pad: 24u-wacht + actieve videozoektocht voor council+channel
        // meetings die nog géén transcript hebben.
        if ($isCouncilWithChannel) {
            if (now()->lessThan($meeting->videoReadyAt())) {
                return; // video staat nog niet online (24u-venster blijft ongemoeid)
            }

            $video = $meeting->video;

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

        // 2) Notule-pad — transcript niet (meer) beschikbaar.
        if ($meeting->notule_detected_at !== null) {
            $this->resolveWithSource($meeting, Meeting::SOURCE_NOTULE);

            return;
        }

        // Skip naar no_source ALLEEN wanneer er minstens één recheck-cyclus op/na de
        // werkdag-deadline draaide en niets opleverde. `notule_checked_at >= deadline`
        // impliceert dat now() ook voorbij de deadline is.
        if ($meeting->notule_checked_at !== null
            && $meeting->notule_checked_at->greaterThanOrEqualTo($deadline)) {
            $meeting->update(['summary_skipped_reason' => Meeting::SKIP_NO_SOURCE]);
            $this->log->handle($meeting, 'resolve', 'warning', 'Geen bron (transcript/notule) — meeting zonder samenvatting vastgelegd');

            return;
        }

        // Een recheck-cyclus is 'due' wanneer het throttle-venster verstreken is, óf
        // wanneer we voorbij de deadline zijn zonder dat er al een post-deadline recheck
        // draaide: dan forceren we één laatste cyclus vóór we mogen skippen.
        $due = $this->notuleCheckDue($meeting) || now()->greaterThanOrEqualTo($deadline);

        if (! $due) {
            return; // binnen throttle-venster én vóór de deadline → wachten
        }

        // Markeer de recheck-cyclus (begrenst zowel de AI-call als de agenda-redispatch
        // tot ~1x per venster, en levert de post-deadline timestamp die de skip vrijgeeft).
        $meeting->update(['notule_checked_at' => now()]);

        if ($this->mediaComplete($meeting)) {
            $this->detectNotule->handle($meeting);
            $meeting->refresh();
            if ($meeting->notule_detected_at !== null) {
                $this->resolveWithSource($meeting, Meeting::SOURCE_NOTULE);

                return;
            }
        }

        // Geen notule (nog) → agenda opnieuw ophalen; een notule kan later in ORI verschijnen.
        IngestMeetingAgendaJob::dispatch($meeting->id);
    }

    private function notuleCheckDue(Meeting $meeting): bool
    {
        if ($meeting->notule_checked_at === null) {
            return true;
        }

        $throttleHours = (int) config('volgjeraad.youtube.notule_recheck_throttle_hours');

        return $meeting->notule_checked_at->lessThan(now()->subHours($throttleHours));
    }

    private function resolveWithSource(Meeting $meeting, string $source): void
    {
        if ($meeting->summary_source === null) {
            $meeting->update(['summary_source' => $source, 'source_resolved_at' => now()]);
        }

        // Nog ontbrekende bijlagen (een agendapunt zonder opgehaalde attachments)?
        // Haal ze (throttled) alsnog op en wacht — NIET skippen. Zodra de media
        // compleet is, dispatcht de gate de samenvattingen.
        if ($this->hasPendingMedia($meeting)) {
            if ($this->notuleCheckDue($meeting)) {
                $meeting->update(['notule_checked_at' => now()]);
                IngestMeetingAgendaJob::dispatch($meeting->id);
            }

            return;
        }

        $this->dispatchSummaries->handle($meeting->fresh());
    }

    private function hasPendingMedia(Meeting $meeting): bool
    {
        return $meeting->agendaItems()->whereNull('attachments_fetched_at')->exists();
    }

    private function mediaComplete(Meeting $meeting): bool
    {
        if ($meeting->agenda_ingested_at === null) {
            return false;
        }

        return $meeting->agendaItems()->whereNull('attachments_fetched_at')->count() === 0;
    }
}
