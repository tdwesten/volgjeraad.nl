<?php

namespace App\Actions\Summaries;

use App\Actions\Logging\RecordProcessingEvent;
use App\Ai\Agents\MeetingSummaryAgent;
use App\Enums\SummaryLevel;
use App\Enums\SummaryStatus;
use App\Enums\VideoStatus;
use App\Models\Meeting;
use App\Models\Summary;
use App\Support\PayloadHasher;
use App\Support\PromptRepository;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Enums\Lab;
use Throwable;

class GenerateMeetingSummary
{
    public function __construct(
        private CheckMeetingCost $checkMeetingCost,
        private EstimateCost $estimateCost,
        private RecordAiUsage $recordAiUsage,
        private RecordProcessingEvent $log,
    ) {}

    public function handle(Meeting $meeting, SummaryLevel $level): ?Summary
    {
        // Idempotency: return existing summary rather than calling the AI again.
        $existing = Summary::where('summarizable_type', $meeting->getMorphClass())
            ->where('summarizable_id', $meeting->getKey())
            ->where('level', $level->value)
            ->where('language', 'nl')
            ->first();
        if ($existing !== null) {
            return $existing;
        }

        $municipality = $meeting->municipality;
        $model = (string) config('volgjeraad.ai.default_summary_model');
        $promptVersion = PromptRepository::version();
        $costCapCents = (int) config('volgjeraad.ai.cost_cap_cents_per_meeting');
        $maxSourceChars = (int) config('volgjeraad.ai.max_source_chars', 24000);
        $maxTranscriptChars = (int) config('volgjeraad.ai.max_transcript_chars', 60000);

        // Build agenda text: besluitenlijst docs first (formal decisions), then rest in position order.
        $items = $meeting->agendaItems()->orderBy('position')->get();
        $besluitenlijstTexts = [];
        $otherTexts = [];

        foreach ($items as $item) {
            $texts = $item->mediaObjects()
                ->withText()
                ->orderBy('position')
                ->pluck('md_text')
                ->filter()
                ->values();

            if ($texts->isEmpty()) {
                continue;
            }

            $isBesluitenlijst = str_contains(mb_strtolower($item->name ?? ''), 'besluitenlijst');
            if (! $isBesluitenlijst) {
                foreach ($item->mediaObjects as $mo) {
                    if (str_contains(mb_strtolower($mo->name ?? ''), 'besluitenlijst')
                        || str_contains(mb_strtolower($mo->file_name ?? ''), 'besluitenlijst')) {
                        $isBesluitenlijst = true;
                        break;
                    }
                }
            }

            $block = $texts->implode("\n\n");
            if ($isBesluitenlijst) {
                $besluitenlijstTexts[] = $block;
            } else {
                $otherTexts[] = $block;
            }
        }

        $agendaText = collect([...$besluitenlijstTexts, ...$otherTexts])
            ->filter()
            ->implode("\n\n---\n\n");

        // Collect transcript text when the video is fully transcribed
        $video = $meeting->video;
        $transcriptText = ($video?->status === VideoStatus::Transcribed && $video->transcript_text !== null)
            ? $video->transcript_text
            : '';

        if ($agendaText === '' && $transcriptText === '') {
            return Summary::create([
                'summarizable_type' => $meeting->getMorphClass(),
                'summarizable_id' => $meeting->getKey(),
                'municipality_id' => $municipality->id,
                'meeting_id' => $meeting->id,
                'level' => $level->value,
                'language' => 'nl',
                'source_hash' => PayloadHasher::hash(['text' => '']),
                'status' => SummaryStatus::Draft->value,
                'title' => '',
                'body' => '',
                'impact_note' => null,
                'confidence' => 0,
                'flags' => ['source_text_missing'],
                'input_tokens' => 0,
                'output_tokens' => 0,
                'prompt_version' => $promptVersion,
                'model' => $model,
            ]);
        }

        // Truncate each source block to its own budget
        $truncated = false;
        if (mb_strlen($agendaText) > $maxSourceChars) {
            $agendaText = mb_substr($agendaText, 0, $maxSourceChars);
            $truncated = true;
        }
        if ($transcriptText !== '' && mb_strlen($transcriptText) > $maxTranscriptChars) {
            $transcriptText = mb_substr($transcriptText, 0, $maxTranscriptChars);
            $truncated = true;
        }

        // Combine into the full source text sent to the agent (and used for the hash)
        $sourceText = $transcriptText !== ''
            ? "=== BESLUITENLIJST + AGENDA ===\n\n{$agendaText}\n\n=== TRANSCRIPT (debat) ===\n\n{$transcriptText}"
            : $agendaText;

        $currentCost = $this->checkMeetingCost->handle($meeting, $model);
        if ($currentCost >= $costCapCents) {
            $this->recordAiUsage->handle(
                $meeting, $municipality, $meeting,
                'meeting_summary', 'openai', $model, $promptVersion,
                0, 0, 0, 'capped',
            );

            $this->log->handle($meeting, 'summarize', 'warning', "Samenvatting [{$level->value}] overgeslagen: kostenplafond bereikt");

            return null;
        }

        $agent = new MeetingSummaryAgent($level, $model, $promptVersion);

        try {
            $response = $agent->prompt($sourceText, provider: Lab::OpenAI, model: $model);

            $structured = $response->structured ?? [];
            $inputTokens = $response->usage?->promptTokens ?? 0;
            $outputTokens = $response->usage?->completionTokens ?? 0;
            $costCents = $this->estimateCost->handle($model, $inputTokens, $outputTokens);

            $flags = $structured['flags'] ?? [];
            if ($truncated) {
                $flags[] = 'source_truncated';
            }

            $summary = Summary::create([
                'summarizable_type' => $meeting->getMorphClass(),
                'summarizable_id' => $meeting->getKey(),
                'municipality_id' => $municipality->id,
                'meeting_id' => $meeting->id,
                'level' => $level->value,
                'language' => 'nl',
                'source_hash' => PayloadHasher::hash(['text' => $sourceText]),
                'status' => SummaryStatus::Draft->value,
                'title' => $structured['title'] ?? '',
                'body' => $structured['body'] ?? '',
                'impact_note' => $structured['impact_note'] ?? null,
                'confidence' => $structured['confidence'] ?? 0,
                'flags' => $flags,
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
                'prompt_version' => $promptVersion,
                'model' => $model,
            ]);

            $this->recordAiUsage->handle(
                $meeting, $municipality, $meeting,
                'meeting_summary', 'openai', $model, $promptVersion,
                $inputTokens, $outputTokens, $costCents, 'ok',
            );

            $this->log->handle($meeting, 'summarize', 'success', "Samenvatting [{$level->value}] gegenereerd (confidence: {$summary->confidence}%)");

            return $summary;
        } catch (Throwable $e) {
            Log::warning('meeting_summary failed', [
                'meeting_id' => $meeting->id,
                'level' => $level->value,
                'error' => $e->getMessage(),
                'class' => get_class($e),
            ]);

            $this->recordAiUsage->handle(
                $meeting, $municipality, $meeting,
                'meeting_summary', 'openai', $model, $promptVersion,
                0, 0, 0, 'failed',
                ['error' => $e->getMessage(), 'class' => get_class($e)],
            );

            $this->log->handle($meeting, 'summarize', 'error', "Samenvatting [{$level->value}] mislukt: {$e->getMessage()}");

            return null;
        }
    }
}
