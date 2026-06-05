<?php

namespace App\Actions\Summaries;

use App\Ai\Agents\MeetingSummaryAgent;
use App\Enums\SummaryLevel;
use App\Enums\SummaryStatus;
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

        // Concat raw agenda texts in position order
        $sourceText = $meeting->agendaItems()
            ->orderBy('position')
            ->get()
            ->map(fn ($item) => $item->sourceText())
            ->filter(fn ($text) => $text !== '')
            ->implode("\n\n---\n\n");

        if ($sourceText === '') {
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

        // Truncate concatenated source text to stay within safe token budget
        $truncated = false;
        if (mb_strlen($sourceText) > $maxSourceChars) {
            $sourceText = mb_substr($sourceText, 0, $maxSourceChars);
            $truncated = true;
        }

        $currentCost = $this->checkMeetingCost->handle($meeting, $model);
        if ($currentCost >= $costCapCents) {
            $this->recordAiUsage->handle(
                $meeting, $municipality, $meeting,
                'meeting_summary', 'openai', $model, $promptVersion,
                0, 0, 0, 'capped',
            );

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

            return null;
        }
    }
}
