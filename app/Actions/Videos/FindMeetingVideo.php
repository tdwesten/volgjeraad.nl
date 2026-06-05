<?php

namespace App\Actions\Videos;

use App\Ai\Agents\VideoMatchAgent;
use App\Enums\VideoStatus;
use App\Models\Meeting;
use App\Models\MeetingVideo;
use App\Services\YouTube\VideoCandidate;
use App\Services\YouTube\YouTubeClient;
use App\Support\PromptRepository;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Enums\Lab;
use Throwable;

class FindMeetingVideo
{
    public function __construct(
        private YouTubeClient $youTubeClient,
    ) {}

    public function handle(Meeting $meeting): ?MeetingVideo
    {
        $channelId = $meeting->municipality->settings['youtube_channel_id'] ?? null;
        if ($channelId === null) {
            Log::warning('find_meeting_video missing channel id', [
                'meeting_id' => $meeting->id,
                'municipality_id' => $meeting->municipality_id,
            ]);

            return null;
        }

        if ($meeting->starts_at === null) {
            return null;
        }

        $windowDays = (int) config('volgjeraad.youtube.search_window_days');
        $from = CarbonImmutable::instance($meeting->starts_at)->subDays($windowDays);
        $to = CarbonImmutable::instance($meeting->starts_at)->addDays($windowDays);

        try {
            $candidates = $this->youTubeClient->searchChannel($channelId, $from, $to);
        } catch (Throwable $e) {
            Log::warning('find_meeting_video search failed', [
                'meeting_id' => $meeting->id,
                'error' => $e->getMessage(),
                'class' => get_class($e),
            ]);
            $candidates = [];
        }

        if ($candidates === []) {
            return $this->store($meeting, VideoStatus::NotFound, candidates: []);
        }

        $choice = $this->pick($meeting, $candidates);
        $threshold = (int) config('volgjeraad.youtube.match_confidence_threshold');
        $confidence = (int) ($choice['confidence'] ?? 0);
        $chosenId = (string) ($choice['video_id'] ?? '');

        $candidateIds = array_map(fn (VideoCandidate $c): string => $c->videoId, $candidates);
        $isKnown = $chosenId !== '' && in_array($chosenId, $candidateIds, true);

        if ($isKnown && $confidence >= $threshold) {
            return $this->store(
                $meeting,
                VideoStatus::Matched,
                candidates: $candidates,
                videoId: $chosenId,
                confidence: $confidence,
                reason: (string) ($choice['reason'] ?? ''),
            );
        }

        // Onbekend id of te lage confidence → menselijke bevestiging.
        $reason = $isKnown
            ? (string) ($choice['reason'] ?? '')
            : 'Agent koos een video_id buiten de kandidatenlijst; handmatige bevestiging vereist.';

        return $this->store(
            $meeting,
            VideoStatus::NeedsConfirmation,
            candidates: $candidates,
            videoId: $isKnown ? $chosenId : null,
            confidence: $confidence,
            reason: $reason,
        );
    }

    /**
     * @param  array<int, VideoCandidate>  $candidates
     * @return array<string, mixed>
     */
    private function pick(Meeting $meeting, array $candidates): array
    {
        $model = (string) config('volgjeraad.ai.default_summary_model');
        $promptVersion = PromptRepository::version();

        $input = json_encode([
            'meeting' => [
                'name' => $meeting->name,
                'starts_at' => CarbonImmutable::instance($meeting->starts_at)->toIso8601String(),
                'type' => $meeting->type->value,
            ],
            'candidates' => array_map(fn (VideoCandidate $c): array => $c->toArray(), $candidates),
        ], JSON_UNESCAPED_UNICODE);

        $agent = new VideoMatchAgent($model, $promptVersion);
        $response = $agent->prompt($input, provider: Lab::OpenAI, model: $model);

        return $response->structured ?? [];
    }

    /**
     * @param  array<int, VideoCandidate>  $candidates
     */
    private function store(
        Meeting $meeting,
        VideoStatus $status,
        array $candidates,
        ?string $videoId = null,
        ?int $confidence = null,
        string $reason = '',
    ): MeetingVideo {
        // Verse query (niet de mogelijk gecachete relatie) zodat attempts correct optelt
        // bij herhaalde aanroepen op hetzelfde in-memory Meeting-object.
        $existing = MeetingVideo::where('meeting_id', $meeting->id)->first();
        $confirmed = $status === VideoStatus::Matched;

        return MeetingVideo::updateOrCreate(
            ['meeting_id' => $meeting->id],
            [
                'youtube_video_id' => $videoId,
                'video_url' => $videoId !== null ? "https://www.youtube.com/watch?v={$videoId}" : null,
                'match_confidence' => $confidence,
                'match_reason' => $reason !== '' ? $reason : null,
                'candidates' => array_map(fn (VideoCandidate $c): array => $c->toArray(), $candidates),
                'status' => $status->value,
                'confirmed_at' => $confirmed ? now() : ($existing?->confirmed_at),
                // Alleen de zoek/match-teller; het transcript-retrybudget blijft onaangeroerd.
                'match_attempts' => ($existing?->match_attempts ?? 0) + 1,
                'last_attempt_at' => now(),
            ],
        );
    }
}
